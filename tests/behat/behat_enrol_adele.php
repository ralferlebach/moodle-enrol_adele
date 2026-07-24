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
 * Custom Behat step definitions for enrol_adele (C.5).
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Context\Step\Given;

/**
 * Custom Behat step definitions for enrol_adele.
 *
 * enrol_adele has no manual "add instance" UI (can_add_instance() is always
 * false — every instance is created lazily by the reconciler), so there is
 * no standard Moodle generator step that can plant one. This mirrors the
 * direct-record-planting pattern already used in
 * tests/reconciler_test.php::plant_state() (PHPUnit), the same minimal
 * local_adele_learning_paths shape, just reachable from a .feature file.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_enrol_adele extends behat_base {
    /**
     * Create a minimal learning path and an active ADELE enrol instance for
     * it in the given course, for the given user.
     *
     * @Given /^an ADELE enrol instance exists in course "(?P<course>(?:[^"]|\\")*)" for user "(?P<user>(?:[^"]|\\")*)"$/
     * @param string $courseshortname The course shortname.
     * @param string $username The username.
     * @return void
     */
    public function an_adele_enrol_instance_exists(string $courseshortname, string $username): void {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => $courseshortname], '*', MUST_EXIST);
        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Behat test path',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => (int) $user->id,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        $instance = \enrol_adele\local\instance_manager::ensure_instance(
            (int) $lpid,
            (int) $course->id
        );
        if (!$instance) {
            throw new \Exception('Could not create an ADELE enrol instance for the Behat fixture.');
        }

        $plugin = enrol_get_plugin('adele');
        $plugin->enrol_user($instance, (int) $user->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
    }
}
