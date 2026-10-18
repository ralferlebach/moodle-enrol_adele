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

use advanced_testcase;

/**
 * The siblings' API this plugin calls, and the dependency floors that
 * guarantee it (mod_adele #43, P0.1).
 *
 * The three ADELE plugins are released together: each declares the others'
 * version of the same release as its floor, so an installation can only
 * pair matching versions - a pairing with an older sibling is refused by
 * Moodle's dependency check instead of failing later with "Call to
 * undefined method". And every class and method of a sibling this plugin
 * calls (outside tests) exists in the installed sibling: when one is
 * renamed or removed, this test names it.
 *
 * @package    enrol_adele
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class sibling_api_test extends advanced_testcase {
    /**
     * Classes and methods of the siblings called by this plugin.
     *
     * @return array
     */
    public static function sibling_api_provider(): array {
        return [
            'local_adele enrol_state::get_effectively_enrolled_courseids' => [
                '\\local_adele\\enrol_state',
                'get_effectively_enrolled_courseids',
            ],
            'local_adele enrol_state::get_entitled_courseids' => [
                '\\local_adele\\enrol_state',
                'get_entitled_courseids',
            ],
            'local_adele enrol_state::get_host_candidate_userids' => [
                '\\local_adele\\enrol_state',
                'get_host_candidate_userids',
            ],
            'local_adele enrol_state::get_host_embeddings' => [
                '\\local_adele\\enrol_state',
                'get_host_embeddings',
            ],
            'local_adele enrol_state::get_host_entitlement' => [
                '\\local_adele\\enrol_state',
                'get_host_entitlement',
            ],
            'local_adele enrol_state::get_learningpaths_embedded_in_course' => [
                '\\local_adele\\enrol_state',
                'get_learningpaths_embedded_in_course',
            ],
            'local_adele enrol_state::get_learningpaths_with_host_embeddings' => [
                '\\local_adele\\enrol_state',
                'get_learningpaths_with_host_embeddings',
            ],
            'local_adele enrol_state::is_participant' => [
                '\\local_adele\\enrol_state',
                'is_participant',
            ],
        ];
    }

    /**
     * Each one exists in the installed sibling.
     *
     * @dataProvider sibling_api_provider
     * @param string $class The class.
     * @param string|null $method The method, or null for the class alone.
     * @return void
     */
    public function test_sibling_api_exists(string $class, ?string $method): void {
        $this->assertTrue(class_exists($class), "$class must exist.");
        if ($method !== null) {
            $this->assertTrue(method_exists($class, $method), "$class::$method must exist.");
        }
    }

    /**
     * The floors name the siblings of the same release.
     *
     * @return void
     */
    public function test_floors_are_the_same_release(): void {
        $plugin = new \stdClass();
        require(__DIR__ . '/../version.php');
        foreach (['local_adele'] as $sibling) {
            $this->assertSame(
                $plugin->version,
                $plugin->dependencies[$sibling] ?? null,
                "The floor for $sibling is the version of this release."
            );
            $installed = (int) get_config($sibling, 'version');
            $this->assertGreaterThanOrEqual($plugin->dependencies[$sibling], $installed, $sibling);
        }
    }
}
