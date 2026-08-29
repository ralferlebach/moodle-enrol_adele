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
 * Tests for the deferred removal of a user's learning path record.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\task\remove_user_path_adhoc;

/**
 * Tests for the deferred removal of a user's learning path record.
 *
 * A short-lived unenrolment — a cohort resync, a mistake corrected seconds
 * later, a bulk tool rebuilding memberships — used to destroy the user's
 * entire learning history, because the observer deleted the only copy of it
 * the moment the last carrying enrolment vanished (issue #3).
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\task\remove_user_path_adhoc
 * @covers      \enrol_adele\observer::user_enrolment_deleted
 */
final class transient_unenrolment_test extends \advanced_testcase {
    /**
     * Skip when the companion plugins are not installed.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele is required.');
        }
        if (!class_exists('\mod_adele\local\host_policy')) {
            $this->markTestSkipped('mod_adele >= 0.3.0 (host_policy) is required.');
        }
    }

    /**
     * A learning path with one node course, embedded in a host course with
     * option 1, and one subscribed user carrying a progress sentinel.
     *
     * @param int $nodecourseid The node course id.
     * @param int $hostcourseid The host course id.
     * @return array [lpid, userid, pathuserid]
     */
    private function plant(int $nodecourseid, int $hostcourseid): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $json = [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'courseNode',
                        'parentCourse' => [],
                        'data' => ['course_node_id' => [$nodecourseid]],
                    ],
                ],
                'edges' => [],
            ],
        ];
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Blip-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
            'json' => json_encode($json),
        ]);
        $pathuserid = (int) $DB->insert_record('local_adele_path_user', (object) [
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
                // The sentinel stands in for everything the record uniquely
                // holds: node progress, teacher overrides, first_enrolled.
                'sentinel' => 'monate-fortschritt',
            ]),
        ]);
        $DB->insert_record('adele', (object) [
            'course' => $hostcourseid,
            'name' => 'LP-Aktivität',
            'intro' => '',
            'introformat' => 1,
            'learningpathid' => $lpid,
            'participantslist' => '1',
            'hostenrolmentmode' => 'visible',
            'userlist' => 1,
            'view' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [$lpid, (int) $user->id, $pathuserid];
    }

    /**
     * Run the queued removal task, if there is one.
     *
     * @return bool Whether a task was found and executed.
     */
    private function run_queued_removal(): bool {
        $task = \core\task\manager::get_next_adhoc_task(time() + remove_user_path_adhoc::DELAY_SECONDS + 1);
        while ($task !== null) {
            if ($task instanceof remove_user_path_adhoc) {
                $task->execute();
                \core\task\manager::adhoc_task_complete($task);
                return true;
            }
            \core\task\manager::adhoc_task_complete($task);
            $task = \core\task\manager::get_next_adhoc_task(time() + remove_user_path_adhoc::DELAY_SECONDS + 1);
        }
        return false;
    }

    /**
     * Losing the carrying enrolment withdraws access at once but leaves the
     * record standing until the deferred task has had its say.
     *
     * @return void
     */
    public function test_losing_carrying_enrolment_defers_the_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $pathuserid] = $this->plant((int) $node->id, (int) $host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        $this->assertTrue(
            observer::is_user_carried($lpid, $userid),
            'Precondition: host course membership must carry the user (option 1).'
        );

        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $host->id]);
        enrol_get_plugin('manual')->unenrol_user($manual, $userid);

        $this->assertFalse(observer::is_user_carried($lpid, $userid));
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['id' => $pathuserid]),
            'The observer must not destroy the learning history itself.'
        );

        $this->assertTrue($this->run_queued_removal(), 'A deferred removal task must have been queued.');
        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['id' => $pathuserid]),
            'A departure that proved durable must remove the record.'
        );
    }

    /**
     * A resync blip must leave the snapshot completely untouched.
     *
     * The user is unenrolled and immediately re-enrolled, exactly as a cohort
     * resync does. When the deferred task runs, the user is carried again, so
     * nothing is deleted: same row id, same sentinel. Before the deferral this
     * assertion failed on the row id — the user had silently been handed a
     * brand new, empty snapshot.
     *
     * @return void
     */
    public function test_resync_blip_preserves_progress(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $pathuserid] = $this->plant((int) $node->id, (int) $host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        $this->assertTrue(observer::is_user_carried($lpid, $userid));

        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $host->id]);
        enrol_get_plugin('manual')->unenrol_user($manual, $userid);
        // The resync puts the membership straight back.
        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        $this->assertTrue($this->run_queued_removal(), 'A deferred removal task must have been queued.');

        $record = $DB->get_record('local_adele_path_user', [
            'learning_path_id' => $lpid,
            'user_id' => $userid,
        ]);
        $this->assertNotFalse($record, 'The snapshot must survive a blip.');
        $this->assertEquals(
            $pathuserid,
            (int) $record->id,
            'It must be the SAME record, not a fresh empty one.'
        );
        $json = json_decode($record->json, true);
        $this->assertEquals('monate-fortschritt', $json['sentinel'] ?? null);
    }

    /**
     * Anything re-enrolled during the waiting window is cleaned up when the
     * deletion finally happens.
     *
     * While the record is still active a recompute may legitimately re-create
     * the target course enrolment the observer purged. If the departure then
     * proves real, that enrolment must not survive the deletion of the record
     * it was derived from.
     *
     * @return void
     */
    public function test_reenrolment_during_the_window_is_purged_on_deletion(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $node->id, (int) $host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $host->id]);
        enrol_get_plugin('manual')->unenrol_user($manual, $userid);

        // A recompute in the waiting window re-creates the target enrolment,
        // because the record is still active.
        local\reconciler::reconcile_user($lpid, $userid);

        $this->assertTrue($this->run_queued_removal());

        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $node->id,
            'customint1' => $lpid,
            'customint2' => local\instance_manager::KIND_TARGET,
        ]);
        if ($instance) {
            $this->assertFalse(
                $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]),
                'An enrolment re-created during the window must not outlive the record.'
            );
        }
    }
}
