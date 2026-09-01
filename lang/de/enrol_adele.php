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
 * @copyright   2026 Wunderbyte GmbH
 * @copyright   2026 Ralf Erlebach
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['adele:config'] = 'Lernpfad-Einschreibeinstanzen konfigurieren';
$string['adele:unenrol'] = 'Nutzer/innen über die Lernpfad-Einschreibung aus einem Kurs austragen';
$string['defaultrole_desc'] = 'Die Rolle, die ADELE zuweist, wenn ein/e Nutzer/in in einen Zielkurs eines Lernpfads eingeschrieben wird.';
$string['event_learning_path_purged'] = 'Lernpfad-Einschreibungen bereinigt';
$string['event_learning_path_purged_description'] = 'Alle ADELE-Einschreibeinstanzen des Lernpfads {$a->learningpathid} wurden restlos entfernt ({$a->removed} Instanz(en)).';
$string['event_learning_path_reconciled'] = 'Lernpfad-Einschreibungen neu berechnet';
$string['event_learning_path_reconciled_description'] = 'Die ADELE-Einschreibungen des Lernpfads {$a->learningpathid} wurden neu berechnet ({$a->affected} Nutzer/in(nen) betroffen).';
$string['event_user_access_revoked'] = 'Lernpfadzugang entzogen';
$string['event_user_access_revoked_description'] = 'Nutzer/in {$a->userid} hat den Zugang zum Lernpfad {$a->learningpathid} verloren, weil keine verbleibende Einschreibeoption die Mitgliedschaft weiterträgt.';
$string['instancename'] = 'ADELE: {$a}';
$string['instancenamehost'] = 'ADELE (Lernpfadzugang): {$a}';
$string['manage_action_purge'] = 'Hart löschen';
$string['manage_action_queued'] = 'Als Hintergrund-Task eingeplant ({$a} betroffene Nutzer/innen) — die Seite blieb dadurch reaktionsfähig statt auf einen großen Lauf zu warten.';
$string['manage_action_reconcile'] = 'Neu berechnen';
$string['manage_col_actions'] = 'Aktionen';
$string['manage_col_active'] = 'Aktiv';
$string['manage_col_course'] = 'Kurs';
$string['manage_col_learningpath'] = 'Lernpfad';
$string['manage_col_suspended'] = 'Suspendiert';
$string['manage_col_type'] = 'Typ';
$string['manage_confirm_purge'] = 'Dies entfernt dauerhaft jede ADELE-Einschreibeinstanz und Einschreibung, die dieser Lernpfad erzeugt hat, für alle Nutzer/innen. Das kann nicht rückgängig gemacht werden und wird auch vom nächsten Reconcile-Lauf nicht wiederhergestellt. Fortfahren?';
$string['manage_filter_all'] = 'Alle';
$string['manage_filter_apply'] = 'Filter anwenden';
$string['manage_filter_status'] = 'Einschreibestatus';
$string['manage_heading'] = 'Verwaltung der Lernpfad-Einschreibung';
$string['manage_instance_disabled'] = 'deaktiviert';
$string['manage_intro'] = 'Alle ADELE-Einschreibeinstanzen, eine Zeile je Lernpfad und Kurs. Über die Filter lässt sich eine bestimmte finden; die Aktion in der Zeile gleicht den zugehörigen Lernpfad auf Anforderung ab — der nächtliche Task tut das ohnehin für alle. Das vollständige Entfernen dessen, was ein Lernpfad erzeugt hat, wird erst angeboten, wenn die Liste auf einen einzelnen Lernpfad eingegrenzt ist.';
$string['manage_no_paths'] = 'Aktuell besitzt kein Lernpfad eine ADELE-Einschreibeinstanz.';
$string['manage_orphaned'] = 'Verwaist (Lernpfad existiert nicht mehr)';
$string['manage_purge_done'] = '{$a} Einschreibeinstanz(en) entfernt.';
$string['manage_reconcile_done'] = 'Für {$a} Nutzer/in(nen) neu berechnet.';
$string['manage_report_count'] = 'Betroffene Datensätze';
$string['manage_report_duplicates'] = 'Doppelte Instanzen zusammengeführt';
$string['manage_report_expired'] = 'Einschreibungen nach Ablauf der Aufbewahrungsfrist entfernt';
$string['manage_report_heading'] = 'Letzter vollständiger Abgleich';
$string['manage_report_host'] = 'Host-Kurse abgeglichen';
$string['manage_report_never'] = 'Der geplante Abgleich ist noch nicht gelaufen. Seine Ergebnisse erscheinen danach hier.';
$string['manage_report_orphaned'] = 'Verwaiste Instanzen entfernt';
$string['manage_report_pass'] = 'Durchgang';
$string['manage_report_roles'] = 'Rollenzuweisungen repariert';
$string['manage_report_targetactual'] = 'Zielkurse, Ist zu Soll';
$string['manage_report_targetwanted'] = 'Zielkurse, Soll zu Ist';
$string['manage_report_uncarried'] = 'Nicht mehr getragene Abonnements zur Entfernung eingereiht';
$string['manage_report_when'] = 'Letzter Lauf: {$a}';
$string['manage_status_active'] = 'Aktiv';
$string['manage_status_suspended'] = 'Suspendiert';
$string['manage_tasks_action'] = 'Reparatur';
$string['manage_tasks_finished'] = 'Beendet';
$string['manage_tasks_heading'] = 'Reparaturen im Hintergrund';
$string['manage_tasks_nextrun'] = 'Nächster Versuch';
$string['manage_tasks_none'] = 'Zurzeit sind keine Reparaturen eingereiht.';
$string['manage_tasks_outcome'] = 'Ergebnis';
$string['manage_tasks_outcome_failed'] = 'Fehlgeschlagen';
$string['manage_tasks_outcome_succeeded'] = 'Erfolgreich';
$string['manage_tasks_recent'] = 'Die zuletzt abgeschlossenen Reparaturen. Eine Task verschwindet nach dem Lauf aus der Warteschlange; hier ist ihr Ergebnis als einziges festgehalten.';
$string['manage_tasks_state'] = 'Status';
$string['manage_tasks_state_queued'] = 'Eingereiht';
$string['manage_tasks_state_retrying'] = 'Fehlgeschlagen, wird erneut versucht';
$string['manage_tasks_state_running'] = 'Läuft';
$string['manage_type_host'] = 'Host-Kurs';
$string['manage_type_target'] = 'Zielkurs';
$string['pluginname'] = 'Lernpfad-Einschreibung';
$string['pluginname_desc'] = 'Diese Einschreibemethode ist Eigentümerin aller Kurseinschreibungen, die ein ADELE-Lernpfad in seinen Zielkursen erzeugt. Eine Einschreibeinstanz gehört zu genau einem Lernpfad und genau einem Zielkurs. Dadurch bleiben die Einschreibungen verschiedener Lernpfade getrennt, und die selbst erzeugten Einschreibungen können zurückgenommen werden, ohne manuelle, Selbst- oder Cohort-Einschreibungen anzutasten.';
$string['privacy:metadata'] = 'Das Plugin „Lernpfad-Einschreibung“ speichert keine personenbezogenen Daten. Die erzeugten Einschreibungen werden vom Einschreibesystem des Moodle-Kerns gespeichert.';
$string['reconciletask'] = 'Lernpfad-Einschreibungen abgleichen';
$string['suspendedretention'] = 'Suspendierte Einschreibungen entfernen nach';
$string['suspendedretention_desc'] = 'Anzahl Tage, die eine Einschreibung suspendiert bleiben darf, bevor dieses Plugin sie endgültig entfernt. Suspendierte Teilnehmende bleiben in der Teilnehmerliste sichtbar und lassen sich nicht von Hand entfernen, da dieses Plugin Eigentümerin seiner Einschreibungen ist; diese Einstellung verhindert, dass die Liste unbegrenzt wächst. 0 bedeutet: suspendierte Einschreibungen bleiben dauerhaft erhalten.';
