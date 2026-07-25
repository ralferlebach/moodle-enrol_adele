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
 * Background "Recompute" for a single learning path.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;

/**
 * Queued by manage.php instead of running synchronously, when the
 * number of affected users exceeds the responsiveness threshold. The
 * operation itself is identical to (and idempotent with) the
 * synchronous path — only the trigger differs.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_learning_path_adhoc extends \core\task\adhoc_task {
    /**
     * Run the recompute for the learning path in custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $learningpathid = (int) ($data->learningpathid ?? 0);
        if (!$learningpathid) {
            return;
        }
        reconciler::reconcile_learning_path($learningpathid);
    }
}
