# Nachweise zu den offenen Checklistenpunkten #11–#18 (Re-Audit 2026-11-22, Lieferung `2026112300`)

Grundlage: Re-Audit „ADELE_Reaudit_20261122_2153_AK_DoD_Nacharbeiten“ (58/91
abgehakt, #17 geschlossen). Diese Datei ordnet **jedem noch offenen Punkt**
die Stelle im Code und die automatisierten Tests zu, die ihn belegen. Sie
unterscheidet:

- **neu** – in `2026112300` hinzugekommen (Code oder Test),
- **vorhanden** – gab es schon, im Re-Audit nicht zugeordnet,
- **offen** – fehlt weiterhin; Grund genannt.

Alle genannten Tests liefen lokal grün (Prüfstand in `README.md`). Ein DoD
ist erst mit dem grünen CI-Lauf **am Commit** erfüllt; das steht für alle
Punkte aus. Die Checkboxen in GitHub setzt die Auditseite.

Kürzel: `L` = `local_adele/tests/`, `M` = `mod_adele/tests/`,
`LE` = `local_adele/tests/e2e/tests/`, `ME` = `mod_adele/tests/e2e/tests/`,
`J` = `local_adele/vue3/tests/unit/`.

## #11 PHP-/CI-Matrix

| Punkt | Nachweis | Stand |
|---|---|---|
| AK3 alle Testarten auf PHP ≥ 8.2 erhalten | PHPUnit läuft **ohne** übersprungene oder unvollständige Fälle: `L/catquiz_test.php::test_get_catquiz_tests_class_exists_with_records` (bisher übersprungen) prüft jetzt echte catquiz-Testumgebungen; `L/course_completion/conditions/catquiz_test.php::test_get_completion_status_is_true` (bisher unvollständig) prüft die Prozent-Schwelle, dazu der Gegenfall `…_below_the_share_is_not_completed` | **neu** |
| DoD2 verbleibende Funktionstests grün | lokal: PHPUnit local 509, mod 149, enrol 75, `adele_release` 70 auf PostgreSQL und MariaDB, **0 übersprungen, 0 unvollständig**; E2E/Smoke siehe README | CI am gemeinsamen HEAD `2026112300` steht aus |

Befund zu den beiden Fällen (belegt): Der übersprungene Fall war **kaputt**.
Einzeln ausgeführt brach er ab (`course_node_id` als Zahl statt Liste,
gemocktes `$DB`). In der Suite fiel das nicht auf, weil der
`class_exists`-Mock des vorherigen Falls in ihn hineinwirkte und ihn
übersprang. Jetzt setzt `tearDown()` den Mock zurück. Der Fall nutzt
catquiz' eigene Abfrage mit echten Datensätzen und prüft dabei auch, dass
Vorlagen und Tests anderer Kurse nicht gelistet werden.

Der unvollständige Fall scheiterte an einer Lücke im Testaufbau, nicht im
Produkt: Den Anteil richtiger Antworten berechnet catquiz aus einem
Fortschrittsdatensatz, den ein Testversuch nicht hat. Die Bedingung holt
diesen Anteil jetzt über die Methode `percentage_of_right_answers()`; das
Verhalten ist unverändert. Der Test liefert den Anteil selbst: 80 % ergibt
„erfüllt“, 40 % „begonnen, nicht erfüllt“.

`uc09_catquiz_completion_test` überspringt nur auf einer Seite **ohne**
local_catquiz; die CI installiert local_catquiz.

## #12 Gruppenbestenliste

| Punkt | Nachweis | Stand |
|---|---|---|
| AK0-L2 | hängt an der Entscheidung zu `userlist=1` ohne Gruppen (siehe unten). Code: `cohort::learner_roster_scope()`, `get_lp_user_path_relations` | **Entscheidung offen** |
| AK0-L8 (Lokalisierung, Tastatur, Screenreader, keine Einschreibungsänderung) | E2E G1–G3 mit `axeSerious()` auf Rangliste, Gruppenwahl und Abschnitt Nicht-Lernende; G3 Gruppenwahl per Label; `L/group_progress_test.php::test_non_learners` (keine Einschreibung, kein Abo angelegt); Strings en/de | vorhanden |
| DoD0-L1 PHPUnit-/API-Matrix | `L/group_progress_test.php` (16 Fälle, neu: `test_non_learners_roles_and_enrolment_status`), `L/teacher_scope_test.php` (9), `L/live_groups_api_matrix_test.php` (Manipulation × 4 Endpunkte), `M/groups_and_live_settings_test.php::test_contract_matrix` | vorhanden + neu |
| DoD0-L2 Isolation gegen manipulierte cmid/Gruppe/Kontext, `userlist=1` | neu: `L/issue569_userlist_privacy_test.php::test_all_results_setting_other_contexts_give_no_more` (anderes Modul, ADELE-Aktivität eines anderen Pfads, Pfad in fremdem Kurs; Gegenprobe: ohne Pfadprüfung rot); vorhanden: `test_foreign_context_is_refused`, `teacher_scope_test` (Kurs-/Modulkontext-Bypass), API-Matrix | vorhanden + neu |
| DoD0-L3 Jest | `J/components/groups/groups_live.spec.js` (Reihenfolge wie vom Server gerankt, Mittel über alle Lernenden inkl. 0 %, „keine Daten“, eigene vor fremden Gruppen, Namen ohne Link); neu `J/components/user_view/UserList.spec.js` „"all results" without groups: … no row is a way anywhere“ | vorhanden + neu |
| DoD0-L4 Playwright Lernenden-/Lehrendenmatrix, DOM- und API-Nichtoffenlegung, Gruppenwechsel | `ME/groups-live.spec.ts` G1–G3; **neu G4** (zwei Aktivitäten eines Kurses mit **verschiedenen Gruppierungen**: Lehrerin – Angebot und Liste je Gruppierung, Gruppenwechsel, Gruppe der anderen Gruppierung vom Server abgelehnt; Lernender in beiden Gruppierungen – je Aktivität die eigene Gruppe, in sichtbaren Gruppen nur Mittelwerte der eigenen Gruppierung; Lernende außerhalb einer Gruppierung – niemand sonst, auch nicht in den Server-Antworten). Gegenprobe: ohne Gruppierungsfilter G4 rot. G1/G2 prüfen fremde Namen jetzt über den Nachnamen (die Liste trennt Vor- und Nachname; der volle Name hätte ein Leck übersehen) | **neu** |
| DoD0-L5 CI-Matrix, a11y, Query-Regression, keine Pflicht-Skips | Pflicht-Skips: keine mehr (#11). Query: `group_progress_test::test_query_budget`, neu `live_activity_test::test_large_cohort` (300 Lernende, auch Gruppenübersicht) | CI aussteht |

### Entscheidung AK0-L2 / #16 AK0-M4

Am 22.11. (09:52) hat die Auftraggeberseite vorgegeben: „Nur die eigenen
Ergebnisse anzeigen“ wird nur ohne Gruppen angezeigt und wirkt nur dort.
Der Code setzt das so um. Ohne Gruppen gilt:

- Mit „Ergebnisse aller“ sehen Lernende die Lernenden der Aktivität,
  allerdings ohne IDs, ohne Benutzernamen und ohne Link.
- Mit „nur eigene“ sehen sie nur ihre eigene Zeile.

Der Re-Audit verlangt dazu eine ausdrückliche Bestätigung. Sie ist am
23.11. angefragt. Je nach Antwort:

- **A (Stand bleibt):** Text-Entwurf für AK0-L2 und AK0-M4:
  `docs/issues/local_adele-12-16-ak-userlist-entwurf.md`.
- **B (strikt eigene Zeile):** Der Code wird zurückgebaut. Die Einstellung
  hätte dann keine Wirkung mehr und müsste entfallen.

## #13 Nicht-Lernende

| Punkt | Nachweis | Stand |
|---|---|---|
| AK1.5 Doppelrollen, gesperrte/abgelaufene Einschreibung, Kurskontext | neu `L/group_progress_test.php::test_non_learners_roles_and_enrolment_status`: Tutor und Lehrkraft ohne Pfad = „nonlearner“, Lernende ohne Pfad = „nopath“ (auch wenn sie zusätzlich lehren – die Teilnehmerrolle entscheidet), gesperrte und abgelaufene Einschreibung nicht gelistet, Lernende mit Pfad nicht gelistet, Teilnehmerliste unverändert. Kurskontext: `get_non_learners` nur mit Modulkontext (`cohort::contract`, API-Matrix) | **neu** |
| AK1.7 Tastatur/AT, kein Overfetch | E2E G3 (`aria-expanded`, axe), Jest `NonLearners.vue`; neu: nur die Felder `userid, fullname, reason, roles` (PHPUnit, s. o.), keine E-Mail (`test_non_learners`) | vorhanden + neu |
| DoD1.1 PHPUnit Rollen, Tutoren, Lehrkräfte ohne Abo, Doppelrollen, Status | wie AK1.5 | **neu** |
| DoD1.2 API: Lernendenrolle, fremde cmid, fremde Gruppe, Toggle | `test_non_learners` (Lernende abgelehnt), `live_groups_api_matrix_test` (Endpunkt `nonlearners` × fremde cmid/Gruppe/Kontext). Der Toggle ist Client-Zustand: Der Server liefert nur auf Anfrage | vorhanden |
| DoD1.3 Jest + Playwright: Standard verborgen ohne Payload, an/aus, eigener Abschnitt, Hinweis ohne Pfad, Rangliste unverändert | Jest `NonLearners.vue` (verborgen, nichts geladen; Gründe getrennt; Fehler); E2E G3: an/aus, eigener Abschnitt, Liste unverändert; **neu**: vor dem Umschalter keine einzige Anfrage `local_adele_get_non_learners` (mit Gegenbeweis, dass die Beobachtung danach greift). „Ohne Pfad“ im Browser: nicht dauerhaft herstellbar (ADELE abonniert eingeschriebene Teilnehmende selbst) – PHPUnit und Jest | vorhanden + neu |
| DoD1.4 CI-Matrix, a11y | lokal grün | CI aussteht |

## #14 Live-Marker

| Punkt | Nachweis | Stand |
|---|---|---|
| AK2-L6 Polling inkrementell, kein paralleler Request, Tab/Unmount/Fehler, Zustand bleibt | `J/components/teacher_view/TeacherView.live.spec.js` (ein Snapshot je Intervall, nie zwei zugleich, Graph einmal gemountet; pausiert verborgen; beendet beim Unmount; Ablehnung ohne Polling); `J/composables/useUserPathRefresh.spec.js` (16 Fälle: Debounce, Fokus/Sichtbarkeit, In-flight, Backoff, Timeout, stop()); Zustand: E2E L3 | vorhanden |
| AK2-L7 aus → keine Marker, kein Polling; Fenster; Richtlinie | `L/live_activity_test.php::test_switched_off`, `test_window_boundaries`; Jest „switched off“; E2E L2 | vorhanden |
| DoD2-L3 Jest mit Fake-Timern | wie AK2-L6 (alle mit `jest.useFakeTimers()`) | vorhanden |
| DoD2-L4 Playwright | E2E L1 (Marker, Status in Text, Polling ohne Reload, Fenster), L2, L3; Zeitsteuerung über die Testuhr | vorhanden |
| DoD2-L5 Query-Budget und Last großer Kohorten | **neu** `L/live_activity_test.php::test_large_cohort`: 300 Lernende in 4 Gruppen, Snapshot, Gruppenübersicht und Namensseite je mit gleicher Abfragezahl wie bei 5 und unter 1 s, Zählungen exakt (beide Datenbanken). **neu** k6: `tests/load/seed_large.php` legt bei installiertem mod_adele eine Aktivität mit Gruppen und Live-Markern an (Standard jetzt 500 Lernende), `adele-read-endpoints.js` fragt mit `CONTEXTID` je Iteration Snapshot, Gruppenübersicht und Namensliste wie die Lehrendenansicht ab (eigene Trends, p95 < 3 s, Checks > 95 %); `load-k6.yml` reicht den Kontext durch. Lokal geprüft: Seed mit 300 Lernenden; die drei Aufrufe aus einer angemeldeten Seite (102/41/64 ms, Marker und Namen vorhanden). k6 selbst lief hier nicht | **neu**; k6-Lauf in CI aussteht |

## #15 Knotenzähler

| Punkt | Nachweis | Stand |
|---|---|---|
| AK3.6 gemeinsamer Snapshot, kein Polling je Knoten/Person, kein Remount | `live_activity::snapshot()` liefert alle Knoten in einer Antwort; Jest TeacherView.live (eine Anfrage je Intervall, Graph einmal gemountet); `NodeLive.vue` liest nur aus dem Store | vorhanden |
| DoD3.1 PHPUnit | `live_activity_test` (Fenstergrenzen, aktiv überall/inaktiv einmal/nie, gestapelt, wiederverwendeter Kurs, Gleichstand, abgeschlossen + Überlappung); **neu** `test_multiple_groups_and_dual_roles_count_once` (zwei Gruppen einmal gezählt, Doppelrolle als Lernende/r, Tutor ohne Pfad nicht gezählt) | vorhanden + neu |
| DoD3.3 Jest | `groups_live.spec.js` NodeLive (zwei Zähler, Namensseiten, Escape/Fokus, Fehler, abgeschaltet); Snapshot-Aktualisierung TeacherView.live | vorhanden |
| DoD3.5 Query/Performance, DB-Matrix, a11y | wie DoD2-L5; a11y E2E L1 (`axeSerious` auf Marker, Zähler, Liste) | **neu**; CI aussteht |

## #16 Gruppenmodus mod_adele

| Punkt | Nachweis | Stand |
|---|---|---|
| AK0-M4 | hängt an der Entscheidung (wie #12 AK0-L2). Bypass über anderen Kontext: neu `test_all_results_setting_other_contexts_give_no_more`, vorhanden `teacher_scope_test` | **Entscheidung offen** |
| AK0-M6 Upgrade, Duplikat, Backup/Restore, erzwungener Gruppenmodus | **neu** `M/groups_and_live_settings_test.php::test_participant_results_field_follows_the_group_mode`: Feld bei Gruppenmodus versteckt (`hideIf`), bei erzwungenem Gruppenmodus des Kurses entfernt (gespeicherter Wert bleibt), bei erzwungenem „Keine Gruppen“ sichtbar; nach Wechsel auf Gruppen und zurück gilt der gespeicherte Wert wieder (Gegenprobe: ohne die Formularregel rot). `test_backup_restore_duplicate` prüft jetzt auch `userlist` und den Gruppenmodus nach Duplikat und Import. Upgrade: `test_upgrade_of_an_old_installation` | **neu** |
| DoD0-M3 Behat/Playwright mit mehreren Einbettungen und unterschiedlichen Gruppierungen | **neu** E2E G4 (siehe #12 DoD0-L4) | **neu** |

## #18 DOM-IDs

| Punkt | Nachweis | Stand |
|---|---|---|
| AK4 zweite Quelle `dropdownMenuButton`, Navigation bei mehreren Einbettungen und Themes | Zweite Quelle: local_catquiz (`lib.php`, entfernt in local_catquiz `2026101701`, Commit `8a62ce30`), siehe `local-18.md`. Weitere Fundstellen im ganzen Moodle-Baum: core `activitychooserbutton.mustache` setzt `dropdownMenuButton-<id>` (eindeutig), local_wunderbyte_table nur eine Klasse. **neu** E2E A18 „three ADELE applications…“ im **Classic-Theme**: keine doppelte ID, kein `dropdownMenuButton`, Navigationslink ohne ID führt zur Übersicht | **neu** |
| AK5 Label fokussiert Feld, Titel, Escape, Fokus zurück | E2E A18 (Knotendialog) und E2 (Kursdialog): Titel, Label-Namen, Escape, Fokus zurück; **neu** Klick auf das Label fokussiert das Feld (beide Dialoge) | vorhanden + neu |
| AK6 Dialoge funktional unverändert | E2E-Editor-Suiten (Speichern, Wiederöffnen), Jest Dialoge; Feedback-Dialog ohne Einstieg: `docs/issues/local_adele-issue-feedbackmodal-unused.md` | vorhanden |
| AK7 (abgehakt) – **neuer Befund** | Die MiniMap von VueFlow gab jedem Rechteck die Knoten-ID (`dndnode_1`). Mit zwei Pfaden auf einer Seite und einer MiniMap (Fluss breiter als 768 px, etwa im Classic-Theme) stand dieselbe ID dreimal auf der Seite (belegt: E2E im Classic-Theme). Behoben: `MiniMapNodeShape.vue` zeichnet die Knoten ohne ID. Jest `J/components/flowchart/MiniMapNodeShape.spec.js`, E2E Classic-Schritt | **neu** |
| DoD2 Node-, Course-, Feedback-Dialog | Knoten- und Kursdialog: siehe AK5. Feedback-Dialog: in der Oberfläche nicht erreichbar, Issue-Entwurf liegt vor | Feedback offen (Entscheidung zum Dialog) |
| DoD3 zwei Dialoginstanzen, Mehrfacheinbettung, `dropdownMenuButton` | Editor: Knoten- und Kursdialog gleichzeitig gemountet, keine ID doppelt; derselbe Dialogtyp zweimal: Jest `issue18_dom_ids.spec.js`. Im Browser gibt es keine Seite mit zwei Editoren; Mehrfacheinbettung: A18 drei Anwendungen (Boost und Classic) | vorhanden + neu |
| DoD6 alle Gates am selben Commit | Jest-Workflow: Gate vor `npm install` (`2026112201`); Lint-Gate jetzt mit Vue-3-Regeln (s. u.) | CI aussteht |

### Lint-Baseline (gesondert beurteilt)

Die ESLint-Konfiguration nutzte `plugin:vue/essential`, also die
**Vue-2-Regeln**, obwohl die Anwendung Vue 3 ist. Belegt durch einen Lauf
mit `plugin:vue/vue3-essential`:

- Vier der 92 Befunde waren Vue-2-Fehlalarme: `no-multiple-template-root`
  (2) und `no-reserved-props` (2). In Vue 3 sind Fragmente erlaubt.
- Ein echter Vue-3-Befund fehlte: `router-link tag="button"` in
  `NotFound.vue`. Vue Router 4 ignoriert diese Eigenschaft.

Umgestellt auf `vue3-essential`, das `tag` entfernt (der Link sah schon
vorher wie ein Knopf aus, über die Klasse). Die Baseline sinkt dadurch von
92 auf 88. Es bleiben:

| Regel | Anzahl | Einordnung |
|---|---:|---|
| `no-unused-vars` | 79 | ungenutzte Importe und Variablen, kein Laufzeitfehler; Aufräumen ist eigene Arbeit |
| `vue/no-mutating-props` | 6 | Komponenten schreiben in Props (Abschluss- und Restriktionseditor) – Funktion erhalten, Refactoring eigenes Issue |
| `no-irregular-whitespace` | 2 | Sonderleerzeichen im Quelltext |
| `vue/return-in-computed-property` | 1 | ein computed ohne Rückgabe in einem Zweig |

Das Gate bleibt eine Ratsche: kein neuer Befund über die Baseline.
