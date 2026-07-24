# Changelog

All notable changes to `enrol_adele` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.9] — 2026-07-24

### Added — C.4 Restore-Hooks umgesetzt (Session 003, Teil 10)

- `restore_instance()`/`restore_user_enrolment()` in `enrol_adele_plugin`
  (`lib.php`) ergänzt — Skip-Strategie (Requirement A-13): ADELE-Instanzen
  und -Einschreibungen werden bei einer Kurswiederherstellung nie aus dem
  Backup übernommen, sondern von der nächsten Reconciliation aus dem
  aktuellen Lernpfad-Zustand neu abgeleitet (selbstheilend, F-6/L-Q-09).
- Vor der Umsetzung Moodles Restore-API gezielt recherchiert (nach den
  beiden Regressionen in Teil 9/8 bewusst nicht aus dem Gedächtnis
  geschrieben): gegen ein reales Plugin mit identischer Architektur
  (`can_add_instance()=false`, `enrol_programs`) sowie gegen Moodle-Core
  selbst (`enrol_manual_plugin::restore_user_enrolment()`) geprüft. Dabei
  einen eigenen Fehler im ersten Entwurf gefunden und korrigiert: der
  Methode fehlte der `$userid`-Parameter gegenüber der verifizierten
  Fünf-Parameter-Signatur.
- **Bewusst vereinfacht:** die in der Spezifikation vorgesehene
  „Same-Course-Ausnahme“ ist **nicht** umgesetzt — reiner,
  bedingungsloser Skip in jedem Fall. Die dafür nötige Erkennung bräuchte
  Restore-Task-API-Oberfläche, die in dieser Umgebung nicht mit
  ausreichender Sicherheit verifizierbar war. Unbedingter Skip ist in
  jedem Fall sicher — siehe Begründung im Codekommentar.
- Kein automatisierter Backup/Restore-Test — stattdessen eine manuelle
  Testanleitung in `docs/verification-live-testing-guide.md`
  (Testanleitung C) ergänzt.
- `php -l`: sauber.

## [Unreleased documentation changes]

### Fixed — echter Produktionsfehler durch G.19 behoben (Session 003, Teil 9)

- **`local_adele` (0.4.10, kein Versionsbump):** Ein zweiter echter CI-Lauf
  des Auftraggebers deckte eine von G.19 (Teil 7) verursachte Regression
  auf: `xmldb_local_adele_install()` läuft, bevor Moodle die eigenen
  Capabilities des Plugins aus `db/access.php` in `{capabilities}`
  registriert — `assign_capability()` validiert das und wirft einen
  `coding_exception`, den ein Frischinstallations-Lauf immer auslöste
  ("Capability 'local/adele:canmanage' was not found!"). Der
  ursprüngliche, vor G.19 vorhandene rohe Insert in `{role_capabilities}`
  umging genau dieses Ordnungsproblem absichtlich oder zufällig — jetzt
  wiederhergestellt als Rückfall, wenn die Capability noch nicht
  registriert ist; sobald sie es ist (z. B. bei einem späteren Upgrade),
  wird weiterhin die korrekte `assign_capability()`-API verwendet.
  `create_role()`/`set_role_contextlevels()` sind von diesem
  Ordnungsproblem nicht betroffen und bleiben unverändert auf der
  Moodle-API.
- `php -l`: sauber. Keine Moodle-Instanz für einen eigenen Installationstest
  verfügbar — Bestätigung steht beim Auftraggeber aus.

## [Unreleased documentation changes]

### Fixed — echter CI-Lauf, phpcs-Fund behoben (Session 003, Teil 8)

- Erster tatsächlicher Lauf gegen eine echte Moodle-Instanz (Moodle 4.5.12,
  PHP 8.2.30, MariaDB 10.11.14) durch den Auftraggeber bestätigt: `phpcs`
  meldete einen Fund in `manage.php` (fehlender Docblock für die Konstante
  `ADELE_MANAGE_ASYNC_THRESHOLD`) — behoben, `//`-Kommentar durch
  `/** ... */`-Docblock ersetzt. Kein Versionsbump (reine Stilkorrektur,
  keine funktionale Änderung).
