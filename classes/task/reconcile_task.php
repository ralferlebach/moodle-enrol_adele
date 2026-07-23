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
 * Nightly reconciliation task.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;

/**
 * Nightly reconciliation task.
 *
 * Safety net against missed events: reconciles every active user path against
 * the actual enrolments. Every operation is idempotent, so running this at any
 * time is always safe.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reconcile_task extends \core\task\scheduled_task {
    /**
     * Task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('reconciletask', 'enrol_adele');
    }

    /**
     * Run the reconciliation.
     *
     * @return void
     */
    public function execute(): void {
        $trace = new \text_progress_trace();
        reconciler::reconcile_all($trace);
        $trace->finished();
    }
}
