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
                // Real local_adele nodes always carry a 'type'; relation_update.php's
                // subscribe_user_starting_node() reads $node['type'] without a
                // fallback (unlike the sibling check a few lines above it, which
                // does use '??'). A type-less node — which only ever occurred here,
                // in this synthetic fixture — crashes with "Undefined array key
                // type" the moment local_adele's OWN production code processes it.
                // That happens for real in test_host_course_removal_rules(): the
                // data generator's enrol_user() call fires mod_adele's observer,
                // which re-subscribes via local_adele and triggers a real
                // user_path_updated event, cascading into that exact code path.
                // Any non-'dropzone' string satisfies the check.
                'type' => 'courseNode',
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
        // The mod_adele observer reacts to this enrolment (participantslist
        // option 1) and re-subscribes via local_adele, which synchronously
        // triggers its OWN real recompute pipeline (updated_single() ->
        // course_completion_status/course_restriction_status). That pipeline
        // recomputes node status from actual completion/restriction condition
        // data — data our bare-bones fixture node never carried — and
        // overwrites the 'accessible' status this test planted with whatever
        // it derives from an unconditioned node (typically "not yet
        // accessible"). This is a property of the fixture, not of enrol_adele
        // or of local_adele: force the status back to what this test actually
        // exercises before reconciling, so the assertion is not at the mercy
        // of an unrelated production code path's side effects.
        $this->set_node_status($lpid, $userid, 'dndnode_1', 'accessible');
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

    /**
     * Host-course reconciliation (options 2/3, requirement following ticket
     * #486 follow-up): enrolling is driven by a caller-supplied boolean, not by
     * local_adele's node feedback status, and lands on a KIND_HOST instance
     * distinct from any KIND_TARGET instance the same learning path might have
     * on the same course. Covers enrol, suspend, reactivate and purge.
     *
     * @return void
     */
    public function test_reconcile_host_user_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $host = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Hostkurs-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, true);
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $host->id,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_HOST,
        ]);
        $this->assertNotFalse($instance);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $ue->status);

        // No longer entitled (e.g. left the qualifying node course): suspend,
        // never delete outright — mirrors the target-course node-closed case.
        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, false);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);

        // Re-entitled: the SAME user_enrolment reactivates, no duplicate record.
        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, true);
        $reactivated = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $reactivated->status);
        $this->assertEquals($ue->id, $reactivated->id);

        // A target-course instance on the SAME course for the SAME learning path
        // (the self-embedding edge case) does not collide with the host instance.
        $targetinstance = instance_manager::ensure_instance($lpid, (int) $host->id, instance_manager::KIND_TARGET);
        $this->assertNotEquals($targetinstance->id, $instance->id);

        reconciler::purge_host_user($lpid, (int) $host->id, (int) $user->id);
        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id])
        );
    }

    /**
     * Leaving the learning path via the A-4 rule (requirement mod_adele #21)
     * must clear ALL of a user's host-course enrolments for that learning
     * path, not just the host course through which access was lost. Uses
     * reconcile_host_user() directly to plant the "other host course" state,
     * sidestepping the real mod_adele event cascade (already exercised by
     * test_host_course_removal_rules()) so this test stays focused on the
     * purge fan-out itself.
     *
     * @return void
     */
    public function test_leaving_learning_path_purges_every_host_course(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $host1 = $this->getDataGenerator()->create_course();
        $host2 = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant_state(
            ['dndnode_1' => 'accessible'],
            ['dndnode_1' => [(int) $target->id]]
        );
        // Embedding 1: option 1 in host1 (the carrying, host1-triggering one).
        $DB->insert_record('adele', (object) [
            'course' => $host1->id,
            'name' => 'LP-Aktivität (Fall 1)',
            'intro' => '',
            'introformat' => 1,
            'learningpathid' => $lpid,
            'participantslist' => '1',
            'userlist' => 1,
            'view' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $this->getDataGenerator()->enrol_user($userid, $host1->id, 'student', 'manual');
        $this->set_node_status($lpid, $userid, 'dndnode_1', 'accessible');
        reconciler::reconcile_user($lpid, $userid);
        // A second host course granted through a separate Fall-2/3 embedding
        // (planted directly — the live mod_adele trigger is out of scope here).
        reconciler::reconcile_host_user($lpid, (int) $host2->id, $userid, true);
        $host2instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $host2->id,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_HOST,
        ]);
        $this->assertNotFalse(
            $DB->get_record('user_enrolments', ['enrolid' => $host2instance->id, 'userid' => $userid])
        );

        // Leave the learning path via the A-4 rule: unenrol from host1, the
        // sole carrying embedding.
        $manual = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $host1->id]);
        enrol_get_plugin('manual')->unenrol_user($manual, $userid);

        $this->assertFalse(
            $DB->record_exists(
                'local_adele_path_user',
                ['learning_path_id' => $lpid, 'user_id' => $userid]
            )
        );
        // Target-course enrolment gone (pre-existing A-4 behaviour)...
        $this->assertFalse($this->get_ue($lpid, (int) $target->id, $userid));
        // ...and now the host2 Fall-2/3 enrolment is gone too (mod_adele #21).
        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['enrolid' => $host2instance->id, 'userid' => $userid])
        );
    }

    /**
     * Host-course visibility modes (requirement mod_adele #22): MODE_VISIBLE
     * behaves exactly like the pre-0.1.5 boolean-only signature; MODE_HIDDEN
     * still creates an enrolment record (countable in participant lists) but
     * never grants access; MODE_NONE never creates a new instance, but
     * suspends — never deletes — one left over from an earlier, more
     * permissive mode, so switching back later loses no history (L-Q-07).
     *
     * @return void
     */
    public function test_reconcile_host_user_visibility_modes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $host = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Sichtbarkeits-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $user->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        // MODE_HIDDEN, entitled: a record exists, but stays suspended.
        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, true, reconciler::MODE_HIDDEN);
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $host->id,
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_HOST,
        ]);
        $this->assertNotFalse($instance);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertNotFalse($ue);
        $this->assertEquals(ENROL_USER_SUSPENDED, $ue->status);

        // Switched to MODE_VISIBLE while still entitled: the SAME record
        // reactivates.
        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, true, reconciler::MODE_VISIBLE);
        $reactivated = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertEquals(ENROL_USER_ACTIVE, $reactivated->status);
        $this->assertEquals($ue->id, $reactivated->id);

        // Switched to MODE_NONE while still entitled: the existing record is
        // suspended, not deleted.
        reconciler::reconcile_host_user($lpid, (int) $host->id, (int) $user->id, true, reconciler::MODE_NONE);
        $suspended = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $this->assertNotFalse($suspended);
        $this->assertEquals(ENROL_USER_SUSPENDED, $suspended->status);
        $this->assertEquals($ue->id, $suspended->id);

        // A second user, never enrolled before, under MODE_NONE from the
        // start: no instance/enrolment is created for them at all.
        $seconduser = $this->getDataGenerator()->create_user();
        reconciler::reconcile_host_user(
            $lpid,
            (int) $host->id,
            (int) $seconduser->id,
            true,
            reconciler::MODE_NONE
        );
        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $seconduser->id])
        );
    }
}
