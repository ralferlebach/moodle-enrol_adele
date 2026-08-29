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
 * Tests for the bidirectional full sweep in reconcile_all().
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
 * Tests for the bidirectional full sweep in reconcile_all().
 *
 * Every test builds the NORMAL case through the real code path first and
 * asserts the precondition, then simulates a lost event by editing the
 * database directly — the situation the nightly sweep exists to heal.
 * Weakening a precondition would make the corresponding test pass without
 * proving anything.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\reconciler::reconcile_all
 */
final class reconcile_all_sweep_test extends \advanced_testcase {
    /**
     * Skip the whole class when the companion plugins are not installed.
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
     * A learning path with one node whose course is $nodecourseid, embedded in
     * $hostcourseid with the given options and mode, plus one subscribed user.
     *
     * @param int $nodecourseid The node (and starting node) course id.
     * @param int $hostcourseid The host course id.
     * @param string $options mod_adele participantslist, e.g. '3'.
     * @param string $mode hostenrolmentmode: visible, hidden or none.
     * @return array [lpid, userid, adeleid]
     */
    private function plant_embedding(
        int $nodecourseid,
        int $hostcourseid,
        string $options = '3',
        string $mode = 'visible'
    ): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $json = [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'courseNode',
                        'parentCourse' => ['starting_node'],
                        'data' => ['course_node_id' => [$nodecourseid]],
                    ],
                ],
                'edges' => [],
            ],
        ];
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Sweep-Testpfad',
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
        $adeleid = (int) $DB->insert_record('adele', (object) [
            'course' => $hostcourseid,
            'name' => 'LP-Aktivität',
            'intro' => '',
            'introformat' => 1,
            'learningpathid' => $lpid,
            'participantslist' => $options,
            'hostenrolmentmode' => $mode,
            'userlist' => 1,
            'view' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [$lpid, (int) $user->id, $adeleid];
    }

    /**
     * Force one node's feedback status back to what the test planted.
     *
     * Enrolling a user through the data generator fires mod_adele's observer,
     * which re-subscribes via local_adele and synchronously triggers its own
     * recompute pipeline. That pipeline derives node status from real
     * completion and restriction data — which this bare-bones fixture node
     * never carried — and overwrites the planted value. A property of the
     * fixture, not of either plugin: reset it before asserting anything that
     * depends on target-course entitlement.
     *
     * @param int $lpid Learning path id.
     * @param int $userid User id.
     * @param string $status The feedback status to restore.
     * @return void
     */
    private function set_node_status(int $lpid, int $userid, string $status): void {
        global $DB;
        $record = $DB->get_record('local_adele_path_user', [
            'learning_path_id' => $lpid,
            'user_id' => $userid,
        ]);
        if (!$record) {
            return;
        }
        $json = json_decode($record->json, true);
        $json['user_path_relation']['dndnode_1']['feedback']['status'] = $status;
        $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
    }

    /**
     * The user enrolment on the ADELE instance of one kind, or false.
     *
     * @param int $lpid Learning path id.
     * @param int $courseid Course id.
     * @param int $userid User id.
     * @param int $kind instance_manager::KIND_TARGET or KIND_HOST.
     * @return \stdClass|false
     */
    private function get_ue(int $lpid, int $courseid, int $userid, int $kind) {
        global $DB;
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $courseid,
            'customint1' => $lpid,
            'customint2' => $kind,
        ]);
        if (!$instance) {
            return false;
        }
        return $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
    }

    /**
     * A missed unenrolment event must not leave host access active forever.
     *
     * The user gains host access through the real observer path, then their
     * carrying node-course enrolment is deleted straight from the database,
     * so no event ever fires. Before the host pass existed, reconcile_all()
     * swept target courses only and this enrolment stayed ACTIVE for good.
     *
     * @return void
     */
    public function test_sweep_revokes_host_access_after_missed_unenrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');

        // Normal case: enrolling into the node course grants host access.
        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');
        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertNotFalse($ue, 'Precondition: the observer must grant host access.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);

        // The lost event: remove the carrying enrolment without firing anything.
        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $node->id]);
        $DB->delete_records('user_enrolments', ['enrolid' => $manual->id, 'userid' => $userid]);

        reconciler::reconcile_all();

        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertNotFalse($ue);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $ue->status,
            'The sweep must revoke host access the observer never got to revoke.'
        );
    }

    /**
     * External drift in the other direction must be healed too.
     *
     * The ADELE host enrolment is deleted from the database while the user
     * remains fully entitled. The sweep has to notice and grant it again.
     *
     * @return void
     */
    public function test_sweep_restores_host_access_after_external_drift(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');

        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');
        $this->assertNotFalse(
            $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST),
            'Precondition: the observer must grant host access.'
        );

        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $host->id,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_HOST,
        ]);
        $DB->delete_records('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);

        reconciler::reconcile_all();

        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertNotFalse($ue, 'The sweep must restore an entitlement that was lost externally.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
    }

    /**
     * A target-course enrolment without an active user path must be suspended.
     *
     * This is the actual-to-wanted direction: the sweep used to enumerate
     * active user paths only, so a user whose path row had gone away was
     * never visited and kept course access indefinitely.
     *
     * @return void
     */
    public function test_sweep_suspends_target_enrolment_without_user_path(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');

        reconciler::reconcile_user($lpid, $userid);
        $ue = $this->get_ue($lpid, (int) $node->id, $userid, instance_manager::KIND_TARGET);
        $this->assertNotFalse($ue, 'Precondition: the user must hold a target enrolment.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);

        // The user path disappears without anything cleaning up after it.
        $DB->delete_records('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]);

        reconciler::reconcile_all();

        $ue = $this->get_ue($lpid, (int) $node->id, $userid, instance_manager::KIND_TARGET);
        $this->assertNotFalse($ue);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $ue->status,
            'An enrolment no active user path justifies must not stay active.'
        );
    }

    /**
     * Deleting the last embedding must cost the user their host access, and
     * take the instance with it.
     *
     * Answers the question raised in issue #7: nothing used to remove host
     * enrolments once the mod_adele activity was gone, because the orphan
     * cleanup only looks at deleted LEARNING PATHS, and the learning path is
     * still very much alive here. Suspending would not be enough — an
     * instance with no embedding has nothing left that could ever justify it
     * again, so it is removed outright, unlike a user who merely lost their
     * entitlement for now.
     *
     * The target-course instance of the same learning path must survive: it
     * is justified by the learning path, not by the embedding.
     *
     * @return void
     */
    public function test_sweep_removes_host_instance_after_embedding_removed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $adeleid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');

        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');
        $this->assertNotFalse(
            $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST),
            'Precondition: the observer must grant host access.'
        );
        $this->set_node_status($lpid, $userid, 'accessible');
        reconciler::reconcile_user($lpid, $userid);
        $this->assertNotFalse(
            $this->get_ue($lpid, (int) $node->id, $userid, instance_manager::KIND_TARGET),
            'Precondition: the target course enrolment must exist.'
        );

        $DB->delete_records('adele', ['id' => $adeleid]);

        reconciler::reconcile_all();

        $this->assertFalse(
            $DB->record_exists('enrol', [
                'enrol' => 'adele',
                'courseid' => $host->id,
                'customint1' => $lpid,
                'customint2' => instance_manager::KIND_HOST,
            ]),
            'A host instance without an embedding must be removed, not kept suspended.'
        );
        $this->assertNotFalse(
            $this->get_ue($lpid, (int) $node->id, $userid, instance_manager::KIND_TARGET),
            'The target course instance is justified by the learning path and must survive.'
        );
    }

    /**
     * Changing hostenrolmentmode to hidden must take effect on the next sweep.
     *
     * Until the host pass existed, a mode change only reached existing
     * participants when they happened to trigger an enrolment event, which
     * for a settled cohort means never.
     *
     * @return void
     */
    public function test_sweep_applies_changed_host_enrolment_mode(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $adeleid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3', 'visible');

        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');
        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertNotFalse($ue, 'Precondition: the observer must grant host access.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);

        $DB->set_field('adele', 'hostenrolmentmode', 'hidden', ['id' => $adeleid]);

        reconciler::reconcile_all();

        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $ue->status,
            'Hidden mode keeps the record but must not grant access.'
        );
    }

    /**
     * Moving an activity from learning path A to B must take A's state with
     * it — and establish B's.
     *
     * The gap issue #8 asks about, and the one case where every other pass
     * looks the other way. Nothing changes about the user's ENROLMENTS when
     * an activity is re-pointed, so no event fires; the subscription to A
     * stays active, and pass 4 then re-grants access to A's node courses on
     * every single run. The user sits in a learning path nothing embeds.
     *
     * @return void
     */
    public function test_switching_the_embedded_learning_path_removes_the_old_state(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $nodea = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpa, $userid, $adeleid] = $this->plant_embedding((int) $nodea->id, (int) $host->id, '3');

        $this->getDataGenerator()->enrol_user($userid, $nodea->id, 'student', 'manual');
        $this->set_node_status($lpa, $userid, 'accessible');
        reconciler::reconcile_user($lpa, $userid);
        $this->assertNotFalse(
            $this->get_ue($lpa, (int) $nodea->id, $userid, instance_manager::KIND_TARGET),
            'Precondition: the user must hold access to the old path.'
        );

        // A second learning path, and the activity re-pointed at it.
        $nodeb = $this->getDataGenerator()->create_course();
        [$lpb] = $this->plant_embedding((int) $nodeb->id, (int) $host->id, '3');
        $DB->set_field('adele', 'learningpathid', $lpb, ['id' => $adeleid]);

        reconciler::reconcile_all();
        $this->run_queued_removals();

        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpa, 'user_id' => $userid]),
            'The subscription to the path the activity no longer embeds must go.'
        );
        $ue = $this->get_ue($lpa, (int) $nodea->id, $userid, instance_manager::KIND_TARGET);
        $this->assertFalse(
            $ue,
            'Access to the old path must not survive it.'
        );
    }

    /**
     * Deleting the activity must not leave its subscribers behind either.
     *
     * Same mechanism as the path switch, reached differently: here the
     * embedding disappears entirely rather than moving.
     *
     * @return void
     */
    public function test_deleting_the_activity_removes_the_subscription(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $adeleid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');

        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]),
            'Precondition: the subscription must exist.'
        );

        $DB->delete_records('adele', ['id' => $adeleid]);

        reconciler::reconcile_all();
        $this->run_queued_removals();

        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]),
            'A subscription no embedding carries must not outlive the embedding.'
        );
    }

    /**
     * A still-carried subscription must survive the sweep untouched.
     *
     * The counter-test to the two above: the sweep must not become a
     * general-purpose subscription reaper.
     *
     * @return void
     */
    public function test_carried_subscription_survives_the_sweep(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');
        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');

        reconciler::reconcile_all();
        $this->run_queued_removals();

        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]),
            'A subscription a node course enrolment still carries must stay.'
        );
    }

    /**
     * Run every queued deferred removal task.
     *
     * @return int Number of tasks executed.
     */
    private function run_queued_removals(): int {
        $executed = 0;
        $due = time() + \enrol_adele\task\remove_user_path_adhoc::DELAY_SECONDS + 1;
        while (($task = \core\task\manager::get_next_adhoc_task($due)) !== null) {
            if ($task instanceof \enrol_adele\task\remove_user_path_adhoc) {
                $task->execute();
                $executed++;
            }
            \core\task\manager::adhoc_task_complete($task);
        }
        return $executed;
    }

    /**
     * A second run must change nothing.
     *
     * Idempotence is what makes a nightly sweep safe to run unattended: if
     * the second pass still moved records, the first one had not converged.
     *
     * @return void
     */
    public function test_second_run_changes_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding((int) $node->id, (int) $host->id, '3');
        $this->getDataGenerator()->enrol_user($userid, $node->id, 'student', 'manual');

        reconciler::reconcile_all();
        $first = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.enrolid, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
           ORDER BY ue.id ASC"
        );
        $this->assertNotEmpty($first, 'Precondition: the sweep must have produced enrolments to compare.');

        reconciler::reconcile_all();
        $second = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.enrolid, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
           ORDER BY ue.id ASC"
        );

        $this->assertEquals($first, $second, 'A converged sweep must not change anything on a second run.');
    }
}
