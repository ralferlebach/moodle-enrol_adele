# Issue-Entwurf: „Jetzt" konsequent über `\core\clock` lesen (enrol_adele)

**Repository:** `Wunderbyte-GmbH/moodle-enrol_adele`
**Labels (Vorschlag):** `refactoring`, `time`, `testability`
**Stand:** 2026-10-05, Session 006
**Begründung und Akzeptanzkriterien:** wie `local_adele-issue-core-clock.md`

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Aktuelle Zeit über `\core\di::get(\core\clock::class)` statt `time()`

## Befund

`enrol_adele` verlangt seit `2026100500` Moodle 4.5; `\core\clock` steht ohne
Rückfallebene zur Verfügung. Direkt aus der Wanduhr gelesen wird an sechs
Stellen:

| Datei | Zeile | Zweck |
|---|---|---|
| `local/reconciler.php` | 514 | Einplanung des verzögerten Entzugs (`remove_user_path_adhoc::DELAY_SECONDS`) |
| `local/reconciler.php` | 569 | Grenze für das endgültige Löschen abgelaufener Suspendierungen |
| `observer.php` | 90 | Einplanung des verzögerten Entzugs |
| `observer.php` | 205 | prüft, ob eine Einschreibung **jetzt** aktiv ist (`timestart`/`timeend`) |
| `local/reconciler.php` | 234 | Zeitstempel des Abgleichsberichts |
| `local/task_log.php` | 83 | Zeitstempel im Aufgabenprotokoll |

Die ersten vier entscheiden: drei darüber, **wann** ein Zugriff entzogen bzw.
eine Suspendierung endgültig gelöscht wird, eine darüber, ob eine
Einschreibung im Moment als aktiv gilt.

## Warum besonders hier

Moodles Task-Manager prüft die Fälligkeit bereits über `\core\clock`. Plant
`enrol_adele` mit `time()`, lassen sich die fünf Minuten Verzögerung des
Entzugs in Tests nur durch Warten oder durch Eingriff in die Prozesszeit
überbrücken. Mit der Core-Uhr genügt in PHPUnit ein `\frozen_clock`.

## Vorschlag und Akzeptanzkriterien

Wie in `local_adele-issue-core-clock.md`: an allen sechs Stellen die Core-Uhr,
verhaltensneutral in Produktion; PHPUnit belegt mit `\frozen_clock`, dass der
Entzug genau nach `DELAY_SECONDS` fällig wird und die Bereinigung genau an der
Tagesgrenze greift.
