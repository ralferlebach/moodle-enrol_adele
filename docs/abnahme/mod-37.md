### Abnahmeprotokoll – Issue #37 (ADELE-eigene Einschreibungen nicht als Teilnehmerquelle werten)
- Ziel-Repository / Branch / Commit-SHA: mod_adele, local_adele, enrol_adele – Lieferung `2026100734`; Commit-SHA: **offen**
- Fachliche Entscheidung: ADELE-generierte Einschreibungen (Ziel-, Gast-, Lehrenden-Instanzen) zählen für keine der Quellen 1–3 und nicht für A-4 als unabhängige Quelle (Pflichtenheft 1d). Zwei unabhängige Sicherungen: Methode `enrol <> 'adele'` und Rolle mit `component = 'enrol_adele'` macht niemanden zum Teilnehmenden.
- Original-AKs: 3 / 3 erfüllt
  1. Testinstanz folgt der Regel, PHPUnit mit zwei Pfaden, die einen Kurs teilen – `adele_source_shared_node_test::test_an_adele_enrolment_in_the_shared_course_carries_nothing_onto_path_b` (live, Speichern mit allen drei Durchläufen, Gastkursabgleich); dazu `participant_source_test` (geteilter Gastkurs, Quelle 1).
  2. Abo und Austritt (A-4) folgen derselben Regel – `test_leaving_uses_the_same_rule`, `test_leaving_the_node_course_of_source_2_leaves_path_b`. **Befund behoben:** A-4 wurde beim Verlassen eines Quelle-2/3-Knotenkurses nicht ausgewertet (Abo und ADELE-Zieleinschreibungen blieben); `enrol_adele\observer::left_course()` prüft jetzt auch diese Pfade.
  3. E2E-Kette H, negative Fälle Quelle 2 und 3 – `adele-source-shared-node.spec.ts` (Story H4, `@withdrawal`): live und nach nächtlichem Abgleich nicht auf dem Pfad/Kurs zu; Gegenprobe eigene Einschreibung; Rücknahme.
- Code: `local/classes/enrol_state.php`, `enrol/classes/observer.php` (`left_course`, `paths_with_node_source`, `is_user_carried`), `mod/classes/local/host_policy.php`
- PHPUnit: `adele_source_shared_node_test` (4), `participant_source_test` (3) – PostgreSQL und MariaDB grün
- Playwright: `adele-source-shared-node.spec.ts` H4 grün (PostgreSQL); H2-Kette (#37 Quelle 1) grün
- Negativkontrollen: nur ADELE-Einschreibung im geteilten Kurs; Speichern; Abgleich; Austritt; Gegenprobe eigene aktive Einschreibung (qualifiziert). Mutationsproben: neuer A-4-Auslöser entfernt → rot; beide Sicherungen entfernt → 2 Tests rot (eine allein reicht).
- Upgrade/Restore/Privacy: n. a. (keine Datenänderung)
- Dokumentation: Pflichtenheft 1d, 4; Protokoll Teil 68
- Verbleibende Abweichungen: CI-Runs/MySQL **offen**
- Entscheidung: [x] schließen nach grünem CI-Lauf des finalen Commits
