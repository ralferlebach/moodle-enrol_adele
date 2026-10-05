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
 * Plugin version and other meta-data are defined here.
 *
 * @package     enrol_adele
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'enrol_adele';
$plugin->release = '0.5.0';
$plugin->version = 2026100503;
// Moodle 4.5 (2024100700) is the lowest supported version; 4.4 and older
// are no longer supported. The trio could not be installed below 4.5 anyway,
// because mod_adele requires 2024100700.
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_ALPHA;
$plugin->supported = [405, 502];
// Requires a local_adele version that provides enrol_state::
// get_host_entitlement(), get_host_embeddings() and
// get_learningpaths_with_host_embeddings(), which the host sweep calls.
$plugin->dependencies = [
    'local_adele' => 2026082902,
];
