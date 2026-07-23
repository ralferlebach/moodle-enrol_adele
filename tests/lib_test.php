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
 * Tests for the enrol_adele plugin class.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

/**
 * Tests for the enrol_adele plugin class.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele_plugin
 */
final class lib_test extends \advanced_testcase {
    /**
     * The plugin is registered and its class can be loaded.
     *
     * @return void
     */
    public function test_plugin_is_available(): void {
        $this->resetAfterTest();

        $plugin = enrol_get_plugin('adele');
        $this->assertInstanceOf(\enrol_adele_plugin::class, $plugin);
        $this->assertSame('adele', $plugin->get_name());
    }

    /**
     * ADELE owns its enrolments, so they must not be editable by hand.
     *
     * @return void
     */
    public function test_enrolments_are_protected(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('adele');

        $instance = (object) [
            'id' => 1,
            'enrol' => 'adele',
            'courseid' => $course->id,
            'name' => null,
            'customint1' => 42,
        ];

        $this->assertTrue($plugin->roles_protected());
        $this->assertFalse($plugin->allow_unenrol($instance));
        $this->assertFalse($plugin->allow_manage($instance));
        $this->assertFalse($plugin->can_add_instance($course->id));
    }

    /**
     * An unnamed instance falls back to the plugin name; a named one is shown as is.
     *
     * @return void
     */
    public function test_get_instance_name(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('adele');

        $unnamed = (object) [
            'enrol' => 'adele',
            'courseid' => $course->id,
            'name' => null,
        ];
        $this->assertSame(
            get_string('pluginname', 'enrol_adele'),
            $plugin->get_instance_name($unnamed)
        );

        $named = (object) [
            'enrol' => 'adele',
            'courseid' => $course->id,
            'name' => 'ADELE: Onboarding',
        ];
        $this->assertSame('ADELE: Onboarding', $plugin->get_instance_name($named));
    }
}
