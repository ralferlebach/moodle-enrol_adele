# Ungenutzte Sprachzeichenketten — ADELE-Ökosystem (Session 004)

**Stand:** 2026-07-25 · **Methode:** Token-genauer Volltext-Scan je Plugin
über `*.php`, `*.mustache`, `*.js`, `*.ts`, `*.vue`, `*.feature`, `*.html`
(ohne `lang/`, `amd/build/`, `node_modules/`, `.git/`, `docs/`). Ein
Schlüssel gilt als „ohne Referenz", wenn sein exakter Name nirgends als
ganzes Token vorkommt.

> **Wichtiger Vorbehalt (gilt v. a. für `local_adele`):** Das Vue-3-Frontend
> lädt Sprachzeichenketten gesammelt über `core_get_component_strings` und
> greift teils **dynamisch** darauf zu (`store.state.strings.<KEY>`, teils
> mit zusammengesetzten Schlüsseln wie `'nodes_feedback_' + status`). Ein
> statischer Scan kann solche dynamischen Zugriffe nicht auflösen. Die
> nachstehenden Einträge sind daher **Kandidaten**, keine bestätigt toten
> Zeichenketten — vor dem Entfernen jeweils im Frontend gegenprüfen. Es
> wurde in dieser Session **nichts entfernt** (Konvention: ungenutzte
> Strings nur listen).

---

## enrol_adele

**0 Kandidaten.** Alle 30 Sprachzeichenketten werden referenziert;
EN/DE-Parität vollständig.

---

## mod_adele

Roh-Scan: 7 ohne Referenz. Nach Abzug der Framework-Konventionen bleiben
**3 echte Kandidaten**:

**Echte Kandidaten (prüfen):**
- `adele:addlearningpath` — Capability-Zeichenkette ohne zugehörigen
  Capability-Eintrag in `db/access.php` (verwaiste Capability).
- `mod/adele:seelearningpath` — **fehlerhaft geformter Schlüssel**
  (führendes `mod/` gehört nicht in den String-Key; korrekt wäre
  `adele:seelearningpath`). Aktuell weder als Capability registriert noch
  referenziert.
- `adelesettings` — mutmaßliches Duplikat von `adelefieldset`; nicht
  referenziert.

**Framework-Konvention — NICHT entfernen (implizit genutzt):**
- `pluginname`, `pluginadministration` — von Moodle-Core geladen.
- `adelename_help`, `mform_select_hostenrolmentmode_help` — Hilfe-Texte,
  automatisch an die gleichnamige Basiszeichenkette per
  `addHelpButton()` gekoppelt.

---

## local_adele

Roh-Scan: 76 ohne Referenz. Aufgeschlüsselt:

### Echte Kandidaten (statisch ohne Referenz) — 72 Stück

Vor Entfernen **im Vue-Frontend gegenprüfen** (dynamischer String-Zugriff!).
Auffällig ist, dass viele davon UI-nahe Zeichenketten sind, die das Frontend
sehr wahrscheinlich dynamisch auflöst (z. B. Farbnamen `BLACK`/`DIM_GRAY`,
`nodes_*`, `modals_*`, `flowchart_*`, `completion_*_feedback`). Die drei in
dieser Session neu ergänzten EN-Schlüssel
`nodes_feedback_to_access` / `_completed` / `_completion` erscheinen hier
ebenfalls — sie werden im Frontend über einen **zusammengesetzten**
Schlüssel angesprochen und sind daher nur scheinbar ohne Referenz.

- `BLACK`
- `DIM_GRAY`
- `RUSTY_RED`
- `VERY_DARK_GRAY`
- `btnbacktooverview`
- `btnsave`
- `btnupdate_positions`
- `cannotdeleteembedded`
- `charthelper_no_description`
- `completion_completion_inbetween_feedback`
- `completion_dates_duration_feedback`
- `completion_end_date_feedback`
- `completion_first_subscription_feedback`
- `completion_start_date_feedback`
- `conditions_catquiz_warning_description`
- `conditions_catquiz_warning_name`
- `course_description_before_condition_course_completed_kurse`
- `course_description_placeholder_checkbox_status`
- `course_restricition_before_condition_to`
- `courselevel`
- `courselevel_desc`
- `description_save_error`
- `flowchart_cancel`
- `flowchart_hover_click_here`
- `flowchart_hover_drag_drop`
- `fromlearningdescriptionplaceholder`
- `fromlearningtitelplaceholder`
- `learningpath_description`
- `learningpath_name`
- `learningpaths_edit_no_learningpaths`
- `learningpaths_edit_site_description`
- `learningpaths_edit_site_name`
- `main_delete`
- `main_duplicate`
- `main_editors`
- `mobile_view_detail_course_link`
- `mobile_view_detail_estimate`
- `modals_how_to_learningpath`
- `modals_next`
- `modals_previous`
- `modulenameplural`
- `node_access_accessible`
- `node_coursefullname`
- `node_courseshortname`
- `node_restriction_before_timed`
- `node_restriction_inbetween_timed`
- `nodes_completion`
- `nodes_course_node`
- `nodes_courses`
- `nodes_edit`
- `nodes_edit_completion`
- `nodes_feedback_completion_after`
- `nodes_feedback_completion_inbetween`
- `nodes_feedback_restriction_before`
- `nodes_feedback_to_access`
- `nodes_feedback_to_completed`
- `nodes_feedback_to_completion`
- `nodes_hide_completion`
- `nodes_items_restrictions`
- `nodes_no_completion_defined`
- `nodes_no_restriction_defined`
- `nodes_potential_start`
- `nodes_progress`
- `nodes_show_completion`
- `nodes_table_checkmark`
- `nodes_table_key`
- `overviewaddingbtn`
- `subject`
- `toclipboard`
- `toclipboarddone`
- `uploadanduseimage`
- `user_view_username`

### Hilfe-Zeichenketten mit genutzter Basis — NICHT entfernen (1)

Automatisch an ihre (referenzierte) Basiszeichenkette gekoppelt:

- `modulename_help`

### Framework-Konvention — NICHT entfernen (3)

- `cachedef_navisteacher`
- `pluginadministration`
- `task_check_timed_restrictions`

- `cachedef_navisteacher` — von der Moodle-Cache-API über die
  Cache-Definition genutzt, nicht per String-API.
- `pluginadministration` — von Moodle-Core geladen.
- `task_check_timed_restrictions` — verwaist; siehe eigenes Issue
  `local_adele-issue-orphaned-task-string.md` (dort mit Umsetzungs-
  vorschlag „entfernen oder als Task-Namen nutzen").

---

## Empfehlung

1. `enrol_adele`: nichts zu tun.
2. `mod_adele`: die 3 echten Kandidaten sind gefahrlos entfernbar; der
   fehlerhafte Schlüssel `mod/adele:seelearningpath` sollte in jedem Fall
   bereinigt werden.
3. `local_adele`: **nicht pauschal entfernen.** Die 72
   Kandidaten zuerst gegen das Vue-Frontend (dynamische Schlüssel) und die
   Mustache-Templates verifizieren. Am sichersten per Laufzeit-Instrumentierung
   (z. B. temporäres Logging in `get_string()`/`core_get_component_strings`)
   über einen vollständigen UI-Durchlauf, statt allein statisch.
