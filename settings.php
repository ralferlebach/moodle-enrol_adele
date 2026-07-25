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
 * Admin settings for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(
        new admin_setting_heading(
            'enrol_adele_settings',
            '',
            get_string('pluginname_desc', 'enrol_adele')
        )
    );

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(
            new admin_setting_configselect(
                'enrol_adele/roleid',
                get_string('defaultrole', 'role'),
                get_string('defaultrole_desc', 'enrol_adele'),
                $student->id ?? null,
                $options
            )
        );
    }
}

// Management page: added under the top-level 'enrolments' category, not
// under this plugin's own 'enrolsettingsadele' settings page (matches how
// Moodle core itself registers such pages via admin/settings/plugins.php
// 'enroltestsettings' external page the same way: $ADMIN->add('enrolments',
// ...)) and a real-world precedent (moodle-tool_uploadenrolmentmethods).
// admin_settingpage objects (like 'enrolsettingsadele') cannot hold child
// pages — only admin_category can — so the original 'enrolsettingsadele'
// parent would not have worked. Still not confirmed against a live
// instance; see docs/verification-live-testing-guide.md.
$ADMIN->add(
    'enrolments',
    new admin_externalpage(
        'enrolsettingsadelemanage',
        get_string('manage_heading', 'enrol_adele'),
        new moodle_url('/enrol/adele/manage.php'),
        'enrol/adele:config'
    )
);
