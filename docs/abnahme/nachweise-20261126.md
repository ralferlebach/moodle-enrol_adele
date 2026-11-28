# Nacharbeiten zum Re-Audit vom 2026-11-26 (Lieferung `2026112600`)

Grundlage: „ADELE_Reaudit_20261126_Nacharbeitsauftrag“ (73/91, #17
geschlossen). Die Datei ordnet jedem Auftrag zu, was geändert, was geprüft
und was offen ist. Die Zuordnung vom 23.11. (`nachweise-20261123.md`) gilt
weiter, soweit hier nichts anderes steht.

**Für alle DoD gilt:** Erfüllt sind sie erst mit dem grünen CI-Lauf auf
`development` am Commit. Die Zahlen hier sind lokale Läufe nach
`pruefkette/README.md`.

## P0 – `NOGROUPS` × `userlist` (#12 AK0-L2/DoD0-L2, #16 AK0-M4)

**Entschieden: Variante A**, freigegeben von der Auftraggeberseite am
2026-11-26 um 21:56 („Entscheidung: A“). Der Code bleibt, wie er seit der
Vorgabe vom 22.11. ist. Die AK-Texte für #12 und #16 stehen einfügefertig in
`issues/local_adele-12-16-ak-userlist-entwurf.md` (mit Freigabevermerk).

Die Tests prüfen die erlaubten Felder und die Nicht-Navigierbarkeit:

| Sollmatrix | Nachweis |
|---|---|
| `NOGROUPS` + `userlist=1`, Kontext der Aktivität: Lernende der Aktivität; fremde Zeilen mit ID < 0, leerem Benutzernamen | `issue569_userlist_privacy_test::test_all_results_setting_lists_the_learners_without_ids`, `group_progress_test::test_no_groups_all_results_lists_the_activitys_learners`, E2E P2/P3 |
| … nicht navigierbar: kein Link, kein Knopf; die Ersatz-ID öffnet keinen Pfad | Jest `UserList.spec.js`, E2E P2 |
| `NOGROUPS` + `userlist=2`: nur die eigene Zeile | `group_progress_test::test_no_groups_only_own_results`, E2E P2 (Rücknahme) |
| anderer Kontext (Kurs, anderes Modul, ADELE-Aktivität eines anderen Pfads, Pfad in fremdem Kurs): nur die eigene Zeile oder Ablehnung | `issue569_userlist_privacy_test::test_all_results_setting_other_contexts_give_no_more` (Gegenprobe rot), `group_progress_test` (Kurskontext) |
| mit Gruppenmodus: `userlist` ohne Wirkung, Gruppenmatrix | `…_with_groups_returns_the_own_row`, `test_participant_results_apply_without_groups_only`, E2E G1–G4, `withdrawn-settings` |
| Formular: Feld nur bei „Keine Gruppen“, auch bei erzwungenem Gruppenmodus; Wert bleibt erhalten | mod `test_participant_results_field_follows_the_group_mode` |

Lokal sind alle Nachweise grün. DoD = derselbe Commit in der CI.

## P1 – ADELE-Einschreibungsstatus im Roster (#13 AK1.5; #12, #14, #15)

