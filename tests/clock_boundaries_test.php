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
use enrol_adele\task\remove_user_path_adhoc;

/**
 * The deadlines of enrol_adele, judged by \core\clock at their exact
 * boundaries (enrol_adele #11).
 *
 * The deferred removal after a departure becomes due exactly
 * DELAY_SECONDS after the departure - a second earlier it does not run.
 * A suspended enrolment is removed once it has been suspended for longer
 * than the retention period: at exactly N days it stays, one second later
 * it goes. No sleep, no wall clock.
 *
 * @package    enrol_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_adele\observer
 * @covers     \enrol_adele\task\remove_user_path_adhoc
 * @covers     \enrol_adele\local\reconciler
 */
final class clock_boundaries_test extends \advanced_testcase {
    /** @var int The frozen start: 2030-01-01 00:00:00 UTC. */
    private const START = 1893456000;

    /**
     * Run the ad hoc tasks that are due by the clock, the way cron does
     * (runAdhocTasks() ignores the time a task is scheduled for).
     *
     * @return void
     */
    private function run_due_tasks(): void {
        global $DB;
        $lock = $this->createMock(\core\lock\lock::class);
        do {
            $now = \core\di::get(\core\clock::class)->time();
            $records = $DB->get_records_select('task_adhoc', 'nextruntime <= ?', [$now], 'id');
            foreach ($records as $record) {
                $task = \core\task\manager::adhoc_task_from_record($record);
                $task->set_lock($lock);
                $task->execute();
                \core\task\manager::adhoc_task_complete($task);
            }
        } while ($records);
    }

    /**
     * A learning path embedded into a new course for its members.
     *
     * @return array [path id, host course]
     */
    private function embedded_path(): array {
        global $DB;
        $host = $this->getDataGenerator()->create_course();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Clock path', 'description' => '', 'timecreated' => self::START, 'timemodified' => self::START,
            'createdby' => 2, 'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
        $DB->insert_record('adele', (object) [
            'course' => $host->id, 'name' => 'Clock', 'intro' => '', 'introformat' => 1,
            'learningpathid' => $lpid, 'participantslist' => '1', 'userlist' => 1, 'view' => 1,
            'timecreated' => self::START, 'timemodified' => self::START,
        ]);
        return [$lpid, $host];
    }

    /**
     * The removal after a departure: not a second before DELAY_SECONDS,
     * exactly then.
     *
     * @return void
     */
    public function test_the_removal_is_due_exactly_after_the_delay(): void {
        global $DB;
        $this->resetAfterTest();
        $clock = $this->mock_clock_with_frozen(self::START);
        [$lpid, $host] = $this->embedded_path();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $host->id, 'student', 'manual');
        $subscribed = fn() => $DB->record_exists('local_adele_path_user', ['user_id' => $user->id, 'learning_path_id' => $lpid]);
        $this->assertTrue($subscribed(), 'Subscribed as a member of the host course.');

        $manual = $DB->get_record('enrol', ['courseid' => $host->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($manual, $user->id);
        $runat = (int) $DB->get_field('task_adhoc', 'nextruntime', ['classname' => '\\' . remove_user_path_adhoc::class]);
        $this->assertSame(self::START + remove_user_path_adhoc::DELAY_SECONDS, $runat, 'Scheduled from the clock.');

        $clock->set_to(self::START + remove_user_path_adhoc::DELAY_SECONDS - 1);
        $this->run_due_tasks();
        $this->assertTrue($subscribed(), 'One second before the delay: not yet.');

        $clock->set_to(self::START + remove_user_path_adhoc::DELAY_SECONDS);
        $this->run_due_tasks();
        $this->assertFalse($subscribed(), 'At the delay: removed.');
    }

    /**
     * Retention: at exactly N days suspended the enrolment stays, one second
     * later it goes.
     *
     * @return void
     */
    public function test_retention_ends_strictly_after_the_period(): void {
        global $DB;
        $this->resetAfterTest();
        $days = 2;
        set_config('suspendedretention', $days, 'enrol_adele');
        $clock = $this->mock_clock_with_frozen(self::START);
        [$lpid] = $this->embedded_path();
        $target = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = instance_manager::ensure_instance($lpid, (int) $target->id);
        enrol_get_plugin('adele')->enrol_user($instance, $user->id, instance_manager::get_role_id(), 0, 0, ENROL_USER_SUSPENDED);
        // Core stamps timemodified with the wall clock; the suspension
        // started at the frozen start.
        $DB->set_field('user_enrolments', 'timemodified', self::START, ['enrolid' => $instance->id, 'userid' => $user->id]);
        $exists = fn() => $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        $purge = new \ReflectionMethod(reconciler::class, 'purge_expired_suspensions');

        $clock->set_to(self::START + $days * DAYSECS);
        $this->assertSame(0, $purge->invoke(null, null));
        $this->assertTrue($exists(), 'Exactly the retention period: kept.');

        $clock->set_to(self::START + $days * DAYSECS + 1);
        $this->assertSame(1, $purge->invoke(null, null));
        $this->assertFalse($exists(), 'One second past it: removed.');
    }
}
