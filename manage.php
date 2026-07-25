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
 * Management page: every learning path that owns at least one ADELE
 * enrolment instance, with "Recompute" and "Hard delete" actions.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_adele\local\instance_manager;
use enrol_adele\local\reconciler;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

/**
 * Above this threshold, actions are queued as an ad-hoc task instead of
 * running synchronously, so the page stays responsive (specification
 * section 6). Not exposed as a setting (yet) — a fixed, documented value
 * keeps behaviour predictable; revisit if real installations need it
 * configurable.
 */
const ADELE_MANAGE_ASYNC_THRESHOLD = 200;

$learningpathid = optional_param('learningpathid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

admin_externalpage_setup('enrolsettingsadelemanage');
$context = context_system::instance();
require_capability('enrol/adele:config', $context);

$pageurl = new moodle_url('/enrol/adele/manage.php');
$PAGE->set_url($pageurl);

/**
 * Number of distinct users an ADELE-owned learning path currently holds an
 * enrolment for (target and host courses combined) — the basis for the
 * async-threshold decision.
 *
 * @param int $learningpathid The learning path id.
 * @return int
 */
function enrol_adele_manage_affected_user_count(int $learningpathid): int {
    global $DB;
    return (int) $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
           FROM {user_enrolments} ue
           JOIN {enrol} e ON e.id = ue.enrolid
          WHERE e.enrol = 'adele' AND e.customint1 = :lpid",
        ['lpid' => $learningpathid]
    );
}

if ($action && $learningpathid) {
    if ($action === 'reconcile') {
        require_sesskey();
        $affected = enrol_adele_manage_affected_user_count($learningpathid);
        if ($affected > ADELE_MANAGE_ASYNC_THRESHOLD) {
            $task = new \enrol_adele\task\reconcile_learning_path_adhoc();
            $task->set_custom_data(['learningpathid' => $learningpathid]);
            \core\task\manager::queue_adhoc_task($task);
            redirect($pageurl, get_string('manage_action_queued', 'enrol_adele', $affected));
        }
        $n = reconciler::reconcile_learning_path($learningpathid);
        redirect($pageurl, get_string('manage_reconcile_done', 'enrol_adele', $n));
    } else if ($action === 'purge') {
        // First click (no $confirm yet): only navigates to a confirmation
        // page, no mutation happens — deliberately does not require sesskey
        // here. The confirmation page's own continue link carries a fresh
        // sesskey, which IS checked below once $confirm is set.
        if (!$confirm) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('manage_heading', 'enrol_adele'));
            echo $OUTPUT->confirm(
                get_string('manage_confirm_purge', 'enrol_adele'),
                new moodle_url($pageurl, [
                    'action' => 'purge',
                    'learningpathid' => $learningpathid,
                    'confirm' => 1,
                    'sesskey' => sesskey(),
                ]),
                $pageurl
            );
            echo $OUTPUT->footer();
            exit;
        }
        require_sesskey();
        $affected = enrol_adele_manage_affected_user_count($learningpathid);
        if ($affected > ADELE_MANAGE_ASYNC_THRESHOLD) {
            $task = new \enrol_adele\task\purge_learning_path_adhoc();
            $task->set_custom_data(['learningpathid' => $learningpathid]);
            \core\task\manager::queue_adhoc_task($task);
            redirect($pageurl, get_string('manage_action_queued', 'enrol_adele', $affected));
        }
        $n = reconciler::purge_learning_path($learningpathid);
        redirect($pageurl, get_string('manage_purge_done', 'enrol_adele', $n));
    }
}

// One row per learning path that owns at least one ADELE instance. A LEFT
// JOIN against local_adele_learning_paths deliberately surfaces orphaned
// instances (learning path deleted, instances somehow left behind) as rows
// with a null name, rather than silently hiding them.
$rows = $DB->get_records_sql(
    "SELECT e.customint1 AS learningpathid,
            lp.name AS learningpathname,
            COUNT(DISTINCT CASE WHEN e.customint2 = :kindtarget THEN e.id END) AS targetcourses,
            COUNT(DISTINCT CASE WHEN ue.status = 0 THEN ue.id END) AS activeenrolments,
            COUNT(DISTINCT CASE WHEN ue.status = 1 THEN ue.id END) AS suspendedenrolments
       FROM {enrol} e
  LEFT JOIN {local_adele_learning_paths} lp ON lp.id = e.customint1
  LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id
      WHERE e.enrol = 'adele'
   GROUP BY e.customint1, lp.name
   ORDER BY lp.name ASC, e.customint1 ASC",
    ['kindtarget' => instance_manager::KIND_TARGET]
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'enrol_adele'));
echo html_writer::tag('p', get_string('manage_intro', 'enrol_adele'));

if (!$rows) {
    echo $OUTPUT->notification(get_string('manage_no_paths', 'enrol_adele'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('manage_col_learningpath', 'enrol_adele'),
    get_string('manage_col_targetcourses', 'enrol_adele'),
    get_string('manage_col_active', 'enrol_adele'),
    get_string('manage_col_suspended', 'enrol_adele'),
    get_string('manage_col_actions', 'enrol_adele'),
];
$table->id = 'enroladelemanagetable';
$table->attributes['class'] = 'generaltable';

foreach ($rows as $row) {
    $lpid = (int) $row->learningpathid;

    if ($row->learningpathname === null) {
        $namecell = html_writer::span(
            '#' . $lpid . ' — ' . get_string('manage_orphaned', 'enrol_adele'),
            'text-danger'
        );
    } else {
        $namecell = format_string($row->learningpathname) . ' (#' . $lpid . ')';
    }

    $reconcileurl = new moodle_url($pageurl, [
        'action' => 'reconcile',
        'learningpathid' => $lpid,
        'sesskey' => sesskey(),
    ]);
    $purgeurl = new moodle_url($pageurl, [
        'action' => 'purge',
        'learningpathid' => $lpid,
    ]);
    $actions = $OUTPUT->single_button(
        $reconcileurl,
        get_string('manage_action_reconcile', 'enrol_adele'),
        'post'
    ) . $OUTPUT->single_button(
        $purgeurl,
        get_string('manage_action_purge', 'enrol_adele'),
        'get'
    );

    $table->data[] = [
        $namecell,
        (int) $row->targetcourses,
        (int) $row->activeenrolments,
        (int) $row->suspendedenrolments,
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
