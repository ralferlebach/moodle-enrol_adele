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
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adele:config'] = 'Configure learning path enrolment instances';
$string['adele:unenrol'] = 'Unenrol users from a course through the learning path enrolment method';
$string['defaultrole_desc'] = 'The role that ADELE assigns when it enrols a user into a target course of a learning path.';
$string['event_learning_path_purged'] = 'Learning path enrolments purged';
$string['event_learning_path_purged_description'] = 'All ADELE enrolment instances of learning path {$a->learningpathid} were hard-removed ({$a->removed} instance(s)).';
$string['event_learning_path_reconciled'] = 'Learning path enrolments recomputed';
$string['event_learning_path_reconciled_description'] = 'The ADELE enrolments of learning path {$a->learningpathid} were recomputed ({$a->affected} user(s) affected).';
$string['event_user_access_revoked'] = 'Learning path access revoked';
$string['event_user_access_revoked_description'] = 'User {$a->userid} lost access to learning path {$a->learningpathid} because no remaining subscription option carries them.';
$string['instancename'] = 'ADELE: {$a}';
$string['instancenamehost'] = 'ADELE (path access): {$a}';
$string['manage_action_purge'] = 'Hard delete';
$string['manage_action_queued'] = 'Queued as a background task ({$a} affected user(s)) - the page stayed responsive rather than waiting for a large run.';
$string['manage_action_reconcile'] = 'Recompute';
$string['manage_col_actions'] = 'Actions';
$string['manage_col_active'] = 'Active';
$string['manage_col_learningpath'] = 'Learning path';
$string['manage_col_suspended'] = 'Suspended';
$string['manage_col_targetcourses'] = 'Target courses';
$string['manage_confirm_purge'] = 'This permanently removes every ADELE enrolment instance and enrolment this learning path created, for every user. This cannot be undone and is not reversed by the next reconciliation run. Continue?';
$string['manage_heading'] = 'Learning path enrolment management';
$string['manage_intro'] = 'Every learning path that currently owns at least one ADELE enrolment instance, across all target and host courses. Use "Recompute" to reconcile a single learning path on demand (the nightly task already does this for all of them); use "Hard delete" to remove everything a learning path created, e.g. after fixing a data problem.';
$string['manage_no_paths'] = 'No learning path currently owns any ADELE enrolment instance.';
$string['manage_orphaned'] = 'Orphaned (learning path no longer exists)';
$string['manage_purge_done'] = 'Removed {$a} enrolment instance(s).';
$string['manage_reconcile_done'] = 'Recomputed for {$a} user(s).';
$string['pluginname'] = 'Learning path enrolment';
$string['pluginname_desc'] = 'This enrolment method owns the course enrolments that an ADELE learning path creates in its target courses. One enrolment instance belongs to exactly one learning path and one target course. This keeps the enrolments of different learning paths apart and lets it withdraw its own enrolments without ever touching manual, self or cohort enrolments.';
$string['privacy:metadata'] = 'The learning path enrolment plugin does not store any personal data. The enrolments it creates are stored by the Moodle core enrolment subsystem.';
$string['reconciletask'] = 'Reconcile learning path enrolments';
