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
 * Background migration of ADELE role assignments after a settings change.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\task;

use enrol_adele\local\reconciler;

/**
 * Queued when an administrator changes enrol_adele/roleid.
 *
 * The nightly reconcile would migrate the role too, but waiting up to a day
 * for a deliberate configuration change to take effect makes the setting look
 * broken — the complaint behind issue #4. Queued rather than run inline
 * because the migration touches every ADELE participant on the site, which a
 * settings form must not block on. Idempotent, so it costs nothing when the
 * nightly run gets there first.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class migrate_roles_adhoc extends \core\task\adhoc_task {
    /**
     * Migrate every ADELE role assignment onto the configured role.
     *
     * @return void
     */
    public function execute(): void {
        reconciler::sync_roles();
    }
}
