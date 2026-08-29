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
 * Tests for the background repair queue and its outcome log.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\manage;
use enrol_adele\local\task_log;
use enrol_adele\task\purge_learning_path_adhoc;
use enrol_adele\task\reconcile_learning_path_adhoc;

/**
 * Tests for the background repair queue and its outcome log.
 *
 * A queued repair that finishes disappears from {task_adhoc}, so the queue
 * alone cannot distinguish "it ran and worked" from "it was never queued".
 * Issue #6 asks for the outcome; these tests pin down both halves.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\task_log
 * @covers      \enrol_adele\local\manage::get_queued_repairs
 */
final class task_status_test extends \advanced_testcase {
    /**
     * A learning path with no nodes — enough to queue repairs against.
     *
     * @return int The learning path id.
     */
    private function plant_learningpath(): int {
        global $DB;
        return (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Task-Testpfad',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
    }

    /**
     * A queued repair is reported with its action, learning path and state.
     *
     * @return void
     */
    public function test_queued_repair_is_reported(): void {
        $this->resetAfterTest();

        $lpid = $this->plant_learningpath();
        $task = new reconcile_learning_path_adhoc();
        $task->set_custom_data(['learningpathid' => $lpid]);
        \core\task\manager::queue_adhoc_task($task);

        $queued = manage::get_queued_repairs();
        $this->assertCount(1, $queued);
        $this->assertEquals('reconcile_learning_path_adhoc', $queued[0]['action']);
        $this->assertEquals($lpid, $queued[0]['learningpathid']);
        $this->assertEquals('queued', $queued[0]['state']);
    }

    /**
     * A task that threw is reported as retrying, not as merely queued.
     *
     * The one state that will not resolve by itself, so the one an
     * administrator has to be able to see.
     *
     * @return void
     */
    public function test_failed_repair_is_reported_as_retrying(): void {
        global $DB;
        $this->resetAfterTest();

        $lpid = $this->plant_learningpath();
        $task = new purge_learning_path_adhoc();
        $task->set_custom_data(['learningpathid' => $lpid]);
        \core\task\manager::queue_adhoc_task($task);

        // What cron does to a task whose execute() threw.
        $DB->set_field('task_adhoc', 'faildelay', 60, ['component' => 'enrol_adele']);

        $queued = manage::get_queued_repairs();
        $this->assertCount(1, $queued);
        $this->assertEquals('retrying', $queued[0]['state']);
    }

    /**
     * Running a repair records its outcome, and the queue empties.
     *
     * @return void
     */
    public function test_running_a_repair_records_its_outcome(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $lpid = $this->plant_learningpath();
        $task = new reconcile_learning_path_adhoc();
        $task->set_custom_data(['learningpathid' => $lpid]);
        \core\task\manager::queue_adhoc_task($task);

        $this->assertSame([], task_log::all(), 'Precondition: nothing recorded yet.');

        $next = \core\task\manager::get_next_adhoc_task(time() + 1);
        $this->assertInstanceOf(reconcile_learning_path_adhoc::class, $next);
        $next->execute();
        \core\task\manager::adhoc_task_complete($next);

        $this->assertSame([], manage::get_queued_repairs(), 'A finished task leaves the queue.');

        $outcomes = task_log::all();
        $this->assertCount(1, $outcomes);
        $this->assertEquals('reconcile', $outcomes[0]['action']);
        $this->assertEquals($lpid, $outcomes[0]['learningpathid']);
        $this->assertEquals('succeeded', $outcomes[0]['outcome']);
        $this->assertNotEmpty($outcomes[0]['timefinished']);
    }

    /**
     * The outcome log is bounded and newest first.
     *
     * Without the cap this would be an ever-growing value in the config
     * table, which is exactly why a rolling list is defensible there at all.
     *
     * @return void
     */
    public function test_outcome_log_is_capped_and_newest_first(): void {
        $this->resetAfterTest();

        for ($i = 1; $i <= task_log::KEEP + 5; $i++) {
            task_log::record('reconcile', $i, $i);
        }

        $outcomes = task_log::all();
        $this->assertCount(task_log::KEEP, $outcomes);
        $this->assertEquals(task_log::KEEP + 5, $outcomes[0]['learningpathid'], 'Newest entry first.');
    }
}
