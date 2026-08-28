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
 * @copyright   2026 Wunderbyte GmbH
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
 * state of its own: every run derives everything fresh, which makes each
 * operation idempotent and self-healing.
 *
 * Node closing = suspension; learning path deletion or loss of access through
 * the embedding course = hard removal. A shared target
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

        // Mark a deliberate, whole-learning-path recomputation (management
        // page "Recompute" / equivalent ad-hoc task), distinct from the
        // routine per-user recompute hook, which does not trigger this event.
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
     * Reconcile the whole installation (scheduled task, CLI).
     *
     * The safety net against missed events; the primary trigger remains the
     * recompute hook in local_adele. Runs six passes, each idempotent and
     * each reporting its own line through the trace:
     *
     * 1. instances: remove orphans (learning path gone),
     * 2. instances: consolidate duplicates,
     * 3. instances: migrate stale roles,
     * 4. target courses, wanted -> actual: every active user path,
     * 5. target courses, actual -> wanted: every user holding an ADELE
     *    target enrolment WITHOUT an active user path,
     * 6. host courses, both directions at once,
     * 7. hard-remove enrolments that have been suspended past the retention
     *    period.
     *
     * Passes 5 and 6 are what make the run bidirectional. Before them the
     * sweep only ever started from the wanted state, so a user whose path row
     * had gone away — or whose host-course entitlement changed while the
     * event was lost — was never visited at all, and their enrolment stayed
     * active forever. Every pass streams through a recordset instead of
     * loading its working set into memory.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of (learning path, user) pairs reconciled across
     *     passes 4 to 6.
     */
    public static function reconcile_all(?progress_trace $trace = null): int {
        if (!self::is_active()) {
            if ($trace) {
                $trace->output('enrol_adele is not active, nothing to do.');
            }
            return 0;
        }

        $report = [
            'orphaned' => self::remove_orphaned_instances($trace),
            'duplicates' => self::consolidate_duplicate_instances($trace),
            'roles' => self::sync_instance_roles($trace),
            'targetwanted' => self::sweep_target_wanted($trace),
            'targetactual' => self::sweep_target_actual($trace),
            'host' => self::sweep_host($trace),
            'expired' => self::purge_expired_suspensions($trace),
        ];

        if ($trace) {
            $trace->output(
                "Done. Instances: {$report['orphaned']} orphaned removed, " .
                "{$report['duplicates']} duplicates consolidated, {$report['roles']} roles migrated. " .
                "Users: {$report['targetwanted']} target (wanted->actual), " .
                "{$report['targetactual']} target (actual->wanted), {$report['host']} host, " .
                "{$report['expired']} expired suspension(s) removed."
            );
        }

        return $report['targetwanted'] + $report['targetactual'] + $report['host'];
    }

    /**
     * Pass 4 — every active user path against its target courses.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of (learning path, user) pairs visited.
     */
    private static function sweep_target_wanted(?progress_trace $trace = null): int {
        global $DB;

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
            $trace->output("  Target courses (wanted -> actual): {$count} learning path/user pair(s) visited.");
        }
        return $count;
    }

    /**
     * Pass 5 — every user holding an ADELE target enrolment that no active
     * user path justifies any more.
     *
     * The counterpart of pass 4: it starts from the ACTUAL state instead of
     * the wanted one. Without it, a user whose path row was deleted or set to
     * a non-active status keeps every target-course enrolment ADELE ever gave
     * them, because pass 4 never enumerates them. reconcile_user() suspends
     * them, since get_entitled_courseids() returns an empty set for a user
     * without an active path.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of (learning path, user) pairs visited.
     */
    private static function sweep_target_actual(?progress_trace $trace = null): int {
        global $DB;

        $count = 0;
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT e.customint1 AS learningpathid, ue.userid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
                    AND e.customint2 = :kind
                    AND ue.status = :active
                    AND NOT EXISTS (
                        SELECT 1
                          FROM {local_adele_path_user} lpu
                         WHERE lpu.learning_path_id = e.customint1
                               AND lpu.user_id = ue.userid
                               AND lpu.status = 'active'
                    )",
            ['kind' => instance_manager::KIND_TARGET, 'active' => ENROL_USER_ACTIVE]
        );
        foreach ($rs as $pair) {
            self::reconcile_user((int) $pair->learningpathid, (int) $pair->userid);
            $count++;
        }
        $rs->close();

        if ($trace) {
            $trace->output("  Target courses (actual -> wanted): {$count} unjustified enrolment(s) revisited.");
        }
        return $count;
    }

    /**
     * Pass 6 — host-course access, in both directions.
     *
     * Host access used to be maintained purely by mod_adele's enrolment
     * observers, so a single lost event (bulk operation with events
     * suppressed, an exception inside the observer, a direct database edit,
     * a partial restore) left it wrong forever: nothing ever re-derived it.
     *
     * The working set is deliberately the UNION of two populations per
     * (learning path, host course) pair:
     *
     * - users with an active user path — heals missed GRANTS;
     * - users currently holding an ADELE host enrolment — heals missed
     *   REVOCATIONS, including the case where the user path row is already
     *   gone and the first population would therefore never mention them.
     *
     * The entitlement itself is never derived here. It is asked of
     * local_adele, which routes the question to mod_adele — the same code the
     * live observer uses, so the sweep and the event path cannot disagree.
     * A null answer means "cannot tell right now" (mod_adele missing or
     * mid-upgrade) and is skipped rather than treated as "not entitled",
     * which would revoke access from everyone.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of (learning path, host course, user) triples visited.
     */
    private static function sweep_host(?progress_trace $trace = null): int {
        global $DB;

        $count = 0;
        foreach (self::get_host_learningpathids() as $lpid) {
            foreach (self::get_host_courseids($lpid) as $hostcourseid) {
                $count += self::reconcile_host_embedding($lpid, $hostcourseid);
            }
        }

        if ($trace) {
            $trace->output("  Host courses: {$count} learning path/host course/user triple(s) visited.");
        }
        return $count;
    }

    /**
     * Re-derive host-course access for every user of one embedding.
     *
     * The unit of work of the host pass, and the entry point mod_adele's
     * ad-hoc task uses when an activity is created, saved or deleted.
     *
     * The working set is deliberately the UNION of two populations:
     *
     * - users with an active user path — heals missed GRANTS;
     * - users currently holding an ADELE host enrolment for this pair —
     *   heals missed REVOCATIONS, including the case where the user path row
     *   is already gone and the first population would never mention them.
     *
     * The entitlement itself is never derived here. It is asked of
     * local_adele, which routes the question to mod_adele — the same code the
     * live observer uses, so sweep and event path cannot disagree. A null
     * answer means "cannot tell right now" (mod_adele missing or mid-upgrade)
     * and is skipped rather than treated as "not entitled", which would
     * revoke access from everyone.
     *
     * @param int $learningpathid The learning path id.
     * @param int $hostcourseid The host course id.
     * @return int Number of users visited.
     */
    public static function reconcile_host_embedding(int $learningpathid, int $hostcourseid): int {
        global $DB;

        if (!self::is_active()) {
            return 0;
        }

        $count = 0;
        $rs = $DB->get_recordset_sql(
            "SELECT DISTINCT userid
               FROM (
                    SELECT lpu.user_id AS userid
                      FROM {local_adele_path_user} lpu
                     WHERE lpu.learning_path_id = :lpid1
                           AND lpu.status = 'active'
                     UNION
                    SELECT ue.userid AS userid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.enrol = 'adele'
                           AND e.customint1 = :lpid2
                           AND e.customint2 = :kind
                           AND e.courseid = :courseid
               ) affected",
            [
                'lpid1' => $learningpathid,
                'lpid2' => $learningpathid,
                'kind' => instance_manager::KIND_HOST,
                'courseid' => $hostcourseid,
            ]
        );
        foreach ($rs as $row) {
            $userid = (int) $row->userid;
            $entitlement = \local_adele\enrol_state::get_host_entitlement($learningpathid, $hostcourseid, $userid);
            if ($entitlement === null) {
                continue;
            }
            [$entitled, $mode] = $entitlement;
            self::reconcile_host_user($learningpathid, $hostcourseid, $userid, (bool) $entitled, (string) $mode);
            $count++;
        }
        $rs->close();

        return $count;
    }

    /**
     * Pass 7 — hard-remove enrolments that have been suspended for longer
     * than the configured retention period.
     *
     * Suspend-not-delete is the right default: a suspended enrolment keeps
     * the record intact for reports and certificates, and costs nothing if
     * the entitlement returns. But Moodle lists suspended participants on the
     * course participants page, and this plugin refuses manual unenrolment
     * (allow_unenrol() is false), so a course that has seen a lot of
     * enrolment turnover accumulates rows no teacher can act on or remove —
     * exactly the complaint in issue #7.
     *
     * MUST run last. Passes 4 to 6 have just brought every status up to date,
     * so anything still suspended at this point is genuinely unjustified, and
     * its timemodified is the moment it became so — core's
     * enrol_plugin::update_user_enrol() sets timemodified on every status
     * change (verified against MOODLE_405_STABLE). Anything reactivated
     * moments ago carries a fresh timestamp and is therefore out of range by
     * construction, with no second entitlement check needed.
     *
     * The caveat that comes with that: timemodified is also touched by
     * timestart/timeend edits and by a restore, so it means "last changed",
     * not strictly "suspended since". For ADELE-owned enrolments those are
     * the same event in practice, since nothing else may write them.
     *
     * A retention of 0 disables the pass entirely.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of enrolments removed.
     */
    private static function purge_expired_suspensions(?progress_trace $trace = null): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');

        $days = (int) get_config('enrol_adele', 'suspendedretention');
        if ($days <= 0) {
            return 0;
        }
        $plugin = enrol_get_plugin('adele');
        if (!$plugin) {
            return 0;
        }

        $cutoff = time() - ($days * DAYSECS);
        $expired = $DB->get_records_sql(
            "SELECT ue.id, ue.userid, ue.enrolid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
                    AND ue.status = :suspended
                    AND ue.timemodified > 0
                    AND ue.timemodified < :cutoff",
            ['suspended' => ENROL_USER_SUSPENDED, 'cutoff' => $cutoff]
        );

        $instances = [];
        $removed = 0;
        foreach ($expired as $ue) {
            $enrolid = (int) $ue->enrolid;
            if (!isset($instances[$enrolid])) {
                $instances[$enrolid] = $DB->get_record('enrol', ['id' => $enrolid]);
            }
            if (!$instances[$enrolid]) {
                continue;
            }
            $plugin->unenrol_user($instances[$enrolid], (int) $ue->userid);
            $removed++;
        }

        if ($trace) {
            $trace->output(
                "  Retention: {$removed} enrolment(s) removed after {$days} day(s) suspended."
            );
        }
        return $removed;
    }

    /**
     * Every learning path the host pass has to consider.
     *
     * The union of learning paths currently embedded with option 2 or 3 and
     * learning paths that still own a host instance. The second half matters:
     * once the last embedding is deleted, the first half no longer mentions
     * the learning path at all, yet its host enrolments are exactly the ones
     * that need revoking.
     *
     * @return int[] Distinct learning path ids.
     */
    private static function get_host_learningpathids(): array {
        global $DB;

        $embedded = \local_adele\enrol_state::get_learningpaths_with_host_embeddings();
        $withinstances = $DB->get_fieldset_select(
            'enrol',
            'DISTINCT customint1',
            "enrol = 'adele' AND customint2 = :kind",
            ['kind' => instance_manager::KIND_HOST]
        );
        $all = array_merge(array_map('intval', $embedded), array_map('intval', $withinstances));
        return array_values(array_unique($all));
    }

    /**
     * Every host course of one learning path the host pass has to consider.
     *
     * Same union rationale as {@see get_host_learningpathids()}: currently
     * embedded host courses plus host courses that still carry an instance.
     *
     * @param int $learningpathid The learning path id.
     * @return int[] Distinct course ids.
     */
    private static function get_host_courseids(int $learningpathid): array {
        global $DB;

        $courseids = [];
        foreach (\local_adele\enrol_state::get_host_embeddings($learningpathid) as $embedding) {
            if (!empty($embedding['option2']) || !empty($embedding['option3'])) {
                $courseids[] = (int) $embedding['courseid'];
            }
        }
        $withinstances = $DB->get_fieldset_select(
            'enrol',
            'DISTINCT courseid',
            "enrol = 'adele' AND customint1 = :lpid AND customint2 = :kind",
            ['lpid' => $learningpathid, 'kind' => instance_manager::KIND_HOST]
        );
        $courseids = array_merge($courseids, array_map('intval', $withinstances));
        return array_values(array_unique($courseids));
    }

    /**
     * Remove ADELE enrol instances nothing justifies any more.
     *
     * Two kinds of orphan, both leaving enrolments that no administrator can
     * explain and — since allow_unenrol() is false — none can remove by hand:
     *
     * 1. The learning path is gone. Left behind by a deletion that bypassed
     *    purge_learning_path(), e.g. a direct database edit.
     * 2. The learning path still exists, but the host-course instance has no
     *    embedding any more: the mod_adele activity that justified it was
     *    deleted. Case 1 never catches this, because it keys on the learning
     *    path, which is very much alive (issue #7).
     *
     * delete_instance() removes the instance together with its
     * user_enrolments and role assignments. That is deliberately harder than
     * the suspend-not-delete rule applied to users elsewhere: suspending
     * preserves a record whose justification might come back, while an
     * instance without any embedding has nothing left to come back to.
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

        $removed = 0;

        $orphaned = $DB->get_records_sql(
            "SELECT e.*
               FROM {enrol} e
          LEFT JOIN {local_adele_learning_paths} lp ON lp.id = e.customint1
              WHERE e.enrol = 'adele' AND lp.id IS NULL"
        );
        foreach ($orphaned as $instance) {
            $plugin->delete_instance($instance);
            $removed++;
            if ($trace) {
                $trace->output(
                    "  Removed orphaned instance {$instance->id} " .
                    "(learning path {$instance->customint1} no longer exists)."
                );
            }
        }

        $removed += self::remove_unembedded_host_instances($trace);

        return $removed;
    }

    /**
     * Remove host-course instances whose embedding has been deleted.
     *
     * Skipped entirely when mod_adele cannot be reached: an empty embedding
     * list would then mean "I cannot tell", not "there are none", and acting
     * on it would delete every host instance on the site.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances removed.
     */
    private static function remove_unembedded_host_instances(?progress_trace $trace = null): int {
        global $DB;

        if (!method_exists('\local_adele\enrol_state', 'get_host_embeddings')) {
            return 0;
        }

        $instances = $DB->get_records(
            'enrol',
            ['enrol' => 'adele', 'customint2' => instance_manager::KIND_HOST]
        );
        if (!$instances) {
            return 0;
        }

        $plugin = enrol_get_plugin('adele');
        $embeddedby = [];
        $removed = 0;
        foreach ($instances as $instance) {
            $lpid = (int) $instance->customint1;
            if (!array_key_exists($lpid, $embeddedby)) {
                $courseids = [];
                foreach (\local_adele\enrol_state::get_host_embeddings($lpid) as $embedding) {
                    $courseids[] = (int) $embedding['courseid'];
                }
                $embeddedby[$lpid] = $courseids;
            }
            if (in_array((int) $instance->courseid, $embeddedby[$lpid], true)) {
                continue;
            }
            $plugin->delete_instance($instance);
            $removed++;
            if ($trace) {
                $trace->output(
                    "  Removed host instance {$instance->id} (learning path {$lpid} is no longer " .
                    "embedded in course {$instance->courseid})."
                );
            }
        }
        return $removed;
    }

    /**
     * Consolidate duplicate ADELE instances (same learning path, course and
     * kind) onto the lowest-id instance.
     *
     * Repairs duplicates created by a race between two near-simultaneous
     * instance creations. Any user_enrolments on a duplicate that the
     * primary instance does not
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
     * Two independent kinds of drift, because a role can go wrong in two
     * places that do not imply each other:
     *
     * 1. The INSTANCE carries a stale roleid. $instance->roleid is set once
     *    at creation and never updated, so a change to enrol_adele/roleid
     *    would otherwise only ever affect newly created instances.
     * 2. A USER is missing the assignment, or holds an ADELE-owned one with
     *    the wrong role. Checking the instance alone cannot see this: an
     *    instance whose roleid already matches passes step 1 without anyone
     *    ever looking at whether its participants actually hold the role. A
     *    bulk role tool, a partial restore or a direct database edit takes
     *    the assignment away silently, and nothing here used to notice.
     *
     * Only assignments this plugin itself made (component 'enrol_adele',
     * itemid = instance id) are ever touched. A role a teacher assigned by
     * hand in the same course context carries a different component and is
     * left strictly alone, in both steps.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances and users repaired.
     */
    private static function sync_instance_roles(?progress_trace $trace = null): int {
        return self::sync_stale_instance_roles($trace) + self::repair_user_role_assignments($trace);
    }

    /**
     * Public entry point for the role migration.
     *
     * Used by the ad-hoc task queued when enrol_adele/roleid changes, so an
     * administrator does not have to wait for the nightly run to see a
     * deliberate configuration change take effect.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances and users repaired.
     */
    public static function sync_roles(?progress_trace $trace = null): int {
        if (!self::is_active()) {
            return 0;
        }
        return self::sync_instance_roles($trace);
    }

    /**
     * Step 1 — instances whose roleid no longer matches the configuration.
     *
     * Instances with roleid 0 are included: reconcile_user() falls back to
     * the configured role when enrolling them, so their participants do hold
     * the current role while the instance record claims nothing at all.
     * Leaving that unrepaired means the next configuration change cannot find
     * them either.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of instances migrated.
     */
    private static function sync_stale_instance_roles(?progress_trace $trace = null): int {
        global $DB;

        $currentroleid = instance_manager::get_role_id();
        if (!$currentroleid) {
            return 0;
        }
        $stale = $DB->get_records_select(
            'enrol',
            "enrol = 'adele' AND roleid <> :roleid",
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
                if ((int) $instance->roleid) {
                    role_unassign(
                        (int) $instance->roleid,
                        (int) $ue->userid,
                        $context->id,
                        'enrol_adele',
                        (int) $instance->id
                    );
                }
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
     * Step 2 — participants whose ADELE-owned role assignment is missing or
     * points at the wrong role.
     *
     * Runs after step 1, so every instance already carries the configured
     * role by the time this looks at users.
     *
     * @param progress_trace|null $trace Optional progress trace.
     * @return int Number of assignments repaired.
     */
    private static function repair_user_role_assignments(?progress_trace $trace = null): int {
        global $DB;

        $currentroleid = instance_manager::get_role_id();
        if (!$currentroleid) {
            return 0;
        }

        $repaired = 0;

        // Wrong role, but ours: unassign before re-adding, so a role changed
        // twice does not leave the intermediate one behind.
        $wrong = $DB->get_records_sql(
            "SELECT ra.id, ra.roleid, ra.userid, ra.contextid, ra.itemid
               FROM {role_assignments} ra
               JOIN {enrol} e ON e.id = ra.itemid
              WHERE ra.component = 'enrol_adele'
                    AND e.enrol = 'adele'
                    AND ra.roleid <> :roleid",
            ['roleid' => $currentroleid]
        );
        foreach ($wrong as $ra) {
            role_unassign(
                (int) $ra->roleid,
                (int) $ra->userid,
                (int) $ra->contextid,
                'enrol_adele',
                (int) $ra->itemid
            );
            $repaired++;
        }

        // Missing entirely: a participant of an ADELE instance with no
        // assignment of ours in that course context.
        $missing = $DB->get_recordset_sql(
            "SELECT ue.id, ue.userid, e.id AS enrolid, e.courseid
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.enrol = 'adele'
                    AND NOT EXISTS (
                        SELECT 1
                          FROM {role_assignments} ra
                          JOIN {context} ctx ON ctx.id = ra.contextid
                         WHERE ra.userid = ue.userid
                               AND ra.component = 'enrol_adele'
                               AND ra.itemid = e.id
                               AND ra.roleid = :roleid
                               AND ctx.contextlevel = :courselevel
                               AND ctx.instanceid = e.courseid
                    )",
            ['roleid' => $currentroleid, 'courselevel' => CONTEXT_COURSE]
        );
        foreach ($missing as $row) {
            $context = \context_course::instance((int) $row->courseid, IGNORE_MISSING);
            if (!$context) {
                continue;
            }
            role_assign($currentroleid, (int) $row->userid, $context->id, 'enrol_adele', (int) $row->enrolid);
            $repaired++;
        }
        $missing->close();

        if ($trace && $repaired) {
            $trace->output("  Repaired {$repaired} role assignment(s) on existing participants.");
        }
        return $repaired;
    }

    /**
     * Hard-remove one user from all ADELE enrolments of a learning path.
     *
     * Used when access through the embedding course is lost. The caller must
     * have removed or deactivated the user path first, otherwise the next
     * reconciliation run re-enrols immediately.
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
     * $mode lets the embedding scale back what "entitled" actually grants,
     * without the caller having to pre-compute a
     * Moodle enrolment status itself:
     * - MODE_VISIBLE (default): entitled => active, matches 0.1.2 behaviour.
     * - MODE_HIDDEN: entitled => an enrolment record still exists (countable
     *   in participant lists, reports, certificates) but stays suspended —
     *   never grants course access.
     * - MODE_NONE: this embedding never grants host-course access, however
     *   entitled the caller reports. Never creates a new instance for it; an
     *   instance left over from a PRIOR, more permissive mode is suspended,
     *   not deleted, so a later mode change back loses no history.
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
     * entirely must clear every one of them, not just the host course that
     * happened to trigger the removal. Mirrors purge_user().
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
     * Hard-remove everything a learning path ever created.
     *
     * Deletes every ADELE instance of the learning path through the plugin API,
     * which also removes the attached user_enrolments and role assignments.
     * Never deletes {enrol} rows directly — that would orphan user_enrolments.
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
