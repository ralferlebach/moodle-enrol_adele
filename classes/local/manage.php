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
 * Queries behind the management page.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\local;

/**
 * Queries behind the management page.
 *
 * Deliberately not left inline in manage.php. A page script cannot be
 * included from a unit test — it calls require(config.php) and
 * admin_externalpage_setup() — so anything living there is only ever
 * exercised by opening the page in a browser. The paginated queries are the
 * part most likely to break on one database and not another, so they live
 * here where a test can reach them.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage {
    /**
     * Instances shown per page.
     */
    const PER_PAGE = 50;

    /**
     * The WHERE fragment and parameters implementing the current filters.
     *
     * Kept in one place so the count query and the page query cannot drift
     * apart — a paginated table whose total is computed from different
     * conditions than its rows shows the wrong number of pages.
     *
     * @param int $learningpathid Filter by learning path, 0 for all.
     * @param string $coursesearch Filter by course short or full name.
     * @param int $kind Filter by instance kind, 0 for all.
     * @param string $status Filter by enrolment status: active, suspended or ''.
     * @return array [string $where, array $params]
     */
    public static function filter(
        int $learningpathid = 0,
        string $coursesearch = '',
        int $kind = 0,
        string $status = ''
    ): array {
        global $DB;

        $where = ["e.enrol = 'adele'"];
        $params = [];

        if ($learningpathid) {
            $where[] = 'e.customint1 = :lpid';
            $params['lpid'] = $learningpathid;
        }
        if ($kind) {
            $where[] = 'e.customint2 = :kind';
            $params['kind'] = $kind;
        }
        if ($coursesearch !== '') {
            $like = '%' . $DB->sql_like_escape($coursesearch) . '%';
            $where[] = '(' . $DB->sql_like('c.shortname', ':cs1', false) . ' OR '
                . $DB->sql_like('c.fullname', ':cs2', false) . ')';
            $params['cs1'] = $like;
            $params['cs2'] = $like;
        }
        if ($status === 'active' || $status === 'suspended') {
            $params['uestatus'] = ($status === 'active') ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;
            $where[] = 'EXISTS (SELECT 1 FROM {user_enrolments} ue
                                 WHERE ue.enrolid = e.id AND ue.status = :uestatus)';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * How many instances match the filter.
     *
     * @param string $where WHERE fragment from {@see filter()}.
     * @param array $params Parameters from {@see filter()}.
     * @return int
     */
    public static function count_instances(string $where, array $params): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {enrol} e
               JOIN {course} c ON c.id = e.courseid
              WHERE {$where}",
            $params
        );
    }

    /**
     * One page of matching instances.
     *
     * Only the requested page is fetched. The previous version of this page
     * aggregated every instance, every user enrolment and every count in a
     * single request.
     *
     * @param string $where WHERE fragment from {@see filter()}.
     * @param array $params Parameters from {@see filter()}.
     * @param string $sort Validated ORDER BY fragment.
     * @param int $from Offset.
     * @param int $limit Page size.
     * @return \stdClass[] Instance rows keyed by enrol id.
     */
    public static function get_page(string $where, array $params, string $sort, int $from, int $limit): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT e.id, e.courseid, e.customint1 AS learningpathid, e.customint2 AS kind, e.status,
                    c.shortname, c.fullname, lp.name AS learningpathname
               FROM {enrol} e
               JOIN {course} c ON c.id = e.courseid
          LEFT JOIN {local_adele_learning_paths} lp ON lp.id = e.customint1
              WHERE {$where}
           ORDER BY {$sort}",
            $params,
            $from,
            $limit
        );
    }

    /**
     * Active and suspended enrolment counts for the given instances.
     *
     * One query for the whole visible page rather than one per row.
     *
     * @param int[] $enrolids Instance ids.
     * @return \stdClass[] Rows keyed by enrol id, with activecount and suspendedcount.
     */
    public static function get_counts(array $enrolids): array {
        global $DB;

        if (!$enrolids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
        return $DB->get_records_sql(
            "SELECT ue.enrolid,
                    SUM(CASE WHEN ue.status = :active THEN 1 ELSE 0 END) AS activecount,
                    SUM(CASE WHEN ue.status = :suspended THEN 1 ELSE 0 END) AS suspendedcount
               FROM {user_enrolments} ue
              WHERE ue.enrolid {$insql}
           GROUP BY ue.enrolid",
            ['active' => ENROL_USER_ACTIVE, 'suspended' => ENROL_USER_SUSPENDED] + $inparams
        );
    }

    /**
     * Map a sort request onto a real column.
     *
     * Only two columns are sortable, and both map onto an actual column.
     * Anything else falls back to a deterministic order rather than letting a
     * request parameter reach an ORDER BY clause.
     *
     * @param string|null $sort The value flexible_table asked for.
     * @return string A safe ORDER BY fragment.
     */
    public static function safe_sort(?string $sort): string {
        switch ($sort) {
            case 'learningpath ASC':
                return 'lp.name ASC';
            case 'learningpath DESC':
                return 'lp.name DESC';
            case 'course DESC':
                return 'c.shortname DESC';
            default:
                return 'c.shortname ASC';
        }
    }

    /**
     * Learning paths that own at least one ADELE instance, for the filter.
     *
     * @return \stdClass[] Rows with id and name; name is null for an orphan.
     */
    public static function get_filter_learningpaths(): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT DISTINCT e.customint1 AS id, lp.name
               FROM {enrol} e
          LEFT JOIN {local_adele_learning_paths} lp ON lp.id = e.customint1
              WHERE e.enrol = 'adele'
           ORDER BY lp.name ASC"
        );
    }

    /**
     * The ADELE repairs currently sitting in the ad-hoc queue.
     *
     * Three states are distinguishable from {task_adhoc} itself, and they
     * mean different things to whoever is waiting:
     *
     * - running: a worker has claimed it (timestarted is set);
     * - retrying: it threw and cron will try again (faildelay > 0) — the one
     *   state an administrator actually needs to see, because it will not
     *   resolve itself;
     * - queued: waiting for the next cron run.
     *
     * @return array List of ['classname', 'action', 'learningpathid',
     *     'state', 'nextruntime', 'faildelay'], soonest first.
     */
    public static function get_queued_repairs(): array {
        global $DB;

        $records = $DB->get_records_select(
            'task_adhoc',
            'component = :component',
            ['component' => 'enrol_adele'],
            'nextruntime ASC',
            'id, classname, customdata, nextruntime, faildelay, timestarted'
        );

        $result = [];
        foreach ($records as $record) {
            $data = json_decode((string) $record->customdata, true);
            $parts = explode('\\', trim((string) $record->classname, '\\'));
            $short = end($parts);
            if ($record->timestarted) {
                $state = 'running';
            } else if ($record->faildelay > 0) {
                $state = 'retrying';
            } else {
                $state = 'queued';
            }
            $result[] = [
                'classname' => (string) $record->classname,
                'action' => $short,
                'learningpathid' => (int) ($data['learningpathid'] ?? 0),
                'state' => $state,
                'nextruntime' => (int) $record->nextruntime,
                'faildelay' => (int) $record->faildelay,
            ];
        }
        return $result;
    }

    /**
     * Number of distinct users a learning path currently holds an ADELE
     * enrolment for, across target and host courses.
     *
     * The basis for deciding whether an action runs inline or is queued.
     *
     * @param int $learningpathid The learning path id.
     * @return int
     */
    public static function affected_user_count(int $learningpathid): int {
        global $DB;
        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele' AND e.customint1 = :lpid",
            ['lpid' => $learningpathid]
        );
    }
}
