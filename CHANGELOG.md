# Changelog

All notable changes to `enrol_adele` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and
the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
