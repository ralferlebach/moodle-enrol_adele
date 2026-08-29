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
 * Management page: every ADELE enrolment instance, with filters, the outcome
 * of the last reconciliation run, and per-learning-path repair actions.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_adele\local\instance_manager;
use enrol_adele\local\manage;
use enrol_adele\local\reconciler;

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

/**
 * Above this threshold, actions are queued as an ad-hoc task instead of
 * running synchronously, so the page stays responsive.
 */
const ADELE_MANAGE_ASYNC_THRESHOLD = 200;

$learningpathid = optional_param('learningpathid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$coursesearch = trim(optional_param('coursesearch', '', PARAM_TEXT));
$kind = optional_param('kind', 0, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);

admin_externalpage_setup('enrolsettingsadelemanage');
$context = context_system::instance();
require_capability('enrol/adele:config', $context);

$pageurl = new moodle_url('/enrol/adele/manage.php');
$filteredurl = new moodle_url($pageurl, array_filter([
    'learningpathid' => $learningpathid,
    'coursesearch' => $coursesearch,
    'kind' => $kind,
    'status' => $status,
]));
$PAGE->set_url($pageurl);

if ($action && $learningpathid) {
    if ($action === 'reconcile') {
        require_sesskey();
        $affected = manage::affected_user_count($learningpathid);
        if ($affected > ADELE_MANAGE_ASYNC_THRESHOLD) {
            $task = new \enrol_adele\task\reconcile_learning_path_adhoc();
            $task->set_custom_data(['learningpathid' => $learningpathid]);
            \core\task\manager::queue_adhoc_task($task);
            redirect($filteredurl, get_string('manage_action_queued', 'enrol_adele', $affected));
        }
        $n = reconciler::reconcile_learning_path($learningpathid);
        redirect($filteredurl, get_string('manage_reconcile_done', 'enrol_adele', $n));
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
                $filteredurl
            );
            echo $OUTPUT->footer();
            exit;
        }
        require_sesskey();
        $affected = manage::affected_user_count($learningpathid);
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_heading', 'enrol_adele'));
echo html_writer::tag('p', get_string('manage_intro', 'enrol_adele'));

// Outcome of the last full reconciliation run. A scheduled task's trace ends
// up in the task log, which is exactly where nobody looks when wondering
// whether reconciliation is doing anything at all.
$report = json_decode((string) get_config('enrol_adele', 'lastreport'), true);
echo $OUTPUT->heading(get_string('manage_report_heading', 'enrol_adele'), 3);
if (!is_array($report) || empty($report['timestamp'])) {
    echo $OUTPUT->notification(get_string('manage_report_never', 'enrol_adele'), 'info');
} else {
    $reporttable = new html_table();
    $reporttable->attributes['class'] = 'generaltable';
    $reporttable->head = [
        get_string('manage_report_pass', 'enrol_adele'),
        get_string('manage_report_count', 'enrol_adele'),
    ];
    $passes = [
        'orphaned' => 'manage_report_orphaned',
        'duplicates' => 'manage_report_duplicates',
        'roles' => 'manage_report_roles',
        'targetwanted' => 'manage_report_targetwanted',
        'targetactual' => 'manage_report_targetactual',
        'host' => 'manage_report_host',
        'uncarried' => 'manage_report_uncarried',
        'expired' => 'manage_report_expired',
    ];
    foreach ($passes as $key => $stringid) {
        $reporttable->data[] = [
            get_string($stringid, 'enrol_adele'),
            (int) ($report[$key] ?? 0),
        ];
    }
    echo html_writer::tag(
        'p',
        get_string('manage_report_when', 'enrol_adele', userdate($report['timestamp']))
    );
    echo html_writer::table($reporttable);
}

// Repairs in the background: what is still queued, and what became of the
// ones that already ran. A bare count answers neither question — a task that
// succeeded and one that was never queued both leave the queue empty.
echo $OUTPUT->heading(get_string('manage_tasks_heading', 'enrol_adele'), 3);
$queued = manage::get_queued_repairs();
if (!$queued) {
    echo html_writer::tag('p', get_string('manage_tasks_none', 'enrol_adele'));
} else {
    $queuedtable = new html_table();
    $queuedtable->attributes['class'] = 'generaltable';
    $queuedtable->id = 'enroladelequeuedtasks';
    $queuedtable->head = [
        get_string('manage_tasks_action', 'enrol_adele'),
        get_string('manage_col_learningpath', 'enrol_adele'),
        get_string('manage_tasks_state', 'enrol_adele'),
        get_string('manage_tasks_nextrun', 'enrol_adele'),
    ];
    foreach ($queued as $task) {
        $state = get_string('manage_tasks_state_' . $task['state'], 'enrol_adele');
        if ($task['state'] === 'retrying') {
            // The only state that will not resolve on its own; say so where
            // it is read, not only in the task log.
            $state = html_writer::span($state, 'text-danger');
        }
        $queuedtable->data[] = [
            s($task['action']),
            $task['learningpathid'] ? '#' . $task['learningpathid'] : '-',
            $state,
            $task['nextruntime'] ? userdate($task['nextruntime']) : '-',
        ];
    }
    echo html_writer::table($queuedtable);
}

$outcomes = \enrol_adele\local\task_log::all();
if ($outcomes) {
    $outcometable = new html_table();
    $outcometable->attributes['class'] = 'generaltable';
    $outcometable->id = 'enroladeletaskoutcomes';
    $outcometable->head = [
        get_string('manage_tasks_action', 'enrol_adele'),
        get_string('manage_col_learningpath', 'enrol_adele'),
        get_string('manage_tasks_outcome', 'enrol_adele'),
        get_string('manage_report_count', 'enrol_adele'),
        get_string('manage_tasks_finished', 'enrol_adele'),
    ];
    foreach ($outcomes as $entry) {
        $failed = ($entry['outcome'] ?? '') === 'failed';
        $outcome = get_string(
            $failed ? 'manage_tasks_outcome_failed' : 'manage_tasks_outcome_succeeded',
            'enrol_adele'
        );
        if ($failed) {
            $outcome = html_writer::span($outcome, 'text-danger');
            if (!empty($entry['message'])) {
                $outcome .= html_writer::tag('div', s($entry['message']), ['class' => 'small text-muted']);
            }
        }
        $outcometable->data[] = [
            s($entry['action'] ?? ''),
            !empty($entry['learningpathid']) ? '#' . (int) $entry['learningpathid'] : '-',
            $outcome,
            (int) ($entry['affected'] ?? 0),
            !empty($entry['timefinished']) ? userdate($entry['timefinished']) : '-',
        ];
    }
    echo html_writer::tag('p', get_string('manage_tasks_recent', 'enrol_adele'));
    echo html_writer::table($outcometable);
}

// Filter form.
$learningpaths = manage::get_filter_learningpaths();
$lpoptions = [0 => get_string('manage_filter_all', 'enrol_adele')];
foreach ($learningpaths as $lp) {
    $lpoptions[(int) $lp->id] = ($lp->name !== null)
        ? format_string($lp->name) . ' (#' . (int) $lp->id . ')'
        : '#' . (int) $lp->id . ' — ' . get_string('manage_orphaned', 'enrol_adele');
}

echo html_writer::start_tag('form', ['method' => 'get', 'action' => $pageurl->out(false), 'class' => 'mb-3']);
echo html_writer::start_div('form-inline');
echo html_writer::label(
    get_string('manage_col_learningpath', 'enrol_adele'),
    'adelefilterlp',
    true,
    ['class' => 'mr-1']
);
echo html_writer::select(
    $lpoptions,
    'learningpathid',
    $learningpathid,
    false,
    ['id' => 'adelefilterlp', 'class' => 'mr-2']
);
echo html_writer::label(
    get_string('manage_col_course', 'enrol_adele'),
    'adelefiltercourse',
    true,
    ['class' => 'mr-1']
);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'coursesearch',
    'id' => 'adelefiltercourse',
    'value' => $coursesearch,
    'class' => 'mr-2',
]);
echo html_writer::label(
    get_string('manage_col_type', 'enrol_adele'),
    'adelefilterkind',
    true,
    ['class' => 'mr-1']
);
echo html_writer::select(
    [
        0 => get_string('manage_filter_all', 'enrol_adele'),
        instance_manager::KIND_TARGET => get_string('manage_type_target', 'enrol_adele'),
        instance_manager::KIND_HOST => get_string('manage_type_host', 'enrol_adele'),
    ],
    'kind',
    $kind,
    false,
    ['id' => 'adelefilterkind', 'class' => 'mr-2']
);
echo html_writer::label(
    get_string('manage_filter_status', 'enrol_adele'),
    'adelefilterstatus',
    true,
    ['class' => 'mr-1']
);
echo html_writer::select(
    [
        '' => get_string('manage_filter_all', 'enrol_adele'),
        'active' => get_string('manage_status_active', 'enrol_adele'),
        'suspended' => get_string('manage_status_suspended', 'enrol_adele'),
    ],
    'status',
    $status,
    false,
    ['id' => 'adelefilterstatus', 'class' => 'mr-2']
);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('manage_filter_apply', 'enrol_adele'),
    'class' => 'btn btn-secondary',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

