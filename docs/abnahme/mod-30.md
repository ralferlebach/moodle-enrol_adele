### Abnahmeprotokoll – Issue #30 (Suspendierte Fremdeinschreibungen nicht als wirksamen ADELE-Zugang behandeln)
- Ziel-Repository / Branch / Commit-SHA: mod_adele, local_adele, enrol_adele – Lieferung `2026100734` (0.7.1); Commit-SHA: **offen** (nach Übernahme)
- Original-AKs: 3 / 3 erfüllt (dazu alle 5 Punkte der „Lösung")
  1. Suspendierte Einschreibungen gewähren keinen Zugang – `effective_source_test::test_suspended_carries_nothing_reactivation_grants_idempotently` (kein Abo, kein Gastkurs), `test_this_course_source_follows_the_same_rule`.
  2. Reaktivierung stellt idempotent wieder her – derselbe Test (reaktiviert → Abo + Zugang; zweimal → eine Subscription, dieselbe `user_enrolments`-ID).
  3. Eine Definition in allen Komponenten – `local_adele\enrol_state::effective_enrolment_sql()` / `get_effectively_enrolled_courseids()`; genutzt von mod `host_policy` (Quellen, Kandidaten), local `enrollment` (Abo, Rollenvergabe), `get_nonparticipant_userids`, enrol `observer::has_foreign_enrolment` (A-4). Pflichtenheft Abschnitt 1d.
  - Lösung: `ue.status = ENROL_USER_ACTIVE` geprüft; `user_enrolment_updated` beobachtet (`mod_adele_observer::user_enrolment_updated`); Einstellung `mod_adele/sourcesincludesuspended` (Standard aus).
- Code: `local/classes/enrol_state.php`, `local/classes/enrollment.php`, `mod/classes/local/host_policy.php`, `mod/classes/observer.php`, `mod/db/events.php`, `mod/settings.php`, `enrol/classes/observer.php`
- PHPUnit: `mod_adele\effective_source_test` (5 Tests, 19 Assertions) – grün auf PostgreSQL 16 und MariaDB 10.11; Suiten local 413 / enrol 72 / mod 89 grün auf beiden
- JS-Unit/Behat/Playwright: n. a. für JS; Playwright-Gesamtlauf (alle Ketten H1–H4 mit Rücknahmen) grün auf PostgreSQL
- PostgreSQL / MariaDB / weitere DBs: lokal beide grün; MySQL und CI-Runs für den finalen Commit: **offen**
- Negativkontrollen: ausgesetzt; Start in der Zukunft; abgelaufen; deaktivierte Methode; Quelle 1 ausgesetzt; Positivprobe zweite aktive Quelle (bleibt), dann beide ausgesetzt (weg). Mutationsproben: Statusfilter entfernt → 3 Tests rot; `user_enrolment_updated` nicht beobachtet → 3 Tests rot.
- Upgrade/Restore/Privacy: Verhalten bestehender Installationen ändert sich bewusst (ausgesetzt trägt nicht mehr); per Einstellung umkehrbar; keine Datenänderung
- Dokumentation/Pflichtenheft: `docs/pflichtenheft.md` 1d, 4; Protokoll Session 006 Teil 68
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen (alle AKs/DoDs belegt) – **nach** grünem CI-Lauf des finalen Commits
