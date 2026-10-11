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

use enrol_adele\task\remove_user_path_adhoc;

/**
 * Taking part in a course is a matter of the role (local_adele #587).
 *
 * A teacher of a course that embeds a learning path is enrolled there like
 * everybody else - and used to be carried as a learner of the path for it.
 * "Carried" now means: enrolled as a participant. A participant who becomes
 * a teacher has left the learners exactly like somebody who was unenrolled:
 * access goes at once, the learning history after the grace period, and not
 * at all if they are a participant again by then.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\observer::role_unassigned
 * @covers      \enrol_adele\observer::is_user_carried
 */
final class participant_role_test extends \advanced_testcase {
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
     * The id of a role.
     *
     * @param string $shortname Its short name.
     * @return int
     */
    private function role(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * Whether a removal of the learner's record is queued.
     *
     * @return bool
     */
    private function removal_queued(): bool {
        global $DB;
        return $DB->record_exists('task_adhoc', ['classname' => '\\' . remove_user_path_adhoc::class]);
    }

    /**
     * Teaching a course carries nobody; taking part in it does - also for
     * somebody who does both.
     *
     * @return void
     */
    public function test_only_participants_are_carried(): void {
        $this->resetAfterTest();
        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $node->id, (int) $host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'editingteacher', 'manual');
        $this->assertFalse(observer::is_user_carried($lpid, $userid), 'A teacher of the course is not its learner.');

        role_assign($this->role('student'), $userid, \context_course::instance($host->id)->id);
        $this->assertTrue(observer::is_user_carried($lpid, $userid), 'Teacher and participant: a participant.');
    }

    /**
     * A participant who becomes a teacher: access is withdrawn at once, the
     * history is deleted when the change has proved durable.
     *
     * @return void
     */
    public function test_participant_who_becomes_teacher_leaves_the_learners(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $pathuserid] = $this->plant((int) $node->id, (int) $host->id);
        $context = \context_course::instance($host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        $this->assertTrue(observer::is_user_carried($lpid, $userid), 'Precondition: a participant is carried.');

        // The role changes; the enrolment stays.
        role_assign($this->role('editingteacher'), $userid, $context->id);
        $this->assertTrue(observer::is_user_carried($lpid, $userid));
        $this->assertFalse($this->removal_queued(), 'Gaining a teaching role takes nothing away.');
        role_unassign($this->role('student'), $userid, $context->id);

        $this->assertTrue(is_enrolled($context, $userid), 'Still enrolled - as a teacher.');
        $this->assertFalse(observer::is_user_carried($lpid, $userid));
        $this->assertTrue(
            $DB->record_exists('local_adele_path_user', ['id' => $pathuserid]),
            'The history is not destroyed on the spot.'
        );
        $this->assertTrue($this->run_queued_removal(), 'The deferred removal must be queued.');
        $this->assertFalse(
            $DB->record_exists('local_adele_path_user', ['id' => $pathuserid]),
            'Durable change: the former participant is no learner of the path any more.'
        );
    }

    /**
     * A participant again before the grace period is over: nothing is lost.
     *
     * @return void
     */
    public function test_participant_again_within_the_grace_period_keeps_the_history(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid, $pathuserid] = $this->plant((int) $node->id, (int) $host->id);
        $context = \context_course::instance($host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        role_assign($this->role('editingteacher'), $userid, $context->id);
        role_unassign($this->role('student'), $userid, $context->id);
        $this->assertFalse(observer::is_user_carried($lpid, $userid));
        role_assign($this->role('student'), $userid, $context->id);
        $this->assertTrue(observer::is_user_carried($lpid, $userid));

        $this->run_queued_removal();
        $record = $DB->get_record('local_adele_path_user', ['id' => $pathuserid]);
        $this->assertNotFalse($record, 'The record must have survived.');
        $this->assertSame('monate-fortschritt', json_decode($record->json, true)['sentinel'] ?? null);
    }

    /**
     * Losing a teaching role while still taking part changes nothing.
     *
     * @return void
     */
    public function test_losing_a_teaching_role_as_a_participant_changes_nothing(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $node = $this->getDataGenerator()->create_course();
        $host = $this->getDataGenerator()->create_course();
        [$lpid, $userid] = $this->plant((int) $node->id, (int) $host->id);
        $context = \context_course::instance($host->id);

        $this->getDataGenerator()->enrol_user($userid, $host->id, 'student', 'manual');
        role_assign($this->role('editingteacher'), $userid, $context->id);
        role_unassign($this->role('editingteacher'), $userid, $context->id);

        $this->assertTrue(observer::is_user_carried($lpid, $userid));
        $this->assertFalse($this->removal_queued());
    }
}
