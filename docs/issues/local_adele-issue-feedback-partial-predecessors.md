# Issue-Entwurf: „n von m Vorgängern" — der Text nennt nicht, was noch fehlt

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `enhancement`, `feedback`, `question`
**Stand:** 2026-10-07, Session 006 — beobachtet mit Testkette F (Feedback und Sprache)
**GitHub:** local_adele #592

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Zugang nach mehreren Vorgängern: Das Feedback nennt weiter alle Vorgänger, auch die bereits abgeschlossenen

## Beobachtung

Knoten 3 verlangt den Abschluss von zwei Vorgängern (2 von 2). Hat die
Lernende einen davon abgeschlossen, bleibt der Text unverändert:

> It will be unlocked when: Completion of the predecessors „Testkurs 01" and
> „Testkurs 02".

„Testkurs 01" ist zu diesem Zeitpunkt abgeschlossen (sein Knoten zeigt
„completed").

Zum Vergleich: Bei zwei **verschiedenen** Bedingungen (Zeitfenster UND
Vorgänger) nennt das Feedback nur noch die fehlende — „from 15/01/33, 10:00",
sobald der Vorgänger abgeschlossen ist, bzw. nur den Vorgänger, sobald das
Fenster offen ist.

## Frage

Soll das Feedback bei „n von m Vorgängern" ebenfalls nur nennen, was noch
fehlt (oder den Stand, z. B. „1 von 2 abgeschlossen")? Der Testplan verlangt
für den Zustand „teilweise erfüllt" einen „korrekten Text"; der jetzige Text
ist als Beschreibung der Regel richtig, als Rückmeldung zum Stand nicht.

## Stand der Tests

`tests/e2e/tests/feedback-texts.spec.ts`, „predecessors, 2 of 2", prüft im
Zustand „teilweise erfüllt" nur, was in beiden Lesarten gilt: Der Knoten
bleibt gesperrt, und der fehlende Vorgänger wird genannt.