| Fall | vorher (belegt) | ab `2026112600` |
|---|---|---|
| ADELE aktiv, Instanz an, im Zeitfenster | Lernende/r | Lernende/r |
| ADELE gesperrt, Einbettung „sichtbar“ (z. B. Person hat den Pfad verlassen, Karenzzeit) | **Lernende/r** | nicht im Roster |
| ADELE gesperrt, Einbettung „verborgen“ (#588: eingeschrieben ohne Zugriff) | Lernende/r | Lernende/r |
| ADELE gesperrt, „verborgen“ nur bei einem **anderen** Pfad im Kurs | **Lernende/r** | nicht im Roster |
| ADELE `timeend` vorbei | **Lernende/r** | nicht im Roster |
| ADELE `timestart` in der Zukunft | **Lernende/r** | nicht im Roster |
| ADELE-Instanz deaktiviert | **Lernende/r** | nicht im Roster |
| ADELE gesperrt + zweite aktive Quelle (manuell) | Lernende/r | Lernende/r |
| Lehrenden-Zugang `customint2=3` | – | nie Lernende/r |

- **Code:** `local_adele/classes/learning_paths.php::roster_members_sql()`.
  Die Zeit kommt über die Uhr (#582); `{adele}` wird nur gelesen, wenn
  mod_adele installiert ist.
- **Tests:** `roster_adele_enrolment_status_test.php` (neu, 3 Fälle).
  Dieselben Personen werden geprüft in der Teilnehmerliste der Lehrenden,
  den Live-Markern, dem Live-Zähler, der Gruppenübersicht, der Liste der
  anderen Personen und in `is_roster_member()`. **Gegenprobe:** auf dem alten
  Code 3 von 3 rot.
- **Angepasst:** `security/roster_course_scope_test` bildet den Modus
  „verborgen“ jetzt ausdrücklich nach.
- **Bekannte Grenze:** In einem Kurs mit „verborgener“ Einbettung bleibt
  eine Person, die den Pfad verlassen hat, bis zum Ende der Karenzzeit
  sichtbar. Am Datensatz lassen sich die beiden Sperrungen nicht
  unterscheiden.
- Issue-Entwurf: `issues/local_adele-issue-roster-adele-enrolment-status.md`.

## P1 – Browser auf `development` (#11–#14, #16, #18)

Die CI-Läufe auf GitHub kann nur die Auftraggeberseite anstoßen. Lokal lief
derselbe Gesamtlauf wie in der CI (Zahlen in `README.md`).

- Die Workflows warnen seit `2026112301`, wenn nicht `development` geprüft
  wird.
- Für die Abnahme die drei E2E-Workflows und die drei Smoke-Workflows unter
  „Use workflow from: development“ starten. In `environment.md` stehen die
  Commits.

## P1 – Lasttest mit Live-Endpunkten (#14 DoD2-L5, #15 DoD3.5)

- `adele-read-endpoints.js`:
  - Der Live-Teil ist **Pflicht** (`LIVE=off` nur ausdrücklich). Ohne
    `CONTEXTID` bricht der Lauf in `setup()` ab.
  - Jeder beantwortete Live-Aufruf zählt in `adele_live_calls{method}`.
    Für **jeden** der drei Endpunkte gilt die Schwelle `count>0`, dazu
    p95 < 3000 ms und `checks` > 95 %.
- `load-k6.yml`:
  - neue Eingabe `live` (required/off);
  - ohne Kontext bricht der Lauf mit `::error` ab. Das gilt für
    `selfcontained`, wenn mod_adele im Seed fehlt, und für `external` ohne
    Eingabe;
  - neuer Schritt „Summarise the live endpoints“: Aufrufe, p95 und Fehler
    landen in der Zusammenfassung des Laufs.
- Lokal: Skript-Syntax geprüft, Zusammenfassung an Beispieldaten geprüft;
  die drei Aufrufe gegen 300 Lernende waren schon am 23.11. geprüft.
  **k6 selbst lief hier nicht.**

## P1 – Nicht-Lernende, UI und API (#13 DoD1.2–1.4)

- **E2E G5 (neu, Fixture G16M + Theo, Tutor in Mitte):**
  - Fehlerfall: Die Antwort wird abgebrochen, der Fehlertext erscheint, kein
    Name wird gezeigt; beim nächsten Öffnen wird neu geladen.
  - Bei offener Liste und Wechsel Nord → Mitte → alle → Nord folgt die
    Liste: Theo erscheint nur bei Mitte und bei allen, Tessa nie (sie ist
    außerhalb der Gruppierung).
  - Es erscheinen keine Lernenden, die Rangliste bleibt unverändert, die
    axe-Prüfung ist grün.
  - Ein Lernender sieht keinen Schalter, und eine direkte Anfrage an den
    Server wird abgelehnt (`ok: false`); die Prüfung hängt also nicht am
    Schalter.
- G3 prüft weiter: keine Anfrage vor dem Umschalten.
- Den Schalter gibt es nur im Client; einen Serverparameter dafür gibt es
  nicht und soll es nicht geben.

## P2 – DOM-IDs (#18 AK4, DoD2/3/6)

- **E2E A18:**
  - Neuer Schritt in **Boost mit sichtbarer MiniMap**: Boost begrenzt die
    Inhaltsbreite; gemessen gab es auch bei 2400 px keine MiniMap. Der
    Schritt hebt die Begrenzung auf, wie eine Seite mit voller
    Inhaltsbreite, und prüft MiniMap-Knoten, doppelte IDs, Referenzen und
    `dropdownMenuButton`.
  - Der Classic-Schritt prüft jetzt auch, dass die MiniMap sichtbar ist.
- **local_catquiz:** Auf `main` steht `id="dropdownMenuButton"` noch,
  auf `ALiSe-v-1.2.0-legacy` nicht (belegt). Zu entscheiden: welche Branches
  unterstützt werden, und ob `main` den Fix bekommt. Entwurf:
  `issues/local_catquiz-main-dropdownmenubutton.md`.
- **Feedback-Dialog (DoD2): Entscheidung offen.** Entweder einen Einstieg
  schaffen und E2E testen, oder den Dialog fachlich freigeben und entfernen.
  Entwurf: `issues/local_adele-issue-feedbackmodal-unused.md`. Kein
  Prüfkriterium wird stillschweigend gestrichen.

## P2 – Werkzeugkette und Lint

- **Version festschreiben:** Der gemeinsame Workflow
  (`Wunderbyte-GmbH/catalyst-moodle-workflows`, `ci.yml`, Stand 2026-11-23)
  hat **keine** Eingabe für die moodle-plugin-ci-Version (`create-project … ^4`
  an zwei Stellen). Aus den Plugins heraus lässt sie sich nicht
  festschreiben; Entwurf `issues/catalyst-moodle-workflows-pin-plugin-ci.md`.
  Bis dahin installiert `pruefkette/ci_gate.sh` die Werkzeuge bei jedem Lauf
  so frisch wie die CI.
- **`ci_gate.sh`** liest jetzt auch `ignore_names` und
  `codechecker_ignore_paths` wie der gemeinsame Workflow.
  `phpdocchecker_ignore_paths` wirkt dort nicht: moodle-plugin-ci liest
  `PHPDOC_IGNORE_PATHS`. Das Gate bildet das ebenso ab.
- **Lint:** Baseline von 88 auf **85** gesenkt.
  - `vue/return-in-computed-property` in `useStatusMessage.js` behoben:
    Fälle ohne passende Kombination geben jetzt ausdrücklich `''` zurück
    statt nichts. Die Verbraucher (`switch` mit `default`, `v-if`) behandeln
    beides gleich; die beiden Jest-Fälle, die `undefined` festhielten,
    erwarten jetzt `''`.
  - 2 × `no-irregular-whitespace` behoben (geschützte Leerzeichen im Code).
- **Die 6 `vue/no-mutating-props`, einzeln bewertet:** Alle schreiben in
  **verschachtelte Felder** eines Objekt-Props (`data.manualcompletionvalue`,
  `data.manualrestrictionvalue`, `completion.information`,
  `learningpath.json.tree`, `data.showCard`). Das Objekt kommt aus dem
  Store bzw. vom VueFlow-Knoten. Vue 3 erlaubt das zur Laufzeit; der Editor
  verlässt sich darauf, dass die Änderung im gemeinsamen Objekt ankommt.
  Laufzeitrisiko gering: keine Zuweisung an das Prop selbst, kein Verlust
  der Reaktivität. Wer sie abbaut, muss Events bzw. Store-Mutationen
  einführen und den Abschluss- und Restriktionseditor neu testen. Das ist
  eigene Arbeit und nur mit Issue, AK und DoD sinnvoll.

## Neuer Befund im lokalen Gesamtlauf (2026-11-26): späte Antworten überschrieben die gewählte Gruppe

- **Gefunden:** Im Gesamtlauf war E2E G5 rot. Nach dem Wechsel Mitte → alle →
  Nord stand Theo, Tutor in Mitte, noch in der Liste der anderen Personen.
- **Ursache, belegt mit Jest:** Die Antworten auf die Anfragen der
  einzelnen Gruppen können in beliebiger Reihenfolge eintreffen. Die zuletzt
  eingetroffene gewann, nicht die zur zuletzt gewählten Gruppe.
  - Betroffen waren `NonLearners.vue` und dasselbe Muster im Store
    (`fetchUserPathRelations`, `fetchGroupOverview`) sowie die Live-Daten
    der Lehrendenansicht.
  - Es gibt kein Datenschutzleck: Jede Antwort betraf eine Gruppe, die die
    Person sehen darf. Angezeigt wurde aber die falsche Gruppe.
- **Behoben:**
  - `NonLearners.vue`: Nur die Antwort auf die letzte Anfrage zählt; nach dem
    Schließen füllt keine Antwort mehr die Liste.
  - Store: Eine Antwort wird verworfen, wenn seit ihrer Anfrage
    ausdrücklich eine andere Gruppe gewählt wurde (Zähler `groupchoice`,
    erhöht nur in `setGroupId`). Dass die Übersicht beim Start die
    Vorgabegruppe auflöst, ist keine Wahl. Eine erste Fassung verglich die
    Gruppen-ID und verwarf dadurch die Pfadbeziehungen beim Start: 18 rote
    lokale E2E-Fälle, belegt und mit dieser Fassung behoben.
  - `TeacherView.vue`: Live-Antworten einer vorigen Gruppe werden verworfen.
- **Tests:**
  - Jest `groups_live.spec.js` („answers out of order“, 2 Fälle). Der Fall
    mit der Reihenfolge war auf dem alten Code rot.
  - Jest `store.groupanswers.spec.js` (4 Fälle), Gegenprobe ohne die Prüfung:
    2 rot. Der vierte Fall bildet den Ablauf der 18 roten E2E-Fälle nach
    (Vorgabegruppe ist keine Wahl).
  - E2E G5 grün, dreimal wiederholt.
- **M1 (Einstellungsabschnitt „Live-Aktivität“), einmal rot:** Der
  Testhelfer klickte, bevor Moodles Formular-JavaScript fertig war, und
  schloss damit den Abschnitt. Jetzt wartet er auf `M.util.pending_js` (wie
  Behat) und entscheidet nach `aria-expanded`. Dreimal wiederholt grün.
  Produktcode ist nicht betroffen.

## Lokaler Gesamtlauf `2026112600` (2026-11-27), CI-nah

Jede Suite lief auf einer frisch geseedeten Seite, ohne Abgleich während des Laufs.

| Suite | Ergebnis |
|---|---|
| Smoke local | 17/17 |
| E2E local Chromium | 83/84, 1 rot (s. u.) |
| E2E local Firefox | 4/4 |
| Smoke mod | 4/4 |
| E2E mod Chromium (mit G5) | 66/66 |
| E2E mod Firefox | 12/12 |
| Smoke enrol | 3/4, 1 rot (s. u.) |
| E2E enrol Chromium | 6/6 |

- **Smoke enrol `manage.spec.ts` „lists the seeded learning path“, Ursache
  belegt, nur lokal:** Die lokale Seite hat über die Läufe 146
  ADELE-Instanzen gesammelt. Die Liste zeigt 50 je Seite, nach Kurs sortiert
  (Bildschirmfoto: Seiten 1–3), und der geseedete Pfad stand nicht auf
  Seite 1. In der CI ist die Seite frisch. Der Test filtert jetzt zuerst nach
  dem Lernpfad. Danach 4/4 grün. Produktcode ist nicht betroffen.
- **E2E local `running-path-change.spec.ts` M2, einmal rot, Ursache offen:**
  Nach dem Klick auf „Zugangskriterien bearbeiten“ von Knoten 2 erschien die
  Bedingungsliste („Manual access“) nicht innerhalb von 30 s. Bildschirmfoto
  und Video hat der anschließende Firefox-Lauf überschrieben. Einzeln 3 von
  3 grün, zusammen mit dem vorher laufenden `roles-transfer-ownership.spec.ts`
  2 von 2 grün. Weder geändert noch „repariert“, solange die Ursache nicht
  belegt ist. In der CI ist es zu beobachten; fällt es dort an, liegt das
  Video bei.
