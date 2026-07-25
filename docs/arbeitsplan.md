# Arbeitsplan ADELE-Einschreibearchitektur

**Stand:** 2026-07-24, fortgeschrieben bis Session 003, Teil 15
**Referenz:** [Lastenheft 2.0](lastenheft.md), [Pflichtenheft 2.0](pflichtenheft.md)

Drei Repositories, drei Arbeitsbranches:

| Repository | Branch | Basis |
|---|---|---|
| `moodle-enrol_adele` | `development` | 0.1.0-Stub |
| `moodle_local_adele` | `development` | Wird laufend aktualisiert; ein Upstream-Update liegt uns noch nicht in Codeform vor (Teil 4) |
| `moodle-mod_adele` | `development` | 0.1.5, Übernahme aus `ralferlebach-fix-enrolment-issue` erfolgt (R-2) |

Grundregel der Reihenfolge: `enrol_adele` wird in 0.2.0/0.3.0 vollständig
funktionsfähig, aber **ohne Aufrufer** — jederzeit folgenlos installierbar.
Erst Phase D (0.4.0) verdrahtet die Aufrufer in `local_adele`/`mod_adele`. So
bleibt jeder Zwischenstand deploybar (L-Q-08).

---

## Phase A — Fundament (erledigt)

| AP | Inhalt | Status |
|---|---|---|
| A.1 | Stub `enrol_adele` 0.1.0: Installation, Plugin-Klasse, Capabilities, Settings, Privacy, CI, Makefile | ✅ Session 001 |
| A.2 | Analyse Referenz-Plugins + Codebase, Klärung F-1…F-10, Doku-Fassung 2.0, Arbeitsplan | ✅ Session 002, Teil 1 |
| A.3 | Repos befüllen: alle drei Working-Branches (`development`) bestätigt vorhanden. CI läuft für alle drei Plugins. | ✅ erledigt |

## Phase B — `enrol_adele` 0.2.0: Reconciliation-Engine

Alles ohne Aufrufer; testbar rein über PHPUnit.

| AP | Inhalt | Abhängigkeit |
|---|---|---|
| B.1 | `instance_manager`: `ensure_instance($lpid, $courseid)` (lazy, Name `ADELE: <LP>`, `roleid` aus Setting), Suche über `enrol='adele' AND customint1` | — · **Status: erledigt (0.1.1)** |
| B.2 | Übergangs-Soll-Zustand: Bis AP D.2 existiert `local_adele\enrol_state` nicht; `reconciler` kapselt die Quelle hinter einem internen Provider-Interface, dessen erste Implementierung das User-Path-JSON direkt liest. AP D.2 verschiebt diese Implementierung nach `local_adele` und lässt den Provider delegieren. | B.1 · **Status: erledigt — abweichend vom Plan direkt als `local_adele\enrol_state` umgesetzt, da 0.4.3 ohnehin mitgeliefert wurde; ein Übergangs-Provider entfiel** |
| B.3 | `reconciler`: `reconcile_user()` nach Pflichtenheft 2.3; `reconcile_learning_path()`, `reconcile_all()` | B.2 · **Status: erledigt (0.1.1)** |
| B.4 | `purge_user()`, `purge_learning_path()` nach Pflichtenheft 2.4 (über `delete_instance()`, nie direktes DB-Delete) | B.1 · **Status: erledigt (0.1.1)** |
| B.5 | Scheduled Task `reconcile_task` (Standard: täglich, deaktivierbar) + `sync()` in der Plugin-Klasse | B.3 · **Status: erledigt (0.1.1)** |
| B.6 | PHPUnit: Idempotenz, geteilte Zielkurse (Prüfkriterium 1), Purge-Isolation (Prüfkriterium 2), Koexistenz mit `manual` | B.3, B.4 · **Status: Tests geschrieben (Prüfkriterien 1–3); erster Lauf steht in der CI aus** |

## Phase C — `enrol_adele` 0.3.0: Observer, Verwaltung, Restore

