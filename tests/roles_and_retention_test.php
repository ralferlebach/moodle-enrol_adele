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
 * Tests for role reconciliation and the suspension retention period.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\instance_manager;
use enrol_adele\local\reconciler;

/**
 * Tests for role reconciliation and the suspension retention period.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\reconciler::sync_roles
 * @covers      \enrol_adele\local\reconciler::reconcile_all
 */
final class roles_and_retention_test extends \advanced_testcase {
    /**
     * Skip when local_adele is not installed.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele is required.');
        }
    }

    /**
     * A learning path with one node course and one subscribed, entitled user.
     *
     * @param int $courseid The node course id.
     * @return array [lpid, userid]
     */
    private function plant(int $courseid): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $json = [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'courseNode',
                        'parentCourse' => [],
                        'data' => ['course_node_id' => [$courseid]],
                    ],
                ],
                'edges' => [],
            ],
        ];
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Rollen-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
            'json' => json_encode($json),
        ]);
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => 0,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
            'json' => json_encode($json + [
                'user_path_relation' => [
                    'dndnode_1' => ['feedback' => ['status' => 'accessible']],
                ],
            ]),
        ]);
        return [$lpid, (int) $user->id];
    }

    /**
     * The ADELE instance of a learning path in a course.
     *
     * @param int $lpid Learning path id.
     * @param int $courseid Course id.
     * @return \stdClass|false
     */
    private function get_instance(int $lpid, int $courseid) {
        global $DB;
        return $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $courseid,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_TARGET,
        ]);
    }

    /**
     * Changing the configured role migrates existing instances and users.
     *
     * @return void
     */
    public function test_role_change_migrates_existing_assignments(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $course->id);

        $student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $teacher = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        set_config('roleid', $student->id, 'enrol_adele');

        reconciler::reconcile_user($lpid, $userid);
        $instance = $this->get_instance($lpid, (int) $course->id);
        $this->assertNotFalse($instance, 'Precondition: the instance must exist.');
        $context = \context_course::instance($course->id);
        $this->assertTrue(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'roleid' => $student->id,
                'component' => 'enrol_adele',
                'itemid' => $instance->id,
            ]),
            'Precondition: the student role must be assigned.'
        );

        set_config('roleid', $teacher->id, 'enrol_adele');
        reconciler::sync_roles();

        $this->assertEquals(
            $teacher->id,
            (int) $DB->get_field('enrol', 'roleid', ['id' => $instance->id]),
            'The instance must carry the newly configured role.'
        );
        $this->assertTrue(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'roleid' => $teacher->id,
                'component' => 'enrol_adele',
                'itemid' => $instance->id,
            ])
        );
        $this->assertFalse(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'roleid' => $student->id,
                'component' => 'enrol_adele',
                'itemid' => $instance->id,
            ]),
            'The superseded assignment must not linger.'
        );
    }

    /**
     * An assignment removed behind the plugin's back is restored.
     *
     * Checking the instance alone cannot see this: its roleid still matches
     * the configuration, so the old implementation considered it clean and
     * never looked at whether the participants actually held the role.
     *
     * @return void
     */
    public function test_missing_role_assignment_is_restored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $course->id);
        $student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        set_config('roleid', $student->id, 'enrol_adele');

        reconciler::reconcile_user($lpid, $userid);
        $instance = $this->get_instance($lpid, (int) $course->id);
        $context = \context_course::instance($course->id);
        $this->assertTrue(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'component' => 'enrol_adele',
                'itemid' => $instance->id,
            ]),
            'Precondition: the assignment must exist.'
        );

        // A bulk tool, a partial restore or a direct edit takes it away.
        $DB->delete_records('role_assignments', [
            'userid' => $userid,
            'contextid' => $context->id,
            'component' => 'enrol_adele',
            'itemid' => $instance->id,
        ]);

        reconciler::sync_roles();

        $this->assertTrue(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'roleid' => $student->id,
                'component' => 'enrol_adele',
                'itemid' => $instance->id,
            ]),
            'The sweep must restore an assignment that went missing.'
        );
    }

    /**
     * A role a teacher assigned by hand is never touched.
     *
     * @return void
     */
    public function test_foreign_role_assignment_survives(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $course->id);
        $student = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $teacher = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        set_config('roleid', $student->id, 'enrol_adele');

        reconciler::reconcile_user($lpid, $userid);
        $context = \context_course::instance($course->id);
        // Assigned by hand: no component, no itemid.
        role_assign($teacher->id, $userid, $context->id);

        reconciler::sync_roles();

        $this->assertTrue(
            $DB->record_exists('role_assignments', [
                'userid' => $userid,
                'contextid' => $context->id,
                'roleid' => $teacher->id,
                'component' => '',
            ]),
            'A manually assigned role must never be removed by this plugin.'
        );
    }

    /**
     * Suspended enrolments are removed once they exceed the retention period,
     * and kept while they are inside it.
     *
     * @return void
     */
    public function test_retention_removes_only_long_suspended_enrolments(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $course->id);
        set_config('suspendedretention', 90, 'enrol_adele');

        reconciler::reconcile_user($lpid, $userid);
        $instance = $this->get_instance($lpid, (int) $course->id);
        $this->assertNotFalse($instance, 'Precondition: the instance must exist.');

        // Entitlement ends; the enrolment is suspended, not removed.
        $DB->set_field('local_adele_path_user', 'status', 'inactive', [
            'learning_path_id' => $lpid,
            'user_id' => $userid,
        ]);
        reconciler::reconcile_all();
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($ue, 'A freshly suspended enrolment must survive the same run.');
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status);

        // Still inside the window.
        $DB->set_field('user_enrolments', 'timemodified', time() - (89 * DAYSECS), ['id' => $ue->id]);
        reconciler::reconcile_all();
        $this->assertTrue(
            $DB->record_exists('user_enrolments', ['id' => $ue->id]),
            'An enrolment suspended for 89 days must stay.'
        );

        // Past the window.
        $DB->set_field('user_enrolments', 'timemodified', time() - (91 * DAYSECS), ['id' => $ue->id]);
        reconciler::reconcile_all();
        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['id' => $ue->id]),
            'An enrolment suspended for 91 days must be removed.'
        );
    }

    /**
     * A retention of zero keeps suspended enrolments indefinitely.
     *
     * @return void
     */
    public function test_retention_zero_keeps_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $course->id);
        set_config('suspendedretention', 0, 'enrol_adele');

        reconciler::reconcile_user($lpid, $userid);
        $instance = $this->get_instance($lpid, (int) $course->id);
        $DB->set_field('local_adele_path_user', 'status', 'inactive', [
            'learning_path_id' => $lpid,
            'user_id' => $userid,
        ]);
        reconciler::reconcile_all();

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        $this->assertNotFalse($ue);
        $DB->set_field('user_enrolments', 'timemodified', time() - (365 * DAYSECS), ['id' => $ue->id]);

        reconciler::reconcile_all();

        $this->assertTrue(
            $DB->record_exists('user_enrolments', ['id' => $ue->id]),
            'With retention 0 nothing may be removed, however old.'
        );
    }
}
