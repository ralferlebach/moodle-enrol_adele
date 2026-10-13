<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event observer implementing the host-course removal rules.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\reconciler;

/**
 * Event observer implementing the host-course removal rules.
 *
 * When a user is unenrolled from a course that embeds a learning path, the
 * ADELE enrolments of that user are hard-removed — but only if no other
 * subscription option still carries them. Suspension in the host course is
 * deliberately NOT a removal and has no observer.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A user was unenrolled from some course.
     *
     * @param \core\event\user_enrolment_deleted $event The event.
     * @return void
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB;

        // Recursion guard: removals performed by ADELE itself (e.g. a target
        // course that is also a host course) must not re-trigger the rules.
        if (($event->other['enrol'] ?? '') === 'adele') {
            return;
        }
        if (!class_exists('\local_adele\enrol_state')) {
            return;
        }
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_adele_path_user')) {
            return;
        }

        self::left_course((int) $event->courseid, (int) $event->relateduserid, false);
    }

    /**
     * A user lost a role in some course.
     *
     * Taking part in a course is a matter of the role (local_adele #587): a
     * participant who becomes a teacher of the course has left its learners
     * exactly like somebody who was unenrolled - with the same consequences
     * and the same grace period for the learning history. Whether anything
     * still carries the user is decided by {@see is_user_carried()}; a
     * teacher who merely lost one of several roles and still takes part is
     * carried, and nothing happens.
     *
     * @param \core\event\role_unassigned $event The event.
     * @return void
     */
    public static function role_unassigned(\core\event\role_unassigned $event): void {
        global $DB;

        $context = $event->get_context();
        if (!$context || $context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        // ADELE's own role bookkeeping is nobody leaving.
        if (($event->other['component'] ?? '') === 'enrol_adele') {
            return;
        }
        if (!class_exists('\local_adele\enrol_state')) {
            return;
        }
        if (!$DB->get_manager()->table_exists('local_adele_path_user')) {
            return;
        }
        self::left_course((int) $context->instanceid, (int) $event->relateduserid, true);
    }

    /**
     * Somebody stopped being a participant of a course - by unenrolment or
     * by losing the role: withdraw what the learning paths embedded there
     * gave them, unless something still carries them.
     *
     * @param int $courseid The course.
     * @param int $userid The user.
     * @param bool $learnersonly Skip paths the user has no subscription to. For role
     *     changes: a teacher losing one of two teaching roles never was a learner
     *     and has nothing to be withdrawn. Unenrolment keeps its unconditional purge.
     * @return void
     */
    private static function left_course(int $courseid, int $userid, bool $learnersonly): void {
        global $DB;

        // Learning paths embedded in the course the user just left. Read via
        // local_adele's own host-course index rather than mod_adele's {adele}
        // table directly.
        $lpids = \local_adele\enrol_state::get_learningpaths_embedded_in_course($courseid);
        if (!$lpids) {
            return;
        }

        foreach ($lpids as $lpid) {
            if (self::is_user_carried($lpid, $userid)) {
                continue;
            }
            if (
                $learnersonly
                && !$DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid])
            ) {
                continue;
            }
            // Withdraw access NOW — that part is correct and wanted the
            // moment entitlement ends — but do not destroy the learning
            // history yet. The user path record is the only copy of node
            // progress, teacher-set overrides and first_enrolled, and this
            // observer cannot tell a genuine departure from a cohort resync
            // that will re-add the user seconds later. A deferred task
            // re-asks the same question once the removal has had time to
            // prove durable (issue #3).
            $task = new \enrol_adele\task\remove_user_path_adhoc();
            $task->set_custom_data(['learningpathid' => $lpid, 'userid' => $userid]);
            $runat = \core\di::get(\core\clock::class)->time()
                + \enrol_adele\task\remove_user_path_adhoc::DELAY_SECONDS;
            $task->set_next_run_time($runat);
            \core\task\manager::queue_adhoc_task($task, true);

            reconciler::purge_user($lpid, $userid);
            // Leaving the learning path must also clear every option-2/3
            // host-course enrolment it created, not just target-course ones:
            // the learning path may be embedded in several host courses at
            // once, each potentially having granted host-course access.
            reconciler::purge_all_host_user($lpid, $userid);

            // Mark that whole-path removal actually fired for this user/path
            // (not the routine per-node suspend/reactivate cycle, which is
            // already visible via core user_enrolment_updated).
            \enrol_adele\event\user_access_revoked::create([
                'context' => \context_system::instance(),
                'relateduserid' => $userid,
                'other' => [
                    'learningpathid' => $lpid,
                ],
            ])->trigger();
        }
    }

    /**
     * Whether any subscription option of any embedding still carries the user.
     *
     * Option 1 carries while the user is enrolled (any method, suspension
     * counts) in an embedding course. Option 2 carries while the user is
     * enrolled in a starting-node course, option 3 while enrolled in any node
     * course. For options 2 and 3, enrolments created by ADELE's own
     * instances do not count — otherwise access would keep itself alive
     * circularly. In every case "enrolled" means: as a participant
     * (local_adele #587) — teaching a course carries nobody.
     *
     * @param int $lpid The learning path id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function is_user_carried(int $lpid, int $userid): bool {
        global $DB;

        // Read via local_adele's own host-course index instead of mod_adele's
        // {adele} table and participantslist string format directly — this
        // class has no knowledge of either.
        $embeddings = \local_adele\enrol_state::get_host_embeddings($lpid);

        $hasoption2 = false;
        $hasoption3 = false;
        foreach ($embeddings as $embedding) {
            if (
                $embedding['option1']
                && self::has_foreign_enrolment($userid, [$embedding['courseid']])
            ) {
                return true;
            }
            $hasoption2 = $hasoption2 || $embedding['option2'];
            $hasoption3 = $hasoption3 || $embedding['option3'];
        }
        if (!$hasoption2 && !$hasoption3) {
            return false;
        }

        // Node courses from the learning path definition (authoritative source,
        // not the per-user snapshot).
        $lp = $DB->get_record('local_adele_learning_paths', ['id' => $lpid], 'id, json');
        if (!$lp) {
            return false;
        }
        $json = json_decode($lp->json, true);
        $startingcourses = [];
        $allcourses = [];
        foreach (($json['tree']['nodes'] ?? []) as $node) {
            $courses = $node['data']['course_node_id'] ?? [];
            if (!is_array($courses)) {
                continue;
            }
            $courses = array_map('intval', $courses);
            $allcourses = array_merge($allcourses, $courses);
            if (in_array('starting_node', $node['parentCourse'] ?? [])) {
                $startingcourses = array_merge($startingcourses, $courses);
            }
        }

        if (
            $hasoption2 && $startingcourses
            && self::has_foreign_enrolment($userid, $startingcourses)
        ) {
            return true;
        }
        if (
            $hasoption3 && $allcourses
            && self::has_foreign_enrolment($userid, $allcourses)
        ) {
            return true;
        }
        return false;
    }

    /**
     * Whether the user takes part in one of the given courses by their own
     * right: a non-ADELE enrolment, as a participant.
     *
     * Suspended enrolments count. Expired enrolments (timeend passed),
     * not-yet-started enrolments (timestart in the future) and enrolments
     * via a disabled enrol instance do NOT count.
     *
     * @param int $userid The user id.
     * @param int[] $courseids Course ids to check.
     * @return bool
     */
    private static function has_foreign_enrolment(int $userid, array $courseids): bool {
        global $DB;

        if (!$courseids) {
            return false;
        }
        [$insql, $inparams] = $DB->get_in_or_equal(array_unique($courseids), SQL_PARAMS_NAMED);
        $now = \core\di::get(\core\clock::class)->time();
        $sql = "SELECT DISTINCT e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                       AND e.enrol <> 'adele'
                       AND e.status = :enabled
                       AND (ue.timestart = 0 OR ue.timestart <= :now1)
                       AND (ue.timeend = 0 OR ue.timeend > :now2)
                       AND e.courseid {$insql}";
        $enrolledin = $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'now1' => $now,
            'now2' => $now,
        ] + $inparams);
        // ... and only as a PARTICIPANT (local_adele #587). The definition is
        // local_adele's, shared with mod_adele's participant sources; the
        // declared dependency guarantees the method.
        foreach ($enrolledin as $courseid) {
            if (\local_adele\enrol_state::is_participant((int) $courseid, $userid)) {
                return true;
            }
        }
        return false;
    }
}
