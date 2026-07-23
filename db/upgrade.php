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
    // No upgrade steps yet. 0.1.0 is the initial install and ships no tables of
    // its own; the grant table arrives in 0.2.0 together with its privacy
    // provider (see docs/pflichtenheft.md, section 5).
    return true;
}
