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
 * Background "Hard delete" for a single learning path.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;
use enrol_adele\local\task_log;

/**
 * Queued by manage.php instead of running synchronously, when the
 * number of affected users exceeds the responsiveness threshold. The
 * confirmation step itself already happened synchronously in manage.php
 * before this task was queued.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_learning_path_adhoc extends \core\task\adhoc_task {
    /**
     * Run the hard delete for the learning path in custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $learningpathid = (int) ($data->learningpathid ?? 0);
        if (!$learningpathid) {
            return;
        }
        // The outcome is recorded either way. A task that succeeds vanishes
        // from the queue, and a task that fails is retried and then vanishes
        // too, so without this an administrator can never find out what
        // happened to a repair they queued (issue #6).
        try {
            $affected = reconciler::purge_learning_path($learningpathid);
            task_log::record('purge', $learningpathid, (int) $affected);
        } catch (\Throwable $e) {
            task_log::record('purge', $learningpathid, 0, 'failed', $e->getMessage());
            throw $e;
        }
    }
}
