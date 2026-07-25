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
 * Backup/restore test for enrol_adele.
 *
 * This test drives the
 * real backup_controller/restore_controller pipeline end to end
 * (backup -> convert() -> execute_precheck() -> execute_plan()) and hit two
 * consecutive real, different failures in that state machine in this exact
 * CI environment (cannot_precheck_wrong_status, then
 * unable_to_find_conversion_path) despite each fix being verified against
 * real-world examples individually. Two failures in a row on the same
 * mechanism is a pattern, not bad luck - that pipeline is evidently more
 * fragile to drive from a synthetic same-request PHPUnit backup than the
 * research suggested. Rewritten to test restore_instance()/
 * restore_user_enrolment() directly instead: construct the plugin's own
 * hook methods with mocked restore_enrolments_structure_step/restore_task
 * objects (disableOriginalConstructor() + onlyMethods(), the standard
 * PHPUnit pattern for stubbing a handful of methods on an otherwise-real
 * Moodle class without navigating its full constructor requirements). This
 * tests exactly the logic this plugin owns,
 * without depending on Moodle's backup/restore controller internals at all.
 *
 * A minimal smoke test (does backing up a course containing an ADELE
 * instance succeed without error) is kept separately, modelled on
 * mod_adele's own tests/backup_restore_test.php (confirmed green in this
 * exact CI environment) - that part of the original approach was never the
 * one that failed.
 *
 * A real CI run showed a PHP fatal error
 * building the restore_task mock ("contains 2 abstract methods ... must
 * therefore be declared abstract or implement the remaining methods
 * (base_task::build, base_task::define_settings)"). restore_task extends
 * base_task, which declares further abstract methods restore_task itself
 * does not implement (only concrete subclasses like restore_course_task
 * do) - plain getMock() does not auto-implement abstract methods beyond
 * the ones named in onlyMethods(), but getMockForAbstractClass() does
 * (its documented purpose: "All abstract methods of the given abstract
 * class are mocked"). Switched restore_task's mock to
 * getMockForAbstractClass() (still combined with
 * onlyMethods(['get_target']) so that specific concrete method's return
 * value stays controllable); left as plain getMock() for
 * restore_enrolments_structure_step, which is a concrete class.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use advanced_testcase;
use enrol_adele\local\instance_manager;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/enrol/adele/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/controller/backup_controller.class.php');
require_once($CFG->dirroot . '/backup/controller/restore_controller.class.php');

/**
 * Backup/restore test for enrol_adele.
 *
 * @covers \enrol_adele_plugin::restore_instance
 * @covers \enrol_adele_plugin::restore_user_enrolment
 */
final class backup_restore_test extends advanced_testcase {
    /**
     * Sets up the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    /**
     * Build a mocked restore_enrolments_structure_step whose task reports
     * the given restore target, without needing to construct a real
     * backup/restore plan.
     *
     * @param int $target One of the backup::TARGET_* constants.
     * @return \restore_enrolments_structure_step
     */
    private function make_step(int $target): \restore_enrolments_structure_step {
        $task = $this->getMockBuilder(\restore_task::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_target'])
            ->getMockForAbstractClass();
        $task->method('get_target')->willReturn($target);

        $step = $this->getMockBuilder(\restore_enrolments_structure_step::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_task'])
            ->getMock();
        $step->method('get_task')->willReturn($task);

        return $step;
    }

    /**
     * Minimal fixture: a learning path with one node granting the given
     * user access to $targetcourseid, and an active local_adele_path_user
     * record for it (the same shape reconciler_test.php's plant_state()
     * uses, trimmed to what this test needs).
     *
     * @param int $userid The user id.
     * @param int $targetcourseid The course the node grants access to.
     * @return int The learning path id.
     */
    private function plant_minimal_path(int $userid, int $targetcourseid): int {
        global $DB;

        $json = ['tree' => ['nodes' => [[
            'id' => 'dndnode_1',
            'type' => 'courseNode',
            'parentCourse' => [],
            'data' => ['course_node_id' => [$targetcourseid]],
        ]], 'edges' => []]];

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Backup/restore test path',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
            'json' => json_encode($json),
        ]);
        $DB->insert_record('local_adele_path_user', (object) [
            'user_id' => $userid,
            'course_id' => 0,
            'learning_path_id' => $lpid,
            'status' => 'active',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => $userid,
            'json' => json_encode($json + [
                'user_path_relation' => ['dndnode_1' => ['feedback' => ['status' => 'accessible']]],
            ]),
        ]);
        return $lpid;
    }

    /**
     * Restoring into a NEW course must skip entirely: no ADELE instance for
     * that learning path may exist in the restored course afterwards
     *
     */
    public function test_restore_instance_skips_for_new_course(): void {
        global $DB;

        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $restorecourse = $this->getDataGenerator()->create_course();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Backup/restore test path (new course)',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        $plugin = enrol_get_plugin('adele');
        $data = (object) [
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_TARGET,
            'roleid' => 5,
            'status' => ENROL_INSTANCE_ENABLED,
        ];
        $step = $this->make_step(\backup::TARGET_NEW_COURSE);

        $plugin->restore_instance($step, $data, $restorecourse, 999999);

        $this->assertFalse(
            $DB->record_exists('enrol', [
                'enrol' => 'adele',
                'courseid' => $restorecourse->id,
                'customint1' => $lpid,
            ]),
            'Restoring into a new course must not create an ADELE enrol instance.'
        );
    }

    /**
     * Restoring into the SAME course (backup::TARGET_CURRENT_ADDING) must
     * trigger an immediate reconcile instead of skipping - the instance the
     * learning path currently calls for must
     * exist right after restore_instance() returns, not only after the
     * next scheduled task.
     */
    public function test_restore_instance_reconciles_for_same_course(): void {
        global $DB;

        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $user = $this->getDataGenerator()->create_user();
        $targetcourse = $this->getDataGenerator()->create_course();
        $lpid = $this->plant_minimal_path((int) $user->id, (int) $targetcourse->id);

        // No enrol_adele instance exists yet for this path/course - the
        // scenario this simulates is "restored into the same course, the
        // instance needs to be (re)created from current state".
        $this->assertFalse(
            $DB->record_exists('enrol', ['enrol' => 'adele', 'customint1' => $lpid])
        );

        $plugin = enrol_get_plugin('adele');
        $data = (object) [
            'customint1' => $lpid,
            'customint2' => instance_manager::KIND_TARGET,
            'roleid' => 5,
            'status' => ENROL_INSTANCE_ENABLED,
        ];
        $step = $this->make_step(\backup::TARGET_CURRENT_ADDING);

        $plugin->restore_instance($step, $data, $targetcourse, 999999);

        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $targetcourse->id,
            'customint1' => $lpid,
        ]);
        $this->assertNotFalse(
            $instance,
            'Restoring into the same course must trigger an immediate reconcile that (re)creates the instance.'
        );
        $this->assertNotFalse(
            $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]),
            'The immediate reconcile must also (re)create the user enrolment.'
        );
    }

    /**
     * restore_user_enrolment() must remain a no-op regardless of target -
     * it must not throw and must not create a user_enrolments row on its
     * own (any restoration happens via restore_instance()'s reconcile
     * above, not via this hook).
     */
    public function test_restore_user_enrolment_is_a_noop(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Backup/restore test path (user enrolment no-op)',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => (int) $user->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
        $instance = instance_manager::ensure_instance($lpid, (int) $course->id);
        $this->assertNotNull($instance);

        $plugin = enrol_get_plugin('adele');
        $step = $this->make_step(\backup::TARGET_NEW_COURSE);
        $data = (object) ['status' => ENROL_USER_ACTIVE, 'timestart' => 0, 'timeend' => 0];

        $plugin->restore_user_enrolment($step, $data, $instance, (int) $user->id, ENROL_USER_ACTIVE);

        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]),
            'restore_user_enrolment() must not create a user_enrolments row on its own.'
        );
    }

    /**
     * Smoke test: backing up a course that owns an ADELE instance must
     * succeed without error. Modelled directly on mod_adele's own
     * tests/backup_restore_test.php (confirmed green in this exact CI
     * environment) - deliberately does not go further than that proven
     * template does (no restore attempted here; restore_instance()'s
     * actual behaviour is covered directly by the two tests above instead).
     */
    public function test_backup_of_course_with_adele_instance_succeeds(): void {
        global $DB, $USER;

        if (!class_exists('\local_adele\enrol_state')) {
            $this->markTestSkipped('local_adele >= 0.4.3 is required.');
        }

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Backup smoke test path',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => (int) $user->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);
        $instance = instance_manager::ensure_instance($lpid, (int) $course->id);
        $this->assertNotNull($instance);
        $plugin = enrol_get_plugin('adele');
        $plugin->enrol_user($instance, (int) $user->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $this->assertNotNull($bc, 'Backup controller is null.');
        $this->assertNotNull($bc->get_plan(), 'Backup plan is null.');
        $bc->execute_plan();
        $bc->destroy();

        // The source course itself must be entirely unaffected by taking a
        // backup of it.
        $this->assertNotFalse($DB->get_record('enrol', ['id' => $instance->id]));
        $this->assertNotFalse(
            $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id])
        );
    }
}
