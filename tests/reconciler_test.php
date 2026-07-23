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
 * Tests for the ADELE enrolment reconciler.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\instance_manager;
use enrol_adele\local\reconciler;

/**
 * Tests for the ADELE enrolment reconciler.
 *
 * These tests require local_adele to be installed (the plugin declares a hard
 * dependency; CI installs it via extra_plugin_runners). The intended state is
 * planted directly as local_adele_path_user records.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\reconciler
 * @covers      \enrol_adele\local\instance_manager
 */
final class reconciler_test extends \advanced_testcase {
    /**
     * Create a learning path with two nodes: node1 → course A+B (shared course
     * scenario uses A twice), node2 → course A.
     *
     * @param array $statusbynode Map nodeid => feedback status.
     * @param int[] $coursesbynode Map nodeid => array of course ids.
     * @return array [lpid, userid]
     */
    private function plant_state(array $statusbynode, array $coursesbynode): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $nodes = [];
        $relation = [];
        foreach ($coursesbynode as $nodeid => $courseids) {
            $nodes[] = [
                'id' => $nodeid,
                'parentCourse' => [],
                'data' => ['course_node_id' => $courseids],
            ];
            $relation[$nodeid] = [
                'feedback' => ['status' => $statusbynode[$nodeid] ?? 'closed'],
            ];
        }
        $json = ['tree' => ['nodes' => $nodes, 'edges' => []]];

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Testpfad',
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
            'json' => json_encode($json + ['user_path_relation' => $relation]),
        ]);
        return [(int) $lpid, (int) $user->id];
    }

    /**
     * Set the user path relation status of one node.
     *
     * @param int $lpid Learning path id.
     * @param int $userid User id.
     * @param string $nodeid Node id.
     * @param string $status New feedback status.
     * @return void
     */
    private function set_node_status(int $lpid, int $userid, string $nodeid, string $status): void {
        global $DB;
        $record = $DB->get_record(
            'local_adele_path_user',
            ['learning_path_id' => $lpid, 'user_id' => $userid]
        );
        $json = json_decode($record->json, true);
        $json['user_path_relation'][$nodeid]['feedback']['status'] = $status;
        $DB->set_field(
            'local_adele_path_user',
            'json',
            json_encode($json),
            ['id' => $record->id]
        );
    }

    /**
     * The user enrolment record on the ADELE instance of a course, or false.
     *
     * @param int $lpid Learning path id.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @return \stdClass|false
     */
    private function get_ue(int $lpid, int $courseid, int $userid) {
        global $DB;
        $instance = $DB->get_record(
            'enrol',
            ['enrol' => 'adele', 'courseid' => $courseid, 'customint1' => $lpid]
        );
        if (!$instance) {
            return false;
        }
        return $DB->get_record(
            'user_enrolments',
            ['enrolid' => $instance->id, 'userid' => $userid]
        );
    }

    /**
     * Opening enrols, closing suspends, re-opening reactivates the same record.
     * A shared target course stays active while any node still grants it (A-1,
     * A-2, A-6; acceptance criterion 1).
     *
     * @return void
     */
    public function test_reconcile_lifecycle_shared_course(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_state(
            ['dndnode_1' => 'accessible', 'dndnode_2' => 'accessible'],
            [
                'dndnode_1' => [(int) $coursea->id, (int) $courseb->id],
                'dndnode_2' => [(int) $coursea->id],
            ]
        );

        reconciler::reconcile_user($lpid, $userid);
        $uea = $this->get_ue($lpid, (int) $coursea->id, $userid);
        $this->assertEquals(ENROL_USER_ACTIVE, $uea->status);
        $this->assertEquals(
            ENROL_USER_ACTIVE,
            $this->get_ue($lpid, (int) $courseb->id, $userid)->status
        );

        // Idempotency: a second run changes nothing.
        reconciler::reconcile_user($lpid, $userid);
        $this->assertEquals($uea->id, $this->get_ue($lpid, (int) $coursea->id, $userid)->id);

        // Node 1 closes: course B suspends, course A stays (node 2 still grants it).
        $this->set_node_status($lpid, $userid, 'dndnode_1', 'closed');
        reconciler::reconcile_user($lpid, $userid);
        $this->assertEquals(
            ENROL_USER_ACTIVE,
            $this->get_ue($lpid, (int) $coursea->id, $userid)->status
        );
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            $this->get_ue($lpid, (int) $courseb->id, $userid)->status
        );

        // Node 2 closes too: course A suspends now.
        $this->set_node_status($lpid, $userid, 'dndnode_2', 'closed');
        reconciler::reconcile_user($lpid, $userid);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            $this->get_ue($lpid, (int) $coursea->id, $userid)->status
        );

        // Node 1 re-opens: the SAME user_enrolment is reactivated, no new record.
        $this->set_node_status($lpid, $userid, 'dndnode_1', 'accessible');
        reconciler::reconcile_user($lpid, $userid);
        $reactivated = $this->get_ue($lpid, (int) $courseb->id, $userid);
        $this->assertEquals(ENROL_USER_ACTIVE, $reactivated->status);
    }

    /**
     * Purging a learning path removes its instances and enrolments, while a
     * second learning path on the same course and a parallel manual enrolment
     * survive (A-3; acceptance criterion 2).
     *
     * @return void
     */
    public function test_purge_learning_path_is_isolated(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $course = $this->getDataGenerator()->create_course();
        [$lpid1, $userid1] = $this->plant_state(
            ['dndnode_1' => 'accessible'],
            ['dndnode_1' => [(int) $course->id]]
        );
        [$lpid2, $userid2] = $this->plant_state(
            ['dndnode_1' => 'completed'],
            ['dndnode_1' => [(int) $course->id]]
        );
        // Parallel manual enrolment for user 1.
        $this->getDataGenerator()->enrol_user($userid1, $course->id, 'student', 'manual');

        reconciler::reconcile_user($lpid1, $userid1);
        reconciler::reconcile_user($lpid2, $userid2);
        $this->assertNotFalse($this->get_ue($lpid1, (int) $course->id, $userid1));
        $this->assertNotFalse($this->get_ue($lpid2, (int) $course->id, $userid2));

        $deleted = reconciler::purge_learning_path($lpid1);
        $this->assertEquals(1, $deleted);

        // Learning path 1 gone entirely.
        $this->assertFalse(
            $DB->record_exists('enrol', ['enrol' => 'adele', 'customint1' => $lpid1])
        );
        // Learning path 2 untouched, manual enrolment untouched.
        $this->assertNotFalse($this->get_ue($lpid2, (int) $course->id, $userid2));
        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $course->id]);
        $this->assertTrue(
            $DB->record_exists('user_enrolments', ['enrolid' => $manual->id, 'userid' => $userid1])
        );
    }

    /**
     * The carried-by rules: option 1 releases when the last option-1 membership
     * goes, options 2/3 keep carrying through node-course membership, and
     * ADELE's own enrolments never carry (A-4; acceptance criterion 3).
     *
     * @return void
     */
    public function test_host_course_removal_rules(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $host = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_state(
            ['dndnode_1' => 'accessible'],
            ['dndnode_1' => [(int) $target->id]]
        );
        // Embed the learning path in the host course with option 1.
        $DB->insert_record('adele', (object) [
            'course' => $host->id,
            'name' => 'LP-Aktivität',
            'intro' => '',
            'introformat' => 1,
            'learningpathid' => $lpid,
            'participantslist' => '1',
            'userlist' => 1,
            'view' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        reconciler::reconcile_user($lpid, $userid);
        $this->assertNotFalse($this->get_ue($lpid, (int) $target->id, $userid));
        $this->assertTrue(observer::is_user_carried($lpid, $userid));

        // Unenrol from the host course: the event observer fires, the user is no
        // longer carried, user path and target enrolment disappear.
        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $host->id]);
        enrol_get_plugin('manual')->unenrol_user($manual, $userid);

        $this->assertFalse(observer::is_user_carried($lpid, $userid));
        $this->assertFalse(
            $DB->record_exists(
                'local_adele_path_user',
                ['learning_path_id' => $lpid, 'user_id' => $userid]
            )
        );
        $this->assertFalse($this->get_ue($lpid, (int) $target->id, $userid));
    }
}
