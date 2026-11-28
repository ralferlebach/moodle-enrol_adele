### Abnahmeprotokoll – Issue #23 (Konkurrierende Fall-2/3-Einbettungen desselben Lernpfads im selben Host-Kurs)
- Ziel-Repository / Commit-SHA: mod_adele – Lieferung `2026100735`; Commit-SHA: **offen**
- Original-AKs: 4 / 4 erfüllt
  1. Deterministisches Ergebnis unabhängig von der Reihenfolge – `host_priority_order_test::test_decision` (Datenprovider: jede Paarung in beiden Einfügereihenfolgen: Quelle 2 ohne Anspruch + Quelle 3 hidden → hidden; hidden + visible → visible; none + visible → visible), `test_live_join_and_cycle` (Live-Pfad, beide Reihenfolgen, zwei Ein-/Austrittsrunden → jedes Mal dasselbe Ergebnis).
  2. Regel „großzügigste Option gewinnt" in Code und Pflichtenheft – `host_policy::get_affected_pairs()`/`mode_rank()`; `docs/pflichtenheft.md` 1a „Priorisierung bei Mehrfacheinbettung".
  3. Einzelne Einbettung wie bisher – Fälle „single embedding, entitled/not entitled".
  4. PostgreSQL und MariaDB – grün auf beiden.
  - DoD: genau eine Entscheidung je (Pfad, Gastkurs) – im Test geprüft (kein redundanter `reconcile_host_user()`-Aufruf je Paar); verschiedene Gastkurse getrennt entschieden (Fall „two host courses").
- Code: keine Änderung nötig; neu `mod/tests/host_priority_order_test.php` (10 Fälle); bestehend `host_enrolment_priority_test`
- Mutationsprobe: Modus „zuletzt verarbeitet gewinnt" → 2 Fälle rot
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen – **nach** grünem CI-Lauf des finalen Commits
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - **Neu** alle Kombinationen und Reihenfolgen: `host_priority_order_test::test_most_generous_wins_in_every_combination_and_order` – 1–3 berechtigende Einbettungen mit allen drei Modi in jeder Einfügereihenfolge (39 Folgen) und eine nicht berechtigende Einbettung jedes Modus an jeder Stelle: über 300 Fälle, live (`get_affected_pairs`) und Abgleich (`get_entitlement`) gleich, stets „großzügigster Modus der berechtigenden Einbettungen“ (visible > hidden > none). Pflichtenheft 1a beschreibt die Regel.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
