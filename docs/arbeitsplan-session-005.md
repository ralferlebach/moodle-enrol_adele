# Arbeitsplan Session 005 — Abarbeitung der Upstream-Issues #2–#8

**Stand:** 2026-08-28, Fassung 2 (überarbeitet nach den Direktiven des
Auftraggebers zu Q1–Q6)
**Referenz:** [Lastenheft](lastenheft.md), [Pflichtenheft](pflichtenheft.md),
[Arbeitsplan Phasen A–G](arbeitsplan.md), [Session 004](sessions/session-004.md)

Geprüfte Codestände (`development`, 2026-08-28): `enrol_adele` 0.2.0
(2026072500), `local_adele` 0.5.2 (2026082700), `mod_adele` 0.2.0 (2026072500).
`local_adele` ist gegenüber Session 004 zwei Releases weiter.

Nullmessung: alle drei Plugins `phpcs --standard=moodle` Exit 0, alle
PHP-Dateien syntaktisch fehlerfrei.

---

## Entscheidungen des Auftraggebers (Fassung 2)

| Frage | Entscheidung |
|---|---|
| Q1 (#3) | Wie vorgeschlagen: Zugriffsentzug **sofort** im Observer, nur das Löschen der `local_adele_path_user`-Zeile wandert in die Ad-hoc-Task |
| Q2 (#7a) | Aufbewahrungsfrist Vorgabe **90 Tage**, `0` = nie austragen |
| Q3 (P1) | **Keine DB-Änderung in `local_adele`.** Stattdessen Bezug auf die `mod_adele`-Tabelle und Auslesen der `mod_adele`-Einstellungen. Bidirektionale Kopplung ist zulässig; `mod_adele` in `local_adele/version.php` als Abhängigkeit deklarieren |
| Q4 (#5) | Diagnosebericht wandert in P7 (`manage.php`) |
| Q5 (#6) | Label „Prio: before publish" bedeutet **niedrigste** Priorität — vor einer Publikation zu erledigen, nicht vorher. P7 bleibt am Ende |
| Q6 (`docs/`) | `docs/` ist im Repo vorhanden und wird weiter gepflegt; es fehlt nur im Archiv-Download wegen `export-ignore`. Fortführung ab dieser Fassung |

**Zone.Identifier:** erledigt, kein Handlungsbedarf. Die 444 Dateien lagen im
hochgeladenen `local_adele`-Analysepaket (roher Ordner-Export, 75 MB, volles
`.git`), nicht in einem Repository. Alle drei `development`-Stände sind frei
davon. Der Punkt entfällt aus der Restliste.

---

## Teil 1 — Befund je Issue

### #2 — `reconcile_all()` heilt nur Zielkurs-Enrolments · BESTÄTIGT

`reconciler::reconcile_all()` (Zeilen 183–217) führt vier Durchgänge aus:
`remove_orphaned_instances()`, `consolidate_duplicate_instances()`,
`sync_instance_roles()` und eine Schleife über `local_adele_path_user` mit
`status='active'`, die je Paar `reconcile_user()` aufruft. `reconcile_user()`
arbeitet ausschließlich mit `KIND_TARGET` (Zeile 92). Ein Host-Durchgang
existiert nicht.

Die Host-Berechtigung wird ausschließlich in `mod_adele` abgeleitet, in zwei
**privaten** Methoden: `is_user_entitled_to_host_via_option()` und der
Gruppierung in `sync_host_access_for_node_enrolment()`. Beide sind nur über
`user_enrolment_created`/`user_enrolment_deleted` erreichbar. Geht ein Ereignis
verloren, korrigiert das nichts jemals wieder.

### #3 — Kurzzeitige Abmeldung löscht den Lernfortschritt · BESTÄTIGT

`enrol_adele\observer::user_enrolment_deleted()` löscht in Zeile 83 hart:
`$DB->delete_records('local_adele_path_user', [...])`. Der Unique-Index
`useridlpid` auf `(user_id, learning_path_id)` enthält `status` nicht; die
Analyse im Issue, dass naives Archivieren die Wiedereinschreibung zum Absturz
bringt, ist nachvollziehbar, ebenso dass `enrollment::buildsqlqueryuserpath()`
nur `status='active'` sucht und die `dml_exception` in Zeile 106 weiterwerfen
würde. Beides ist mit der entschiedenen Ad-hoc-Task-Lösung gegenstandslos.

### #4 — Rollenänderungen reconciliieren · TEILWEISE BEREITS UMGESETZT

Das Issue beschreibt einen Zustand, der so nicht mehr besteht.
`reconciler::sync_instance_roles()` (Zeilen 348–379) existiert seit 0.2.0, läuft
in jedem `reconcile_all()` und tut das Verlangte: stale `enrol.roleid` finden,
`role_unassign`/`role_assign` mit `component='enrol_adele'` und
`itemid = instance id`, dann `enrol.roleid` nachziehen. Fremdrollen bleiben
durch die Component/Itemid-Bindung unangetastet.

Echte Restlücken:

1. **Keine Prüfung auf Nutzerebene.** Verglichen wird nur `enrol.roleid` gegen
   die Konfiguration. Fehlt einem Nutzer die Rollenzuweisung ganz, fällt das nie
   auf — die Instanz gilt als sauber.
2. **Instanzen mit `roleid = 0`** sind durch `roleid <> 0` (Zeile 354)
   ausgeschlossen.
3. **Keine Sofortwirkung.** Eine Konfigurationsänderung greift frühestens beim
   nächsten Nachtlauf.
4. **Keine Testabdeckung.** Kein Test in `tests/` berührt `sync_instance_roles()`.

### #5 — Vollständiger Soll-Ist-Reconcile · TEILWEISE, Dachthema

Vorhanden: verwaiste Instanzen, Duplikate, Rollensynchronisierung, Soll→Ist für
Zielkurse. Fehlend:

- **Ist→Soll fehlt vollständig.** `reconcile_all()` startet ausschließlich vom
  Sollbestand. Ein Nutzer mit aktiver ADELE-Einschreibung, dessen Pfadzeile weg
  oder inaktiv ist, wird von der Schleife **nie besucht** — seine Einschreibung
  bleibt dauerhaft `ACTIVE`. Derselbe Mechanismus wie in #2, nur auf der
  Zielkursseite, und der direkte Erzeuger der Symptome aus #7.
- Host-Zustände (= #2).
- Diagnosebericht: heute eine Zeile `progress_trace` mit vier Zahlen.
- Idempotenznachweis: kein Test auf Änderungsfreiheit im zweiten Lauf.

#2 ist fachlich eine Teilmenge von #5; getrennte Umsetzung würde
`reconcile_all()` zweimal umbauen.

### #6 — `manage.php` paginieren und Aktionen asynchron · TEILWEISE

Der asynchrone Teil ist vorhanden: `ADELE_MANAGE_ASYNC_THRESHOLD = 200`,
oberhalb dessen `reconcile_learning_path_adhoc` bzw. `purge_learning_path_adhoc`
eingereiht werden (Zeilen 40, 76–81, 107–112).

Fehlend: die Pagination. Die Abfrage in Zeile 122 gruppiert unbegrenzt über
`{enrol}` ⋈ `{user_enrolments}` und erzeugt eine `html_table` mit einer Zeile je
Lernpfad. Ebenso fehlen Filter und jede Rückmeldung über eingereihte Tasks.

### #7 — Entfernte Kursteilnehmende bleiben sichtbar · BESTÄTIGT, drei Ursachen

**(a) Konstruktionsbedingt.** `reconcile_host_user()` und `reconcile_user()`
setzen nicht mehr Berechtigte auf `ENROL_USER_SUSPENDED`, sie tragen sie nicht
aus. Moodle zeigt suspendierte Einschreibungen in der Teilnehmerliste weiterhin
an. Da `allow_manage()` und `allow_unenrol()` in `lib.php` beide `false`
liefern, sind sie dort nicht bearbeitbar oder löschbar — exakt die Beschreibung
im Issue.

**(b) Gelöschte Einbettung hinterlässt Einschreibungen — dauerhaft.**
`adele_delete_instance()` löscht die `{adele}`-Zeile und ruft
`enrol_state::remove_host_course_index()`. Die zugehörige `KIND_HOST`-Instanz im
Host-Kurs und ihre `user_enrolments` bleiben **unangetastet**.
`remove_orphaned_instances()` greift nur, wenn der *Lernpfad* verschwunden ist
(`LEFT JOIN local_adele_learning_paths … WHERE lp.id IS NULL`) — der existiert
hier weiter. Einen `course_module_deleted`-Observer gibt es im gesamten
Ökosystem nicht. Ergebnis: Einschreibungen ohne fachliche Begründung, die von
keinem Mechanismus je wieder entfernt werden. Das ist die Antwort auf die
Rückfrage im Issue-Kommentar.

**(c) Geänderte `participantslist` entzieht nie.** `saved_module()` ruft nur die
`enroll_*_participants()`-Sweeps für die *aktuell gewählten* Optionen auf. Wird
Option 3 abgewählt, passiert für die dadurch entstandenen Zugänge nichts.

### #8 — Änderungen an mod_adele-Einstellungen in Enrolments · BESTÄTIGT

Ohne Beschreibungstext, am Code aber eindeutig mit „nein" zu beantworten:

- `adele_update_instance()` synchronisiert den Host-Kurs-Index, löst keinen
  Reconcile aus.
- `saved_module()` fügt nur hinzu, entzieht nie (siehe #7c).
- Eine Änderung von `hostenrolmentmode` wirkt erst beim nächsten
  Enrolment-Ereignis des Nutzers — für bestehende Teilnehmer praktisch nie.

### Zusätzlicher Befund — `version.php` aller drei Plugins

Beim Prüfen der Abhängigkeitsdeklaration für Q3 sind drei Fehler aufgefallen,
die in keinem Issue stehen:

1. **`enrol_adele` deklariert eine Abhängigkeit auf sich selbst:**
   ```php
   $plugin->dependencies = [
       'local_adele' => 2026072500,
       'enrol_adele' => 2026072500,   // ← sich selbst
   ];
   ```
   Derzeit folgenlos, weil die eigene Version die Bedingung immer erfüllt, aber
   sachlich falsch und in der Plugin-Verwaltung sichtbar.

2. **Der deklarierte Graph ist zirkulär** und widerspricht der in
   [`arbeitsplan.md`, G-Q1](arbeitsplan.md) getroffenen Entscheidung, dass
   `local_adele` **keine** deklarierte Abhängigkeit auf `enrol_adele` erhält.
   Ist-Zustand: `local_adele → {mod_adele, enrol_adele}`,
   `mod_adele → {local_adele, enrol_adele}`,
   `enrol_adele → {local_adele, enrol_adele}`. Ob eine Neuinstallation aller
   drei Plugins damit überhaupt durchläuft, ist **nicht verifiziert** — die CI
   installiert in einen bereits bestehenden Baum und beweist das nicht.

3. **`local_adele` deklariert `$plugin->supported = [405, 405]`**, die CI prüft
   aber Moodle 4.5 **und** 5.0. Auf 5.0 meldet das Plugin sich damit als nicht
   unterstützt. `enrol_adele` deklariert `[401, 502]`, obwohl `mod_adele`
   `2024100700` (4.5) verlangt und die Trias damit faktisch erst ab 4.5
   installierbar ist.

Punkt 1 und 3 sind Einzeiler. Punkt 2 braucht eine Entscheidung (Q7 unten).

Zur Direktive aus Q3: `local_adele/version.php` **deklariert `mod_adele`
bereits** (`'mod_adele' => 2026072500`). Hier ist nichts nachzutragen. Auch der
direkte Tabellenzugriff hat Präzedenz — `enrollment::buildsqlquerypath()` joint
`{adele}` schon heute, mit ausdrücklichem Kommentar dazu. Die Direktive
formalisiert damit einen bestehenden Zustand, statt einen neuen zu schaffen.

---

## Teil 2 — Architektur nach der Q3-Direktive

Die ursprünglich vorgeschlagene Spalte `hostenrolmentmode` in
`local_adele_host_courses` entfällt. Stattdessen:

```
   enrol_adele                local_adele                    mod_adele
   ───────────                ───────────                    ─────────
   reconciler                 enrol_state                    host_policy (neu)
   reconcile_all()  ────────► get_host_entitlement()  ─────► get_entitlement()
   reconcile_host_user()      get_host_embeddings()   ─────► get_embeddings()
                                     │                            │
                                     │                            ▼
                                     │                     liest {adele}:
                                     │                     participantslist,
                                     │                     hostenrolmentmode
                                     │                     (alleinige Quelle)
                                     ▼
                              deklarierte Abhängigkeit
                              local_adele → mod_adele
                              (bereits vorhanden)
```

Drei Eigenschaften dieser Aufteilung:

- **Die Ableitungslogik bleibt in `mod_adele`** — dort, wo die Semantik von
  `participantslist` und `hostenrolmentmode` zu Hause ist. Sie wird nur aus
  privaten Observer-Methoden in eine öffentliche, dokumentierte Klasse gehoben.
  Damit ist die Rückfrage „P1.3/4 ggf. weiterhin im mod-Plugin implementieren?"
  mit ja beantwortet.
- **`enrol_adele` bleibt frei von `mod_adele`-Wissen.** Es ruft ausschließlich
  `local_adele\enrol_state` auf und kennt weder `{adele}` noch
  `participantslist` — die Akzeptanzkriterien aus
  [`issues/enrol_adele-issue-undeclared-mod_adele-dependency.md`](issues/enrol_adele-issue-undeclared-mod_adele-dependency.md)
  bleiben erfüllt.
- **Die Kopplung sitzt in `local_adele`**, wo sie laut Direktive sein darf und
  bereits deklariert ist.

**Folge, die entschieden werden muss (Q8):** `local_adele_host_courses` wird
damit zur Dublette von `{adele}`. Der Vorschlag ist, `{adele}` zur alleinigen
Quelle zu machen, die Tabelle vorerst stehen und unbenutzt zu lassen und ihren
Abbau als eigenen Punkt zu führen — ein Löschen wäre eine Schemaänderung, und
Schemaänderungen sind für diese Sitzung ausgeschlossen. Die Alternative
(Tabelle weiter für die Optionsflags nutzen, Modus frisch aus `{adele}`) wäre
eine halbe Lösung mit zwei Wahrheiten und wird nicht empfohlen.

---

## Teil 3 — Abhängigkeiten der Arbeitspakete

```
   ┌──────────────────────────────────────┐
   │ P0  version.php-Sofortkorrekturen    │   unabhängig, Minuten
   └──────────────────────────────────────┘

   ┌──────────────────────────────────────┐
   │ P1  Host-Berechtigung als eine        │
   │     Quelle der Wahrheit (in mod_adele)│
   │     + Ist→Soll-Durchgang              │
   │     (#2 + #5, OHNE Schemaänderung)    │
   └───┬──────────────┬───────────────┬────┘
       │              │               │
 ┌─────▼──────┐  ┌────▼──────────┐  ┌─▼─────────────┐
 │ P4  #7b    │  │ P3  #8 + #7c  │  │ P5  #7a       │
 │ Verwaiste  │  │ Sofortwirkung │  │ Aufbewahrungs-│
 │ Host-Inst. │  │ beim Speichern│  │ regel 90 Tage │
 └────────────┘  └───────────────┘  └───────────────┘

 ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────────┐
 │ P2  #3 Ad-hoc-   │   │ P6  #4 Rollen-   │   │ P7  #6 manage.php    │
 │     Task (+5 min)│   │     abgleich     │   │     + Diagnosebericht│
 └──────────────────┘   └──────────────────┘   └──────────────────────┘
    unabhängig             unabhängig              unabhängig, zuletzt
```

---

## Teil 4 — Arbeitspakete

### P0 — Sofortkorrekturen in `version.php`

1. Selbstabhängigkeit `'enrol_adele' => …` aus `enrol_adele/version.php`
   entfernen.
2. `local_adele`: `$plugin->supported = [405, 502]` statt `[405, 405]`, passend
   zur CI-Matrix.
3. `enrol_adele`: `$plugin->supported` und `$plugin->requires` auf die
   tatsächliche Untergrenze 4.5 ziehen, oder begründet bei 4.1 belassen und den
   Widerspruch zu `mod_adele` dokumentieren.
4. Q3 ist bereits erfüllt (`local_adele → mod_adele` deklariert) — nichts zu tun.

Betroffen: `enrol_adele`, `local_adele`. Versionsbump: ja (Metadaten wirken auf
Installation und Upgrade).

### P1 — Host-Berechtigung vereinheitlichen, Reconcile bidirektional (#2, #5)

**Keine Schemaänderung.**

1. **`mod_adele`**: neue öffentliche Klasse `mod_adele\local\host_policy` mit
   - `get_embeddings(int $learningpathid): array` — je Einbettung
     `courseid`, `option1/2/3`, `hostenrolmentmode`, direkt aus `{adele}`;
   - `get_entitlement(int $lpid, int $hostcourseid, int $userid): array` →
     `[bool $entitled, string $mode]`, inklusive der Gruppierung
     „großzügigste Option gewinnt".
   Die bisherigen privaten Methoden `is_user_entitled_to_host_via_option()` und
   die Gruppierung in `sync_host_access_for_node_enrolment()` werden dorthin
   verschoben; der Observer ruft künftig dieselbe Klasse auf. Verhalten
   unverändert — `host_enrolment_priority_test` sichert das ab.
2. **`local_adele`**: `enrol_state::get_host_entitlement()` und
   `get_host_embeddings()` delegieren an `mod_adele\local\host_policy`, mit
   `class_exists()`-Rückfall auf `warn_enrol_adele_missing()`-Muster, falls
   `mod_adele` fehlt. `get_learningpaths_embedded_in_course()` liest ebenfalls
   `{adele}` statt des Index.
3. **`enrol_adele`**: `reconcile_all()` in benannte Durchgänge zerlegen:
   - Instanzen: verwaist, dupliziert, Rollen (unverändert)
   - Ziel Soll→Ist: aktive Pfadnutzer (unverändert)
   - **Ziel Ist→Soll (neu):** Nutzer mit ADELE-Zielkurs-Einschreibung ohne
     aktive Pfadzeile → suspendieren
   - **Host (neu):** je Lernpfad × Host-Einbettung über die **Vereinigung** aus
     aktiven Pfadnutzern und aktuell über ADELE-Host-Instanzen eingeschriebenen
     Nutzern → `reconcile_host_user()` mit frisch abgeleiteter Berechtigung
   Durchgängig `get_recordset_sql()`, je Durchgang eine `progress_trace`-Zeile.
4. **`local_adele_host_courses`** bleibt unangetastet und unbenutzt (siehe Q8).
   Die Schreibaufrufe in `adele_add_instance()`/`adele_update_instance()`/
   `adele_delete_instance()` bleiben zunächst bestehen, damit ein Rückbau
   möglich bleibt.
5. **Tests**: die beiden Tests aus Issue #2
   (`tests/reconcile_all_host_sweep_test.php`) nachbauen, je ein Test pro
   Abweichungsklasse aus #5, ein Idempotenztest.

Betroffen: alle drei Plugins. Versionsbump: alle drei.

### P2 — Aufgeschobener Austrag über Ad-hoc-Task (#3, Q1 entschieden)

1. Neue Klasse `enrol_adele\task\revoke_membership_adhoc`, terminiert auf
   `time() + 300`, Nutzdaten `{learningpathid, userid}`.
2. `observer::user_enrolment_deleted()` führt `purge_user()` und
   `purge_all_host_user()` **sofort** aus (Zugriffsentzug ist korrekt und
   gewollt), löscht die `local_adele_path_user`-Zeile aber **nicht** mehr,
   sondern reiht die Task ein.
3. Die Task prüft `is_user_carried()` erneut und löscht die Zeile nur, wenn der
   Austragungsgrund fortbesteht.
4. Wiedereinschreibung innerhalb des Fensters: `buildsqlqueryuserpath()` findet
   die noch aktive Zeile, der Reconcile stellt den Zugriff wieder her, der
   Fortschritt bleibt vollständig erhalten. Kein Index-Konflikt, kein Eingriff
   in `local_adele`.
5. `reconciler_test::test_host_course_removal_rules` umstellen: die Löschung
   findet beim Task-Lauf statt, nicht im Observer.
6. Zwei neue Tests: Blip innerhalb des Fensters erhält den Snapshot; echter
   Austritt löscht nach Ablauf.

Offen: soll das Fenster (300 s) als Einstellung konfigurierbar sein? Vorschlag:
zunächst als dokumentierte Konstante, wie `ADELE_MANAGE_ASYNC_THRESHOLD`.

Betroffen: `enrol_adele`. Versionsbump: ja.

### P4 — Verwaiste Host-Instanzen aufräumen (#7b)

1. `remove_orphaned_instances()` um einen zweiten Fall erweitern:
   `KIND_HOST`-Instanzen, zu deren `(learningpathid, courseid)` keine
   `{adele}`-Zeile mehr existiert → `delete_instance()`. Mit `{adele}` als
   alleiniger Quelle (P1) ist das eine direkte Abfrage statt eines Umwegs über
   den Index.
2. Zusätzlich prüfen, ob `adele_delete_instance()` allein genügt — Moodle ruft
   es beim Löschen der Aktivität auf — oder ob der Papierkorb
   (`deletioninprogress`, `course_module_deleted`) einen eigenen Pfad braucht.
   **Am Core verifizieren, nicht aus dem Gedächtnis entscheiden.**
3. Test: Aktivität löschen → Host-Instanz und ihre Einschreibungen sind weg;
   Instanzen anderer Lernpfade im selben Kurs bleiben unberührt.

Betroffen: `enrol_adele`, ggf. `mod_adele`. Versionsbump: ja.

### P3 — Einstellungsänderungen wirken sofort (#8, #7c)

1. `mod_adele`: in `saved_module()` nach dem Speichern eine Ad-hoc-Task
   einreihen, die genau das Paar (Lernpfad, Host-Kurs) reconciliiert — statt nur
   die gewählten Optionen zu sweepen.
2. Die Task nutzt den Host-Durchgang aus P1 und entzieht damit automatisch, was
   durch Abwahl einer Option unbegründet geworden ist.
3. Tests: `participantslist` von `3` auf `1` → Host-Zugänge werden entzogen;
   `hostenrolmentmode` von `visible` auf `hidden` → bestehende Einschreibungen
   werden suspendiert.

Betroffen: `mod_adele`, `enrol_adele`. Versionsbump: ja.

### P5 — Aufbewahrungsregel für suspendierte Einschreibungen (#7a, Q2 entschieden)

1. Neue Einstellung `enrol_adele/suspendedretention`, Vorgabe **90**, `0` = nie
   austragen. Einheit Tage.
2. Neuer Durchgang in `reconcile_all()`: ADELE-Einschreibungen, die seit mehr als
   N Tagen suspendiert sind und deren Berechtigung weiterhin fehlt → austragen.
   Grundlage für „seit wann suspendiert" ist `user_enrolments.timemodified`;
   dass dieses Feld beim Suspendieren gesetzt wird, ist **an Moodles
   `update_user_enrol()` zu verifizieren**, bevor darauf aufgebaut wird.
3. Gilt einheitlich für Ziel- und Host-Kurse.
4. Sprachstrings EN/DE, alphabetisch einsortiert.
5. Tests für den Grenzfall knapp unter und knapp über der Frist sowie für
   `0` = nie.

Betroffen: `enrol_adele`. Versionsbump: ja.

### P6 — Rollenabgleich vervollständigen (#4)

1. `sync_instance_roles()` um eine Prüfung auf Nutzerebene erweitern: je
   `user_enrolments`-Zeile gegen `role_assignments` mit `component='enrol_adele'`
   und `itemid = instance id` abgleichen, fehlende Zuweisung ergänzen,
   abweichende migrieren. Fremdzuweisungen weiterhin nie anfassen.
2. `roleid = 0`-Instanzen einbeziehen.
3. Ad-hoc-Migration beim Speichern von `enrol_adele/roleid` einreihen.
4. Tests: Rollenwechsel, fehlende Zuweisung, Fremdrolle bleibt erhalten,
   Idempotenz.

Betroffen: `enrol_adele`. Versionsbump: ja.

### P7 — Verwaltungsseite skalierbar machen (#6) plus Diagnosebericht (Q4)

1. `html_table` durch `flexible_table` mit serverseitiger Pagination ersetzen;
   Gesamtzahl über eine eigene `COUNT`-Abfrage, Datenabfrage mit `LIMIT`/`OFFSET`.
2. Filter für Lernpfad, Kurs, Typ (Target/Host) und Status.
3. Statusanzeige eingereihter Ad-hoc-Tasks über `{task_adhoc}`.
4. **Diagnosebericht aus #5** hier als eigener Abschnitt: Ergebnis des letzten
   `reconcile_all()`-Laufs je Durchgang (verwaist, dupliziert, Rollen, Ziel
   Soll→Ist, Ziel Ist→Soll, Host, Austragungen nach Frist). Speicherung als
   Plugin-Config-Wert beim Task-Ende, damit kein neues Schema nötig ist.
5. Behat-Test für Pagination und Filter.

Betroffen: `enrol_adele`. Versionsbump: ja.

---

## Teil 5 — Reihenfolge

| Schritt | Paket | Issues | Stand |
|---|---|---|---|
| 1 | P0 | — | ✅ Session 005, Teil 2 |
| 2 | P1 | #2, #5 | ✅ Session 005, Teil 3 |
| 3 | Q8 | — | ✅ Session 005, Teil 4 — Indextabelle entfernt |
| 4 | P4 | #7b | ✅ Session 005, Teil 4 |
| 5 | P3 | #8, #7c | ✅ Session 005, Teil 4 |
| 6 | P2 | #3 | ✅ Session 005, Teil 5 |
| 7 | P5 | #7a | ✅ Session 005, Teil 6 |
| 8 | P6 | #4 | ✅ Session 005, Teil 6 |
| 9 | P7 | #6 | offen — niedrigste Priorität laut Label, plus Q4 |

Alle Nachweise stehen aus, bis die CI gelaufen ist: PHPUnit lässt sich in der
Arbeitsumgebung nicht ausführen.

#7 gilt erst nach P4, P3 **und** P5 als geschlossen — drei getrennte Ursachen,
nicht vorzeitig abhaken.

---

## Teil 6 — Noch offen

**Q7 — Zirkulärer Abhängigkeitsgraph.** Der Ist-Zustand widerspricht der
Entscheidung aus G-Q1 („`local_adele` bekommt **keine** deklarierte
Abhängigkeit auf `enrol_adele`"). Soll der Graph auf den damals beschlossenen
Zielzustand zurückgeführt werden, oder gilt die Zirkularität mit der Q3-Direktive
(„bidirektionale Kopplung ist OK") jetzt ausdrücklich als gewollt? Im zweiten
Fall wäre G-Q1 in `arbeitsplan.md` als überholt zu kennzeichnen — und es bleibt
zu verifizieren, ob eine **Neuinstallation** aller drei Plugins mit einem
zirkulären Graphen überhaupt durchläuft.

**Q8 — `local_adele_host_courses`: entschieden, Tabelle entfernt.** Direktive
des Auftraggebers: keine Dubletten, keine leeren Gerüste. Umgesetzt in Session
005, Teil 4 — Definition aus `install.xml`, `drop_table()`-Schritt in
`db/upgrade.php`, Schreibmethoden und Aufrufer entfernt. Kein Datenverlust,
jede Spalte war eine Kopie aus `{adele}`.

**Q9 — Ad-hoc-Fenster in P2: entschieden, feste Konstante.**
`remove_user_path_adhoc::DELAY_SECONDS = 300`, bewusst keine Einstellung: ein
pro Instanz einstellbarer Wert wird irgendwo auf null gesetzt, und dann ist der
Datenverlust zurück.

---

## Teil 7 — Nicht Teil dieser Sitzung

- **G.10 Capability-Redesign** — Issue-Dokument liegt vor, wartet auf
  Entscheidung.
- **CI-Abhängigkeitsfrage** `local_adele` → `enrol_adele` (Part-14-Behelf vs.
  `assertDebuggingCalled()`).
- **`docs/prompt-templates/sessionstart.txt` und `sessionende.txt`** aus dem
  `mod_elang`-Muster stehen jetzt neben den neuen `session-start-prompt.md` und
  `environment-setup.md` im catquiz-Format. Ob die alten Vorlagen entfallen
  sollen, ist offen — sie wurden bewusst nicht ohne Rücksprache entfernt.
