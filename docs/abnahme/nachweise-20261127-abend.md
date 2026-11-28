# Nachweise zum Re-Audit vom 2026-11-27 abends (0.8.0, Version `2026112701`)

Grundlage ist „ADELE 0.8.0 – Re-Audit und Nacharbeitsauftrag“ vom 27.11.2026
abends (Build `2026112700`, 78/91 erfüllt). Die Release bleibt `0.8.0`, die Version
ist `2026112701`. Protokolle stehen im Nachweispaket
`adele-0.8.0-2026112701-nachweise.zip`, die Kommentare je Issue in
`adele-0.8.0-2026112701-kommentare.zip`.

## P1 – FeedbackModal (#18 DoD2): Option A, entfernt

**Freigabe des Auftraggebers (2026-11-27, 21:28):** „Ja, bitte entfernen.“

Davor erhielt der Auftraggeber Bildschirmfotos und die Herkunft:
- Rest der Feedback-Bearbeitung von 01/2024 (`5f858f2`);
- seit `bf98dfe` (03/2024) ohne Öffner;
- kein Anfang einer Warnung vor ungespeicherten Änderungen.

**Entfernt:**
- `vue3/components/modals/FeedbackModal.vue` und seine Einbindung in `CompletionFlow.vue`;
- `DIALOGS.feedback`;
- `store.state.feedback`;
- `modals_edit_feedback` (de/en).

**Regressionsprüfung des Feedback-Knotens:**
- E2E E1 (`editor-reference-path.spec.ts`), neuer Schritt:
  - Feedback im Abschlusseditor schreiben und speichern;
  - gespeicherter Wert im Kriterium;
  - in neuer Sitzung wieder angezeigt;
  - auf der Seite kein `[id*="feedbackModal"]` und kein Verweis darauf.
- Der bestehende Schritt für den Zugangseditor bleibt.
- Jest `issue18_dom_ids.spec.js`: `DIALOGS` = node, course, tutorial; nichts in den
  Anwendungen nennt `feedbackModal`.

**DoD2, Vorschlag für den Issue-Text:** Node-, Kurs- und Tutorial-Dialog per
Playwright (Name, Fokus, Labels, Escape, Fokus-Rückgabe):
- Node und Tutorial: A18;
- Kurs: E2.

Der Feedback-Dialog entfällt mit der Freigabe vom 27.11.2026.

**DoD3:**
- Jest „the same dialog twice in one application“;
- A18 „three ADELE applications on one course page“ in Boost mit sichtbarer
  MiniMap und in Classic.

Neuer Issue-Entwurf: `issues/local_adele-issue-unsaved-changes-on-leave.md`. Die
Warnung „Nicht gespeicherte Änderungen gehen verloren.“ gibt es nur an den
eigenen Knöpfen der Editoren; `beforeunload` und `changechecker` fehlen. Die
Entscheidung darüber steht aus.

## P2 – #13: Rolle einer inaktiven Lehrenden-Freigabe

**Reproduziert (belegt):**
- Eine Lehrende hat ihre eigene, parallele Einschreibung (manuell, Rolle
  Teacher) und zusätzlich eine Freigabe der Lehrenden-Ansicht mit der
  Studierendenrolle (kind 3, Modus `enrol_student`).
- Die Freigabe wird ausgesetzt oder läuft ab. Moodle behält die
  Rollenzuweisung.
- Auf `2026112700` steht die Person danach weiter **als Lernende** in der
  Teilnehmerliste: Die Rollenabfrage von `get_nonparticipant_userids()` zählte
  die Rollen jeder kind-3-Instanz ohne Status- und Zeitprüfung.

**Behoben:** Rollen einer Freigabe zählen nur, solange ihre Einschreibung zählt:
- Instanz aktiviert;
- Einschreibung des Nutzers aktiv und im Zeitfenster.

Das ist dieselbe Bedingung wie beim eigenen Recht.

**Test** `roster_lifecycle_test::test_the_role_of_an_inactive_grant_does_not_count`
(Fälle „grant suspended“ und „grant expired“):
- Freigabe aktiv: Die Person nimmt teil (Zähler 2/2/2).
- Freigabe inaktiv: Die Rollenzuweisung besteht nachweislich weiter; die Person
  ist auf dem Roster, aber Personal (`nonlearner`, Zähler 1/1/1).

Gegenprobe auf `2026112700`: 2/2 rot (Teilnehmerliste). Auf `2026112701`: der ganze
Lifecycle-Test 4/4 auf PostgreSQL 16 und MariaDB 10.11.

## Prüfstand

Siehe `README.md`, Zeile „Re-Audit 2026-11-27 abends“.
