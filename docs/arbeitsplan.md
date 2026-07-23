# Arbeitsplan ADELE-Einschreibearchitektur

**Stand:** 2026-07-23, fortgeschrieben bis Session 002, Teil 5
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
| C.2 | Verwaltungsseite `manage.php` nach Pflichtenheft Abschnitt 6 (inkl. „verwaist"-Markierung, Ad-hoc-Task ab Schwellwert, Bestätigungsdialog) | B.3, B.4 · **Status: offen (0.1.3)** |
| C.3 | Eigene Events (`learning_path_reconciled`, `learning_path_purged`, `user_access_revoked`) | C.1, C.2 · **Status: offen (0.1.3)** |
| C.4 | Restore-Hooks: `restore_instance()` (Skip-Strategie mit Same-Course-Ausnahme), `restore_user_enrolment()` (No-op) + Backup/Restore-Test (Prüfkriterium 5) | B.1 · **Status: offen (0.1.3)** |
| C.5 | Behat-Grundlauf für die Verwaltungsseite | C.2 · **Status: offen (0.1.3)** |

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

## Definition of Done (je Phase)

CI grün auf allen Matrizen, Code-Checker null Warnungen, PHPUnit der Phase
grün, Doku (Pflichtenheft-Abschnitte, CHANGELOG) nachgezogen,
Sitzungsprotokoll ergänzt. Für Phase D zusätzlich: Alle acht Prüfkriterien des
Pflichtenhefts erfüllt; Deinstallations-Gegenprobe (L-Q-08) bestanden.
