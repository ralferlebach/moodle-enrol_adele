### Abnahmeprotokoll – Issue #19 (Einschreibung in Lernpfad an beliebiger Stelle, „Fall 3")
- Ziel-Repository / Commit-SHA: mod_adele – Lieferung `2026100735` (0.7.1); Commit-SHA: **offen** (nach Übernahme)
- Original-AKs: 6 / 6 erfüllt
  1. `participantslist` bietet Option 3 – Formular-Label „for people enrolled in any course of the learning path" (`SOURCE_ANY`), über das Formular gewählt in E2E H2 (`host-course-participants.spec.ts`) und H5.
  2. Speichern abonniert und schreibt alle ein, die in irgendeinem Node-Kurs sind, Startknoten oder nicht – `any_node_source_test::test_source_3_on_save`.
  3. Mehrfach qualifizierende Person nur einmal – derselbe Test (Person in N2 und N3: eine Subscription, eine Host-Einschreibung); erneutes Speichern ändert nichts (`test_saving_twice_is_idempotent`).
  4. Fall 1 und Fall 2 unverändert – `test_source_1_unchanged`, `test_source_2_unchanged`, `test_all_sources_together`.
  5. PostgreSQL und MariaDB – grün auf beiden (siehe Prüfstand in `README.md`).
  6. Abhängigkeit auf `local_adele` in `version.php` – `$plugin->dependencies` im Gleichschritt (`2026100735`), geprüft von `sibling_api_test`/`version_dependencies_test`.
  - Weitere Testvorgaben: Personen ohne Node-Kurs bleiben unberührt (`none` in allen Fällen 0); der frühere `$courseid`-Blocker tritt nicht auf (Speichern ruft `subscribe_user_to_learning_path` ohne Kurs; alle Tests grün).
- Code: keine Änderung nötig (Option 3 bestand bereits); neu `mod/tests/any_node_source_test.php` (5 Tests)
- JS-Unit/Behat/Playwright: Playwright H2/H5 grün
- Negativkontrollen: Person nur im Gastkurs bei Quelle 3 → nicht abonniert; Person in keinem Kurs → nichts
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen – **nach** grünem CI-Lauf des finalen Commits
