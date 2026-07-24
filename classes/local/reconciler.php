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
    /** @var string Entitled => active host-course enrolment (default, 0.1.2 behaviour). */
    const MODE_VISIBLE = 'visible';

    /** @var string Entitled => enrolment record exists but stays suspended (never grants access). */
    const MODE_HIDDEN = 'hidden';

    /** @var string This embedding never grants host-course access, however entitled. */
    const MODE_NONE = 'none';

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
        $instances = instance_manager::get_instances($learningpathid, instance_manager::KIND_TARGET);

        foreach ($entitled as $courseid) {
            if (!isset($instances[$courseid])) {
                $instance = instance_manager::ensure_instance(
                    $learningpathid,
                    $courseid,
                    instance_manager::KIND_TARGET
                );
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

        // Specification 7.3: marks a deliberate, whole-learning-path
        // recomputation (management page "Recompute" / equivalent ad-hoc
        // task) - distinct from the routine per-user recompute hook, which
        // does not trigger this event.
        \enrol_adele\event\learning_path_reconciled::create([
            'context' => \context_system::instance(),
            'other' => [
                'learningpathid' => $learningpathid,
                'affected' => count($userids),
            ],
        ])->trigger();

        return count($userids);
    }

    /**
     * Reconcile every active user path in the system (scheduled task, CLI).
     *
     * Safety net against missed events; the primary trigger is the recompute
     * hook in local_adele. Extended (fix G.5, Session 003) to go beyond
     * target-course enrolments: also migrates stale roles (G.14), removes
     * orphaned instances (learning path no longer exists) and consolidates
     * duplicate instances (fix G.6 closes the race that creates them going
     * forward; this repairs any that already exist). Uses a recordset
     * instead of loading everything into memory at once.
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

        $orphaned = self::remove_orphaned_instances($trace);
        $duplicates = self::consolidate_duplicate_instances($trace);
        $rolesmigrated = self::sync_instance_roles($trace);

        $count = 0;
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT learning_path_id, user_id
               FROM {local_adele_path_user}
              WHERE status = 'active'"
        );
        foreach ($rs as $pair) {
            self::reconcile_user((int) $pair->learning_path_id, (int) $pair->user_id);
            $count++;
        }
        $rs->close();

        if ($trace) {
            $trace->output(
                "Reconciled {$count} learning path/user pairs, removed {$orphaned} orphaned " .
                "instance(s), consolidated {$duplicates} duplicate instance(s), migrated roles " .
                "on {$rolesmigrated} instance(s)."
            );
        }
        return $count;
    }

    /**
     * Remove ADELE enrol instances whose learning path no longer exists.
     *
     * Fix G.5 (Session 003): reconcile_all() previously never looked at
     * anything except active user paths, so an instance left behind by a
     * learning path deletion that bypassed purge_learning_path() (e.g. a
     * direct database edit, or a bug predating this fix) was never cleaned
     * up. delete_instance() removes the instance and its user_enrolments.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances removed.
     */
    private static function remove_orphaned_instances(?progress_trace $trace = null): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return 0;
        }

        $orphaned = $DB->get_records_sql(
            "SELECT e.*
               FROM {enrol} e
          LEFT JOIN {local_adele_learning_paths} lp ON lp.id = e.customint1
              WHERE e.enrol = 'adele' AND lp.id IS NULL"
        );
        foreach ($orphaned as $instance) {
            $plugin->delete_instance($instance);
            if ($trace) {
                $trace->output(
                    "  Removed orphaned instance {$instance->id} " .
                    "(learning path {$instance->customint1} no longer exists)."
                );
            }
        }
        return count($orphaned);
    }

    /**
     * Consolidate duplicate ADELE instances (same learning path, course and
     * kind) onto the lowest-id instance.
     *
     * Fix G.5/G.6 (Session 003): G.6 closes the race that creates these
     * going forward; this repairs any that already exist. Any
     * user_enrolments on a duplicate that the primary instance does not
     * already have for that user are re-created on the primary before the
     * duplicate is deleted, so no active access is lost — only the
     * bookkeeping is merged.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of duplicate instances removed.
     */
    private static function consolidate_duplicate_instances(?progress_trace $trace = null): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return 0;
        }

        $groups = $DB->get_records_sql(
            "SELECT MIN(id) AS primaryid, courseid, customint1, customint2, COUNT(*) AS n
               FROM {enrol}
              WHERE enrol = 'adele'
           GROUP BY courseid, customint1, customint2
             HAVING COUNT(*) > 1"
        );

        $removed = 0;
        foreach ($groups as $group) {
            $primary = $DB->get_record('enrol', ['id' => $group->primaryid]);
            $duplicates = $DB->get_records_select(
                'enrol',
                "enrol = 'adele' AND courseid = :courseid AND customint1 = :lpid " .
                    "AND customint2 = :kind AND id <> :primaryid",
                [
                    'courseid' => $group->courseid,
                    'lpid' => $group->customint1,
                    'kind' => $group->customint2,
                    'primaryid' => $group->primaryid,
                ]
            );
            foreach ($duplicates as $duplicate) {
                $users = $DB->get_records('user_enrolments', ['enrolid' => $duplicate->id]);
                foreach ($users as $ue) {
                    $primaryue = $DB->get_record(
                        'user_enrolments',
                        ['enrolid' => $primary->id, 'userid' => $ue->userid]
                    );
                    if (!$primaryue) {
                        $plugin->enrol_user(
                            $primary,
                            (int) $ue->userid,
                            $primary->roleid ?: instance_manager::get_role_id(),
                            0,
                            0,
                            (int) $ue->status
                        );
                    } else if ((int) $ue->status === ENROL_USER_ACTIVE && (int) $primaryue->status !== ENROL_USER_ACTIVE) {
                        $plugin->update_user_enrol($primary, (int) $ue->userid, ENROL_USER_ACTIVE);
                    }
                }
                $plugin->delete_instance($duplicate);
                $removed++;
                if ($trace) {
                    $trace->output(
                        "  Consolidated duplicate instance {$duplicate->id} onto {$primary->id} " .
                        "(learning path {$group->customint1}, course {$group->courseid})."
                    );
                }
            }
        }
        return $removed;
    }

    /**
     * Migrate ADELE-owned role assignments on existing instances to the
     * currently configured role.
     *
     * Fix G.14 (Session 003): previously, enrol_adele/roleid only affected
     * newly created instances — reconcile_user()/reconcile_host_user() used
     * $instance->roleid, which is set once at creation and never updated.
     * Only role assignments this plugin itself made (component
     * 'enrol_adele', itemid = instance id) are touched — a foreign role
     * assignment in the same course context is never removed.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances migrated.
     */
    private static function sync_instance_roles(?progress_trace $trace = null): int {
        global $DB;

        $currentroleid = instance_manager::get_role_id();
        $stale = $DB->get_records_select(
            'enrol',
            "enrol = 'adele' AND roleid <> :roleid AND roleid <> 0",
            ['roleid' => $currentroleid]
        );

        $migrated = 0;
        foreach ($stale as $instance) {
            $context = \context_course::instance($instance->courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            $assignments = $DB->get_records('user_enrolments', ['enrolid' => $instance->id]);
            foreach ($assignments as $ue) {
                role_unassign($instance->roleid, (int) $ue->userid, $context->id, 'enrol_adele', (int) $instance->id);
                role_assign($currentroleid, (int) $ue->userid, $context->id, 'enrol_adele', (int) $instance->id);
            }
            $DB->set_field('enrol', 'roleid', $currentroleid, ['id' => $instance->id]);
            $migrated++;
            if ($trace) {
                $trace->output(
                    "  Migrated role on instance {$instance->id} from {$instance->roleid} to {$currentroleid} " .
                    "(" . count($assignments) . " user(s))."
                );
            }
        }
        return $migrated;
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
        foreach (instance_manager::get_instances($learningpathid, instance_manager::KIND_TARGET) as $instance) {
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
     * Reconcile one user's HOST-course enrolment for one learning path embedding.
     *
     * Unlike reconcile_user() (target courses, entitlement derived from
     * local_adele's node feedback status), entitlement here is a plain boolean
     * the caller supplies: only mod_adele knows whether the user currently
     * holds membership in a qualifying node course for a given embedding's
     * option (2 = starting node, 3 = any node). This method is purely
     * mechanical — create/reactivate/suspend the one host-course instance —
     * mirroring the target-course logic without the set aggregation, since a
     * host-course instance is scoped to a single course.
     *
     * $mode (requirement mod_adele #22) lets the embedding scale back what
     * "entitled" actually grants, without the caller having to pre-compute a
     * Moodle enrolment status itself:
     * - MODE_VISIBLE (default): entitled => active, matches 0.1.2 behaviour.
     * - MODE_HIDDEN: entitled => an enrolment record still exists (countable
     *   in participant lists, reports, certificates) but stays suspended —
     *   never grants course access.
     * - MODE_NONE: this embedding never grants host-course access, however
     *   entitled the caller reports. Never creates a new instance for it; an
     *   instance left over from a PRIOR, more permissive mode is suspended,
     *   not deleted, so a later mode change back loses no history (L-Q-07).
     *
     * @param int $learningpathid The learning path id.
     * @param int $hostcourseid The course embedding the mod_adele activity.
     * @param int $userid The user id.
     * @param bool $entitled Whether the user currently qualifies for host access.
     * @param string $mode MODE_VISIBLE, MODE_HIDDEN or MODE_NONE.
     * @return void
     */
    public static function reconcile_host_user(
        int $learningpathid,
        int $hostcourseid,
        int $userid,
        bool $entitled,
        string $mode = self::MODE_VISIBLE
    ): void {
        global $DB;

        if (!self::is_active()) {
            return;
        }

        if ($mode === self::MODE_NONE) {
            $entitled = false;
        }

        $plugin = enrol_get_plugin('adele');
        $instance = instance_manager::get_instances($learningpathid, instance_manager::KIND_HOST)[$hostcourseid]
            ?? null;

        if ($entitled && !$instance) {
            $instance = instance_manager::ensure_instance($learningpathid, $hostcourseid, instance_manager::KIND_HOST);
        }
        if (!$instance) {
            return;
        }
        if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            return;
        }

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid]);
        $targetstatus = ($entitled && $mode !== self::MODE_HIDDEN) ? ENROL_USER_ACTIVE : ENROL_USER_SUSPENDED;

        if (!$ue) {
            // Nothing to suspend if there was never a record and the user
            // isn't entitled (covers MODE_NONE and plain non-entitlement alike).
            if (!$entitled) {
                return;
            }
            $plugin->enrol_user(
                $instance,
                $userid,
                $instance->roleid ?: instance_manager::get_role_id(),
                0,
                0,
                $targetstatus
            );
        } else if ((int) $ue->status !== $targetstatus) {
            $plugin->update_user_enrol($instance, $userid, $targetstatus);
        }
    }

    /**
     * Hard-remove one user's HOST-course enrolment for one learning path embedding.
     *
     * Used directly when the specific host course is already known (e.g. a
     * future admin action). purge_all_host_user() is the counterpart for the
     * common case — a user leaving the learning path entirely — where every
     * host embedding of that learning path is potentially affected, not just
     * one.
     *
     * @param int $learningpathid The learning path id.
     * @param int $hostcourseid The course embedding the mod_adele activity.
     * @param int $userid The user id.
     * @return void
     */
    public static function purge_host_user(int $learningpathid, int $hostcourseid, int $userid): void {
        global $DB;

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return;
        }
        $instance = instance_manager::get_instances($learningpathid, instance_manager::KIND_HOST)[$hostcourseid]
            ?? null;
        if (
            $instance && $DB->record_exists(
                'user_enrolments',
                ['enrolid' => $instance->id, 'userid' => $userid]
            )
        ) {
            $plugin->unenrol_user($instance, $userid);
        }
    }

    /**
     * Hard-remove ALL of one user's HOST-course enrolments for a learning path.
     *
     * The same learning path can be embedded (subscription options 2/3) in
     * several different host courses at once; leaving the learning path
     * entirely — the A-4 case this is wired into — must clear every one of
     * them, not just the host course that happened to trigger the removal.
     * Mirrors purge_user() (mod_adele #21 / enrol_adele project ticket
     * enrol_adele-issue-host-purge-on-leave, Session 002 Teil 12).
     *
     * @param int $learningpathid The learning path id.
     * @param int $userid The user id.
     * @return void
     */
    public static function purge_all_host_user(int $learningpathid, int $userid): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return;
        }
        foreach (instance_manager::get_instances($learningpathid, instance_manager::KIND_HOST) as $instance) {
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

        \enrol_adele\event\learning_path_purged::create([
            'context' => \context_system::instance(),
            'other' => [
                'learningpathid' => $learningpathid,
                'removed' => count($instances),
            ],
        ])->trigger();

        return count($instances);
    }
}
