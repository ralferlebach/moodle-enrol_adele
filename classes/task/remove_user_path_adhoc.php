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
 * Deferred removal of a user's learning path record.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;
use enrol_adele\observer;

/**
 * Deletes a user's local_adele_path_user record — but only once the reason
 * for it has proved durable.
 *
 * That row is the ONLY copy of the user's learning history: node progress,
 * completion state, manual overrides set by teachers, and the first_enrolled
 * timestamp that timed windows hang off. Deleting it the instant the last
 * carrying enrolment disappears is right for a genuine departure and
 * catastrophic for a blip — and the observer cannot tell the two apart at the
 * moment the event fires. A cohort resync, an accidental removal undone
 * seconds later, a bulk tool rebuilding memberships: each of those fires
 * user_enrolment_deleted and used to destroy months of progress, after which
 * re-enrolment handed the user a fresh, empty snapshot.
 *
 * Waiting settles it without touching the data model at all. When this task
 * runs it re-asks the same question the observer asked; if the user is
 * carried again by then, the deletion simply does not happen and the snapshot
 * was never in danger. If they are still not carried, the departure was real
 * and the row goes.
 *
 * Access revocation is deliberately NOT deferred: the observer purges the
 * enrolments immediately, because withdrawing access is correct and wanted
 * the moment entitlement ends. Only the destruction of history waits.
 *
 * That split leaves one gap this task has to close itself. For the length of
 * the wait the user path record is still active, so any recompute happening
 * in that window — a course completion, a node update, the nightly sweep —
 * legitimately re-enrols the user the observer just purged. If the departure
 * then turns out to be real, those re-created enrolments would survive the
 * deletion of the record and only be caught by the NEXT sweep, and then only
 * as a suspension. So whenever this task does delete the record, it purges
 * again. Both purges are idempotent; the second one is usually a no-op.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_user_path_adhoc extends \core\task\adhoc_task {
    /**
     * How long to wait before the removal is considered durable.
     *
     * Deliberately a documented constant rather than a setting: a value that
     * can be tuned per site is a value that will be set to zero somewhere,
     * which restores exactly the data loss this task exists to prevent. Five
     * minutes comfortably covers the resync and bulk-import windows observed
     * in practice while keeping the residual record short-lived.
     */
    const DELAY_SECONDS = 300;

    /**
     * Remove the user path record, unless the user is carried again.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $learningpathid = (int) ($data->learningpathid ?? 0);
        $userid = (int) ($data->userid ?? 0);
        if (!$learningpathid || !$userid) {
            return;
        }

        if (!$DB->record_exists('local_adele_learning_paths', ['id' => $learningpathid])) {
            // The whole learning path went away in the meantime; its own
            // purge path owns the cleanup, and there is nothing left to keep
            // this record consistent with.
            $DB->delete_records(
                'local_adele_path_user',
                ['learning_path_id' => $learningpathid, 'user_id' => $userid]
            );
            return;
        }

        if (observer::is_user_carried($learningpathid, $userid)) {
            // The removal did not stick — a resync, a corrected mistake, a
            // rebuilt membership. The snapshot stays and the user picks up
            // where they left off; access is restored by the reconciler,
            // which the re-enrolment event has already triggered.
            return;
        }

        $DB->delete_records(
            'local_adele_path_user',
            ['learning_path_id' => $learningpathid, 'user_id' => $userid]
        );

        // Close the window described above: anything re-enrolled while the
        // record was still active goes now.
        reconciler::purge_user($learningpathid, $userid);
        reconciler::purge_all_host_user($learningpathid, $userid);
    }
}
