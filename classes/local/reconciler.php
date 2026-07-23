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
 * Stateless reconciliation of ADELE enrolments.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\local;

use progress_trace;

/**
 * Stateless reconciliation of ADELE enrolments.
 *
 * The intended state lives entirely in local_adele
 * (\local_adele\enrol_state::get_entitled_courseids()); this class compares it
 * against Moodle's user_enrolments on the ADELE instances and enrols,
 * reactivates or suspends accordingly. There is deliberately no persisted
 * state of its own (decision F-6): every run derives everything fresh, which
 * makes each operation idempotent and self-healing.
 *
 * Node closing = suspension; learning path deletion or loss of access through
 * the embedding course = hard removal (decisions F-1/F-2). A shared target
 * course stays active as long as any node still grants it, because entitlement
 * is computed as a set across all nodes.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconciler {
    /**
     * Whether reconciliation can run: plugin enabled and local_adele new enough.
     *
     * @return bool
     */
    public static function is_active(): bool {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        return enrol_is_enabled('adele')
            && enrol_get_plugin('adele') !== null
            && class_exists('\local_adele\enrol_state');
    }

    /**
     * Reconcile one user against one learning path.
     *
     * Idempotent: enrols where entitled and missing, reactivates where entitled
     * and suspended, suspends where enrolled but no longer entitled. Disabled
     * instances are left alone (Moodle convention).
     *
     * @param int $learningpathid The learning path id.
     * @param int $userid The user id.
     * @return void
     */
    public static function reconcile_user(int $learningpathid, int $userid): void {
        global $DB;

        if (!self::is_active()) {
            return;
        }

        $entitled = \local_adele\enrol_state::get_entitled_courseids($learningpathid, $userid);
        $plugin = enrol_get_plugin('adele');
        $instances = instance_manager::get_instances($learningpathid);

        foreach ($entitled as $courseid) {
            if (!isset($instances[$courseid])) {
                $instance = instance_manager::ensure_instance($learningpathid, $courseid);
                if ($instance) {
                    $instances[$courseid] = $instance;
                }
            }
        }

        foreach ($instances as $courseid => $instance) {
            if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
                continue;
            }
            $ue = $DB->get_record(
                'user_enrolments',
                ['enrolid' => $instance->id, 'userid' => $userid]
            );
            $shouldbeactive = in_array($courseid, $entitled);

            if ($shouldbeactive && !$ue) {
                $plugin->enrol_user(
                    $instance,
                    $userid,
                    $instance->roleid ?: instance_manager::get_role_id(),
                    0,
                    0,
                    ENROL_USER_ACTIVE
                );
            } else if ($shouldbeactive && (int) $ue->status === ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
            } else if (!$shouldbeactive && $ue && (int) $ue->status === ENROL_USER_ACTIVE) {
                $plugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            }
        }
    }

    /**
     * Reconcile every user with an active user path on the given learning path.
     *
     * @param int $learningpathid The learning path id.
     * @return int Number of users reconciled.
     */
    public static function reconcile_learning_path(int $learningpathid): int {
        global $DB;

        if (!self::is_active()) {
            return 0;
        }

        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT user_id
               FROM {local_adele_path_user}
              WHERE learning_path_id = :lpid AND status = 'active'",
            ['lpid' => $learningpathid]
        );
        foreach ($userids as $userid) {
            self::reconcile_user($learningpathid, (int) $userid);
        }
        return count($userids);
    }

    /**
     * Reconcile every active user path in the system (scheduled task, CLI).
     *
     * Safety net against missed events; the primary trigger is the recompute
     * hook in local_adele.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of (learning path, user) pairs reconciled.
     */
    public static function reconcile_all(?progress_trace $trace = null): int {
        global $DB;

        if (!self::is_active()) {
            if ($trace) {
                $trace->output('enrol_adele is not active, nothing to do.');
            }
            return 0;
        }

        $pairs = $DB->get_records_sql(
            "SELECT DISTINCT learning_path_id, user_id
               FROM {local_adele_path_user}
              WHERE status = 'active'"
        );
        foreach ($pairs as $pair) {
            self::reconcile_user((int) $pair->learning_path_id, (int) $pair->user_id);
        }
        if ($trace) {
            $trace->output('Reconciled ' . count($pairs) . ' learning path/user pairs.');
        }
        return count($pairs);
    }

    /**
     * Hard-remove one user from all ADELE enrolments of a learning path.
     *
     * Used when access through the embedding course is lost (requirement A-4).
     * The caller must have removed or deactivated the user path first,
     * otherwise the next reconciliation run re-enrols immediately (invariant
     * in the specification, section 2.5).
     *
     * @param int $learningpathid The learning path id.
     * @param int $userid The user id.
     * @return void
     */
    public static function purge_user(int $learningpathid, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return;
        }
        foreach (instance_manager::get_instances($learningpathid) as $instance) {
            if (
                $DB->record_exists(
                    'user_enrolments',
                    ['enrolid' => $instance->id, 'userid' => $userid]
                )
            ) {
                $plugin->unenrol_user($instance, $userid);
            }
        }
    }

    /**
     * Hard-remove everything a learning path ever created (requirement A-3).
     *
     * Deletes every ADELE instance of the learning path through the plugin API,
     * which also removes the attached user_enrolments and role assignments.
     * Never deletes {enrol} rows directly — that would orphan user_enrolments
     * (documented anti-pattern, see specification 2.4).
     *
     * Instances of other learning paths and all other enrolment methods are
     * untouched by construction, because each instance belongs to exactly one
     * learning path.
     *
     * @param int $learningpathid The learning path id.
     * @return int Number of instances deleted.
     */
    public static function purge_learning_path(int $learningpathid): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return 0;
        }
        $instances = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'customint1' => $learningpathid]
        );
        foreach ($instances as $instance) {
            $plugin->delete_instance($instance);
        }
        return count($instances);
    }
}
