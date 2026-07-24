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
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'enrol_adele';
$plugin->release = '0.1.10';
$plugin->version = 2026072309;
$plugin->requires = 2022112800;
$plugin->maturity = MATURITY_ALPHA;
$plugin->supported = [401, 501];
// Fix G.2 full solution (Session 003): raised from 2026072301 because
// observer.php now calls enrol_state::get_host_embeddings()/
// get_learningpaths_embedded_in_course(), which only exist from this
// local_adele version onward. Installing this alongside an older
// local_adele would fatal on those calls.
$plugin->dependencies = [
    'local_adele' => 2026072404,
];
