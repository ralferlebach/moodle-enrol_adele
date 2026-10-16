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

namespace enrol_adele;

use enrol_adele\local\instance_manager;
use enrol_adele\local\reconciler;
use enrol_adele\task\reconcile_host_embedding_adhoc;

/**
 * Settings taken back after they took effect - the two rules decided on
 * 2026-10-13.
 *
 * 1. A participant source removed in a saved activity takes its
 *    subscriptions with it at once - not after the nightly sweep and the
 *    delay that protects against enrolment blips.
 * 2. Host mode "none" after an enrolment that was active: suspended at once,
 *    removed after the retention period. "hidden" stays suspended for good
 *    (that record keeps the learner countable).
 *
 * The browser counterpart is mod_adele's withdrawn-settings.spec.ts.
 *
 * @package     enrol_adele
 * @category    test
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\reconciler::withdraw_uncarried_subscriptions
 * @covers      \enrol_adele\local\reconciler::remove_uncarried_user
 * @covers      \enrol_adele\local\reconciler::remove_unused_host_instance
 * @covers      \enrol_adele\task\reconcile_host_embedding_adhoc
 */
final class withdrawn_settings_test extends \advanced_testcase {
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
     * A one-node learning path on $nodecourse.
     *
     * @param int $nodecourse The course of the starting node.
     * @return int The learning path id.
     */
    private function path(int $nodecourse): int {
        global $DB;
        $json = ['tree' => ['nodes' => [[
            'id' => 'dndnode_1',
            'type' => 'courseNode',
            'parentCourse' => ['starting_node'],
            'data' => ['course_node_id' => [$nodecourse]],
        ]], 'edges' => []]];
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Rücknahme-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode($json),
        ]);
    }

    /**
     * Embed a path into a course.
     *
     * @param int $lpid The learning path.
     * @param int $hostcourse The course.
     * @param string $options participantslist.
     * @param string $mode hostenrolmentmode.
     * @return int The activity's id.
     */
    private function embed(int $lpid, int $hostcourse, string $options, string $mode = 'visible'): int {
        global $DB;
        return (int) $DB->insert_record('adele', (object) [
            'course' => $hostcourse,
            'name' => 'Rücknahme-Aktivität',
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
    }

    /**
     * Subscribe a user to a path, with a sentinel standing for their history.
     *
     * @param int $lpid The learning path.
     * @param int $userid The user.
     * @return int The subscription's id.
     */
    private function subscribe(int $lpid, int $userid): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => 0,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []], 'sentinel' => 'verlauf']),
        ]);
    }

    /**
     * Run the task a teacher's save queues.
     *
     * @param int $lpid The learning path.
     * @param int $hostcourse The host course.
     * @param bool $withdraw Whether the save asked for the withdrawal.
     * @return void
     */
    private function saved(int $lpid, int $hostcourse, bool $withdraw): void {
        $task = new reconcile_host_embedding_adhoc();
        $task->set_custom_data([
            'learningpathid' => $lpid,
            'hostcourseid' => $hostcourse,
            'withdrawuncarried' => $withdraw,
        ]);
        $task->execute();
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
     * Rule 1: a source removed in a saved activity takes the subscriptions
     * only it carried - at once; a subscription another source carries stays.
     *
     * @return void
     */
    public function test_saved_source_removal_withdraws_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $adeleid = $this->embed($lpid, (int) $host->id, '1,2');

        // A: carried by "this course" only. B: carried by "starting node" only.
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $suba = $this->subscribe($lpid, (int) $a->id);
        $subb = $this->subscribe($lpid, (int) $b->id);
        $this->getDataGenerator()->enrol_user($a->id, $host->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($b->id, $node->id, 'student', 'manual');
        $this->assertTrue(observer::is_user_carried($lpid, (int) $a->id), 'Precondition: "this course" carries A.');
        $this->assertTrue(observer::is_user_carried($lpid, (int) $b->id), 'Precondition: "starting node" carries B.');

        $DB->set_field('adele', 'participantslist', '2', ['id' => $adeleid]);
        $this->saved($lpid, (int) $host->id, true);

        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['id' => $suba]),
            'The source that carried A is gone: A leaves the path when the form is saved.'
        );
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['id' => $subb]),
            'B is carried by the remaining source and keeps the path, history included.'
        );
        $this->assertTrue(
            is_enrolled(\context_course::instance($host->id), $a->id),
            "A's own enrolment in the host course is not ADELE's and stays."
        );
    }

    /**
     * Counter-check: the nightly path (no withdrawal asked for) still leaves
     * the subscription to the deferred removal - the protection against
     * enrolment blips is untouched.
     *
     * @return void
     */
    public function test_without_the_flag_the_removal_stays_deferred(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $adeleid = $this->embed($lpid, (int) $host->id, '1');
        $a = $this->getDataGenerator()->create_user();
        $suba = $this->subscribe($lpid, (int) $a->id);
        $this->getDataGenerator()->enrol_user($a->id, $host->id, 'student', 'manual');

        $DB->set_field('adele', 'participantslist', '2', ['id' => $adeleid]);
        $this->saved($lpid, (int) $host->id, false);

        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['id' => $suba]),
            'Without the flag nothing is withdrawn at once.'
        );
    }

    /**
     * The withdrawal decides nothing when mod_adele cannot be asked: an
     * empty answer must not read as "not carried".
     *
     * @return void
     */
    public function test_withdrawal_keeps_everything_when_mod_adele_cannot_answer(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $a = $this->getDataGenerator()->create_user();
        $suba = $this->subscribe($lpid, (int) $a->id);

        reconciler::$hostpolicyreachableoverride = false;
        try {
            $this->assertSame(0, reconciler::withdraw_uncarried_subscriptions($lpid));
            $this->assertDebuggingCalled();
        } finally {
            reconciler::$hostpolicyreachableoverride = null;
        }
        $this->assertTrue($DB->record_exists('local_adele_path_user', ['id' => $suba]));
    }

    /**
     * Rule 2: "none" after an active enrolment - suspended at once, removed
     * after the retention period. "hidden" stays suspended for good.
     *
     * @return void
     */
    public function test_none_is_suspended_then_removed_hidden_is_kept(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('suspendedretention', 90, 'enrol_adele');

        $node = $this->getDataGenerator()->create_course();
        $hostnone = $this->getDataGenerator()->create_course();
        $hosthidden = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $none = $this->embed($lpid, (int) $hostnone->id, '2');
        $hidden = $this->embed($lpid, (int) $hosthidden->id, '2');
        $user = $this->getDataGenerator()->create_user();
        $this->subscribe($lpid, (int) $user->id);
        $this->getDataGenerator()->enrol_user($user->id, $node->id, 'student', 'manual');
        reconciler::reconcile_all();
        foreach ([$hostnone, $hosthidden] as $host) {
            $ue = $this->host_ue($lpid, (int) $host->id, (int) $user->id);
            $this->assertNotFalse($ue, 'Precondition: "visible" granted host access.');
            $this->assertEquals(ENROL_USER_ACTIVE, (int) $ue->status);
        }

        $DB->set_field('adele', 'hostenrolmentmode', 'none', ['id' => $none]);
        $DB->set_field('adele', 'hostenrolmentmode', 'hidden', ['id' => $hidden]);
        reconciler::reconcile_all();
        $uenone = $this->host_ue($lpid, (int) $hostnone->id, (int) $user->id);
        $uehidden = $this->host_ue($lpid, (int) $hosthidden->id, (int) $user->id);
        $this->assertNotFalse($uenone, '"none": first suspended, not removed.');
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $uenone->status);
        $this->assertNotFalse($uehidden);
        $this->assertEquals(ENROL_USER_SUSPENDED, (int) $uehidden->status);

        // Inside the retention period: both stay.
        foreach ([$uenone, $uehidden] as $ue) {
            $DB->set_field('user_enrolments', 'timemodified', time() - (89 * DAYSECS), ['id' => $ue->id]);
        }
        reconciler::reconcile_all();
        $this->assertTrue($DB->record_exists('user_enrolments', ['id' => $uenone->id]), '"none", 89 days: still there.');
        $this->assertTrue($DB->record_exists('user_enrolments', ['id' => $uehidden->id]));

        // Past it: "none" goes, "hidden" stays.
        foreach ([$uenone, $uehidden] as $ue) {
            $DB->set_field('user_enrolments', 'timemodified', time() - (91 * DAYSECS), ['id' => $ue->id]);
        }
        reconciler::reconcile_all();
        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['id' => $uenone->id]),
            '"none", past the retention period: removed.'
        );
        $this->assertTrue(
            $DB->record_exists('user_enrolments', ['id' => $uehidden->id]),
            '"hidden" is suspended by design and keeps the learner countable: never removed while entitled.'
        );
    }

    /**
     * Deleted is (cleanly) gone (decision of 2026-10-15): deleting an
     * embedding takes the subscriptions only it carried and the host
     * enrolment method it needed; a learner another embedding carries keeps
     * everything.
     *
     * @return void
     */
    public function test_deleted_embedding_is_cleanly_gone(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        $otherhost = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $adeleid = $this->embed($lpid, (int) $host->id, '1,2');
        $this->embed($lpid, (int) $otherhost->id, '1');

        // A: member of the host course. B: in the starting node course - and
        // so in the host course through ADELE. C: member of the OTHER course.
        $a = $this->getDataGenerator()->create_user();
        $b = $this->getDataGenerator()->create_user();
        $c = $this->getDataGenerator()->create_user();
        $suba = $this->subscribe($lpid, (int) $a->id);
        $subb = $this->subscribe($lpid, (int) $b->id);
        $subc = $this->subscribe($lpid, (int) $c->id);
        $this->getDataGenerator()->enrol_user($a->id, $host->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($b->id, $node->id, 'student', 'manual');
        $this->getDataGenerator()->enrol_user($c->id, $otherhost->id, 'student', 'manual');
        reconciler::reconcile_all();
        $this->assertNotFalse($this->host_ue($lpid, (int) $host->id, (int) $b->id), 'Precondition: B is in the host course.');

        $DB->delete_records('adele', ['id' => $adeleid]);
        $this->saved($lpid, (int) $host->id, true);

        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['id' => $suba]),
            'A: only the deleted embedding carried them.'
        );
        $this->assertFalse($DB->record_exists('local_adele_path_user', ['id' => $subb]), 'B: the same.');
        $this->assertTrue($DB->record_exists('local_adele_path_user', ['id' => $subc]), 'C: carried by the other embedding.');
        $this->assertEmpty(
            instance_manager::get_instances($lpid, instance_manager::KIND_HOST)[(int) $host->id] ?? null,
            'No embedding in the course needs the host enrolment method any more: it is gone, with its rows.'
        );
        $this->assertFalse($this->host_ue($lpid, (int) $host->id, (int) $b->id));
        $this->assertTrue(is_enrolled(\context_course::instance($host->id), $a->id), "A's own enrolment stays.");
    }

    /**
     * Counter-check: the host method stays while an embedding in the course
     * still needs it.
     *
     * @return void
     */
    public function test_host_method_stays_while_an_embedding_needs_it(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        $lpid = $this->path((int) $node->id);
        $this->embed($lpid, (int) $host->id, '2');
        $b = $this->getDataGenerator()->create_user();
        $this->subscribe($lpid, (int) $b->id);
        $this->getDataGenerator()->enrol_user($b->id, $node->id, 'student', 'manual');
        reconciler::reconcile_all();

        $this->assertFalse(reconciler::remove_unused_host_instance($lpid, (int) $host->id));
        $this->assertNotEmpty(instance_manager::get_instances($lpid, instance_manager::KIND_HOST)[(int) $host->id] ?? null);
        $this->assertNotFalse($this->host_ue($lpid, (int) $host->id, (int) $b->id));
    }
}
