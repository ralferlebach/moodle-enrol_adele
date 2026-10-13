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

/**
 * Issue #11: the delayed withdrawal is scheduled from core's clock.
 *
 * Moodle's task manager decides from \core\clock whether an ad-hoc task is
 * due. The withdrawal that enrol_adele queues must be scheduled from the same
 * clock, or the two disagree in every test that freezes or shifts time.
 *
 * @package    enrol_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_adele\local\reconciler
 */
final class clock_frozen_test extends \advanced_testcase {
    /**
     * An uncarried subscription is queued for removal exactly DELAY_SECONDS after the frozen "now".
     *
     * @return void
     */
    public function test_withdrawal_is_scheduled_from_the_clock(): void {
        global $DB;
        $this->resetAfterTest();

        $now = 1893456000; // 2030-01-01 00:00:00 UTC.
        $this->mock_clock_with_frozen($now);

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        // A subscription to a path that is embedded nowhere: uncarried by
        // definition, so the sweep has to queue its removal.
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'learning_path_id' => 424242,
            'status' => 'active',
            'createdby' => $user->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'json' => '{}',
        ]);

        $sweep = new \ReflectionMethod(\enrol_adele\local\reconciler::class, 'sweep_uncarried_subscriptions');
        $sweep->setAccessible(true);
        $queued = $sweep->invoke(null, null);

        $this->assertSame(1, $queued, 'the uncarried subscription must be queued');
        $runtime = $DB->get_field(
            'task_adhoc',
            'nextruntime',
            ['classname' => '\\' . \enrol_adele\task\remove_user_path_adhoc::class]
        );
        $this->assertEquals(
            $now + \enrol_adele\task\remove_user_path_adhoc::DELAY_SECONDS,
            (int) $runtime,
            'the removal must run DELAY_SECONDS after the frozen now, not after the wall clock'
        );
    }
}
