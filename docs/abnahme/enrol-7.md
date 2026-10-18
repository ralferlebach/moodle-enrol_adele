### Abnahmeprotokoll – enrol_adele #7 (Entfernte Kursteilnehmende bleiben in der Teilnehmerliste sichtbar)
- Ziel-Repository / Commit-SHA: enrol_adele, mod_adele – Lieferung `2026100735`; Commit-SHA: **offen**
- Das Issue hat keine formale AK-Liste; abgenommen gegen die erwartete Wirkung und die Prüfaufträge des Kommentars:
  1. Liste bleibt nach wiederholtem Ein-/Austragen leer – `participant_list_cycles_test::test_repeated_cycles_leave_nobody_behind`: drei Personen, drei Runden über einen Node-Kurs (Quelle 3); nach jeder Karenzzeit ist **niemand** mehr in der Teilnehmerliste des Gastkurses (weder aktiv noch suspendiert), nie ein zweiter Datensatz je Person.
  2. Entfernte Personen sind kein Ballast mehr – in der Karenzzeit (300 s) als „suspendiert" gelistet (#20), danach entfernt (#21).
  3. Löschen einer Einbettung entfernt ihre automatischen Einschreibungen – `participant_list_cycles_test::test_deleting_the_embedding_takes_its_enrolments`, `withdrawn_settings_test::test_deleted_embedding_is_cleanly_gone`, E2E `withdrawn-settings.spec.ts` (H1, Löschen und Papierkorb).
  4. Unberechtigte Einschreibungen ausgeschlossen bzw. Bedingungen benannt – ausgeschlossen: ADELE-Einschreibungen als Quelle (#37, `adele_source_shared_node_test`, E2E H4), ausgesetzte Fremdeinschreibungen als Quelle (#30). Bewusst sichtbar bleibende Datensätze (kein Fehler): verborgener Modus (`hidden`), suspendierte Zieleinschreibung eines noch aktiven Lernenden bei geschlossenem Knoten (wird beim Öffnen reaktiviert), die Karenzzeit nach einem Austritt; nach Pfadaustritt entfernt die Aufbewahrungsfrist auch suspendierte Reste.
- Code: Änderung über #20/#21 (Suspendieren und späteres Austragen); neu `mod/tests/participant_list_cycles_test.php` (2 Tests)
- PostgreSQL / MariaDB: grün auf beiden
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen – **nach** grünem CI-Lauf des finalen Commits
