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
 * Privacy provider for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use enrol_adele\local\teacher_grants;

/**
 * Privacy provider for enrol_adele.
 *
 * Learner enrolments keep no data of their own here; the core enrolment
 * subsystem reports them. The teacher grants (local_adele #600) are rows of
 * this plugin: who was enrolled into which course, from which activity, with
 * which role. They belong to the context of the course they enrol into.
 * Deleting a grant takes its enrolment and role away with it.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * What is stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(teacher_grants::TABLE, [
            'cmid' => 'privacy:metadata:grants:cmid',
            'courseid' => 'privacy:metadata:grants:courseid',
            'userid' => 'privacy:metadata:grants:userid',
            'mode' => 'privacy:metadata:grants:mode',
            'roleid' => 'privacy:metadata:grants:roleid',
            'timecreated' => 'privacy:metadata:grants:timecreated',
        ], 'privacy:metadata:grants');
        return $collection;
    }

    /**
     * The course contexts a person holds grants in.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $list = new contextlist();
        $list->add_from_sql(
            "SELECT ctx.id
               FROM {" . teacher_grants::TABLE . "} g
               JOIN {context} ctx ON ctx.instanceid = g.courseid AND ctx.contextlevel = :courselevel
              WHERE g.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $list;
    }

    /**
     * The people holding grants in a course context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {" . teacher_grants::TABLE . "} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export a person's grants.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $grants = [];
            foreach (teacher_grants::get(['courseid' => $context->instanceid, 'userid' => $userid]) as $grant) {
                $grants[] = [
                    'cmid' => (int) $grant->cmid,
                    'mode' => $grant->mode,
                    'roleid' => (int) $grant->roleid,
                    'timecreated' => transform::datetime($grant->timecreated),
                ];
            }
            if ($grants) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:grants', 'enrol_adele')],
                    (object) ['grants' => $grants]
                );
            }
        }
    }

    /**
     * Delete all grants in a course context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if ($context->contextlevel == CONTEXT_COURSE) {
            teacher_grants::remove(['courseid' => $context->instanceid]);
        }
    }

    /**
     * Delete a person's grants in the given contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                teacher_grants::remove(['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete the grants of several people in a course context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            teacher_grants::remove(['courseid' => $context->instanceid, 'userid' => (int) $userid]);
        }
    }
}
