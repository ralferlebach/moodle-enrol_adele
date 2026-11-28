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

namespace enrol_adele\local;

/**
 * Enrolments of teachers into node courses, granted through the teacher view
 * of a mod_adele activity (local_adele #600, TP4).
 *
 * Every grant is a row with its source: the course module, the course, the
 * person, the activity's mode and the role. The enrolment itself is one
 * ADELE instance per course (instance_manager::KIND_TEACHER), apart from the
 * learner instances, which no learning path recompute touches. What a person
 * holds in a course is the union of their grants there: enrolled while one
 * grant is left, each role while one grant gives it. Taking one source away
 * leaves what other sources give; nothing of another enrolment method or of
 * another component's role assignment is ever touched.
 *
 * Whether a grant may exist is mod_adele's decision (its activity, mode and
 * policies); this class keeps the rows and makes Moodle's enrolments and
 * role assignments follow them.
 *
 * @package    enrol_adele
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_grants {
    /** @var string The table. */
    public const TABLE = 'enrol_adele_teacher_grants';

    /**
     * Record a grant (or update the one from the same source) and apply.
     *
     * @param int $cmid The mod_adele course module.
     * @param int $learningpathid The path the activity embeds.
     * @param int $courseid The node course.
     * @param int $userid The person.
     * @param string $mode The activity's mode.
     * @param int $roleid The role.
     * @return bool Whether the person is enrolled with the role afterwards.
     */
    public static function add(int $cmid, int $learningpathid, int $courseid, int $userid, string $mode, int $roleid): bool {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['cmid' => $cmid, 'courseid' => $courseid, 'userid' => $userid]);
        $data = [
            'cmid' => $cmid,
            'learningpathid' => $learningpathid,
            'courseid' => $courseid,
            'userid' => $userid,
            'mode' => $mode,
            'roleid' => $roleid,
            'timecreated' => \core\di::get(\core\clock::class)->time(),
        ];
        if ($row) {
            $DB->update_record(self::TABLE, (object) (['id' => $row->id] + $data));
        } else {
            $DB->insert_record(self::TABLE, (object) $data);
        }
        self::apply($courseid, $userid);
        return self::holds($courseid, $userid, $roleid);
    }

    /**
     * Remove grants and apply for everybody concerned.
     *
     * @param array $conditions Conditions on the table, e.g. ['cmid' => 5].
     * @return int Number of grants removed.
     */
    public static function remove(array $conditions): int {
        global $DB;
        $rows = $DB->get_records(self::TABLE, $conditions);
        if (!$rows) {
            return 0;
        }
        $DB->delete_records_list(self::TABLE, 'id', array_keys($rows));
        $pairs = [];
        foreach ($rows as $row) {
            $pairs[$row->courseid . '_' . $row->userid] = [(int) $row->courseid, (int) $row->userid];
        }
        foreach ($pairs as [$courseid, $userid]) {
            self::apply($courseid, $userid);
        }
        return count($rows);
    }

    /**
     * The grants matching conditions.
     *
     * @param array $conditions
     * @return \stdClass[]
     */
    public static function get(array $conditions = []): array {
        global $DB;
        return $DB->get_records(self::TABLE, $conditions, 'id ASC');
    }

    /**
     * Make Moodle's enrolment and roles of a person in a course follow the
     * grants there.
     *
     * @param int $courseid The course.
     * @param int $userid The person.
     * @return void
     */
    public static function apply(int $courseid, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return;
        }
        $rows = $DB->get_records(self::TABLE, ['courseid' => $courseid, 'userid' => $userid]);
        $instance = $rows
            ? instance_manager::ensure_teacher_instance($courseid)
            : instance_manager::get_teacher_instance($courseid);
        if (!$instance) {
            return;
        }
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        if (!$rows) {
            if ($ue) {
                $plugin->unenrol_user($instance, $userid);
            }
            return;
        }

        if (!$ue) {
            $plugin->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_ACTIVE);
        } else if ((int) $ue->status !== ENROL_USER_ACTIVE) {
            $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
        }

        $context = \context_course::instance($courseid);
        $wanted = array_unique(array_map(fn($row) => (int) $row->roleid, $rows));
        $held = array_map('intval', $DB->get_fieldset_select(
            'role_assignments',
            'roleid',
            'userid = :userid AND contextid = :contextid AND component = :component AND itemid = :itemid',
            ['userid' => $userid, 'contextid' => $context->id, 'component' => 'enrol_adele', 'itemid' => $instance->id]
        ));
        foreach (array_diff($wanted, $held) as $roleid) {
            role_assign($roleid, $userid, $context->id, 'enrol_adele', (int) $instance->id);
        }
        foreach (array_diff($held, $wanted) as $roleid) {
            role_unassign($roleid, $userid, $context->id, 'enrol_adele', (int) $instance->id);
        }
    }

    /**
     * Whether a person is actively enrolled through the teacher grants of a
     * course and holds a role there.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $roleid
     * @return bool
     */
    public static function holds(int $courseid, int $userid, int $roleid): bool {
        global $DB;
        $instance = instance_manager::get_teacher_instance($courseid);
        if (!$instance) {
            return false;
        }
        $active = $DB->record_exists('user_enrolments', [
            'enrolid' => $instance->id, 'userid' => $userid, 'status' => ENROL_USER_ACTIVE,
        ]);
        return $active && $DB->record_exists('role_assignments', [
            'userid' => $userid,
            'contextid' => \context_course::instance($courseid)->id,
            'roleid' => $roleid,
            'component' => 'enrol_adele',
            'itemid' => $instance->id,
        ]);
    }
}
