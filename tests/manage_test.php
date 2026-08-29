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
 * Tests for the queries behind the management page.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele;

use enrol_adele\local\instance_manager;
use enrol_adele\local\manage;

/**
 * Tests for the queries behind the management page.
 *
 * Filtering, counting and paging have to agree with each other; a total
 * computed from different conditions than the rows produces a table with the
 * wrong number of pages, which looks like data loss to whoever is using it.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \enrol_adele\local\manage
 */
final class manage_test extends \advanced_testcase {
    /**
     * One learning path with instances in the given number of courses.
     *
     * @param int $courses How many target-course instances to create.
     * @param string $shortnameprefix Course shortname prefix.
     * @return array [lpid, courseids]
     */
    private function plant_instances(int $courses, string $shortnameprefix): array {
        global $DB;

        $lpid = (int) $DB->insert_record('local_adele_learning_paths', (object) [
            'name' => 'Verwaltungs-Testpfad ' . $shortnameprefix,
            'description' => '',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby' => 2,
            'json' => json_encode(['tree' => ['nodes' => [], 'edges' => []]]),
        ]);

        $courseids = [];
        for ($i = 1; $i <= $courses; $i++) {
            $course = $this->getDataGenerator()->create_course([
                'shortname' => $shortnameprefix . $i,
                'fullname' => 'Kurs ' . $shortnameprefix . ' ' . $i,
            ]);
            instance_manager::ensure_instance($lpid, (int) $course->id, instance_manager::KIND_TARGET);
            $courseids[] = (int) $course->id;
        }
        return [$lpid, $courseids];
    }

    /**
     * The unfiltered count matches the number of instances, and a page never
     * returns more rows than its size.
     *
     * @return void
     */
    public function test_count_and_paging_agree(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $this->plant_instances(7, 'PAGE');

        [$where, $params] = manage::filter();
        $total = manage::count_instances($where, $params);
        $this->assertEquals(7, $total);

        $sort = manage::safe_sort(null);
        $firstpage = manage::get_page($where, $params, $sort, 0, 3);
        $secondpage = manage::get_page($where, $params, $sort, 3, 3);
        $lastpage = manage::get_page($where, $params, $sort, 6, 3);

        $this->assertCount(3, $firstpage);
        $this->assertCount(3, $secondpage);
        $this->assertCount(1, $lastpage);
        $this->assertEmpty(
            array_intersect_key($firstpage, $secondpage),
            'Consecutive pages must not overlap.'
        );
    }

    /**
     * Filtering by learning path, course name and kind narrows both the count
     * and the rows.
     *
     * @return void
     */
    public function test_filters_narrow_count_and_rows(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        [$lpa] = $this->plant_instances(3, 'ALPHA');
        $this->plant_instances(2, 'BETA');

        [$where, $params] = manage::filter($lpa);
        $this->assertEquals(3, manage::count_instances($where, $params));

        [$where, $params] = manage::filter(0, 'BETA');
        $this->assertEquals(2, manage::count_instances($where, $params));

        [$where, $params] = manage::filter(0, '', instance_manager::KIND_HOST);
        $this->assertEquals(0, manage::count_instances($where, $params), 'No host instances were created.');

        [$where, $params] = manage::filter(0, '', instance_manager::KIND_TARGET);
        $this->assertEquals(5, manage::count_instances($where, $params));
    }

    /**
     * The course search is case-insensitive and matches the full name too.
     *
     * @return void
     */
    public function test_course_search_matches_both_names_case_insensitively(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $this->plant_instances(1, 'GAMMA');

        [$where, $params] = manage::filter(0, 'gamma');
        $this->assertEquals(1, manage::count_instances($where, $params), 'Shortname, lower case.');

        [$where, $params] = manage::filter(0, 'Kurs GAMMA');
        $this->assertEquals(1, manage::count_instances($where, $params), 'Fullname.');

        [$where, $params] = manage::filter(0, 'nichtvorhanden');
        $this->assertEquals(0, manage::count_instances($where, $params));
    }