// A purge wipes an entire learning path, so it is offered only once the list
// has been narrowed to one — never as a per-row button that could be hit by
// accident on the wrong line.
if ($learningpathid) {
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'purge', 'learningpathid' => $learningpathid]),
        get_string('manage_action_purge', 'enrol_adele'),
        'get'
    );
}

[$where, $params] = manage::filter($learningpathid, $coursesearch, $kind, $status);
$total = manage::count_instances($where, $params);

if (!$total) {
    echo $OUTPUT->notification(get_string('manage_no_paths', 'enrol_adele'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new flexible_table('enrol_adele_manage');
$table->define_columns(['learningpath', 'course', 'type', 'active', 'suspended', 'actions']);
$table->define_headers([
    get_string('manage_col_learningpath', 'enrol_adele'),
    get_string('manage_col_course', 'enrol_adele'),
    get_string('manage_col_type', 'enrol_adele'),
    get_string('manage_col_active', 'enrol_adele'),
    get_string('manage_col_suspended', 'enrol_adele'),
    get_string('manage_col_actions', 'enrol_adele'),
]);
$table->define_baseurl($filteredurl);
$table->sortable(true, 'course', SORT_ASC);
$table->no_sorting('type');
$table->no_sorting('active');
$table->no_sorting('suspended');
$table->no_sorting('actions');
// The flexible_table class gives ids to rows and cells but not to the table itself,
// so tests would have to match on a row id prefix. One stable id is
// cheaper to depend on than that.
$table->attributes['id'] = 'enroladelemanagetable';
$table->attributes['class'] = 'generaltable';
$table->setup();
$table->pagesize(manage::PER_PAGE, $total);

$sort = manage::safe_sort($table->get_sql_sort());

// Only the current page is fetched — the whole point of the exercise. The
// previous version aggregated every instance, every user enrolment and every
// count in a single request.
$rows = manage::get_page($where, $params, $sort, $table->get_page_start(), $table->get_page_size());

// Enrolment counts for the visible page only, in one query.
$counts = manage::get_counts(array_keys($rows));

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

    $coursecell = html_writer::link(
        new moodle_url('/course/view.php', ['id' => $row->courseid]),
        format_string($row->shortname)
    );

    $typecell = ((int) $row->kind === instance_manager::KIND_HOST)
        ? get_string('manage_type_host', 'enrol_adele')
        : get_string('manage_type_target', 'enrol_adele');
    if ((int) $row->status !== ENROL_INSTANCE_ENABLED) {
        $typecell .= ' ' . html_writer::span(get_string('manage_instance_disabled', 'enrol_adele'), 'badge badge-secondary');
    }

    $count = $counts[$row->id] ?? null;

    $reconcileurl = new moodle_url($filteredurl, [
        'action' => 'reconcile',
        'learningpathid' => $lpid,
        'sesskey' => sesskey(),
    ]);

    $table->add_data([
        $namecell,
        $coursecell,
        $typecell,
        $count ? (int) $count->activecount : 0,
        $count ? (int) $count->suspendedcount : 0,
        $OUTPUT->single_button($reconcileurl, get_string('manage_action_reconcile', 'enrol_adele'), 'post'),
    ]);
}

$table->finish_output();
echo $OUTPUT->footer();
