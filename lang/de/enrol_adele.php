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
 * Language strings for enrol_adele.
 *
 * @package     enrol_adele
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adele:config'] = 'Lernpfad-Einschreibeinstanzen konfigurieren';
$string['adele:unenrol'] = 'Nutzer/innen über die Lernpfad-Einschreibung aus einem Kurs austragen';
$string['defaultrole_desc'] = 'Die Rolle, die ADELE zuweist, wenn ein/e Nutzer/in in einen Zielkurs eines Lernpfads eingeschrieben wird.';
$string['instancename'] = 'ADELE: {$a}';
$string['instancenamehost'] = 'ADELE (Lernpfadzugang): {$a}';
$string['pluginname'] = 'Lernpfad-Einschreibung';
$string['pluginname_desc'] = 'Diese Einschreibemethode ist Eigentümerin aller Kurseinschreibungen, die ein ADELE-Lernpfad in seinen Zielkursen erzeugt. Eine Einschreibeinstanz gehört zu genau einem Lernpfad und genau einem Zielkurs. Dadurch bleiben die Einschreibungen verschiedener Lernpfade getrennt, und die selbst erzeugten Einschreibungen können zurückgenommen werden, ohne manuelle, Selbst- oder Cohort-Einschreibungen anzutasten.';
$string['privacy:metadata'] = 'Das Plugin „Lernpfad-Einschreibung“ speichert keine personenbezogenen Daten. Die erzeugten Einschreibungen werden vom Einschreibesystem des Moodle-Kerns gespeichert.';
$string['reconciletask'] = 'Lernpfad-Einschreibungen abgleichen';