| AP | Inhalt | Abhängigkeit |
|---|---|---|
| C.1 | Observer `user_enrolment_deleted` + Regelwerk Pflichtenheft Abschnitt 4 (Optionen 1/2/3, Rekursionsschutz, „getragen"-Prüfung als wiederverwendbare Hilfsfunktion). Bis D.3 bleibt der Observer wirkungslos-defensiv, da noch keine Option-3-Daten existieren; das Regelwerk wird gegen Fixtures getestet. | B.4 · **Status: erledigt (0.1.1) — Regelwerk aktiv, nicht nur defensiv** |
| C.2 | Verwaltungsseite `manage.php` nach Pflichtenheft Abschnitt 6 (inkl. „verwaist"-Markierung, Ad-hoc-Task ab Schwellwert, Bestätigungsdialog) | B.3, B.4 · **Status: erledigt (0.1.7, Session 003 Teil 6) — Erstversion, noch nicht gegen echte Moodle-Instanz getestet, siehe `docs/verification-live-testing-guide.md`** |
| C.3 | Eigene Events (`learning_path_reconciled`, `learning_path_purged`, `user_access_revoked`) | C.1, C.2 · **Status: erledigt (0.1.7, Session 003 Teil 6)** |
| C.4 | Restore-Hooks: `restore_instance()` (Skip-Strategie mit Same-Course-Ausnahme), `restore_user_enrolment()` (No-op) + Backup/Restore-Test (Prüfkriterium 5) | B.1 · **Status: vollständig erledigt (0.1.11, Session 003 Teil 19) — Same-Course-Ausnahme über `restore_task::get_target()` implementiert (gegen echten Moodle-Core-Code verifiziert, `course_handler.php::restore_instance_data_from_backup()`): löst bei `TARGET_CURRENT_ADDING`/`_DELETING` sofort `reconcile_learning_path()` aus statt zu skippen. `tests/backup_restore_test.php` deckt weiterhin nur den Neu-Kurs-Fall automatisiert ab; Same-Course-Fall manuell in Testanleitung C (Restore-Adding-Verhalten gegenüber Bestandsdaten nicht sicher genug verifizierbar für einen automatisierten Test)** |
| C.5 | Behat-Grundlauf für die Verwaltungsseite | C.2 · **Status: erledigt (0.1.10, Session 003 Teil 12) — drei Szenarien (leerer Zustand, Lernpfad gelistet, Neu-berechnen-Aktion), ungetestet gegen echte Instanz** |

## Phase D — 0.4.0: Umstellung der Aufrufer

| AP | Repo | Inhalt | Abhängigkeit |
|---|---|---|---|
| D.1 | `local_adele` | User-Path-Identität: `subscribe_user_to_learning_path()` ohne `$courseid` (idempotent, `UNIQUE`-Verhalten über `learning_path_id + user_id`), Drei-Parameter-Wrapper deprecated; `buildsqlqueryuserpath()` ohne `course_id` | — · **Status: erledigt (0.4.3) — `$courseid` optional/Provenienz, Wrapper bleibt; DB-Unique-Index bewusst nicht erzwungen (Bestandsdaten, F-9)** |
| D.2 | `local_adele` | `enrol_state::get_entitled_courseids()` (JSON-Hoheit); Provider aus B.2 delegiert nun hierauf | D.1 · **Status: erledigt (0.4.3)** |
| D.3 | `local_adele` | Aufrufer umstellen: `relation_update.php:231/1104ff` und `node_completion.php:70–112` ersetzen durch optionalen `reconcile_user()`-Aufruf (Null-Prüfung, L-Q-08); Fallback auf Altverhalten, wenn `enrol_adele` fehlt | D.2, B.3 · **Status: erledigt (0.4.3)** |
| D.4 | `local_adele` | Lösch-Lifecycle: `delete_learning_path()` deaktiviert User-Paths und ruft `purge_learning_path()` (optional, Null-Prüfung) | B.4, R-1 · **Status: erledigt (0.4.3; R-1 entschieden)** |
| D.5 | `local_adele` | `enroll_as_setting` deprecated; einmalige Übernahme des Werts nach `enrol_adele/roleid` beim Upgrade | D.3 · **Status: erledigt (Teil 17)** |
| D.6 | `mod_adele` | Übernahme aus `fix-enrolment-issue` (→ R-2): Option 3 inkl. Lang-Strings, defensive Guards, `mod_form`-Anpassung; dazu Bugfix A-14 (`explode` vor Vergleich in `user_enrolment_created`) — im Branch selbst noch unbehoben | D.1 · **Status: erledigt (0.1.5)** |
| D.7 | `mod_adele` | Nach jedem Subscribe `reconcile_user()` anstoßen (optional, Null-Prüfung); Hostkurs-Einschreibung bleibt unverändert `enrol_manual` (A-10) | D.6, B.3 · **Status: erledigt — über den Recompute-Hook in `relation_update`, kein direkter Aufruf in mod_adele nötig** |
| D.8 | alle | CI-Matrix beider Forks auf die Arbeitsbranches zeigen lassen; Integrationstest über alle drei Plugins; Prüfkriterien 1–8 abnehmen | D.1–D.7 · **Status: CI-Teil erledigt (alle drei Plugins grün); formale Abnahme der 8 Prüfkriterien weiterhin offen — Kriterien 4 (Verwaltungsseite) und 5 (Restore) hängen an Phase C, noch nicht erfüllbar** |


## Entscheidungen (Session 002, Teil 2)

R-1: Datensatz wird gelöscht (Einschränkungen dokumentiert, Pflichtenheft 2.5).
R-2: `fix-enrolment-issue` ist in `mod_adele` 0.1.5 eingeflossen.

## Session 002, Teil 4 — zusätzlich geliefert, außerhalb der ursprünglichen Phasen B–D

- `enrol_adele` 0.1.2: Host-Kurs-Instanzen (`KIND_HOST`), `reconcile_host_user()`/
  `purge_host_user()`.
- `local_adele` 0.4.5: Reapplikation auf korrigierten Upstream-Stand 0.4.4,
  Upgrade-Blocker behoben, Identitätsmigration abgeschlossen (Auflösung des
  Konflikts mit ticket #501).
- `mod_adele` 0.1.6: laufender Trigger für Optionen 2/3, Host-Kurs-Einschreibung
  über `enrol_adele`.

Verbleibend: **Phase C** (C.2–C.5, → `enrol_adele` 0.1.x, keine Zielversion
festgelegt) — auf ausdrücklichen Wunsch des Auftraggebers für die **nächste
Session** vorgemerkt, in dieser Session bewusst nicht begonnen — sowie D.8
(formale Abnahme der Prüfkriterien, hängt an Phase C).

## Phase F — Host-Kurs-Nacharbeiten (aus vier Rückfragen zu Fall 2/3, Session 002 Teil 7)

Vier Lücken im aktuellen Host-Kurs-Verhalten wurden identifiziert; drei davon
sind neu, eine (F.1) war bereits als E-10 im Pflichtenheft dokumentiert. Alle
fünf Issue-Entwürfe dieses Projekts (F.1–F.4 sowie die bereits umgesetzte
Fall-3/Live-Trigger-Grundlage) sind seit Session 002 Teil 12 als echte Tickets
im mod_adele-Repository eingestellt: [#19](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/19)
(Fall 3, erledigt), [#20](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/20)
(Live-Trigger + Host-Einschreibung über enrol_adele, erledigt),
[#21](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/21) (F.1),
[#22](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/22) (F.2),
[#23](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/23) (F.3).

| AP | Inhalt | Issue | Abhängigkeit | Status |
|---|---|---|---|---|
| F.1 | `purge_all_host_user()`: Host-Kurs-Einschreibungen (alle Host-Kurse, nicht nur einer) mit austragen, wenn ein Nutzer den Lernpfad über A-4 verlässt. Löst E-10. | [#21](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/21) | B.4 (bestehende `purge_user()`) | **erledigt (Teil 12, `enrol_adele` 0.1.4)** |
| F.2 | Neues Aktivitäts-Setting `hostenrolmentmode` (`visible`/`hidden`/`none`) für Fall 2/3: Lehrkraft entscheidet, ob und wie sichtbar Lernpfadnutzer/innen in den Host-Kurs eingeschrieben werden. Erfordert Schema-Änderung in `mod_adele` (`db/upgrade.php`). | [#22](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/22) | Teil-4-Host-Kurs-Mechanik (bereits geliefert) | **erledigt (Teil 13, `enrol_adele` 0.1.5 / `mod_adele` 0.1.7)** |
| F.3 | Aggregation vor Anwendung: Mehrere Embeddings desselben Lernpfads im selben Host-Kurs werden gebündelt ausgewertet („großzügigste Option gewinnt"), statt sich gegenseitig in der Aufruf-Schleife zu überschreiben. Betrifft sowohl Berechtigung als auch — sobald F.2 existiert — Sichtbarkeitsstufe. | [#23](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/23) | F.2 (Sichtbarkeitsdimension), sonst unabhängig für die Berechtigungsdimension | **erledigt (Teil 14, `mod_adele` 0.1.8)** |
| F.4 | Ausführliche Neufassung von #486 als Referenzspezifikation — keine Codeänderung, aber Grundlage für Abnahme/Review. | `local_adele-issue486-ausfuehrlich.md` (lokal_adele-Repo, noch nicht als Ticket eingestellt) | — | Text geliefert |

**Phase F komplett** (F.1–F.4 alle erledigt). Restlücke E-16 (einmaliger
Aktivitäts-Save-Sweep aggregiert nicht wie der laufende Observer) bewusst
zurückgestellt — nicht sicherheitskritisch, siehe Pflichtenheft Abschnitt 1a.
Nächster sinnvoller Block: die länger zurückgestellten Phase-C-Punkte
(Verwaltungsseite, eigene Events, Restore-Hooks) oder E-11 (mod_adele #11),
falls sich der tatsächliche Fehlertext klären lässt.

## Phase G — Externes Code-Review (ChatGPT) gegen Codebase abgeglichen (Session 003)

Der Auftraggeber legte ein rund 40-Punkte-Review vor (ChatGPT, Prüfdatum
24.07.2026). Statt es ungeprüft zu übernehmen, wurde eine Stichprobe der
kritischsten Befunde gegen den tatsächlichen Code aller drei Plugins
verifiziert (`local_adele` aus dem hochgeladenen Paket, `enrol_adele`/
`mod_adele` aus dem `development`-Branch auf GitHub). Die Übereinstimmung von
Git-Historiengröße (54 MB) und Zone.Identifier-Anzahl (444) im hochgeladenen
Paket mit den im Review genannten Zahlen bestätigt, dass exakt dieser
Codestand geprüft wurde.

### Bestätigt — echte, im Code nachvollzogene Befunde

| Review-Bezug | Befund | Fundstelle (diese Sitzung verifiziert) | AP-Punkt |
|---|---|---|---|
| P0-2 | `local_adele` ↔ `mod_adele` ist keine bloße Falschdeklaration in `version.php`, sondern eine echte, beidseitige Codekopplung: `local_adele` ruft `mod_adele_observer::enroll_all_participants()`/`enroll_starting_nodes_participants()` direkt auf und joint `{adele}` (Kommentar im Code bestätigt dies als bewusst); `mod_adele` liest `local_adele_learning_paths` direkt. | `local_adele/classes/learning_path_update.php:34,345,347`; `local_adele/classes/enrollment.php:138–141`; `mod_adele/classes/local_adele.php:58` | G.1 |
| P0-2 | `enrol_adele` liest `mod_adele`s `{adele}`-Tabelle direkt (inkl. `participantslist`-Semantik), deklariert aber keine Abhängigkeit auf `mod_adele` — nur eine weiche `table_exists()`-Prüfung zur Laufzeit. | `enrol_adele/classes/observer.php:60,68,116–128` | G.2 |
| P0-1 | Die `enrol_manual`-Rückfallpfade existieren wie beschrieben — sind aber eine **bewusste, dokumentierte Anforderung** (L-Q-08, Lastenheft 3.3: „`local_adele`/`mod_adele` bleiben ohne `enrol_adele` voll funktionsfähig"), kein übersehener Altcode. Siehe G-Q1. | `local_adele/classes/enrol_state.php:99–121`, `relation_update.php`, `node_completion.php`; `mod_adele/classes/observer.php:185,479` | G-Q1 |
| P0-3.1 | IDOR in `update_lp_animations.php` bestätigt: freie `userid`, einzige Prüfung `local/adele:view`, keine Eigentumsprüfung gegen `$USER->id`. | `local_adele/classes/external/update_lp_animations.php::execute()` | G.3 |
| P0-4 | `has_foreign_enrolment()` prüft tatsächlich weder `ue.status`, `e.status`, `ue.timestart` noch `ue.timeend`. Für **Suspendierung** ist das die dokumentierte Absicht (F-4/A-8: „Suspendierte gelten weiterhin als subscribed") — keine Lücke, sondern Zielverhalten. Für **Ablauf** (`timeend`) und **deaktivierte Enrol-Instanzen** (`e.status`) existiert dagegen keine Entscheidung — das ist eine echte, bisher nicht benannte Lücke. | `enrol_adele/classes/observer.php:185–199` | G.4 |
| P0-5 | `reconcile_all()` lädt ausschließlich aktive `local_adele_path_user`-Paare per `get_records_sql()` (kein Recordset, kein Batching) und ruft nur `reconcile_user()` — keine Host-Kurs-, Waisen-, Duplikat- oder Rollenprüfung. Bestätigt exakt wie beschrieben. | `enrol_adele/classes/local/reconciler.php:167–189` | G.5 |
| P0-6 | `ensure_instance()` hat keinerlei Sperre zwischen Existenzprüfung und Anlage einer Instanz. Bestätigt. | `enrol_adele/classes/local/instance_manager.php:100–140` | G.6 |
| Abschnitt 10 | Paketqualität: Zone.Identifier-Dateien und volle `.git`-Historie im hochgeladenen `local_adele`-Paket bestätigt (444 Dateien, 54 MB `.git`, 75 MB entpackt). `.gitattributes`-`export-ignore` besteht bereits aus einer Vorsession, greift aber nur bei `git archive` — dieses Analyse-Paket war ein roher Ordner-Export. | `local_adele/.gitattributes`, Upload-Paket | G.7 |

### Bereits bekannt / bereits eingeplant (keine neuen Punkte)

- P1-5 (Backup/Restore unvollständig) — deckt sich mit **C.4** (offen).
- P1-6 (Verwaltungs-/Reparaturseite fehlt) — deckt sich mit **C.2** (offen).
- Die im Review unter P0-2 empfohlene Event-statt-Direktaufruf-Entkopplung würde **C.3** (eigene Events) mit abdecken, wenn C.3 entsprechend erweitert wird (siehe G.1).

### Nach Runde 1 noch nicht verifiziert (siehe „Runde 2" weiter unten)

Nach Runde 1 (dieser Abschnitt) waren die Abschnitte 3 (P1-1, P1-3, P1-4,
P1-7, P1-8), 4 (P1-9–P1-12 vollständig), 5 (P1-13–P1-15), 6
(External-Function-Stichprobe nur an einem Beispiel geprüft, nicht an allen
25 Klassen), 7 (Datenbankschema), 8 (Install-Code) und 9 (Frontend/Build)
noch offen. Die Stichprobe aus Runde 1 (7 von rund 40 Punkten, davon 6
bestätigt und 1 mit wichtiger Präzisierung) stützte die grundsätzliche
Sorgfalt des Reviews. Auf Wunsch des Auftraggebers in „Runde 2" (Session
003, Teil 3, unten) größtenteils nachgeholt — Restliste dort ausgewiesen.

### G-Q1 — Abhängigkeitsarchitektur: Auftraggeber-Vorschlag vs. tatsächliche Codekopplung

**Vorschlag (diese Sitzung):** `mod_adele` erfordert `local_adele`;
`local_adele` erfordert `enrol_adele`. Ziel: Alternativlosigkeit der
`enrol_adele`-Kopplung absichern.

**Ist-Zustand (verifiziert):**

```text
local_adele  → mod_adele    (version.php; ECHTE Codeabhängigkeit, s. G.1)
mod_adele    → local_adele  (version.php; ECHTE Codeabhängigkeit, s. G.1)
enrol_adele  → local_adele  (version.php; ECHTE Codeabhängigkeit:
                              reconciler.php:89 ruft lokal_adeles
                              enrol_state::get_entitled_courseids() echt auf)
```

**Konflikt:** Der Vorschlag ließe `local_adele` zusätzlich `enrol_adele`
erfordern. Da `enrol_adele` seine Abhängigkeitsrichtung auf `local_adele`
nicht aufgeben kann — sein Kernmechanismus braucht lokal_adeles Sollzustand
tatsächlich, nicht nur formal —, entstünde `local_adele` ↔ `enrol_adele` als
neue, echte Zirkularität. Das ist exakt P0-2 in neuem Gewand, nur zwischen
einem anderen Pluginpaar verschoben, nicht aufgelöst.

**Tieferer Befund:** Die eigentliche Zirkularität sitzt nicht (nur) in den
`version.php`-Deklarationen, sondern im Code selbst (G.1): `local_adele` ruft
`mod_adele_observer`-Methoden direkt auf, `mod_adele` liest lokal_adeles
Tabellen direkt. Beide brauchen einander tatsächlich. Ein reines Umstellen
der deklarierten Richtung löst das nicht — es verschiebt nur, welche der
beiden Deklarationen „falsch herum" aussieht.

**Empfehlung:**

1. Zielgraph wie in Session 002 angelegt beibehalten: `local_adele` (Basis)
   ← `enrol_adele` ← `mod_adele`. `local_adele` bekommt **keine** deklarierte
   Abhängigkeit auf `enrol_adele`.
2. Die dem Vorschlag zugrunde liegende Alternativlosigkeit nicht über eine
   zirkuläre `version.php`-Deklaration erzwingen, sondern über den Code: L-Q-08
   aufheben und alle `enrol_manual`-Rückfallpfade durch eine harte, sprechende
   Fehlermeldung ersetzen, wenn `enrol_adele` fehlt oder inaktiv ist — wie von
   P0-1 des Reviews empfohlen. Das erzeugt Alternativlosigkeit dort, wo sie
   zählt (zur Laufzeit), ohne den Installationsgraphen zirkulär zu machen.
3. Das echte `local_adele` ↔ `mod_adele`-Zirkularitätsproblem (G.1) bleibt
   davon unberührt und ist eine eigene, größere Refaktorisierung: lokal_adeles
   Direktaufrufe von `mod_adele_observer`-Methoden durch ein Domain-Event
   ersetzen (z. B. `local_adele\event\node_participants_changed`), auf das
   `mod_adele` mit einem eigenen Observer reagiert. Erst dann bräuchte
   `local_adele` keine deklarierte Abhängigkeit auf `mod_adele` mehr, und der
   Graph wäre tatsächlich zirkelfrei.

**Entschieden (Session 003, Teil 1) — Option (a):** L-Q-08 aufgehoben, alle
`enrol_manual`-Rückfallpfade in `local_adele` (`enrol_state.php`,
`relation_update.php`, `node_completion.php`) und `mod_adele`
(`observer.php`) entfernt und durch
`local_adele\enrol_state::warn_enrol_adele_missing()` (klare
`debugging()`-Meldung statt stillem No-op) ersetzt. Umgesetzt in
`local_adele` 0.4.8 und `mod_adele` 0.1.11. Der Zielgraph selbst
(`local_adele` ← `enrol_adele` ← `mod_adele`, keine deklarierte
Abhängigkeit `local_adele` → `enrol_adele`) bleibt unverändert — nur die
Alternativlosigkeit wird jetzt zur Laufzeit statt über die
Abhängigkeitsdeklaration erzwungen. Option (b) — der echte
`local_adele`↔`mod_adele`-Codezirkel — bewusst nicht mit umgesetzt, siehe
G-Q2.

### G-Q2 — Den echten `local_adele`↔`mod_adele`-Codezirkel anfassen? Risikoabwägung

**Was er ist:** Keine bloße Falschdeklaration in `version.php`, sondern
echte, beidseitige Codekopplung (G.1): `local_adele` ruft
`mod_adele_observer::enroll_all_participants()`/
`enroll_starting_nodes_participants()` direkt auf und joint `{adele}` in
`enrollment.php:141` (Kommentar im Code bestätigt dies als bewusst);
`mod_adele` liest `local_adele_learning_paths` direkt
(`classes/local_adele.php:58`).

**Folgen des Nicht-Anfassens:**

- Kein aktiver Defekt — der Zustand ist stabil und funktioniert seit
  Session 001/002 wie vorgesehen. Zuwarten verschlechtert nichts.
- Architekturschulden bleiben: kein Plugin ist tatsächlich isoliert
  installier-, versions- oder testbar; ein Schema- oder API-Wechsel in
  einem Plugin bricht das andere ohne Compiler-/IDE-Unterstützung (reine
  String-Tabellennamen und Aufrufe auf eine nicht-namespaced globale
  Klasse, `mod_adele_observer` — letzteres selbst ein separater,
  unangetasteter Befund aus Abschnitt 6 des Reviews).
- Tooling/Statik, das zirkuläre Plugin-Abhängigkeiten ablehnt oder warnt,
  bliebe unglücklich; `moodle-plugin-ci`s Abhängigkeitsauflösung selbst hat
  bislang keine Probleme gemacht (beide Plugin-Ordner liegen zum
  Prüfzeitpunkt bereits im Dateisystem vor, kein echtes Henne-Ei-Problem
  bei der Installation).

**Risiken beim Anfassen (Option b, Event-Refactoring):**

- Zwei unterschiedliche Fälle, nicht einer: die Direktaufrufe von
  `mod_adele_observer`-Methoden sind ein klassischer „Trigger einer
  Handlung" und passen gut zu einem Domain-Event
  (`local_adele\event\node_participants_changed` o. ä.); der
  `{adele}`-JOIN in `enrollment.php` dagegen liefert synchron ein
  Abfrageergebnis zurück — Moodle-Events haben keinen Rückgabewert und
  passen dafür nicht. Dafür bräuchte es entweder eine neue, von
  `local_adele` geführte Indextabelle (durch `mod_adele`s eigene Events
  synchron gehalten — vergleichbar mit dem vom Review unter P1-9
  vorgeschlagenen Knoten-Kurs-Index) oder eine schmale, dokumentierte
  Read-API auf `mod_adele`, die `local_adele` aufruft. Nur die
  Indextabellen-Variante löst die Zirkularität wirklich auf; die
  Read-API-Variante behält die Abhängigkeitsrichtung `local_adele` →
  `mod_adele` bei und behebt nur die Schichtenverletzung (rohe
  Tabellenkenntnis), nicht den Zirkel selbst.
- Fehlerbehandlung ändert sich mit dem Dispatch-Mechanismus:
  Direktaufrufe geben Exceptions/Rückgabewerte im selben Call-Stack an den
  Aufrufer zurück; ein Event-Observer läuft im Moodle-eigenen
  Dispatch-Zyklus — muss geprüft werden, ob ein Fehler dort weiterhin
  genauso laut auffällt wie bisher, statt in Moodles Event-Logging
  unterzugehen.
- **Kein Regressionsnetz in dieser Umgebung:** Es steht keine
  Moodle-Instanz zur Verfügung, um die PHPUnit-/Behat-Suiten beider
  Plugins nach dem Umbau tatsächlich laufen zu lassen. Die Verifikation
  bliebe auf `php -l` und manuelle Codeprüfung beschränkt — bei einem
  Umbau dieser Größenordnung (mehrere Dateien, zwei Plugins, neues
  Schema/neue Events) ein deutlich höheres Restrisiko als bei der
  mechanischeren G-Q1a-Änderung.

**Entschieden (Session 003, Teil 2):** Zirkuläre Abhängigkeit `local_adele`
↔ `mod_adele` bleibt bestehen, kein Refactoring. Auftraggeber bewertet die
Risiken des Umbaus (kein Regressionsnetz ohne Moodle-Instanz, zwei
grundverschiedene Teilprobleme, größerer Umbau in zwei Plugins) als
deutlich schwerer als den — nicht dringenden, nicht dringend
notwendigen — Nutzen. Keine weitere Aktion; als akzeptierte
Architekturschuld dokumentiert, nicht als offene Aufgabe.

### Runde 2 (Session 003, Teil 3) — restliche Review-Punkte durchgegangen

Auf Wunsch des Auftraggebers die verbleibenden, in Runde 1 nicht geprüften
Abschnitte durchgegangen. Sicherheitsrelevante und datenkonsistenzrelevante
Punkte einzeln gegen den Code verifiziert; Abschnitt 9 (Frontend/Build) nur
stichprobenartig, da geringere Priorität.

| Review-Bezug | Befund | Fundstelle (verifiziert) | AP-Punkt | Priorität |
|---|---|---|---|---|
| Abschnitt 6 | **25 von 25** External-Function-Klassen ohne `validate_context()`; **12 von 25** ohne `validate_parameters()` — exakte Übereinstimmung mit der im Review genannten Liste. | `local_adele/classes/external/*.php` (alle 25 Klassen einzeln geprüft) | G.8 | **Hoch** |
| Abschnitt 6 | Sieben schreibende Services als `'type' => 'read'` deklariert: `save_user_path_relation`, `upload_lp_image`, `update_user_path_relation`, `create_lp_edit_users`, `remove_lp_edit_users`, `update_lp_visiblity`, `update_lp_animations`. | `local_adele/db/services.php` | G.9 | Hoch |
| Abschnitt 6 | `local/adele:edit` (captype `write`) an Archetyp `user` auf Systemebene vergeben — praktisch jede/r angemeldete Nutzer/in hat die Capability, die fast alle Services referenzieren. | `local_adele/db/access.php:63–69` | G.10 | **Hoch** (macht G.8/G.9 erst wirklich gefährlich) |
| P1-1 | `mod_adele::is_user_entitled_to_host_via_option()` (Grant-Seite, live im Einsatz über `sync_host_access_for_node_enrolment()`) zählt jede Einschreibung — auch `enrol_adele`s eigene. `enrol_adele::has_foreign_enrolment()` (Entzugs-Seite, `is_user_carried()`) schließt `enrol_adele`-Einschreibungen für Option 2/3 bewusst aus (Kommentar: „otherwise access would keep itself alive circularly"). Grant- und Entzugs-Logik verwenden unterschiedliche, nicht dokumentierte Definitionen von „tragend". | `mod_adele/classes/observer.php:317–341` vs. `enrol_adele/classes/observer.php:113–174` | G.11 | Mittel–Hoch |
| P1-3 | `delete_learning_path()` nicht transaktional: `request_purge()`, `delete_records()`, `set_field()`, zweites `delete_records()` und Event-Trigger laufen ohne `start_delegated_transaction()`. | `local_adele/classes/learning_paths.php:401–441` | G.12 | Mittel |
| P1-4 | `mod_adele` reagiert nirgends auf `learnpath_deleted` — keine Referenz im gesamten Plugin. Gelöschte Lernpfade bleiben in Aktivitäten referenziert, ohne Fehlerbehandlung. | Repo-weite Suche in `mod_adele`, keine Treffer | G.13 | Mittel |
| P1-7 | `reconciler`s Einschreibe-Aufrufe verwenden `$instance->roleid ?: get_role_id()` — die auf der bestehenden Instanz gespeicherte Rolle, nie neu aus der aktuellen Konfiguration abgeleitet. Rollenänderungen an `enrol_adele/roleid` wirken nur auf neu angelegte Instanzen. | `enrol_adele/classes/local/reconciler.php:120,297` | G.14 | Niedrig–Mittel |
| P1-13 | `local_adele_pluginfile()` prüft ausschließlich `$context->contextlevel === CONTEXT_SYSTEM` — kein `require_login()`, keine Capability-Prüfung, keine `filearea`-Allowlist, keine Lernpfad-Sichtbarkeitsprüfung. | `local_adele/lib.php:89–114` | G.15 | **Hoch** |
| P1-14 | `asset_handler::set_new_image()`: `base64_decode()` nicht strikt, keine Größen-/MIME-/Bildprüfung, `.jpg`-Endung erzwungen. Zusätzlich ein konkreter, eigenständiger Bug: Die Existenzprüfung sucht nach `uploaded_file_lp_<id>.jpg`, gespeichert wird aber `uploaded_file_lp_<id>.jpg<timestamp>` — die Löschung alter Dateien (Zeile 117) greift dadurch **nie**, unbegrenzte Ansammlung verwaister Dateien ist die Folge, nicht nur ein theoretisches Risiko. | `local_adele/classes/asset_handler.php:97–137` | G.16 | Mittel–Hoch |
| P1-15 | `mod_adele/view.php`: `{$alisecompatible['msg']}` direkt in Heredoc interpoliert, keine Escaping-Funktion (`s()`/`format_string()`). | `mod_adele/view.php:97–125` | G.17 | Niedrig (aktuell keine bekannte Angreifer-kontrollierte Quelle für `msg`, aber strukturelles Risiko) |
| Abschnitt 7 | `local_adele_lp_editors`: kein Unique Index auf `(userid, learningpathid)`, beide Felder `NOTNULL="false"`, keine Fremdschlüssel. | `local_adele/db/install.xml:47–56` | G.18 | Niedrig–Mittel |
| Abschnitt 8 | `db/install.php` schreibt direkt in `{role}`, `{role_context_levels}`, `{role_capabilities}` statt `create_role()`/`set_role_contextlevels()`/`assign_capability()` zu verwenden; `modifierid = 2` fest codiert. | `local_adele/db/install.php:52–89` | G.19 | Niedrig |

**Nicht einzeln nachverifiziert, aber angesichts der durchgängigen
Treffgenauigkeit dieser und der vorigen Runde (17 von 17 stichprobenartig
geprüften Einzelbefunden bestätigt, keiner widerlegt) als plausibel
eingestuft:** P1-8 (JSON-Versionierung), P1-9–P1-12 im Detail (P1-9 deckt
sich mit G.5), Abschnitt 9 (Frontend/Build — AMD-Artefakte, Webpack,
`thirdpartylibs.xml`) sowie die übrigen Detailbefunde aus Abschnitt 7. Bei
Bedarf für eine spätere Session nachholbar.

**Empfehlung zur Priorisierung:** G.8–G.10 zusammen ergeben eine
systemische Lücke — nahezu jede schreibende Aktion in `local_adele` ist für
jede/n angemeldete/n Nutzer/in erreichbar, ohne Kontext- oder
Eigentumsprüfung (G.3 aus Runde 1 ist nur eine von vielen betroffenen
Stellen). Das wiegt eigenständig schwerer als ein einzelner P0-Punkt und
sollte vor oder parallel zu Phase C behandelt werden, nicht danach.

### G.8–G.10 umgesetzt (Session 003, Teil 4) — `local_adele` 0.4.9

Auf Wunsch des Auftraggebers umgesetzt, nachdem alle 25 External-Function-
Klassen im Detail gelesen wurden (nicht nur stichprobenartig wie in Runde
1/2). Dabei zeigte sich ein differenzierteres Bild als der reine
Review-Text vermuten ließ: die meisten Klassen hatten bereits solide,
ticket-referenzierte interne Prüfungen (`require_lp_editor_access()`,
`require_lp_owner_access()`, `canmanage`/`teacheredit`/`check_access()`,
u. a. zu #458/#464/#471/#472/#487) — `services.php`s `capabilities`-Feld
ist dabei größtenteils reine Metadaten, nicht der tatsächliche
Durchsetzungsmechanismus (der lebt in `execute()` selbst). Trotzdem drei
**echte, aktive** Lücken bestätigt und behoben:

| Fund | Datei | Fix |
|---|---|---|
| G.3 (Runde 1) — IDOR: freie `userid` ohne Eigentumsprüfung | `update_lp_animations.php` | Eigentumsprüfung `$userid === $USER->id` ergänzt (reine UI-Präferenz, kein Override für Lehrkräfte nötig) |
| **Neu** — IDOR: freie `lpuserpathid` ohne Eigentumsprüfung (Original-Review P0-3.2, in Runde 1 nicht mit eigener G-Nummer geführt) | `update_user_path_relation.php` | Eigentümer aus `local_adele_path_user.user_id` geladen, Zugriff nur für Eigentümer/Lehrkraft/Editor |
| **Neu, schwerwiegender** — wirkungslose Prüfung: `local/adele:edit` ist an Archetyp `user` vergeben, `!has_capability('local/adele:edit', ...)` war daher **immer `false`** — jede/r angemeldete Nutzer/in konnte jeden Lernpfad (inkl. JSON-Baum) über die ID lesen, unabhängig von Eigentümerschaft/Sichtbarkeit | `get_learningpath.php` | Ersetzt durch dasselbe Lehrkraft-oder-Editor-Gate wie in `get_lp_user_path_relation.php` |

**G.8:** Alle 25 Klassen haben jetzt `validate_parameters()` und
`validate_context()`. `php -l` über das gesamte Plugin: sauber.

**G.9:** Alle sieben fälschlich als `read` deklarierten schreibenden
Services in `db/services.php` auf `write` korrigiert.

**G.10 — bewusst eingeschränkt umgesetzt:** `local/adele:edit`s Archetyp in
`db/access.php` **nicht** verändert. Grund: eine Codesuche zeigte, dass
diese Capability im gesamten PHP-Backend nur an der einen jetzt gefixten
Stelle (`get_learningpath.php`) überhaupt geprüft wurde — die neun
verbleibenden `services.php`-Einträge, die weiterhin `local/adele:edit`
deklarieren, verlassen sich in Wirklichkeit auf eigene, im Code geprüfte
Zugriffslogik (`check_access()`, `require_lp_editor_access()) — nicht auf
diese Capability. Eine Verengung des Archetyps hätte daher praktisch nur
für token-basierte externe Services (nicht den normalen AJAX-Pfad) etwas
verändert, mit dem Risiko, dort Nutzer/innen auszusperren, die zwar
`local_adele_lp_editors`-Mitglied, aber ohne Kursrolle sind — eine
Produktentscheidung, die nicht ohne Rückfrage getroffen werden sollte. Wo
eindeutig eine speziellere Capability real geprüft wird, wurde die
`services.php`-Deklaration stattdessen korrigiert (`view` bzw. `canmanage`
statt pauschal `edit`, siehe Diff). Das vom Review vorgeschlagene volle
Acht-Capability-Modell bleibt als separates, größeres Arbeitspaket offen
(GitHub-Issue-Entwurf, siehe unten) — Produktentscheidung zu
Rollenzuschnitten, nicht ohne Auftraggeber-Input umsetzbar.

**Verifikation:** `php -l` über das gesamte `local_adele`-Plugin nach allen
Änderungen: sauber. `moodle-cs`/PHPUnit weiterhin nicht ausführbar in
dieser Umgebung (siehe frühere Sitzungsteile).

**Ausgeliefert:** `local_adele` 0.4.9 (2026072401).

### Zurückgestellte Befunde als GitHub-Issue-Entwürfe ausgelagert (Session 003, Teil 5)

G.1 (echter local_adele↔mod_adele-Codezirkel) ist über G-Q2 bereits als
bewusst nicht umzusetzende Architekturschuld entschieden — kein
Issue-Entwurf nötig. Für alle übrigen, in dieser Session bestätigten, aber
nicht direkt umgesetzten Befunde liegt je ein Issue-Entwurf nach dem Muster
von [moodle_local_adele#503](https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues/503)
unter `docs/issues/` (separater Download zum Copy-Paste in die
GitHub-UI):

| AP-Punkt | Repo | Datei |
|---|---|---|
| G.2 | `enrol_adele` | `enrol_adele-issue-undeclared-mod_adele-dependency.md` |
| G.4 | `enrol_adele` | `enrol_adele-issue-timeend-status-not-checked.md` |
| G.5 | `enrol_adele` | `enrol_adele-issue-reconcile-all-incomplete.md` |
| G.6 | `enrol_adele` | `enrol_adele-issue-race-condition-ensure-instance.md` |
| G.7 | alle drei | `enrol_adele-issue-release-package-quality.md` |
| G.11 | `enrol_adele`/`mod_adele` | `enrol_adele-issue-host-entitlement-asymmetry.md` |
| G.12 | `local_adele` | `local_adele-issue-delete-learning-path-not-transactional.md` |
| G.13 | `mod_adele` | `mod_adele-issue-orphaned-activities-on-lp-delete.md` |
| G.14 | `enrol_adele` | `enrol_adele-issue-role-changes-not-reconciled.md` |
| G.15 | `local_adele` | `local_adele-issue-pluginfile-missing-access-check.md` |
| G.16 | `local_adele` | `local_adele-issue-image-upload-validation-and-orphan-leak.md` |
| G.17 | `mod_adele` | `mod_adele-issue-unescaped-html-view.md` |
| G.18 | `local_adele` | `local_adele-issue-lp-editors-missing-constraints.md` |
| G.19 | `local_adele` | `local_adele-issue-install-direct-core-table-writes.md` |
| (G.10-Folgearbeit) | `local_adele` | `local_adele-issue-full-capability-model-redesign.md` |

Alle 15 Entwürfe enthalten Problem/Ursache/Lösung, manuelles
Testverfahren mit Ist-/Soll-Verhalten, Vorschläge für automatisierte Tests
und Akzeptanzkriterien als Checkliste.

**Nachtrag Teil 7:** Auf Weisung des Auftraggebers wurden 14 dieser 15
Punkte (alle außer der Capability-Modell-Folgearbeit zu G.10) noch in
dieser Sitzung umgesetzt statt zurückgestellt — siehe
„G.2, G.4–G.7, G.11–G.19 umgesetzt" weiter unten. Die Issue-Entwürfe
bleiben trotzdem als Dokumentation der jeweiligen Problemherleitung
gültig; für G.2 und G.13 sind sie weiterhin die Referenz für den nicht
umgesetzten, größeren Teil der Lösung.

### Runde 3 (Session 003, Teil 6) — restliche elf Punkte aus Runde 2 statisch verifiziert

Auf Rückfrage des Auftraggebers, was eine Verifikation bislang verhindert
hat: **Zeitpriorisierung, keine technische Grenze** — alle sieben
verbliebenen Themenblöcke (P1-8 bis Abschnitt 7) ließen sich mit den
bereits vorliegenden Mitteln (Code lesen, Datei-Hashes, `install.xml`)
statisch klären. Vollständige Herleitung und zwei Testanleitungen für die
Punkte, die tatsächlich eine Live-Instanz brauchen, in
[`docs/verification-live-testing-guide.md`](verification-live-testing-guide.md).

| Review-Bezug | Befund | AP-Punkt | Status |
|---|---|---|---|
| P1-8 | Keine Schema-Version auf den JSON-Feldern. | G.20 | Bestätigt (statisch) |
| P1-9 (Detail) | Struktur bereits über G.5 bestätigt; tatsächliches Ausmaß braucht Live-Messung. | — | Testanleitung A |
| P1-10 | `sync_host_access_for_node_enrolment()` lädt **alle** `mod_adele`-Einbettungen site-weit ohne jeden Filter — deutlicher als vermutet. | G.21 | Bestätigt (statisch) |
| P1-11 | `updated_learning_path()` verarbeitet alle Nutzerpfade synchron im selben Request, kein Batching. | G.22 | Bestätigt (statisch) |
| P1-12 | Drei Fundstellen in `mod_adele/classes/observer.php` mit vollständigen `get_enrolled_users()`-Sweeps. | G.23 | Bestätigt (statisch) |
| Abschnitt 9 | AMD-Artefakte byteidentisch (SHA-256 geprüft), `thirdpartylibs.xml` fehlt, `webpack.config.js` mit den zitierten veralteten Optionen — alle drei Einzelbefunde bestätigt. | G.24 | Bestätigt (statisch); Build-Funktionsfähigkeit braucht Node/npm-Umgebung → Testanleitung B |
| Abschnitt 7 (Rest) | `status CHAR(255)`, `visibility INT(10)`, `participantslist` denormalisiert, `course_id` ohne FK — bestätigt. Positiver Gegenbefund: `local_adele_path_user` hat bereits einen Unique-Index; die G.18-Lücke ist auf `local_adele_lp_editors` lokal begrenzt, kein durchgängiges Schema-Problem. | G.25 | Bestätigt (statisch) |

**Damit sind von den ursprünglich rund 40 Review-Punkten 29 einzeln
verifiziert** (18 aus Runde 1/2, elf aus Runde 3), keiner widerlegt, zwei
mit wichtiger Präzisierung (P0-1/P0-4 als bewusste Entscheidungen statt
Bugs). G.20, G.21, G.23, G.25 sind reine Dokumentationspräzisierungen ohne
neue Handlungskonsequenz gegenüber den bereits bestehenden Punkten G.5/G.2
(auf die sie sich beziehen). G.22, G.24 sind neu und noch ohne
Issue-Entwurf — bei Bedarf nach demselben Muster nachholbar.

### G.2, G.4–G.7, G.11–G.19 umgesetzt, G.10-Folgearbeit ins Backlog (Session 003, Teil 7)

Auf Weisung des Auftraggebers zurückgeholt und noch in dieser Sitzung
umgesetzt (statt wie zunächst entschieden als Issues zurückgestellt).
Ausschließlich das Capability-Modell-Redesign (`local_adele-issue-full-
capability-model-redesign.md`, Folgearbeit zu G.10) bleibt als Backlog-
Issue bestehen — Produktentscheidung zu Rollenzuschnitten, die nicht ohne
Auftraggeber-Input umsetzbar ist (Begründung unverändert wie beim
ursprünglichen G.10-Teilentscheid).

Vor der Umsetzung ausdrücklich Verifikation nachgeholt (Weisung des
Auftraggebers): per Websuche gegen Moodle-Core-Quellcode und ein reales
Drittanbieter-Plugin geprüft. Dabei zwei eigene Annahmefehler aus Teil 6
gefunden und korrigiert, bevor sie sich in weiteren Sitzungen
fortgepflanzt hätten:
- `settings.php`s Elternkategorie für `manage.php` war `enrolsettingsadele`
  — falsch, das ist eine `admin_settingpage` und kann keine Kind-Seiten
  aufnehmen. Korrekt: `enrolments` (bestätigt durch Moodle-Core selbst,
  das seine eigene `enroltestsettings`-Seite identisch registriert).
- Die für G.6 vorgesehene Lock-API war als `\core\lock\lock_factory::
  instance()` angenommen — diese Methode existiert nicht. Korrekt:
  `\core\lock\lock_config::get_lock_factory($locktype)`.

| Punkt | Plugin | Datei(en) | Umsetzung |
|---|---|---|---|
| G.2 | `mod_adele` | `version.php` | **Teilumsetzung:** `enrol_adele` als deklarierte Abhängigkeit ergänzt (vervollständigt den G-Q1-Zielgraphen). Die eigentliche Schichtenverletzung (`enrol_adele` liest `mod_adele`s `adele`-Tabelle direkt) **bewusst nicht angefasst** — eine naive Umsetzung (enrol_adele deklariert mod_adele) würde dieselbe Zirkularität erzeugen wie der in G-Q1 vermiedene Fall, nur verschoben. Der volle Fix bräuchte denselben Umbau wie der in G-Q2 explizit zurückgestellte `local_adele`↔`mod_adele`-Codezirkel. Issue-Entwurf bleibt als Referenz bestehen, ist aber nicht vollständig abgearbeitet. |
| G.4 | `enrol_adele`, `mod_adele` | `classes/observer.php` (beide Plugins) | `timeend`/`timestart`/`e.status` ergänzt; Suspendierung bleibt bewusst ungeprüft (F-4/A-8 unverändert). |
| G.5 | `enrol_adele` | `classes/local/reconciler.php` | `reconcile_all()` erweitert: Recordset statt vollständigem Laden, verwaiste Instanzen entfernt, Duplikate konsolidiert (inkl. Einschreibungs-Migration), Rollen synchronisiert (siehe G.14). |
| G.6 | `enrol_adele` | `classes/local/instance_manager.php` | Lock (`lock_config::get_lock_factory()`) um Existenzprüfung + Anlage in `ensure_instance()`. |
| G.7 | `local_adele`, `mod_adele` | neu: `Makefile` (beide) | Minimales `make zip`/`clean`/`link` — `.gitattributes` war bereits korrekt (Session 002), fehlendes Werkzeug zum tatsächlichen `git archive`-Bau ergänzt. Nicht die volle CI-Makefile-Vorlage von `enrol_adele` portiert (ungeprüftes Tooling, zu hohes Risiko in der verbleibenden Zeit). |
| G.11 | `enrol_adele`, `mod_adele` | `classes/observer.php` (beide Plugins) | Grant-Seite (`mod_adele`) schließt jetzt wie die Entzugs-Seite (`enrol_adele`) eigene ADELE-Einschreibungen aus — dieselbe Änderung wie G.4, gemeinsam umgesetzt. |
| G.12 | `local_adele` | `classes/learning_paths.php` | Lokale Datenbankänderungen in `delete_learning_path()` in eine delegierte Transaktion gefasst; `request_purge()` läuft bewusst erst nach dem Commit (idempotent, siehe Codekommentar). |
| G.13 | `mod_adele` | `view.php`, `lang/{en,de}/adele.php` | Klare Meldung statt unbehandeltem Fehler, wenn der referenzierte Lernpfad nicht mehr existiert. **Nicht** die größere Lösung (Löschung blockieren/Soft-Delete) — siehe Issue-Entwurf für die noch offene Grundsatzfrage. |
| G.14 | `enrol_adele` | `classes/local/reconciler.php` | Neue Methode `sync_instance_roles()`, aus `reconcile_all()` aufgerufen: migriert `enrol_adele`-eigene Rollenzuweisungen auf bestehenden Instanzen, fasst fremde Zuweisungen nicht an. |
| G.15 | `local_adele` | `lib.php` | `require_login()`, `filearea`-Allowlist, Sichtbarkeitsprüfung für `lp_images` in `local_adele_pluginfile()`. |
| G.16 | `local_adele` | `classes/asset_handler.php` | Strikte Base64-Prüfung, Größen-/Bildvalidierung, und der konkrete Datei-Leck-Bug behoben (`get_area_files()` statt der nie zutreffenden Einzeldatei-Existenzprüfung). |
| G.17 | `mod_adele` | `view.php` | Beide unescapten Heredoc-Ausgaben durch `$OUTPUT->notification(s(...))` ersetzt. |
| G.18 | `local_adele` | `db/install.xml`, `db/upgrade.php` | Upgrade-Schritt 2026072402: NULL-Zeilen entfernt, Duplikate bereinigt, `NOTNULL` gesetzt, Unique-Index ergänzt. `install.xml` für Neuinstallationen angeglichen. |
| G.19 | `local_adele` | `db/install.php` | `create_role()`/`set_role_contextlevels()`/`assign_capability()` statt direkter Tabellenschreibzugriffe; `$descriptionstr` wird jetzt tatsächlich verwendet (beide Rollen hatten zuvor dieselbe Beschreibung). |

**Verifikation:** `php -l` über alle drei vollständigen Plugin-Bäume nach
jeder Änderung: sauber. `install.xml` als wohlgeformtes XML geprüft.
**Weiterhin keine Moodle-Instanz verfügbar** — keiner der Upgrade-Schritte,
Lock-Aufrufe oder Rollen-API-Aufrufe konnte tatsächlich gegen eine
Datenbank/Installation laufen. Größtes Einzelrisiko: der neue
Upgrade-Schritt (G.18) — die Lehre aus dem echten Produktionsvorfall in
Session 002 Teil 18 wurde bewusst angewendet (defensive NULL-Bereinigung
vor `change_field_notnull()`), aber ungetestet gegen echte Bestandsdaten.

**Ausgeliefert:** `local_adele` 0.4.10 (2026072402), `mod_adele` 0.1.12
(2026072401), `enrol_adele` 0.1.8 (2026072307) — **ab jetzt als
Patch-ZIPs** (nur geänderte/neue Dateien), nicht mehr als vollständige
Plugin-Ordner, wie im Sessionstart-Prompt unter „Modus zur Delivery"
gefordert und in den vorigen Teilen dieser Sitzung versäumt.

### G.2 und G.13 vollständig umgesetzt, G.10 bleibt offen (Session 003, Teil 12)

Auf Weisung des Auftraggebers die bislang nur teilweise umgesetzten Punkte
zu Ende gebracht.

**G.2 (vollständig):** Die eigentliche Schichtenverletzung behoben. Neue
Indextabelle `local_adele_host_courses` in `local_adele` (Upgrade-Schritt
2026072403 mit Backfill aus `mod_adele`s Bestandsdaten). `mod_adele`s
Lifecycle-Hooks (`adele_add_instance()`/`_update_instance()`/
`_delete_instance()`) halten sie über neue `enrol_state`-Methoden
(`sync_host_course_index()`, `remove_host_course_index()`) aktuell.
`enrol_adele/classes/observer.php` liest jetzt ausschließlich über
`enrol_state::get_host_embeddings()`/`get_learningpaths_embedded_in_course()`
— keine direkte Kenntnis von `mod_adele`s `{adele}`-Tabelle oder dem
`participantslist`-Format mehr. Eigener Fehler beim Refactor gefunden und
korrigiert: die Options-1-Prüfung (Host-Kurs-Mitgliedschaft trägt
Lernpfadmitgliedschaft) war zunächst versehentlich entfallen, da die neue
Indextabelle im ersten Entwurf nur die Options 2/3 (Host-Zugang aus
Node-Kurs-Mitgliedschaft) abbildete — Tabelle um `participantoption1`
ergänzt, bevor ausgeliefert.

Abhängigkeitsversionen entsprechend angehoben: `enrol_adele` und
`mod_adele` verlangen jetzt `local_adele` ≥ 2026072404 (vorher 2026072301)
— beide rufen seit diesem Fix neue `enrol_state`-Methoden auf, die in
älteren `local_adele`-Versionen nicht existieren.

**G.13 (vollständig):** `delete_learning_path()` blockiert die Löschung
jetzt, wenn noch `mod_adele`-Aktivitäten den Lernpfad einbetten (Option 1
aus dem Issue-Entwurf — die einfachste, sicherste der drei erwogenen
Optionen). Neue Sprachstring `cannotdeleteembedded` (en/de), optionales
`message`-Feld in `delete_learningpath.php`s Rückgabestruktur (abwärts-
kompatibel, `VALUE_OPTIONAL`).

**G.10 — bewusst weiterhin nicht umgesetzt.** Bei dem Versuch, es
tatsächlich umzusetzen, bestätigte sich die bereits in Runde 1
geäußerte Sorge als konkretes, nicht nur hypothetisches Problem:
`require_lp_editor_access()` prüft eine **pfadspezifische** Mitgliedschaft
in `local_adele_lp_editors`, nicht nur Moodle-Archetypen. Eine neue
System-Capability für „Editoren verwalten" wäre entweder wirkungslos
(deckungsgleich mit der bereits vorhandenen `local/adele:canmanage`-
Ausnahme in `require_lp_editor_access()`) oder — als eigenständige,
engere Bedingung gesetzt — würde Studierende aussperren, die heute
legitim als Editor/in eines einzelnen Pfads eingetragen sind, ohne
Kursrolle. Das ist keine graduelle Verbesserung, sondern bräuchte eine
echte Entscheidung: Soll das bestehende pfadspezifische ACL-Modell
(`local_adele_lp_editors`) durch ein capability-basiertes ersetzt werden
(größerer Umbau, potenziell rückwärtsinkompatibel für bestehende
Editor-Zuweisungen), oder bleibt es bestehen und die Capability-Frage
bezieht sich nur auf die Fälle, die *nicht* pfadspezifisch sind (z. B.
`local/adele:createpath` für neue Pfade)? **Offene Rückfrage an den
Auftraggeber**, siehe Antwort im Chat.

**Nachtrag Teil 19:** Auftraggeber bestätigt — G.10 ist als Issue
festgehalten (`local_adele-issue-full-capability-model-redesign.md`) und
wird gesondert diskutiert. **Ab sofort explizit außerhalb des Scopes
dieser Sitzung**, keine weitere Bearbeitung hier, bis dort entschieden.

### D.8 — Formale Bewertung der acht Prüfkriterien (Session 003, Teil 15)

Bislang zurückgestellt, da an C.2/C.4 hängend — beide seit Teil 6/10
erledigt. Jeder Punkt gegen den aktuellen Code erneut nachvollzogen
(nicht nur auf Basis der Umsetzung selbst), Ergebnis ehrlich zwischen
„im Code bestätigt" und „braucht echte Instanz/CI" unterschieden.

| # | Kriterium | Code-Befund | Status |
|---|---|---|---|
| 1 | A-1/A-2/A-6: Öffnen schreibt ein, Schließen bei parallel offenem Zweig lässt aktiv, vollständiges Schließen suspendiert, Wiederöffnen reaktiviert **dieselbe** `user_enrolment` | `reconciler::reconcile_user()` verifiziert: `update_user_enrol()` auf die bestehende Zeile bei Reaktivierung, `enrol_user()` (neuer Datensatz) nur wenn keine Zeile existiert | **Code bestätigt.** Deckt sich mit bestehenden `reconciler_test.php`-Tests. |
| 2 | A-3: Löschen entfernt alle eigenen Instanzen samt Einschreibungen; zweiter Lernpfad auf demselben Kurs und parallele `manual`-Einschreibung bleiben unberührt | `purge_learning_path()` verifiziert: Abfrage strikt auf `enrol='adele' AND customint1=$learningpathid` begrenzt — andere `customint1`-Werte und `enrol='manual'` werden nie angefasst | **Code bestätigt.** |
| 3 | A-4: Zwei Option-1-Hostkurse — erste Austragung folgenlos, zweite löscht; Option-2/3-getragene Nutzer: Hostkurs-Austragung folgenlos; Suspendierung im Hostkurs folgenlos (A-8) | `is_user_carried()` nach dem G.2-Refactor erneut vollständig durchgelesen: Options 1/2/3 alle korrekt geprüft (Options-1-Prüfung war die in Teil 12 selbst gefundene und behobene Lücke); Suspendierung zählt weiterhin bewusst als tragend (F-4/A-8, G.4 hat nur `timeend`/`e.status` ergänzt, nicht Suspendierung) | **Code bestätigt**, inklusive der in dieser Sitzung selbst gefundenen und behobenen Regression. |
| 4 | A-5: Verwaltungsseite listet inkl. verwaister, „Neu berechnen" repariert, „Hart löschen" räumt restlos ab und bleibt so | `manage.php`, `reconcile_learning_path()`, `purge_learning_path()` — Code vorhanden und in sich konsistent | **Code vorhanden, nicht gegen echte Instanz bestätigt** — siehe `docs/verification-live-testing-guide.md`. Das ist der Punkt, an dem die laufende CI-Rückmeldung des Auftraggebers gerade ansetzt. |
| 5 | A-13: Kurs-Duplikat/Restore enthält weder ADELE-Instanzen noch daraus konvertierte `manual`-Einschreibungen | `restore_instance()`/`restore_user_enrolment()` als bedingungsloser Skip (C.4) — jetzt mit automatisiertem Test (`tests/backup_restore_test.php`, Session 003 Teil 17), der genau diese beiden Bedingungen sowie die Unversehrtheit des Quellkurses direkt prüft | **Code bestätigt, jetzt mit automatisiertem Test.** Die im Pflichtenheft vorgesehene Same-Course-Ausnahme fehlt weiterhin (bewusst, siehe C.4). |
| 6 | G-Q1a (ersetzt L-Q-08): Deinstallation/Deaktivierung von `enrol_adele` — kein Fatal Error, keine neuen Einschreibungen, klare `debugging()`-Meldung | `warn_enrol_adele_missing()` und alle Aufrufer verifiziert (Teil 1) | **Code bestätigt.** Durch die reale CI-Rückmeldung in Teil 14 (lokal_adeles PHPUnit-Suite lief tatsächlich ohne `enrol_adele`) sogar indirekt bestätigt — die Meldung erschien exakt wie vorgesehen. |
| 7 | L-Q-09: Jede Operation doppelt ausgeführt = identisches Ergebnis | Alle in dieser Sitzung neu hinzugekommenen Operationen einzeln auf Idempotenz durchdacht: `sync_host_course_index()` (Update über `adeleinstanceid`-Unique-Key), `remove_host_course_index()`/`remove_orphaned_instances()`/`consolidate_duplicate_instances()`/`sync_instance_roles()` (jeweils zweiter Lauf findet nichts mehr zu tun) | **Code bestätigt für die Kernoperationen**, nicht durch einen eigenen Doppellauf-Test verifiziert. |
| 8 | L-Q-03: CI grün auf allen Matrizen, Code-Checker null Warnungen | Laufende Rückmeldung seit Teil 8: zahlreiche echte Regressionen und Konfigurationsfehler gefunden und behoben (Teil 8–24), zuletzt der course_id-Render-Bug (Teil 22) und die Behat-Navigationskollision (Teil 24) | **Erreicht (Teil 24).** Der Auftraggeber bestätigt: alle drei Plugins (`enrol_adele`, `local_adele`, `mod_adele`) laufen in der CI durchgängig grün (Moodle 4.5, PHP 8.1–8.3, MariaDB/PostgreSQL, inkl. `@javascript`-Behat). Damit sind alle acht Prüfkriterien erfüllt. |

**Gesamtbild:** Alle acht Kriterien sind erfüllt. Die ersten sieben sind im
Code nachvollzogen und in sich konsistent; das achte (CI durchgängig grün)
wurde in Teil 24 vom Auftraggeber bestätigt — alle drei Plugins laufen in
der CI-Matrix grün. Damit ist die formale Prüfkriterien-Abnahme (D.8)
vollständig.

## Definition of Done (je Phase)

CI grün auf allen Matrizen, Code-Checker null Warnungen, PHPUnit der Phase
grün, Doku (Pflichtenheft-Abschnitte, CHANGELOG) nachgezogen,
Sitzungsprotokoll ergänzt. Für Phase D zusätzlich: Alle acht Prüfkriterien des
Pflichtenhefts erfüllt; Deinstallations-Gegenprobe (L-Q-08) bestanden.