- Bestätigt durch denselben Lauf: `phpcpd` clean, PHPDoc-Checker clean,
  **bestehende PHPUnit-Suite (`lib_test`, `reconciler_test`) grün — 9
  Tests, 46 Assertions** — trotz der umfangreichen Änderungen an
  `observer.php`/`reconciler.php`/`instance_manager.php` in Teil 7 keine
  Regression in den vorhandenen Tests. Erste echte Bestätigung seit Beginn
  dieser Sitzung, dass die gemachten Änderungen tatsächlich funktionieren,
  nicht nur `php -l`-sauber sind.

## [0.1.8] — 2026-07-24

### Added — G.2, G.4–G.7, G.11–G.19 umgesetzt (Session 003, Teil 7)

Auf Weisung des Auftraggebers zurückgeholt und noch in dieser Sitzung
umgesetzt, statt wie zunächst entschieden als Issues zurückgestellt. Nur
die Capability-Modell-Folgearbeit zu G.10 bleibt Backlog-Issue. Vor der
Umsetzung Verifikation per Websuche nachgeholt — dabei zwei eigene
Annahmefehler aus Teil 6 gefunden und korrigiert (`settings.php`-
Elternkategorie, Lock-API-Signatur).

- **`enrol_adele` 0.1.8:** `classes/observer.php` (G.4/G.11: `timeend`/
  `timestart`/`e.status`), `classes/local/instance_manager.php` (G.6: Lock
  in `ensure_instance()`), `classes/local/reconciler.php` (G.5: erweiterter
  `reconcile_all()` mit Recordset/Waisen-Bereinigung/Duplikat-
  Konsolidierung; G.14: Rollen-Sync), `settings.php` (Korrektur der
  Elternkategorie aus Teil 6).
- **`local_adele` 0.4.10:** `classes/learning_paths.php` (G.12:
  Transaktion), `lib.php` (G.15: Zugriffsprüfung), `classes/asset_handler.php`
  (G.16: Validierung + Datei-Leck-Bugfix), `db/install.xml`+`db/upgrade.php`
  (G.18: Unique-Index), `db/install.php` (G.19: Moodle-Rollen-APIs), neu:
  `Makefile` (G.7).
- **`mod_adele` 0.1.12:** `classes/observer.php` (G.4/G.11), `version.php`
  (G.2 Teilumsetzung: Abhängigkeit auf `enrol_adele` ergänzt), `view.php`
  (G.13: klare Meldung bei gelöschtem Lernpfad; G.17: Escaping), Sprach-
  dateien, neu: `Makefile` (G.7).
- `php -l` über alle drei vollständigen Plugin-Bäume: sauber. `install.xml`
  als wohlgeformtes XML geprüft. Weiterhin keine Moodle-Instanz verfügbar
  für PHPUnit/Behat oder echte Upgrade-/Lock-/Rollen-API-Tests.
- **Ab dieser Auslieferung: Patch-ZIPs** (nur geänderte/neue Dateien) statt
  vollständiger Plugin-Ordner, wie im Sessionstart-Prompt gefordert.

## [0.1.7] — 2026-07-24

### Added — C.2/C.3 umgesetzt, Runde 3 der Review-Verifikation (Session 003, Teil 6)

- **C.2** `manage.php`: Verwaltungsseite nach Pflichtenheft Abschnitt 6.
  Listet alle Lernpfade mit ADELE-Instanzen (inkl. „verwaist"-Markierung
  für Instanzen ohne zugehörigen Lernpfad-Datensatz), Spalten für
  Zielkurse/aktive/suspendierte Einschreibungen, Aktionen „Neu
  berechnen"/„Hart löschen" (Letztere mit Bestätigungsdialog). Ab 200
  betroffenen Nutzer/innen läuft die Aktion als Ad-hoc-Task statt
  synchron. Registriert unter Website-Administration → Plugins →
  Einschreibungsmethoden → Lernpfad-Einschreibung (`enrol/adele:config`).
- **C.3** Drei neue Events: `learning_path_reconciled`,
  `learning_path_purged`, `user_access_revoked` — ausgelöst aus
  `reconciler::reconcile_learning_path()`/`purge_learning_path()` sowie
  aus `observer::user_enrolment_deleted()`, wenn Regelwerk A-4
  tatsächlich greift.
