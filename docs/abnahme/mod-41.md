### Abnahmeprotokoll – Issue #41 (Sicherung und Duplikat verlieren den Host-Einschreibemodus)
- Ziel-Repository / Commit-SHA: mod_adele – behoben in `2026100731`, ergänzt in `2026100734`; Commit-SHA: **offen**
- Original-AKs: 3 / 3 erfüllt
  1. Feld in der Sicherungsliste – `backup/moodle2/backup_adele_stepslib.php`.
  2. Duplikat mit `none`/`hidden` behält den Wert – `backup_hostenrolmentmode_test::test_a_duplicate_keeps_the_mode` (zusätzlich `visible`).
  3. Ältere Sicherung ohne Feld → `visible` – `test_an_import_keeps_the_mode_an_older_backup_gives_the_default`.
- Zusatz (Master P0.2): Restore in anderen Kurs (`restore_policy_test`), neben bestehender Einbettung mit `none` → keine ADELE-Methode, niemand eingeschrieben (`test_none_restored_beside_an_existing_embedding_enrols_nobody`); mit Nutzerdaten `hidden` → nur Berechtigte, ausgesetzt (`restore_policy_test::test_enrolments_are_derived_not_copied`).
- Vor der Behebung beide Original-Tests rot (gemessen: `visible` statt `none`).
- PostgreSQL / MariaDB: grün; CI **offen**
- Entscheidung: [x] schließen nach grünem CI-Lauf
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Feld im Backup und Restore aller drei Modi belegt (`backup_hostenrolmentmode_test`, neu `test_each_mode_restored_with_users_gives_its_enrolments`).
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
