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
 * All ADELE enrolments and instances a learning path owns were hard-removed.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\event;

/**
 * Triggered by "Hard delete" on the management page (C.2), the equivalent
 * ad-hoc task, or local_adele's own learning-path deletion flow (via
 * enrol_state::request_purge()), per specification 7.3.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_path_purged extends \core\event\base {
    /**
     * Init parameters.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_learning_path_purged', 'enrol_adele');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        return get_string(
            'event_learning_path_purged_description',
            'enrol_adele',
            [
                'learningpathid' => $this->other['learningpathid'] ?? 'unknown',
                'removed' => $this->other['removed'] ?? '0',
            ]
        );
    }

    /**
     * Get url.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/enrol/adele/manage.php');
    }

    /**
     * Custom validation.
     *
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['learningpathid'])) {
            throw new \coding_exception('The \'learningpathid\' value must be set in other.');
        }
    }
}
