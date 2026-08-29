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
 * End-to-end matrix for enrolment-relevant mod_adele setting changes.
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
 * End-to-end matrix for enrolment-relevant mod_adele setting changes.
 *
 * Issue #8 in one sentence: an activity's settings decide who is entitled, so
 * changing them has to change the enrolments — in BOTH directions. Building
 * up the new state was never the hard part; taking the old one away is, and
 * it is the direction with no event behind it, because widening or narrowing
 * a setting changes nobody's course membership.
 *
 * Every test here therefore does the same three things: establish a state
 * through the real code path, change one setting, and then assert what must
 * have gone as well as what must have appeared.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\reconciler::reconcile_all
 * @covers      \enrol_adele\local\reconciler::reconcile_host_embedding
 */
final class settings_propagation_test extends \advanced_testcase {
    /**
     * Skip when the companion plugins are older than this plugin needs.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele is required.');
        }
        if (!method_exists('\local_adele\enrol_state', 'get_host_entitlement')) {
            $this->markTestSkipped('The installed local_adele predates enrol_state::get_host_entitlement().');
        }
    }

    /**
     * A learning path whose starting node is $startcourse and whose second
     * node is $othercourse, embedded in $hostcourse.
     *
     * Two nodes are the minimum needed to tell subscription options 2 and 3
     * apart: option 2 only counts the starting node, option 3 counts any.
     *
     * @param int $startcourse Course of the starting node.
     * @param int $othercourse Course of the second node.
     * @param int $hostcourse Host course carrying the activity.
     * @param string $options participantslist value.
     * @param string $mode hostenrolmentmode value.
     * @return array [lpid, adeleid]
     */
    private function plant(
        int $startcourse,
        int $othercourse,
        int $hostcourse,
        string $options,
        string $mode = 'visible'
    ): array {
        global $DB;

        $json = [
            'tree' => [
                'nodes' => [
                    [
                        'id' => 'dndnode_1',
                        'type' => 'courseNode',
                        'parentCourse' => ['starting_node'],
                        'data' => ['course_node_id' => [$startcourse]],
                    ],
                    [
                        'id' => 'dndnode_2',
                        'type' => 'courseNode',
                        'parentCourse' => ['dndnode_1'],
                        'data' => ['course_node_id' => [$othercourse]],
                    ],
                ],
                'edges' => [],
            ],
        ];
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Matrix-Testpfad ' . $options . '-' . $mode . '-' . $hostcourse,
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode($json),
        ]);
        $adeleid = (int) $DB->insert_record('adele', (object) [
            'course' => $hostcourse,
            'name' => 'Matrix-Aktivität',
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

        return [$lpid, $adeleid];
    }

    /**
     * The user's ADELE host enrolment for a pair, or false.
     *
     * @param int $lpid Learning path id.
     * @param int $hostcourseid Host course id.
     * @param int $userid User id.
     * @return \stdClass|false
     */
    private function host_ue(int $lpid, int $hostcourseid, int $userid) {
        global $DB;
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $hostcourseid,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_HOST,
        ]);
        if (!$instance) {
            return false;
        }
        return $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
    }

    /**
     * Narrowing participantslist from "any node" to "starting node only"
     * must take away what only the wider setting justified.
     *
     * The expansive direction has always worked; this is the subtraction.
     *
     * @return void
     */
    public function test_participantslist_narrowed_revokes_the_now_unjustified(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $start = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $adeleid] = $this->plant(
            (int) $start->id,
            (int) $other->id,
            (int) $host->id,
            '3'
        );

        // Carried by the SECOND node only, which option 3 accepts and
        // option 2 does not.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $other->id, 'student', 'manual');
        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue, 'Precondition: option 3 must grant host access.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);

        $DB->set_field('adele', 'participantslist', '2', ['id' => $adeleid]);
        reconciler::reconcile_all();

        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int) $ue->status,
            'A user only the wider option carried must lose host access when it is withdrawn.'
        );
    }

    /**
     * Widening participantslist from "starting node only" to "any node"
     * must grant what the wider setting now justifies.
     *
     * @return void
     */
    public function test_participantslist_widened_grants_the_newly_entitled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $start = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $adeleid] = $this->plant(
            (int) $start->id,
            (int) $other->id,
            (int) $host->id,
            '2'
        );

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $other->id, 'student', 'manual');
        $this->assertFalse(
            $this->host_ue($lpid, (int) $host->id, (int) $user->id),
            'Precondition: option 2 must not grant access from a non-starting node.'
        );

        $DB->set_field('adele', 'participantslist', '3', ['id' => $adeleid]);
        reconciler::reconcile_all();

        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue, 'Widening the option must grant access.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
    }

    /**
     * The full visibility mode matrix, walked in one go.
     *
     * visible -> hidden -> none -> visible. Each step is asserted, because
     * the interesting part is not any single transition but that the state
     * follows the setting in both directions repeatedly.
     *
     * @return void
     */
    public function test_host_enrolment_mode_matrix(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $start = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $adeleid] = $this->plant(
            (int) $start->id,
            (int) $other->id,
            (int) $host->id,
            '3',
            'visible'
        );

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $start->id, 'student', 'manual');
        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue, 'Precondition: visible must grant access.');
        $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);

        $DB->set_field('adele', 'hostenrolmentmode', 'hidden', ['id' => $adeleid]);
        reconciler::reconcile_all();
        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue, 'Hidden keeps the record.');
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status, 'Hidden must not grant access.');

        $DB->set_field('adele', 'hostenrolmentmode', 'none', ['id' => $adeleid]);
        reconciler::reconcile_all();
        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        if ($ue !== false) {
            $this->assertEquals(ENROL_USER_SUSPENDED, (int) $ue->status, 'None must not grant access.');
        }

        $DB->set_field('adele', 'hostenrolmentmode', 'visible', ['id' => $adeleid]);
        reconciler::reconcile_all();
        $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
        $this->assertNotFalse($ue, 'Returning to visible must grant access again.');
        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int) $ue->status,
            'The mode is not a one-way street: going back must restore access.'
        );
    }

    /**
     * Changing the learning path and narrowing the option at the same time.
     *
     * The combined case, which is what actually happens when someone
     * re-purposes an existing activity rather than creating a new one.
     *
     * @return void
     */
    public function test_combined_path_and_option_change(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $starta = $this->getDataGenerator()->create_course();
        $othera = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpa, $adeleid] = $this->plant(
            (int) $starta->id,
            (int) $othera->id,
            (int) $host->id,
            '3'
        );

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $othera->id, 'student', 'manual');
        $this->assertNotFalse(
            $this->host_ue($lpa, (int) $host->id, (int) $user->id),
            'Precondition: the user must hold access through path A.'
        );
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpa, 'user_id' => $user->id]),
            'Precondition: the user must be subscribed to path A.'
        );

        // The activity is re-pointed at a different path AND narrowed.
        $startb = $this->getDataGenerator()->create_course();
        $otherb = $this->getDataGenerator()->create_course();
        [$lpb] = $this->plant((int) $startb->id, (int) $otherb->id, (int) $host->id, '2');
        $DB->set_field('adele', 'learningpathid', $lpb, ['id' => $adeleid]);
        $DB->set_field('adele', 'participantslist', '2', ['id' => $adeleid]);

        reconciler::reconcile_all();
        $this->run_queued_removals();

        $this->assertFalse(
            $DB->record_exists('enrol', [
                'enrol' => 'adele',
                'courseid' => $host->id,
                'customint1' => $lpa,
                'customint2' => instance_manager::KIND_HOST,
            ]),
            'The host instance of the abandoned path must be gone.'
        );
        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['learning_path_id' => $lpa, 'user_id' => $user->id]),
            'The subscription to the abandoned path must be gone.'
        );
    }

    /**
     * Saving the same configuration twice must change nothing the second time.
     *
     * @return void
     */
    public function test_repeated_reconcile_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $start = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid] = $this->plant((int) $start->id, (int) $other->id, (int) $host->id, '3');

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $start->id, 'student', 'manual');

        reconciler::reconcile_host_embedding($lpid, (int) $host->id);
        $first = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.enrolid, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
           ORDER BY ue.id ASC"
        );
        $this->assertNotEmpty($first, 'Precondition: there must be something to compare.');

        reconciler::reconcile_host_embedding($lpid, (int) $host->id);
        reconciler::reconcile_host_embedding($lpid, (int) $host->id);

        $second = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.enrolid, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
           ORDER BY ue.id ASC"
        );
        $this->assertEquals($first, $second, 'Repeating the same reconcile must not move anything.');
        $this->assertCount(
            1,
            $DB->get_records('local_adele_path_user', ['learning_path_id' => $lpid, 'user_id' => $user->id]),
            'No duplicate subscription may appear.'
        );
    }

    /**
     * Run every queued deferred removal task.
     *
     * @return void
     */
    private function run_queued_removals(): void {
        $due = time() + \enrol_adele\task\remove_user_path_adhoc::DELAY_SECONDS + 1;
        while (($task = \core\task\manager::get_next_adhoc_task($due)) !== null) {
            if ($task instanceof \enrol_adele\task\remove_user_path_adhoc) {
                $task->execute();
            }
            \core\task\manager::adhoc_task_complete($task);
        }
    }
}
