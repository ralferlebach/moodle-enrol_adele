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
 * Background re-derivation of host-course access for one embedding.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;

/**
 * Queued by mod_adele whenever an activity that embeds a learning path is
 * created, saved or deleted.
 *
 * Saving an activity changes who is entitled to the host course — a
 * deselected subscription option, a narrowed visibility mode, or the removal
 * of the activity altogether all revoke access that used to be justified.
 * None of that reached existing participants before: the live observers only
 * fire on enrolment events, so a settled cohort would have waited for the
 * next nightly sweep, and a deleted activity was never cleaned up at all.
 *
 * Queued rather than run inline because a popular host course can hold
 * thousands of enrolments and saving a form must not block on them. The
 * operation is idempotent, so a duplicate queue entry costs nothing.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_host_embedding_adhoc extends \core\task\adhoc_task {
    /**
     * Re-derive host access for the (learning path, host course) pair in
     * custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $learningpathid = (int) ($data->learningpathid ?? 0);
        $hostcourseid = (int) ($data->hostcourseid ?? 0);
        if (!$learningpathid || !$hostcourseid) {
            return;
        }
        reconciler::reconcile_host_embedding($learningpathid, $hostcourseid);
    }
}