- Zwei neue Ad-hoc-Task-Klassen für den Schwellwert-Fall.
- Sprachstrings (en/de) ergänzt, alphabetisch sortiert geprüft.
- `php -l` über das gesamte Plugin: sauber. **Nicht** gegen eine echte
  Moodle-Instanz getestet (keine in dieser Umgebung verfügbar) — siehe
  `docs/verification-live-testing-guide.md` für die dafür nötigen
  manuellen Schritte, insbesondere ob `manage.php` korrekt im Admin-Baum
  erscheint (Elternkategorie `enrolsettingsadele` aus Moodle-Konvention
  abgeleitet, nicht an einer Instanz bestätigt).
- **Review-Verifikation Runde 3:** Die verbliebenen elf Punkte aus Runde 2
  (P1-8 bis Abschnitt 7) statisch verifiziert — neun vollständig bestätigt
  (G.20–G.23, G.25), zwei brauchen echte Live-Testung (P1-9-Ausmaß,
  Abschnitt-9-Build-Funktionsfähigkeit) — Testanleitung dafür in
  `docs/verification-live-testing-guide.md`.

## [Unreleased documentation changes]

### Added — Phase G, externes Review abgeglichen, G-Q1a umgesetzt (Session 003, Teil 1)

- `docs/arbeitsplan.md`: Neue Phase G dokumentiert den Abgleich eines
  externen Code-Reviews (ChatGPT) gegen die tatsächliche Codebase aller
  drei Plugins. Sieben Befunde stichprobenartig verifiziert (sechs
  bestätigt, einer — P0-4/Suspendierung — als teilweise bereits bewusst
  entschiedenes Verhalten präzisiert statt unbesehen als Bug übernommen).
  Neue Punkte G.1–G.7 sowie Entscheidungsfrage G-Q1
  (Abhängigkeitsarchitektur `local_adele`/`enrol_adele`/`mod_adele`,
  Auftraggeber-Vorschlag gegen tatsächliche Codekopplung abgewogen).
- **G-Q1a entschieden und umgesetzt:** L-Q-08 aufgehoben. `docs/lastenheft.md`
  und `docs/pflichtenheft.md` entsprechend nachgezogen (Abschnitt 1.4,
  E-11-Nachtrag, Codebeispiel Abschnitt 7.3, Prüfkriterium 6).
