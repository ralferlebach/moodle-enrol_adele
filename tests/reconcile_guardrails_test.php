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
 * Guardrails for the reconciler's destructive passes (PR #9 review).
 *
 * Two hazards found in review:
 *
 * F1 - the "cannot tell is not a no" rule was enforced on the entitlement
 * channel but not on the embeddings channel: with mod_adele unreachable,
 * enrol_state::get_host_embeddings() returns [] - indistinguishable from
 * "nothing is embedded" - and the sweep would delete every host instance
 * site-wide and queue every subscription for deferred removal.
 *
 * F2 - the retention pass treated every long-suspended enrolment as
 * unjustified. False for hidden-mode host enrolments (suspended BY DESIGN,
 * forever, to stay countable in reports) and for target enrolments of users
 * still active on a path whose node merely closed.
 *
 * @package    enrol_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use advanced_testcase;
use enrol_adele\local\instance_manager;
use enrol_adele\local\reconciler;

/**
 * Destructive-pass guardrails (PR #9 review findings F1/F2).
 *
 * @package    enrol_adele
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \enrol_adele\local\reconciler
 */
final class reconcile_guardrails_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele is required.');
        }
        if (!class_exists('\mod_adele\local\host_policy')) {
            $this->markTestSkipped('mod_adele >= 0.3.0 (host_policy) is required.');
        }
    }

    protected function tearDown(): void {
        reconciler::$hostpolicyreachableoverride = null;
        parent::tearDown();
    }

    /**
     * A learning path with one node course, embedded in a host course with the
     * given options and mode, plus one subscribed user (same fixture shape as
     * reconcile_all_sweep_test).
     *
     * @param int $nodecourseid The node (and starting node) course id.
     * @param int $hostcourseid The host course id.
     * @param string $options mod_adele participantslist, e.g. '3'.
     * @param string $mode hostenrolmentmode: visible, hidden or none.
     * @return array [lpid, userid]
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
            'name' => 'Guardrail-Testpfad',
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
        $DB->insert_record('adele', (object) [
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

        return [$lpid, (int) $user->id];
    }

    /**
     * The user's host-course enrolment row, or false.
     *
     * @param int $hostcourseid The host course id.
     * @param int $userid The user id.
     * @return \stdClass|false
     */
    private function host_ue(int $hostcourseid, int $userid) {
        global $DB;
        return $DB->get_record_sql(
            "SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele' AND e.customint2 = :kind
                    AND e.courseid = :courseid AND ue.userid = :userid",
            ['kind' => instance_manager::KIND_HOST, 'courseid' => $hostcourseid, 'userid' => $userid]
        );
    }

    /**
     * F1: with mod_adele unreachable the embeddings channel degrades to [],
     * which must SKIP the destructive passes loudly - not read as "nothing is
     * embedded anywhere" and delete every host instance and subscription.
     *
     * @return void
     */
    public function test_unreachable_host_policy_skips_destructive_passes(): void {
        global $DB;
        $this->resetAfterTest(true);
        $host = $this->getDataGenerator()->create_course();
        $node = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding($node->id, $host->id, '3', 'visible');
        // Option 3 entitles via a (non-adele) enrolment in any node course.
        $this->getDataGenerator()->enrol_user($userid, $node->id);

        reconciler::reconcile_all();
        $this->assertNotFalse($this->host_ue($host->id, $userid), 'Precondition: host access granted.');
        $this->resetDebugging();

        // Simulate mod_adele being unloadable (missing from disk, autoload
        // fatal) - cron still runs in that state.
        reconciler::$hostpolicyreachableoverride = false;
        reconciler::reconcile_all();

        $this->assertNotFalse(
            $this->host_ue($host->id, $userid),
            'The host enrolment must survive a sweep that cannot reach mod_adele.'
        );
        $this->assertTrue(
            $DB->record_exists('enrol', ['enrol' => 'adele', 'customint2' => instance_manager::KIND_HOST]),
            'Host enrol instances must not be deleted as "unembedded".'
        );
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]),
            'The subscription must not be queued away.'
        );
        $queued = $DB->count_records_select(
            'task_adhoc',
            $DB->sql_like('classname', ':cls'),
            ['cls' => '%remove_user_path_adhoc%']
        );
        $this->assertSame(0, $queued, 'No deferred removals may be queued while the answer is unknowable.');

        $messages = array_map(static fn($m) => $m->message, $this->getDebuggingMessages());
        $this->resetDebugging();
        $this->assertNotEmpty(
            array_filter($messages, static fn($m) => strpos($m, 'host_policy') !== false),
            'The skip must be loud, naming host_policy.'
        );
    }

    /**
     * F1: the deferred-removal task must keep the learner's record when it
     * cannot verify the departure - deleting on uncertainty is the data loss
     * the task exists to prevent.
     *
     * @return void
     */
    public function test_unreachable_host_policy_keeps_data_in_the_removal_task(): void {
        global $DB;
        $this->resetAfterTest(true);
        $host = $this->getDataGenerator()->create_course();
        $node = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding($node->id, $host->id, '3', 'visible');

        reconciler::$hostpolicyreachableoverride = false;
        $task = new \enrol_adele\task\remove_user_path_adhoc();
        $task->set_custom_data(['learningpathid' => $lpid, 'userid' => $userid]);
        $task->execute();
        $this->resetDebugging();

        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $userid]),
            'The only copy of the learner\'s progress must survive an unverifiable removal.'
        );
    }

    /**
     * F2: an entitled hidden-mode host enrolment is suspended BY DESIGN -
     * the retention pass must not purge it however old the suspension is.
     *
     * @return void
     */
    public function test_retention_keeps_entitled_hidden_mode_enrolments(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('suspendedretention', 30, 'enrol_adele');
        $host = $this->getDataGenerator()->create_course();
        $node = $this->getDataGenerator()->create_course();
        [, $userid] = $this->plant_embedding($node->id, $host->id, '3', 'hidden');
        // Option 3 entitles via a (non-adele) enrolment in any node course.
        $this->getDataGenerator()->enrol_user($userid, $node->id);

        reconciler::reconcile_all();
        $ue = $this->host_ue($host->id, $userid);
        $this->assertNotFalse($ue, 'Precondition: hidden-mode host enrolment exists.');
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status, 'Hidden mode means suspended by design.');

        // Age the suspension far beyond the retention window.
        $DB->set_field('user_enrolments', 'timemodified', time() - 40 * DAYSECS, ['id' => $ue->id]);
        reconciler::reconcile_all();

        $this->assertNotFalse(
            $this->host_ue($host->id, $userid),
            'An ENTITLED hidden-mode enrolment must survive the retention pass.'
        );
    }

    /**
     * F2: a suspended TARGET enrolment of a user still active on the path
     * (their node merely closed) is not a departed learner - retention must
     * leave it alone.
     *
     * @return void
     */
    public function test_retention_keeps_target_enrolments_of_active_paths(): void {
        global $DB;
        $this->resetAfterTest(true);
        set_config('suspendedretention', 30, 'enrol_adele');
        $host = $this->getDataGenerator()->create_course();
        $node = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_embedding($node->id, $host->id, '3', 'visible');

        reconciler::reconcile_all();
        // The node closes: the target enrolment is suspended, the path stays active.
        $record = $DB->get_record('local_adele_path_user', [
            'learning_path_id' => $lpid, 'user_id' => $userid,
        ]);
        $json = json_decode($record->json, true);
        $json['user_path_relation']['dndnode_1']['feedback']['status'] = 'closed';
        $DB->set_field('local_adele_path_user', 'json', json_encode($json), ['id' => $record->id]);
        reconciler::reconcile_all();

        $target = $DB->get_record_sql(
            "SELECT ue.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele' AND e.customint2 = :kind
                    AND e.courseid = :courseid AND ue.userid = :userid",
            ['kind' => instance_manager::KIND_TARGET, 'courseid' => $node->id, 'userid' => $userid]
        );
        $this->assertNotFalse($target, 'Precondition: target enrolment exists.');
        $this->assertEquals(ENROL_USER_SUSPENDED, $target->status, 'Closed node means suspended target.');

        $DB->set_field('user_enrolments', 'timemodified', time() - 40 * DAYSECS, ['id' => $target->id]);
        reconciler::reconcile_all();

        $this->assertNotFalse(
            $DB->get_record('user_enrolments', ['id' => $target->id]),
            'A closed-node suspension of an ACTIVE learner must survive the retention pass.'
        );
    }
}
