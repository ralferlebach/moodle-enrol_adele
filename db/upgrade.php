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
 * Upgrade steps for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the enrol_adele upgrade steps from the given old version.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_enrol_adele_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026072302) {
        // The install.php script now auto-enables the plugin on fresh installs (a real
        // CI run surfaced that enrol_is_enabled('adele') was false out of the
        // box — every reconciler call silently no-op'd via is_active()'s
        // guard, since Moodle enrol plugins are site-disabled until explicitly
        // added to $CFG->enrol_plugins_enabled, and this plugin has no
        // teacher-facing "add instance" workflow that would otherwise surface
        // the problem). Sites that installed an earlier version never ran that
        // install step; apply the same fix here so upgrading also unblocks
        // them, not just fresh installs.
        $enabled = enrol_get_plugins(true);
        if (!array_key_exists('adele', $enabled)) {
            $enabled['adele'] = true;
            set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
        }

        upgrade_plugin_savepoint(true, 2026072302, 'enrol', 'adele');
    }

    if ($oldversion < 2026072305) {
        // Requirement D.5: local_adele/enroll_as_setting gave way to
        // enrol_adele/roleid (the enrol_manual path it served is gone since
        // 2026-10-17, mod_adele #20; local_adele removes the setting in its
        // upgrade 2026100734). Sites that never explicitly set
        // enrol_adele/roleid inherit the legacy value once here, so the
        // effective role stays the same across the upgrade instead of
        // silently reverting to the student-archetype default that
        // instance_manager::get_role_id() falls back to when neither
        // setting is present.
        if (empty(get_config('enrol_adele', 'roleid'))) {
            $legacy = get_config('local_adele', 'enroll_as_setting');
            if (!empty($legacy)) {
                set_config('roleid', $legacy, 'enrol_adele');
            }
        }

        upgrade_plugin_savepoint(true, 2026072305, 'enrol', 'adele');
    }

    if ($oldversion < 2026100732) {
        // The first table of this plugin: the enrolments teachers receive
        // through the teacher view of a mod_adele activity, with their source
        // (local_adele #600, TP4). Learner enrolments stay stateless.
        $table = new xmldb_table('enrol_adele_teacher_grants');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learningpathid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mode', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('roleid', XMLDB_KEY_FOREIGN, ['roleid'], 'role', ['id']);
        $table->add_index('cmid_courseid_userid', XMLDB_INDEX_UNIQUE, ['cmid', 'courseid', 'userid']);
        $table->add_index('courseid_userid', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026100732, 'enrol', 'adele');
    }

    return true;
}
