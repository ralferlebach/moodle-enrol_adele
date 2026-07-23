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
 * Language strings for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adele:config'] = 'Configure learning path enrolment instances';
$string['adele:unenrol'] = 'Unenrol users from a course through the learning path enrolment method';
$string['defaultrole_desc'] = 'The role that ADELE assigns when it enrols a user into a target course of a learning path.';
$string['instancename'] = 'ADELE: {$a}';
$string['instancenamehost'] = 'ADELE (path access): {$a}';
$string['pluginname'] = 'Learning path enrolment';
$string['pluginname_desc'] = 'This enrolment method owns the course enrolments that an ADELE learning path creates in its target courses. One enrolment instance belongs to exactly one learning path and one target course. This keeps the enrolments of different learning paths apart and lets it withdraw its own enrolments without ever touching manual, self or cohort enrolments.';
$string['privacy:metadata'] = 'The learning path enrolment plugin does not store any personal data. The enrolments it creates are stored by the Moodle core enrolment subsystem.';
$string['reconciletask'] = 'Reconcile learning path enrolments';
