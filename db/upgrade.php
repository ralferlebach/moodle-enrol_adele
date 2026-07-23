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
    // This plugin ships no tables of its own (decision F-6, stateless
    // reconciliation — see docs/pflichtenheft.md, section 1a/2.3). Nothing
    // here ever adds a table; upgrade steps only ever touch configuration.

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

    return true;
}
