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
 * Custom Behat step definitions for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
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
     * Visit a relative URL directly.
     *
     * "Given I am on "<url>"" (used previously)
     * is not a registered step in this site's actual Moodle/Behat version -
     * the second real CI run showed "missing steps" for it. Rather than
     * guess a third time at whichever exact core step phrasing this Moodle
     * version does support, this defines an unambiguous custom one using
     * only behat_base's own well-established locate_path() helper (already
     * relied on internally by many core steps, e.g. i_am_on_homepage()),
     * so correctness does not depend on the exact core step catalogue of
     * whichever Moodle version this runs against.
     *
     * @Given /^I directly visit the url "(?P<url_string>(?:[^"]|\\")*)"$/
     * @param string $url Relative URL, e.g. "enrol/adele/manage.php".
     * @return void
     */
    public function i_directly_visit_the_url(string $url): void {
        $this->getSession()->visit($this->locate_path($url));
    }

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

    /**
     * Open the management page filtered to one learning path, found by name.
     *
     * The filter dropdown labels each option with the learning path id, which
     * is not stable across scenarios — the sequence keeps counting even
     * though the table is emptied. Selecting by visible text would therefore
     * only work in whichever scenario happens to run first. The dropdown
     * itself is covered by the course and type filter scenarios, which use
     * fixed values.
     *
     * @Given /^I open the ADELE management page filtered to learning path "(?P<name>(?:[^"]|\\")*)"$/
     * @param string $name The learning path name.
     * @return void
     */
    public function i_open_the_management_page_filtered_to(string $name): void {
        global $DB;

        $lpid = $DB->get_field('local_adele_learning_paths', 'id', ['name' => $name], MUST_EXIST);
        $this->i_directly_visit_the_url('enrol/adele/manage.php?learningpathid=' . (int) $lpid);
    }

    /**
     * Create the given number of ADELE enrol instances, each in its own
     * course, all belonging to one learning path.
     *
     * Used to push the management page past its page size so that pagination
     * is exercised for real rather than asserted on an empty list.
     *
     * @Given /^(?P<count>\d+) ADELE enrol instances exist$/
     * @param int $count How many instances (and courses) to create.
     * @return void
     */
    public function adele_enrol_instances_exist(int $count): void {
        global $DB;

        $lpid = $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Behat bulk path',
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        $generator = \testing_util::get_data_generator();
        for ($i = 1; $i <= $count; $i++) {
            $course = $generator->create_course(['shortname' => 'BULK' . $i, 'fullname' => 'Bulk course ' . $i]);
            \enrol_adele\local\instance_manager::ensure_instance((int) $lpid, (int) $course->id);
        }
    }
}
