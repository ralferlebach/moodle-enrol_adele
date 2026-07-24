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
 * ADELE learning path enrolment plugin.
 *
 * This plugin owns every course enrolment that an ADELE learning path creates in a
 * target course. One enrolment instance is scoped to exactly one pair of
 * learning path and target course:
 *
 *     enrol      = 'adele'
 *     courseid   = target course id
 *     customint1 = local_adele_learning_paths.id
 *
 * Neither the mod_adele instance nor the host course that embeds the learning
 * path is part of this identity. See docs/pflichtenheft.md.
 *
 * Since 0.1.1 the plugin carries the stateless reconciler that creates,
 * reactivates, suspends and removes these enrolments (classes/local/).
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * ADELE learning path enrolment plugin class.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_adele_plugin extends enrol_plugin {
    /**
     * Name of the enrol instance field that carries the learning path id.
     */
    const FIELD_LEARNINGPATHID = 'customint1';

    /**
     * Roles are assigned and revoked by ADELE, so they must not be edited by hand.
     *
     * @return bool
     */
    public function roles_protected() {
        return true;
    }

    /**
     * Manual un-enrolment is not allowed: the learning path state is the only
     * authority over these enrolments and would immediately restore them.
     *
     * @param stdClass $instance The enrol instance.
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return false;
    }

    /**
     * Manual editing of single user enrolments is not allowed, for the same reason
     * as manual un-enrolment.
     *
     * @param stdClass $instance The enrol instance.
     * @return bool
     */
    public function allow_manage(stdClass $instance) {
        return false;
    }

    /**
     * Instances are created by the learning path lifecycle, never by a teacher in
     * the "Enrolment methods" page.
     *
     * @param int $courseid The course id.
     * @return bool
     */
    public function can_add_instance($courseid) {
        return false;
    }

    /**
     * This plugin does not use the standard add/edit instance form.
     *
     * @return bool
     */
    public function use_standard_editing_ui() {
        return false;
    }

    /**
     * Whether the current user may delete an instance.
     *
     * Deleting is normally done by the learning path lifecycle. It stays available
     * to holders of enrol/adele:config as a manual repair option.
     *
     * @param stdClass $instance The enrol instance.
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/adele:config', $context);
    }

    /**
     * Whether the current user may enable or disable an instance.
     *
     * @param stdClass $instance The enrol instance.
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/adele:config', $context);
    }

    /**
     * Human readable instance name, shown on the course participants page.
     *
     * @param stdClass $instance The enrol instance.
     * @return string
     */
    public function get_instance_name($instance) {
        if (empty($instance->name)) {
            return get_string('pluginname', 'enrol_adele');
        }
        return format_string(
            $instance->name,
            true,
            ['context' => context_course::instance($instance->courseid)]
        );
    }

    /**
     * Reconcile every active user path against the actual enrolments.
     *
     * Entry point for CLI and upgrade scripts; the scheduled task and the
     * recompute hook in local_adele use the reconciler directly.
     *
     * @param progress_trace $trace Progress trace.
     * @return int 0 on success (Moodle sync convention).
     */
    public function sync(progress_trace $trace): int {
        \enrol_adele\local\reconciler::reconcile_all($trace);
        return 0;
    }

    /**
     * Skip restoring ADELE enrol instances from a course backup (C.4,
     * requirement A-13).
     *
     * Every instance is derived state: it exists only because a learning
     * path currently grants access, and is owned entirely by the
     * reconciler (decision A-9 — this plugin keeps no table of its own).
     * Restoring a stale instance from a backup — into a duplicated course,
     * a different course, or even the same course after some other change
     * happened in between — would reintroduce exactly the "enrolment
     * without provenance" problem enrol_adele exists to solve, and Moodle's
     * own fallback for non-restorable enrolments (converting them to
     * enrol_manual) would be worse still. The reconciler recreates
     * whatever the current learning path state actually calls for on its
     * own (nightly task at the latest, per decision F-6/L-Q-09 —
     * idempotent and self-healing), without needing this data at all.
     *
     * Verified against enrol_programs (a real-world plugin with the same
     * can_add_instance()=false / lazily-managed-instances shape as this
     * one), which uses the identical unconditional-skip pattern. A
     * same-course exception was in the original specification (docs/
     * pflichtenheft.md, requirement A-13) but is deliberately NOT
     * implemented here: detecting "restoring into the same course it came
     * from" needs restore_controller/task API surface (backup::TARGET_*,
     * original_course_id) that could not be verified with confidence in
     * this environment (no live Moodle instance) — after two real
     * regressions this session from acting on unverified Moodle API
     * assumptions, guessing at a third felt like the wrong tradeoff against
     * unconditional skip, which is safe in every case (worst case: a
     * bounded gap until the next reconciliation catches up, exactly the
     * self-healing behaviour the architecture already relies on).
     *
     * @param restore_enrolments_structure_step $step Restore step.
     * @param stdClass $data Instance data from the backup file.
     * @param stdClass $course The (possibly new) course being restored into.
     * @param int $oldid The instance id in the original, backed-up course.
     * @return void
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid): void {
        return;
    }

    /**
     * Skip restoring individual ADELE user enrolments from a course backup
     * (C.4, requirement A-13).
     *
     * Never reached in practice, since restore_instance() above never
     * creates an instance for a restored enrolment to attach to — kept as
     * an explicit no-op regardless, matching the specification and making
     * the intent explicit rather than relying on that implicit consequence.
     *
     * @param restore_enrolments_structure_step $step Restore step.
     * @param stdClass $data User enrolment data from the backup file.
     * @param stdClass $instance The (already restored-or-skipped) enrol instance.
     * @param int $userid The (already-mapped, new) user id.
     * @param int $oldinstancestatus The user's enrolment status in the original course.
     * @return void
     */
    public function restore_user_enrolment(
        restore_enrolments_structure_step $step,
        $data,
        $instance,
        $userid,
        $oldinstancestatus
    ): void {
        return;
    }
}
