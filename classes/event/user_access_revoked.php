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
 * A user lost their learning path access.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_adele\event;

/**
 * Triggered from observer::user_enrolment_deleted() when leaving an
 * embedding course removes a user's learning path membership because no
 * other subscription option still carries them. Deliberately NOT triggered
 * for the routine per-node suspend/reactivate cycle, which is already
 * visible via core \core\event\user_enrolment_updated events; this event
 * marks the harder, whole-path removal case specifically.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_access_revoked extends \core\event\base {
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
        return get_string('event_user_access_revoked', 'enrol_adele');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        return get_string(
            'event_user_access_revoked_description',
            'enrol_adele',
            [
                'userid' => $this->relateduserid ?? 'unknown',
                'learningpathid' => $this->other['learningpathid'] ?? 'unknown',
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
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
