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
 * Event observer implementing the host-course removal rules (requirement A-4).
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\reconciler;

/**
 * Event observer implementing the host-course removal rules (requirement A-4).
 *
 * When a user is unenrolled from a course that embeds a learning path, the
 * ADELE enrolments of that user are hard-removed — but only if no other
 * subscription option still carries them. Suspension in the host course is
 * deliberately NOT a removal (decision F-4) and has no observer.
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
        if (!$dbman->table_exists('adele') || !$dbman->table_exists('local_adele_path_user')) {
            return;
        }

        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;

        // Learning paths embedded in the course the user just left.
        $embeddings = $DB->get_records('adele', ['course' => $courseid], '', 'id, learningpathid');
        if (!$embeddings) {
            return;
        }
        $lpids = [];
        foreach ($embeddings as $embedding) {
            $lpids[(int) $embedding->learningpathid] = true;
        }

        foreach (array_keys($lpids) as $lpid) {
            if (self::is_user_carried($lpid, $userid)) {
                continue;
            }
            // Decision R-1: remove the user path record entirely. Course-derived
            // progress re-derives on re-subscription; the documented caveats
            // (manual master overrides, first_enrolled for timed windows) are
            // accepted — see specification 2.5.
            $DB->delete_records(
                'local_adele_path_user',
                ['learning_path_id' => $lpid, 'user_id' => $userid]
            );
            reconciler::purge_user($lpid, $userid);
            // Requirement mod_adele #21: leaving the learning path must also
            // clear every Fall-2/3 host-course enrolment it created, not just
            // target-course ones — the learning path may be embedded in
            // several host courses at once, each potentially having granted
            // host-course access.
            reconciler::purge_all_host_user($lpid, $userid);

            // Specification 7.3: marks that rule A-4 actually fired for this
            // user/path (not the routine per-node suspend/reactivate cycle,
            // which is already visible via core user_enrolment_updated).
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
     * counts — decision F-4/A-8) in an embedding course. Option 2 carries while
     * the user is enrolled in a starting-node course, option 3 while enrolled
     * in any node course. For options 2 and 3, enrolments created by ADELE's
     * own instances do not count — otherwise access would keep itself alive
     * circularly (specification, section 4).
     *
     * @param int $lpid The learning path id.
     * @param int $userid The user id.
     * @return bool
     */
    public static function is_user_carried(int $lpid, int $userid): bool {
        global $DB;

        $embeddings = $DB->get_records(
            'adele',
            ['learningpathid' => $lpid],
            '',
            'id, course, participantslist'
        );

        $hasoption2 = false;
        $hasoption3 = false;
        foreach ($embeddings as $embedding) {
            $options = array_map('trim', explode(',', (string) $embedding->participantslist));
            if (
                in_array('1', $options)
                && self::has_foreign_enrolment($userid, [(int) $embedding->course])
            ) {
                return true;
            }
            $hasoption2 = $hasoption2 || in_array('2', $options);
            $hasoption3 = $hasoption3 || in_array('3', $options);
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
     * Whether the user holds any non-ADELE enrolment in one of the given courses.
     *
     * Suspended enrolments count (decision F-4/A-8). Expired enrolments
     * (timeend passed), not-yet-started enrolments (timestart in the
     * future) and enrolments via a disabled enrol instance do NOT count
     * (fix G.4, Session 003 — previously unchecked; F-4/A-8 only ever
     * covered suspension, not expiry or a disabled method).
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
        $now = time();
        $sql = "SELECT 1
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                       AND e.enrol <> 'adele'
                       AND e.status = :enabled
                       AND (ue.timestart = 0 OR ue.timestart <= :now1)
                       AND (ue.timeend = 0 OR ue.timeend > :now2)
                       AND e.courseid {$insql}";
        return $DB->record_exists_sql($sql, [
            'userid' => $userid,
            'enabled' => ENROL_INSTANCE_ENABLED,
            'now1' => $now,
            'now2' => $now,
        ] + $inparams);
    }
}
