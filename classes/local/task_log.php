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
 * Outcome log for background repairs.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\local;

/**
 * What became of the repairs that ran in the background.
 *
 * An ad-hoc task disappears from {task_adhoc} the moment it succeeds, so the
 * queue alone can only ever answer "what is still pending". An administrator
 * who queued a repair and comes back later sees an empty queue and cannot
 * tell whether it ran, what it changed, or whether it failed and was retried
 * away — which is why issue #6 asks for the outcome, not just the state.
 *
 * Kept as a bounded list in plugin config rather than a table of its own. A
 * rolling window of the last few dozen repairs is operational feedback, not
 * data anyone reports on, and it does not justify a schema, an upgrade step
 * and a privacy provider entry. The cap is what keeps that defensible: the
 * value can never grow beyond it, however often repairs run.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_log {
    /** @var string Plugin config key holding the rolling list. */
    const CONFIG_KEY = 'taskoutcomes';

    /** @var int How many outcomes are kept. */
    const KEEP = 25;

    /**
     * Record the outcome of one background repair.
     *
     * Never throws: a failure to write operational feedback must not turn a
     * successful repair into a failed task.
     *
     * @param string $action Short action key, e.g. 'reconcile' or 'purge'.
     * @param int $learningpathid The learning path the repair ran for.
     * @param int $affected How many records the repair touched.
     * @param string $outcome 'succeeded' or 'failed'.
     * @param string $message Optional detail, e.g. an exception message.
     * @return void
     */
    public static function record(
        string $action,
        int $learningpathid,
        int $affected,
        string $outcome = 'succeeded',
        string $message = ''
    ): void {
        try {
            $entries = self::all();
            array_unshift($entries, [
                'action' => $action,
                'learningpathid' => $learningpathid,
                'affected' => $affected,
                'outcome' => $outcome,
                'message' => \core_text::substr($message, 0, 200),
                'timefinished' => time(),
            ]);
            set_config(self::CONFIG_KEY, json_encode(array_slice($entries, 0, self::KEEP)), 'enrol_adele');
        } catch (\Throwable $e) {
            debugging('enrol_adele: could not record task outcome: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The recorded outcomes, newest first.
     *
     * @return array List of outcome entries; empty when nothing has run yet.
     */
    public static function all(): array {
        $raw = get_config('enrol_adele', self::CONFIG_KEY);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
