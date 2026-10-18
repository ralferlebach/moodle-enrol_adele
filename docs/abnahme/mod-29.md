### Abnahmeprotokoll – Issue #29 (Backup und Restore vollständig und sicher)
- Ziel-Repository / Commit-SHA: mod_adele (+ enrol_adele `restore_instance`) – Lieferung `2026100734`; Commit-SHA: **offen**
- Original-AKs: 3 / 3 erfüllt
  1. Alle Aktivitätsfelder – `restore_policy_test::test_every_field_and_the_intro_files_come_back` (jedes Feld außer id/course/timemodified gleich). **Befund behoben:** Dateien der Beschreibung wurden nie wiederhergestellt (`after_execute()` holt sie jetzt).
  2. Keine zufällige numerische Verknüpfung – fremde Website oder gelöschter Pfad → `learningpathid = 0` mit Protokollwarnung, Seite zeigt „noch kein Lernpfad gewählt" (`test_from_another_site_the_activity_has_no_path`, `test_a_deleted_path_is_not_restored`).
  3. Host-Index und Einschreibungen passen zum Zielsystem – `after_restore()` leitet ab wie beim Anlegen; ADELE-Einschreibungen und Lernpfad-Datensätze werden nie kopiert, auch nicht mit Nutzerdaten (`test_enrolments_are_derived_not_copied`; `enrol_adele` überspringt Instanzen/Nutzereinschreibungen beim Restore).
  - Lösung: Restore-Politik dokumentiert (Pflichtenheft 5.2a): gleicher Kurs/anderer Kurs/andere Website; Pfad-ID zugeordnet oder kontrolliert unkonfiguriert.
- Proben: Website-Prüfung entfernt → rot; Dateien nicht wiederhergestellt → rot.
- Privacy/Datenisolation: keine Daten fremder Nutzer kommen über die Sicherung in ADELE-Tabellen; Abos entstehen nur aus Berechtigungen der Zielwebsite.
- PostgreSQL / MariaDB: grün; CI **offen**
- Entscheidung: [x] schließen nach grünem CI-Lauf
