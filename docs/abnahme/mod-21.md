### Abnahmeprotokoll – Issue #21 (Austragung automatischer Einschreibungen beim Verlassen des Lernpfades, auch Fall 2/3)
- Ziel-Repository / Commit-SHA: enrol_adele (+ mod_adele Tests) – Lieferung `2026100735`; Commit-SHA: **offen**
- Original-AKs: 4 / 4 erfüllt
  1. Verlassen nach A-4 trägt alle Fall-2/3-Host-Einschreibungen aus, nicht nur Ziel-Einschreibungen – seit 2026-10-17 zweistufig (mit #20 abgestimmt, vom Nutzer bestätigt): sofort **suspendiert** (`reconciler::suspend_all_host_user()`), nach bestätigtem Austritt (`remove_user_path_adhoc`, 300 s) **ausgetragen** (`remove_uncarried_user()` → `purge_all_host_user()`). `reconciler_test::test_leaving_learning_path_purges_every_host_course` (beide Stufen), `node_enrolment_after_save_test::test_durable_departure_removes_the_suspended_host_enrolment`.
  2. Mehrere Host-Kurse: alle betroffenen bereinigt – derselbe enrol-Test (Host 2 über eine andere Einbettung: erst suspendiert, dann ausgetragen).
  3. Weiterhin getragene Person behält alles – `reconciler_test::test_host_course_removal_rules`, `node_enrolment_after_save_test::test_source_3_any_node_last_membership_suspends` (eine von zwei Mitgliedschaften endet → aktiv), `participant_list_cycles_test`.
  4. PostgreSQL und MariaDB – grün auf beiden.
- Code: `enrol/classes/observer.php` (suspendiert statt löscht), `enrol/classes/local/reconciler.php` (`suspend_all_host_user()`), `enrol/classes/task/remove_user_path_adhoc.php` (Doku)
- Playwright: H5 (Karenzzeit über die Testuhr: erst `adele:1`, danach keine Einschreibung, kein Abo)
- Negativkontrollen: Rückkehr innerhalb der Karenzzeit → dieselbe Einschreibung aktiv, nach der Karenzzeit nicht ausgetragen (PHPUnit und H5). Mutationsprobe: sofortiges Löschen statt Suspendieren → PHPUnit 2 rot, H5 rot.
- Dokumentation: `docs/pflichtenheft.md` 1a („Hard-Removal …, geändert 2026-10-17"), 2.5
- Verbleibende Abweichungen: keine. Hinweis: In der Karenzzeit erscheint die Person in der Teilnehmerliste des Gastkurses als suspendiert (vgl. enrol_adele #7).
- Entscheidung: [ ] offen lassen  [x] schließen – **nach** grünem CI-Lauf des finalen Commits
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Austritt über A-4, zwei Host-Kurse, mehrere Einbettungen, unabhängige Fremdeinschreibung: `reconciler_test`, `node_enrolment_after_save_test::test_durable_departure_removes_the_suspended_host_enrolment`, `participant_list_cycles_test`; E2E H5.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
