### Abnahmeprotokoll – Issue #22 (Ein-/Ausschreibungen im Fall 2 & 3 konfigurierbar machen)
- Ziel-Repository / Commit-SHA: mod_adele, enrol_adele – Lieferung `2026100735`; Commit-SHA: **offen**
- Original-AKs: 6 / 6 erfüllt
  1. Wahl je Einbettung: sichtbar / verborgen (suspendiert) / keine – Formularfeld „Enrolment in this course" (E2E H3, `host-course-participants.spec.ts`; `withdrawn-settings.spec.ts`).
  2. `hidden` erzeugt eine nachvollziehbare Einschreibung ohne Zugang – `host_enrolment_mode_test::test_each_mode_on_a_new_embedding` (Datensatz suspendiert, in `get_enrolled_users()` enthalten, kein Zugang); E2E H3 („listed as suspended").
  3. `none` legt keine Host-Instanz an – derselbe Test (`{enrol}` ohne ADELE-Instanz; Abo dennoch vorhanden); E2E H3.
  4. Moduswechsel verliert keine Daten – `host_enrolment_mode_test::test_changing_the_mode_keeps_the_record`: visible → hidden → none → visible über `update_moduleinfo()` (den Speicherweg des Formulars), **derselbe** `user_enrolments`-Datensatz durchgehend, Zugang nur bei visible. Präzisierung durch die Entscheidung vom 2026-10-13: eine wegen `none` suspendierte Einschreibung wird nach der Aufbewahrungsfrist entfernt (`withdrawn_settings_test::test_none_is_suspended_then_removed_hidden_is_kept`).
  5. Bestand unverändert (`visible`) – Schema-Standard `visible` (`test_without_a_mode_it_is_visible`), leerer Wert verhält sich wie `visible`; der Upgrade-Schritt 2026072301 legt das Feld `NOT NULL` mit Standard `visible` an, bestehende Zeilen erhalten dabei `visible` (die Datenbank füllt den Standard ein; kein eigenes UPDATE).
  6. PostgreSQL und MariaDB – grün auf beiden.
- Code: keine Änderung nötig; neu `mod/tests/host_enrolment_mode_test.php` (3 Tests)
- Weitere: `settings_propagation_test::test_host_enrolment_mode_matrix`, `reconciler_test::test_reconcile_host_user_visibility_modes`
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen – **nach** grünem CI-Lauf des finalen Commits
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Wechsel aller Modi, Bestands-Default, Erhalt suspendierter Datensätze, `none` ohne neuen Grant: `host_enrolment_mode_test` (3); **neu** Restore mit Nutzerdaten je Modus: `restore_policy_test::test_each_mode_restored_with_users_gives_its_enrolments` (visible → aktiv, hidden → suspendiert, none → keine).
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