    /**
     * The status filter looks at the enrolments, not at the instance.
     *
     * @return void
     */
    public function test_status_filter_uses_enrolments(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        [$lpid, $courseids] = $this->plant_instances(2, 'STATUS');
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('adele');
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $courseids[0],
            'customint1' => $lpid,
        ]);
        $plugin->enrol_user($instance, (int) $user->id, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);

        [$where, $params] = manage::filter(0, '', 0, 'suspended');
        $this->assertEquals(1, manage::count_instances($where, $params));

        [$where, $params] = manage::filter(0, '', 0, 'active');
        $this->assertEquals(0, manage::count_instances($where, $params));
    }

    /**
     * The per-page counts split active from suspended, and an instance with
     * no enrolments simply has no row rather than a zero one.
     *
     * @return void
     */
    public function test_counts_split_active_and_suspended(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        [$lpid, $courseids] = $this->plant_instances(2, 'COUNT');
        $active = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('adele');
        $instance = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $courseids[0],
            'customint1' => $lpid,
        ]);
        $plugin->enrol_user($instance, (int) $active->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
        $plugin->enrol_user($instance, (int) $suspended->id, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);

        $empty = $DB->get_record('enrol', [
            'enrol' => 'adele',
            'courseid' => $courseids[1],
            'customint1' => $lpid,
        ]);

        $counts = manage::get_counts([(int) $instance->id, (int) $empty->id]);
        $this->assertEquals(1, (int) $counts[$instance->id]->activecount);
        $this->assertEquals(1, (int) $counts[$instance->id]->suspendedcount);
        $this->assertArrayNotHasKey((int) $empty->id, $counts);

        $this->assertSame([], manage::get_counts([]), 'An empty page must not produce a query.');
    }

    /**
     * Only the two sortable columns reach the ORDER BY clause.
     *
     * @return void
     */
    public function test_safe_sort_rejects_anything_else(): void {
        $this->assertEquals('lp.name ASC', manage::safe_sort('learningpath ASC'));
        $this->assertEquals('lp.name DESC', manage::safe_sort('learningpath DESC'));
        $this->assertEquals('c.shortname DESC', manage::safe_sort('course DESC'));
        $this->assertEquals('c.shortname ASC', manage::safe_sort('course ASC'));
        $this->assertEquals('c.shortname ASC', manage::safe_sort(null));
        $this->assertEquals('c.shortname ASC', manage::safe_sort('e.id; DROP TABLE mdl_user'));
    }

    /**
     * The cost of showing the first page must not depend on how much there is.
     *
     * The point of issue #6. The previous page aggregated every instance and
     * every enrolment in one request, so it got slower with every learning
     * path an installation ever created — the kind of regression that is
     * invisible on a development site and fatal on a real one.
     *
     * Measured in DATABASE QUERIES, not in wall time: a timing assertion on a
     * CI runner is a coin flip, while the query count is exactly what the
     * change was about and is deterministic. Rendering one page must cost the
     * same three queries whether there are 10 instances or 210, and must
     * never return more rows than the page holds.
     *
     * @return void
     */
    public function test_first_page_cost_is_independent_of_total_size(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        // The global goes inside the closure: a closure does not inherit the
        // caller's globals.
        $measure = function (): array {
            global $DB;
            $before = $DB->perf_get_reads();
            [$where, $params] = manage::filter();
            $total = manage::count_instances($where, $params);
            $rows = manage::get_page($where, $params, manage::safe_sort(null), 0, 10);
            manage::get_counts(array_keys($rows));
            return ['reads' => $DB->perf_get_reads() - $before, 'total' => $total, 'rows' => count($rows)];
        };

        $this->plant_instances(10, 'SMALL');
        $small = $measure();
        $this->assertEquals(10, $small['total']);
        $this->assertEquals(10, $small['rows']);

        $this->plant_instances(200, 'LARGE');
        $large = $measure();
        $this->assertEquals(210, $large['total'], 'Precondition: the data set must really have grown.');

        $this->assertEquals(
            10,
            $large['rows'],
            'A page must never return more rows than its size, however large the table.'
        );
        $this->assertEquals(
            $small['reads'],
            $large['reads'],
            'Rendering the first page must cost the same number of queries with 210 instances as with 10.'
        );
    }

    /**
     * An orphaned instance — learning path gone — still appears, with a null
     * name, instead of quietly dropping out of the list.
     *
     * @return void
     */
    public function test_orphaned_instance_is_still_listed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();

        [$lpid] = $this->plant_instances(1, 'ORPHAN');
        $DB->delete_records('local_adele_learning_paths', ['id' => $lpid]);

        [$where, $params] = manage::filter();
        $this->assertEquals(1, manage::count_instances($where, $params));
        $rows = manage::get_page($where, $params, manage::safe_sort(null), 0, 10);
        $row = reset($rows);
        $this->assertNull($row->learningpathname);
    }
}
