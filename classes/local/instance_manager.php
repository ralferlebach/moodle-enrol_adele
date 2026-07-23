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
 * Manages the enrol instances owned by ADELE.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\local;

/**
 * Manages the enrol instances owned by ADELE.
 *
 * One instance is scoped to exactly one pair of learning path and target
 * course: enrol = 'adele', courseid = target course, customint1 = learning
 * path id. Instances are created lazily by the reconciler; teachers cannot add
 * them by hand (see enrol_adele_plugin::can_add_instance()).
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_manager {
    /**
     * All ADELE enrol instances belonging to a learning path, keyed by course id.
     *
     * Should the identity invariant ever be violated (two instances for the same
     * pair), the first one wins deterministically (lowest id).
     *
     * @param int $learningpathid The learning path id.
     * @return \stdClass[] Enrol instance records keyed by course id.
     */
    public static function get_instances(int $learningpathid): array {
        global $DB;
        $records = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'customint1' => $learningpathid],
            'id ASC'
        );
        $bycourse = [];
        foreach ($records as $record) {
            if (!isset($bycourse[(int) $record->courseid])) {
                $bycourse[(int) $record->courseid] = $record;
            }
        }
        return $bycourse;
    }

    /**
     * Get or lazily create the instance for a learning path × target course pair.
     *
     * Returns null when the target course no longer exists — a learning path may
     * reference a course that has been deleted since.
     *
     * @param int $learningpathid The learning path id.
     * @param int $courseid The target course id.
     * @return \stdClass|null The enrol instance record, or null.
     */
    public static function ensure_instance(int $learningpathid, int $courseid): ?\stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $existing = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'courseid' => $courseid, 'customint1' => $learningpathid],
            'id ASC',
            '*',
            0,
            1
        );
        if ($existing) {
            return reset($existing);
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || $course->id == SITEID) {
            return null;
        }

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return null;
        }

        $lpname = $DB->get_field('local_adele_learning_paths', 'name', ['id' => $learningpathid]);
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => self::get_role_id(),
            'customint1' => $learningpathid,
            'name' => get_string('instancename', 'enrol_adele', $lpname ?: $learningpathid),
        ]);

        return $DB->get_record('enrol', ['id' => $instanceid]);
    }

    /**
     * The role assigned in target courses.
     *
     * enrol_adele/roleid is authoritative. A value in the legacy setting
     * local_adele/enroll_as_setting is honoured as fallback (takeover per
     * decision F-8); last resort is the student archetype.
     *
     * @return int The role id.
     */
    public static function get_role_id(): int {
        $roleid = get_config('enrol_adele', 'roleid');
        if (empty($roleid)) {
            $roleid = get_config('local_adele', 'enroll_as_setting');
        }
        if (empty($roleid)) {
            $student = get_archetype_roles('student');
            $student = reset($student);
            $roleid = $student->id ?? 0;
        }
        return (int) $roleid;
    }
}
