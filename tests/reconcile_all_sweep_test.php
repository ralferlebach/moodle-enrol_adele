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
     * Deleting the last embedding must cost the user their host access.
     *
     * Answers the question raised in issue #7: nothing used to remove host
     * enrolments once the mod_adele activity was gone, because the orphan
     * cleanup only looks at deleted LEARNING PATHS, and the learning path is
     * still very much alive here.
     *
     * @return void
     */
    public function test_sweep_revokes_host_access_after_embedding_removed(): void {
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

        $DB->delete_records('adele', ['id' => $adeleid]);

        reconciler::reconcile_all();

        $ue = $this->get_ue($lpid, (int) $host->id, $userid, instance_manager::KIND_HOST);
        $this->assertNotFalse($ue);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $ue->status,
            'Host access must not outlive the embedding that justified it.'
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
