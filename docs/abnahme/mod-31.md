### Abnahmeprotokoll – Issue #31 (`mod_adele/index.php` reparieren)
- Ziel-Repository / Commit-SHA: mod_adele – Lieferung `2026100734`; Commit-SHA: **offen**
- Original-AKs: 3 / 3 erfüllt (dazu alle Punkte der Lösung)
  1. Keine undefinierten Variablen/fehlenden Strings – Kurskontext statt `$modulecontext`; neue Ereignisklasse `course_module_instance_list_viewed` (fehlte); String `noadeleinstances` en/de.
  2. Leere und befüllte Übersicht – Behat `mod_adele_index.feature`: leer → Weiterleitung zum Kurs mit Hinweis (statt `notice()`); befüllt als Lehrperson und als Lernende → je Aktivität eine Zeile mit Abschnitt und Name; Fremde → keine Liste.
  3. Developer-Debugging sauber – Behat läuft mit DEBUG_DEVELOPER und bricht bei jeder Meldung ab: 4/4 grün.
  - Tabellenköpfe/Ausrichtung: „Abschnitt"/„Name" für Formate mit Abschnitten, sonst „Name"; Ausrichtung je Spalte.
  - PHPUnit für das Ereignis: `index_event_test`.
- Probe: alte `index.php` gegen die Feature → „Undefined variable $modulecontext".
- PostgreSQL / MariaDB: PHPUnit beide grün; Behat PostgreSQL; CI **offen**
- Verbleibende Abweichungen: keine
- Entscheidung: [x] schließen nach grünem CI-Lauf
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Leere und gefüllte Kursübersicht unter Developer-Debugging: Behat `@mod_adele_index` (Szenarien siehe Prüfstand), `index_event_test`.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
