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
 * enrol_adele installation steps.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Enable the plugin by default on install.
 *
 * Moodle enrol plugins are site-disabled until explicitly added to
 * $CFG->enrol_plugins_enabled, even after installation — a manual admin step
 * that is easy to miss. Unlike most enrol plugins, this one has no
 * teacher-facing "add instance" workflow (can_add_instance() is always
 * false): every instance is created lazily by the reconciler. Left disabled,
 * the plugin would silently do nothing at all after install, with no UI path
 * to discover why. Auto-enabling removes that trap. Pattern matches
 * enrol_coursecompleted's install.php.
 *
 * @return bool
 */
function xmldb_enrol_adele_install(): bool {
    $enabled = enrol_get_plugins(true);
    $enabled['adele'] = true;
    set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));
    return true;
}