- Kein Codeunterschied **in `enrol_adele` selbst** — die Entscheidung
  betrifft ausschließlich Aufrufer in `local_adele`/`mod_adele` (siehe
  „Verwandte Änderungen" unten). `enrol_adele`s eigener Reconciler war
  bereits korrekt und brauchte keine Anpassung.

### Verwandte Änderungen in anderen Repositories (G-Q1a)

- **`local_adele` 0.4.8:** `classes/enrol_state.php` um
  `warn_enrol_adele_missing()` ergänzt; `request_reconcile()`/
  `request_purge()` rufen sie statt eines stillen No-op auf.
  `classes/relation_update.php` (`enrol_user_into_node()`) und
  `classes/node_completion.php` (`enrol_child_courses()`): `enrol_manual`-
  Rückfallblöcke entfernt, `first_enrolled`/Boundary-Scheduling/
  Gruppenzuweisung bleiben unverändert.
- **`mod_adele` 0.1.11:** `classes/observer.php`
  (`sync_host_access_for_node_enrolment()`, `subscribe_user_course()`):
  dieselbe Härtung; dabei einen veralteten Docblock-Kommentar korrigiert
  (E-16 hatte die Sweep-Methoden bereits umgeroutet, ohne den Kommentar
  nachzuziehen).
- Beide mit `php -l` (PHP 8.3) einzeln und im Volllauf über das gesamte
  jeweilige Plugin geprüft: sauber. `moodle-cs`/PHPUnit konnten in dieser
  Umgebung nicht ausgeführt werden (keine Moodle-Instanz, kein `phpcs` mit
  Moodle-Standard installiert) — manuelle Stilprüfung (Zeilenlänge,
  Namenskonventionen) an den geänderten Stellen durchgeführt.

### Added — G.8–G.10 umgesetzt (Session 003, Teil 4)

- Kein Codeunterschied **in `enrol_adele` selbst**. Betrifft ausschließlich
  `local_adele` (siehe „Verwandte Änderungen" unten). Doku:
  `docs/arbeitsplan.md`, neuer Abschnitt „G.8–G.10 umgesetzt".

### Verwandte Änderungen in anderen Repositories (G.8–G.10)

- **`local_adele` 0.4.9:** Alle 25 `classes/external/*.php`-Klassen rufen
  jetzt `validate_parameters()` und `validate_context()` (G.8). Sieben
  fälschlich als `read` deklarierte schreibende Services in
  `db/services.php` auf `write` korrigiert (G.9); Capability-Deklarationen
  dort angepasst, wo eine einzelne speziellere Capability real geprüft
  wird (G.10, Teilumsetzung — `local/adele:edit`s Archetyp bewusst nicht
  verändert, Begründung im Arbeitsplan). Beim vollständigen Lesen aller 25
  Klassen drei echte IDOR-artige Bugs gefunden und behoben:
  `update_lp_animations.php` (G.3), `update_user_path_relation.php`
  (fehlende Eigentumsprüfung auf `lpuserpathid`) und `get_learningpath.php`
  (eine Berechtigungsprüfung, die nie greifen konnte, weil `local/adele
  :edit` an jede/n angemeldete/n Nutzer/in vergeben ist — jede/r konnte
  jeden Lernpfad per ID lesen).
- `php -l` über das gesamte Plugin nach allen Änderungen: sauber.

## [0.1.6] — 2026-07-23

### Added

- Requirement D.5: `db/install.php` and `db/upgrade.php` (step 2026072305)
  now adopt local_adele's legacy `enroll_as_setting` value as the starting
  value for `enrol_adele/roleid`, once, if the latter isn't already set —
  covers both a fresh install onto a site with local_adele already
  configured, and an existing enrol_adele install being upgraded. Keeps the
  effectively assigned role stable across the transition instead of it
  silently reverting to the student-archetype default. local_adele's
  `enroll_as_setting` is now documented there as deprecated (fallback-only,
  used when enrol_adele is absent).

## [0.1.5] — 2026-07-23

### Added

- `reconciler::reconcile_host_user()` gained a `$mode` parameter
  (`MODE_VISIBLE`/`MODE_HIDDEN`/`MODE_NONE`), letting a teacher scale back
  what Fall-2/3 host-course entitlement actually grants
  ([mod_adele #22](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/22),
  resolves pflichtenheft E-12): `MODE_VISIBLE` is the unchanged 0.1.2
  behaviour (active enrolment); `MODE_HIDDEN` still creates an enrolment
  record (countable in participant lists/reports) but keeps it suspended,
  never granting course access; `MODE_NONE` never creates a new instance for
  that embedding, and suspends — never deletes — one left over from an
  earlier, more permissive mode, so a later mode change back loses no
  history. `reconciler` stays purely mechanical; `mod_adele` computes and
  supplies the mode from its own new `hostenrolmentmode` setting.
- PHPUnit coverage for all three modes, including the "existing record from
  a prior mode gets suspended, not deleted" and "MODE_NONE never creates a
  first record" cases.

### Notes

- Companion release: mod_adele 0.1.7, which adds the `hostenrolmentmode`
  activity setting (`{adele}` schema change) driving this parameter.

## [0.1.4] — 2026-07-23

### Added

- `reconciler::purge_all_host_user()`: removes ALL of a user's host-course
  (Fall 2/3) enrolments for a learning path — the same learning path can be
  embedded in several host courses at once, and losing the learning path
  entirely (A-4) previously only cleared target-course enrolments, leaving
  host-course ones (`enrol_adele-issue-host-purge-on-leave`, now
  [mod_adele #21](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/21))
  active indefinitely. Wired into `observer::user_enrolment_deleted()`'s
  existing A-4 branch, right after the pre-existing `purge_user()` call.
  Resolves pflichtenheft E-10.
- PHPUnit coverage: leaving a learning path via the A-4 rule now clears a
  Fall-2/3 host-course enrolment in a SECOND host course, not just the one
  through which access was lost.

## [0.1.3] — 2026-07-23

### Fixed

- **Critical:** the plugin was never enabled by default. Moodle enrol plugins
  are site-disabled until explicitly added to `$CFG->enrol_plugins_enabled` —
  a manual admin step this plugin gave no indication was needed, since it has
  no teacher-facing "add instance" workflow (`can_add_instance()` is always
  `false`) that would otherwise surface the problem. With the plugin
  disabled, `reconciler::is_active()` silently short-circuited every call —
  no enrolments, no suspensions, no purges, ever, with no error anywhere.
  Surfaced by the first real CI run (`reconciler_test`, 3 of 4 tests failing
  with "Attempt to read property on bool" / "false is not false").
  `db/install.php` now auto-enables the plugin on fresh installs
  (pattern matches `enrol_coursecompleted`); a new upgrade step
  (2026072302) applies the same fix retroactively to already-installed sites.
- PHPUnit fixtures (`tests/reconciler_test.php`) now set a `type` on synthetic
  nodes. Without it, a test that exercises the real mod_adele → local_adele
  event cascade (`test_host_course_removal_rules`) crashed inside
  `local_adele\relation_update::subscribe_user_starting_node()` with
  "Undefined array key 'type'" — a real, pre-existing local_adele bug the
  test happened to expose, not a defect in this plugin.

### Notes

- Requires local_adele 0.4.6 (2026072302), which fixes the underlying
  `subscribe_user_starting_node()` bug this test surfaced (missing `??`
  fallback, inconsistent with the identical check a few lines above it).

## [0.1.2] — 2026-07-23

### Added

- Second, independent instance kind: HOST-course instances
  (`instance_manager::KIND_HOST`, discriminated from the existing
  KIND_TARGET via `customint2`) for `mod_adele` subscription options 2
  ("starting node") and 3 ("any node"), where host-course membership is a
  CONSEQUENCE of node-course membership rather than an independent grant.
- `reconciler::reconcile_host_user()`: mechanical enrol/reactivate/suspend for
  one host-course instance, driven by a caller-supplied boolean (only
  `mod_adele` knows the course → option → learning path mapping).
  `reconciler::purge_host_user()` as a building block for a future hard-removal
  trigger (not yet wired to anything automatic — see pflichtenheft E-10).
- PHPUnit coverage for the host-course lifecycle (enrol, suspend, reactivate,
  purge, non-collision with a target instance on the same course).

### Notes

- Requires local_adele 0.4.5 (2026072301), which completes the identity
  migration to `(user_id, learning_path_id)` and fixes a production upgrade
  blocker. Companion releases: local_adele 0.4.5, mod_adele 0.1.6.

## [0.1.1] — 2026-07-16

### Added

- `local\instance_manager`: lazy creation of the enrol instances, one per
  learning path × target course; role taken from `enrol_adele/roleid` with a
  one-way fallback to the legacy `local_adele/enroll_as_setting` (F-8).
- `local\reconciler`: stateless, idempotent reconciliation
  (`reconcile_user/learning_path/all`) — enrols where entitled, reactivates
  where suspended, suspends where no node grants the course any more — plus
  hard removal (`purge_user`, `purge_learning_path`, always through
  `delete_instance()`, never raw DB deletes).
- Observer for `\core\event\user_enrolment_deleted` implementing the
  host-course removal rules for subscription options 1/2/3 (requirement A-4),
  with recursion guard; ADELE's own enrolments never count as carrying. Per
  decision R-1 the user path record is deleted before purging.
- Nightly scheduled task `reconcile_task` as safety net, and `sync()` on the
  plugin class for CLI use.
- PHPUnit tests covering acceptance criteria 1–3.

### Notes

- Requires local_adele 0.4.3 (2026071600), which ships
  `\local_adele\enrol_state` — the single place that interprets the user path
  JSON. Companion releases: local_adele 0.4.3, mod_adele 0.1.5.

### Changed (Session 002, Teil 3)

- Display name shortened from "ADELE learning path enrolment" to "Learning
  path enrolment" (`pluginname`, `pluginname_desc`, `privacy:metadata`,
  `reconciletask`, both capability descriptions; en/de). The component
  identifier `enrol_adele` and the per-instance label `ADELE: {$a}` are
  unchanged. No functional change — no version bump.
- CI: all three plugins now share a `development` working branch, replacing
  the never-created `ralferlebach-enrol-plugin`. local_adele's CI additionally
  switched its mod_adele dependency from upstream `Wunderbyte-GmbH/master` to
  our own fork's `development` branch; mod_adele's CI gained a local_adele
  `extra_plugin_runner` it never had, despite `version.php` requiring it.


## [Unreleased documentation changes]

### local_adele 0.4.7 (Session 002, Teil 18) — production upgrade fix, no enrol_adele code change

- Fixed a real `dml_read_exception` hit during upgrade on the requester's
  own production instance, in `db/upgrade.php`'s ticket-#501 duplicate
  cleanup step (2026072200): `get_fieldset_sql()` referencing `course_id`
  failed to read. Root cause not confirmed with certainty (no raw DB error
  text available from the reported error page) — most likely explanation:
  an earlier interrupted upgrade left step 2024052300's savepoint recorded
  without the corresponding `course_id` field DDL having actually applied.
  Step 2026072200 now guarantees the column exists immediately before
  relying on it (same defensive pattern step 2024052300 already uses for
  the same field) — self-healing regardless of the exact history.


### mod_adele 0.1.10 (Session 002, Teil 17) — no enrol_adele code change beyond D.5

- Resolves pflichtenheft E-16: the one-time activity-save sweep
  (`enroll_starting_nodes_participants()`/`enroll_any_nodes_participants()`)
  previously called `subscribe_user_course()` with only its own embedding's
  mode, so a narrower sibling embedding saved after a more generous one
  could transiently downgrade access. Both sweep methods now route each
  swept user through `sync_host_access_for_node_enrolment()` — the same
  aggregation the live observer already used since 0.1.8 — instead of
  duplicating the logic. `subscribe_user_course()` remains available as
  public API but is no longer called internally by either sweep method.
- E-11 ("Message was not sent") root-caused, not a plugin bug: Moodle's own
  `enrol_manual`/`enrol_self` "send course welcome message" feature,
  triggered by an `enrol_manual` enrolment (the fallback path used when
  enrol_adele is absent, or a teacher's own manual enrolment), failing to
  deliver through the core messaging system on a demo instance with no
  configured message processor. Confirmed by grepping all three plugins for
  any messaging-related code — none exists. No code fix; resolvable only
  via Moodle site configuration (disable the welcome message, or configure
  a working message processor).

### mod_adele 0.1.9 (Session 002, Teil 16) — full codechecker cleanup, split CI matrix, no enrol_adele code change

- Fixed the lang-file ordering bug from 0.1.8: `hostenrolmentmode` strings
  were inserted in a plausible-looking but not strictly alphabetical spot in
  both `lang/en/adele.php` and `lang/de/adele.php` — Moodle's
  `LangFilesOrdering` sniff requires strict order, `mform_options_*` sorts
  entirely before `mform_select_*` (o < s), which the previous placement
  violated. Both files re-sorted programmatically, not by hand, to guarantee
  correctness.
- Auto-fixed (`phpcbf`) every remaining PSR12 finding across the whole
  plugin, including files never touched by this project before now
  (`index.php`, `view.php`, `classes/local_adele.php`,
  `classes/privacy/provider.php`, `classes/event/course_module_viewed.php`)
  — previously left alone as out-of-scope pre-existing debt, now cleaned up
  on explicit request. `moodle-plugin-ci codechecker --max-warnings=0` should
  be fully green.
- Removed a genuinely useless method override in
  `tests/generator/lib.php` (`create_instance()` did nothing but forward to
  the parent unchanged) — the one finding `phpcbf` could not fix
  automatically.
- CI workflow split into two jobs, matching local_adele/enrol_adele's own
  structure: `moodle500to502` (PHP 8.2, the modern range) and `moodle405`
  (PHP 8.1, the floor `version.php` still declares support for). The
  previous single-job setup only covered 500-502 and silently dropped 405
  from testing entirely, even though it's still a declared-supported
  version.


### mod_adele (Session 002, Teil 15) — no version bump, no enrol_adele code change

- Fixed a real, pre-existing `TypeError` in `adele_add_instance()`/
  `adele_update_instance()`: `implode()` on `participantslist` assumed an
  array always, but test fixtures (and possibly other paths) supply a plain
  string — a hard error since PHP 8.1, only surfaced once CI actually ran.
- Dropped Moodle 4.01–4.04 from `$plugin->supported` (now `[405, 502]`) and
  raised `$plugin->requires` to 4.5's version code accordingly; CI now
  explicitly tests MOODLE_500/501/502_STABLE instead of auto-detecting the
  full (previously 401–405) supported range, which is what pulled in
  MOODLE_404_STABLE and failed there (an extra_plugin_runner dependency
  needs newer core than 4.04 provides). Also fixed the same unquoted `on:`
  YAML footgun already fixed for `enrol_adele` in an earlier round.
- `phplint`'s "Not enough arguments (missing: 'plugin')" failure could not
  be fixed from mod_adele's own CI file — points to an issue in the shared
  `Wunderbyte-GmbH/catalyst-moodle-workflows` reusable workflow itself.
  Documented as a comment in the CI file with a ready, commented-out
  `disable_phplint: true` stopgap.


### mod_adele 0.1.8 (Session 002, Teil 14) — no enrol_adele code change

- Resolves pflichtenheft E-13 / [mod_adele #23](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/23):
  several embeddings of the same learning path targeting the same host
  course now aggregate to one deterministic decision ("most generous
  option wins") before calling `reconcile_host_user()`, instead of one call
  per embedding overwriting the last. Uses the existing `$mode` API from
  0.1.5 unchanged — no change to `enrol_adele` itself. Details in
  pflichtenheft section 1a and in mod_adele's own version history.


### Fixed — dependency (Session 002, Teil 11)

- `version.php` demanded local_adele ≥ 2026072302 (0.4.6) — stricter than
  actually required. The only change between 0.4.5 and 0.4.6 was an
  unrelated defensive fix inside local_adele's own
  `subscribe_user_starting_node()` (a `?? ''` fallback, Teil 8); nothing
  enrol_adele itself calls or depends on. The genuine functional minimum is
  0.4.5 (2026072301) — the release that completed the identity migration
  (`user_id + learning_path_id`, no `course_id`) enrol_adele's reconciler
  and `local_adele\enrol_state` depend on. Relaxed accordingly, matching
  what mod_adele already declares. On sites where local_adele is below even
  0.4.5, this does not by itself make enrol_adele work — it only removes an
  unnecessary block; local_adele still needs upgrading to at least 0.4.5 for
  the plugins to function together correctly.
- No functional change to enrol_adele's own code, no version bump (as
  requested) — `$plugin->version`/`$plugin->release` unchanged at
  2026072302/0.1.3.


### Fixed — tests (Session 002, Teil 10)

- `test_host_course_removal_rules` failed against real Moodle/MariaDB CI
  (`Failed asserting that false is not false`) after the 0.1.3 fixes landed.
  Root cause: the mod_adele observer reacts to the test's own
  `enrol_user()` call and re-subscribes via local_adele, which
  synchronously runs its real completion/restriction recompute pipeline —
  overwriting the fixture's manually planted 'accessible' status with
  whatever it derives for a node that carries no real condition data
  (typically "not yet accessible"), before the test's own
  `reconcile_user()` call ever reads it. Not a defect in enrol_adele or in
  local_adele — a test-isolation gap masked earlier by the "Undefined
  array key 'type'" crash (0.1.3) that used to interrupt the cascade
  before it could overwrite anything. Fixed by re-asserting the intended
  node status right before reconciling. No functional change, no version
  bump (test-only).


### Fixed — tooling (Session 002, Teil 9)

- `make check` did not exist — the old Makefile only had `checks` (plural),
  so `make check` failed with "No rule to make target". Makefile replaced
  wholesale with the auto-detecting-paths template used across the
  project's other plugins (adapted from a `mod_elang` original), keeping
  `zip`/`clean`/`link`/`unlink` as an enrol_adele-specific addition since the
  template itself has no packaging targets. No functional plugin change, no
  version bump.


### Added — issue drafts (Session 002, Teil 7)

- Vier Issue-Entwürfe zu Lücken im Host-Kurs-Verhalten (Fall 2/3), aus
  Rückfragen zum bestehenden Host-Kurs-Mechanismus abgeleitet:
  `enrol_adele-issue-host-purge-on-leave.md` (E-10 — Host-Kurs-Einschreibungen
  werden nicht mit ausgetragen, wenn ein Nutzer den Lernpfad verlässt),
  `enrol_adele-issue-host-visibility.md` (E-12 — Lehrkräfte können den
  Host-Kurs-Zugang bei Fall 2/3 nicht konfigurieren), `enrol_adele-issue-host-
  priority.md` (E-13 — konkurrierende Embeddings desselben Lernpfads im
  selben Host-Kurs überschreiben sich nicht-deterministisch), sowie eine
  ausführliche Neufassung von ticket #486
  (`local_adele-issue486-ausfuehrlich.md`). Alle unter `docs/issues/`.
  Arbeitsplan Phase F.


### Changed — repo hygiene (Session 002, Teil 6)

- `.gitignore`/`.gitattributes` standardised across all three plugins
  (`enrol_adele`, `local_adele`, `mod_adele`) from a project-wide template.
  `local_adele`'s upstream 0.4.4/0.4.5 base shipped with neither file — both
  restored. No functional change, no version bump.
- Issue draft for mod_adele's live option-2/3 trigger + host-course-via-
  enrol_adele change archived under `docs/issues/` for traceability.


### Changed — documentation (Session 002, Teil 1)

- Architecture pivoted from a persisted grants table to a **stateless,
  idempotent reconciliation** (decision F-6): the intended state is derived
  from `local_adele`'s user paths on every run; the plugin ships no tables of
  its own, permanently. Traceability comes from Moodle's standard logging.
  Rationale: none of the four reference enrol plugins materialises reasons,
  and the Vue editor reuses node ids of deleted nodes, which would silently
  corrupt persisted node references.
- `docs/lastenheft.md` and `docs/pflichtenheft.md` rewritten as version 2.0
  (requirements A-1…A-15, answers F-1…F-10); `docs/arbeitsplan.md` added.
- Plugin code unchanged — still a stub that only installs.

### Planned — 0.2.0

- Instance management (`ensure_instance()`, lazy creation per learning path ×
  target course) and the `reconciler` (`reconcile_user/learning_path/all`,
  `purge_user/learning_path`), plus a scheduled reconciliation task.
  Fully functional but with no callers yet — installable without effect.

### Planned — 0.3.0

- Observer for `user_enrolment_deleted` in host courses implementing the
  carried-by rules for subscription options 1/2/3.
- Admin manage page (per learning path: recalculate, hard delete), custom
  events for bulk operations, backup/restore hooks (skip strategy).

### Planned — 0.4.0

- `local_adele` and `mod_adele` switched over: corrected user-path identity
  (no host course), `enrol_state::get_entitled_courseids()`, reconciler calls,
  deletion lifecycle, `enroll_as_setting` deprecation; `mod_adele` gains
  option 3 ("any node") and the participantslist comparison fix.
- No migration of legacy `enrol_manual` data (decision F-9: left untouched).

## [0.1.0] - 2026-07-16

Initial stub. Installs; does nothing else. No enrolment is created or removed.

### Added

- `enrol_adele_plugin` class establishing the ownership rules: roles are
  protected, and instances cannot be added, managed or unenrolled by hand,
  because the learning path state is the only authority over these enrolments.
- Instance identity fixed as `enrol = 'adele'`, `courseid = target course`,
  `customint1 = learning path id`, exposed as
  `enrol_adele_plugin::FIELD_LEARNINGPATHID`.
- Capabilities `enrol/adele:config` (manager) and `enrol/adele:unenrol`
  (deliberately granted to nobody by default).
- Setting `enrol_adele/roleid` for the role assigned in target courses.
- Language packs `en` and `de`.
- Privacy `null_provider` — accurate while the plugin stores no data of its own.
- Hard dependency on `local_adele`; the reverse direction stays undeclared and
  optional to avoid a dependency cycle.
- Moodle Plugin CI across Moodle 4.1–4.5 and 5.0–5.1, code checker at zero
  warnings.
- Makefile for local development, and the specification under `docs/`.

### Notes

- No database table on purpose. Open questions in `docs/pflichtenheft.md`
  section 7 — above all whether node ids are stable across saves — must be
  settled before a schema is frozen. An enrolment plugin without its own table
  is the normal case; `enrol_guest` has none either.

[Unreleased]: https://github.com/ralferlebach/moodle-enrol_adele/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/ralferlebach/moodle-enrol_adele/releases/tag/v0.1.0
