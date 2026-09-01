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
 * Seed a browser-test fixture for enrol_adele.
 *
 * Creates a learning path, a target course and one ADELE enrolment, then
 * prints shell assignments so the caller can source them or feed them into
 * the job environment:
 *
 *     php enrol/adele/tests/playwright/seed.php > /tmp/seed.env
 *     . /tmp/seed.env
 *
 * DESTRUCTIVE. It resets the admin password so the browser suite has a known
 * credential, and is meant for a throwaway CI site, never for an installation
 * anyone depends on. It refuses to run unless ADELE_SEED_I_KNOW=1 is set.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');
// The data generators live under lib/testing and are not autoloaded outside
// the PHPUnit bootstrap; this script runs against a normal site.
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');

if (getenv('ADELE_SEED_I_KNOW') !== '1') {
    cli_error(
        "Refusing to run: this script resets the admin password and writes fixture data.\n" .
        "Set ADELE_SEED_I_KNOW=1 to confirm this is a throwaway site."
    );
}

$adminpassword = getenv('ADELE_ADMIN_PASSWORD') ?: 'Playwright!23';
$suffix = time();
$lpname = 'Playwright-Lernpfad ' . $suffix;
$courseshortname = 'PW' . $suffix;

// Moodle's default is to mark the session cookie Secure. A browser refuses
// to store such a cookie over plain http, so no session is established. The
// browser suite runs against http://127.0.0.1, so the flag has to go.
if (!empty($CFG->cookiesecure)) {
    set_config('cookiesecure', 0);
}

// A site served over plain HTTP cannot keep a Secure session cookie. Moodle's
// installer defaults cookiesecure to on, the browser then drops the cookie,
// no session exists, and the login token check fails — so Moodle answers a
// perfectly valid login with "Invalid login, please try again". The failure
// names the wrong cause, which is why it belongs here rather than in a
// workaround inside the browser suite.
if (strpos($CFG->wwwroot, 'https://') !== 0) {
    set_config('cookiesecure', 0);
}

// A known admin credential for the browser suite.
$admin = get_admin();
$admin->password = hash_internal_user_password($adminpassword);
$DB->set_field('user', 'password', $admin->password, ['id' => $admin->id]);

$generator = new testing_data_generator();

$course = $generator->create_course([
    'shortname' => $courseshortname,
    'fullname' => 'Playwright-Zielkurs ' . $suffix,
]);

$json = [
    'tree' => [
        'nodes' => [
            [
                'id' => 'dndnode_1',
                'type' => 'courseNode',
                'parentCourse' => ['starting_node'],
                'data' => ['course_node_id' => [(int) $course->id]],
            ],
        ],
        'edges' => [],
    ],
];

$lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
    'name' => $lpname,
    'description' => '',
    'timecreated' => time(),
    'timemodified' => time(),
    'createdby' => (int) $admin->id,
    'json' => json_encode($json),
]);

$student = $generator->create_user([
    'username' => 'pwstudent' . $suffix,
    'firstname' => 'Playwright',
    'lastname' => 'Student',
]);

$DB->insert_record('local_adele_path_user', (object) [
    'user_id' => (int) $student->id,
    'course_id' => 0,
    'learning_path_id' => $lpid,
    'status' => 'active',
    'timecreated' => time(),
    'timemodified' => time(),
    'createdby' => (int) $admin->id,
    'json' => json_encode($json + [
        'user_path_relation' => [
            'dndnode_1' => ['feedback' => ['status' => 'accessible']],
        ],
    ]),
]);

// Let the plugin create its own instance and enrolment, rather than planting
// them: if that path is broken the fixture should fail here, loudly, instead
// of handing the browser suite a state the plugin could never produce.
\enrol_adele\local\reconciler::reconcile_user($lpid, (int) $student->id);

$instance = $DB->get_record('enrol', [
    'enrol' => 'adele',
    'courseid' => $course->id,
    'customint1' => $lpid,
]);
if (!$instance) {
    cli_error('Seeding failed: enrol_adele did not create an instance for the seeded learning path.');
}

printf("export ADELE_BASE_URL='%s'\n", $CFG->wwwroot);
printf("export ADELE_ADMIN_USER='%s'\n", $admin->username);
printf("export ADELE_ADMIN_PASSWORD='%s'\n", $adminpassword);
printf("export ADELE_LP_NAME='%s'\n", $lpname);
printf("export ADELE_LP_ID='%d'\n", $lpid);
printf("export ADELE_COURSE_SHORTNAME='%s'\n", $courseshortname);
