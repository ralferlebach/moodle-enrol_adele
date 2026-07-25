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
 * Two independent kinds of instance share the same mechanics but serve
 * opposite directions of the relationship:
 *
 * - KIND_TARGET (unchanged since 0.1.1): courseid = a course a learning path
 *   node grants access to. Reconciled from local_adele's node feedback status
 *   (see reconciler::reconcile_user()).
 * - KIND_HOST (new in 0.1.2): courseid = the course that embeds the mod_adele
 *   activity. Used only for subscription options 2/3 ("starting node" / "any
 *   node"), where mod_adele grants host-course membership as a CONSEQUENCE of
 *   the learner already holding a node-course enrolment, rather than the
 *   other way around.
 *   Reconciled from a caller-supplied boolean (see
 *   reconciler::reconcile_host_user()), because only mod_adele knows which
 *   courses count as "starting" or "any" node for a given embedding.
 *
 * Both kinds share the identity (enrol='adele', courseid, customint1) plus a
 * customint2 discriminator, so a course that happens to be both a host and a
 * target of the same learning path (a self-embedding edge case) still gets
 * two distinct, independently manageable instances rather than a collision.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\local;

/**
 * Manages the enrol instances owned by ADELE.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instance_manager {
    /** @var int Instance grants access to a learning path TARGET (node) course. */
    const KIND_TARGET = 1;

    /** @var int Instance grants access to a learning path HOST course (options 2/3). */
    const KIND_HOST = 2;

    /**
     * All ADELE enrol instances of one kind belonging to a learning path, keyed
     * by course id.
     *
     * Should the identity invariant ever be violated (two instances for the same
     * pair), the first one wins deterministically (lowest id).
     *
     * @param int $learningpathid The learning path id.
     * @param int $kind KIND_TARGET or KIND_HOST.
     * @return \stdClass[] Enrol instance records keyed by course id.
     */
    public static function get_instances(int $learningpathid, int $kind = self::KIND_TARGET): array {
        global $DB;
        $records = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'customint1' => $learningpathid, 'customint2' => $kind],
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
     * Get or lazily create the instance for a learning path × course × kind triple.
     *
     * Returns null when the course no longer exists — a learning path may
     * reference a course that has been deleted since.
     *
     * @param int $learningpathid The learning path id.
     * @param int $courseid The course id (target course for KIND_TARGET, host
     *   course for KIND_HOST).
     * @param int $kind KIND_TARGET or KIND_HOST.
     * @return \stdClass|null The enrol instance record, or null.
     */
    public static function ensure_instance(
        int $learningpathid,
        int $courseid,
        int $kind = self::KIND_TARGET
    ): ?\stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $existing = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'courseid' => $courseid, 'customint1' => $learningpathid, 'customint2' => $kind],
            'id ASC',
            '*',
            0,
            1
        );
        if ($existing) {
            return reset($existing);
        }

        // Without a lock, two near-simultaneous events for the same
        // (learning path, course, kind) could both pass the existence check
        // above and each create an instance.
        $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_adele_instance');
        $resource = "lp{$learningpathid}_course{$courseid}_kind{$kind}";
        $lock = $lockfactory->get_lock($resource, 5);
        if (!$lock) {
            // Could not acquire the lock within the timeout — fail closed
            // rather than risk a duplicate; the next reconcile pass will
            // retry (all operations are idempotent).
            return null;
        }

        try {
            // Re-check inside the lock: another process may have created
            // the instance while we were waiting for it.
            $existing = $DB->get_records(
                'enrol',
                ['enrol' => 'adele', 'courseid' => $courseid, 'customint1' => $learningpathid, 'customint2' => $kind],
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
            $namestring = $kind === self::KIND_HOST ? 'instancenamehost' : 'instancename';
            $instanceid = $plugin->add_instance($course, [
                'status' => ENROL_INSTANCE_ENABLED,
                'roleid' => self::get_role_id(),
                'customint1' => $learningpathid,
                'customint2' => $kind,
                'name' => get_string($namestring, 'enrol_adele', $lpname ?: $learningpathid),
            ]);

            return $DB->get_record('enrol', ['id' => $instanceid]);
        } finally {
            $lock->release();
        }
    }

    /**
     * The role assigned in both target and host courses.
     *
     * enrol_adele/roleid is authoritative; the last resort is the student
     * archetype.
     *
     * @return int The role id.
     */
    public static function get_role_id(): int {
        $roleid = get_config('enrol_adele', 'roleid');
        if (empty($roleid)) {
            $student = get_archetype_roles('student');
            $student = reset($student);
            $roleid = $student->id ?? 0;
        }
        return (int) $roleid;
    }
}
