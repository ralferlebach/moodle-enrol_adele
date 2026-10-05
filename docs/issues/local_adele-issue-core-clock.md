# Issue-Entwurf: „Jetzt" konsequent über `\core\clock` lesen

**Repository:** `Wunderbyte-GmbH/moodle_local_adele`
**Labels (Vorschlag):** `refactoring`, `time`, `testability`
**Stand:** 2026-10-05, Session 006
**Gleichartige Entwürfe:** `enrol_adele-issue-core-clock.md`, `mod_adele-issue-core-clock.md`
**Hängt zusammen mit:** `local_adele-issue-timed-condition-timestamps.md`

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Aktuelle Zeit über `\core\di::get(\core\clock::class)` statt `time()` / `new DateTime()`

## Hintergrund

Seit Moodle 4.4 stellt der Core eine Uhr bereit (`\core\clock`,
Standardimplementierung `\core\system_clock`), die über `\core\di` bezogen
wird. Moodle selbst nutzt sie unter anderem im Task-Manager
(`lib/classes/task/manager.php`) und in `enrollib.php`. Für Tests liefert der
Core `\frozen_clock` und `\incrementing_clock` (`lib/testing/classes`).

`local_adele` verlangt seit `2026100500` Moodle 4.5 (`$plugin->requires =
2024100700`); die Uhr steht damit ohne Rückfallebene zur Verfügung.

## Befund

`local_adele` liest „jetzt" an 22 Stellen direkt aus der Wanduhr (`time()`,
`new DateTime()`); zwei weitere Stellen parsen gespeicherte Zeichenketten mit
`strtotime()` und gehören zum Zeitstempel-Issue. Von den 22 Stellen
entscheiden diese über Zugang, Zustand oder Zeitplanung:

| Datei | Zeile | Zweck |
|---|---|---|
| `course_restriction/conditions/timed.php` | 133 | Zeitfenster der Zugangsbedingung |
| `course_restriction/conditions/timed_duration.php` | 139–145 | relative Zeit seit Einschreibung |
| `helper/adhoc_task_helper.php` | 88, 118 | Zeitpunkt der Neuauswertung an Fenstergrenzen |
| `relation_update.php` | 1349 | `first_enrolled`, Basis der relativen Zeit |
| `node_completion.php` | 79 | `first_enrolled` |
| `enrollment.php` | 83–84 | Zeitstempel der Pfadzuordnung |

Die übrigen Stellen setzen `timecreated`/`timemodified`/`last_seen_by_owner`
(`learning_paths.php`, `ownership.php`, `helper/user_path_relation.php`,
`asset_handler.php`). Sie entscheiden nichts, sollen aber dieselbe Uhr nutzen,
damit gespeicherte und bewertete Zeiten in Tests zusammenpassen.

## Warum

- **Prüfbarkeit:** Zeitabhängiges Verhalten lässt sich heute nur durch Warten
  oder durch Eingriffe in die Prozesszeit testen. Mit der Core-Uhr genügt in
  PHPUnit `\core\di::set(\core\clock::class, new \frozen_clock($t))`, und
  Grenzen sind sekundengenau prüfbar.
- **Einheitlichkeit:** Moodles Task-Manager entscheidet bereits über
  `\core\clock`, ob ein Auftrag fällig ist. Plant `local_adele` mit `time()`,
  Moodle aber mit der Uhr, laufen beide in Tests auseinander.
- **Keine Verhaltensänderung in Produktion:** `\core\system_clock` liefert
  dieselbe Zeit wie `time()`.

## Vorschlag

1. An allen genannten Stellen
   `$now = \core\di::get(\core\clock::class)->time();` bzw. `->now()` für ein
   `DateTimeImmutable`.
2. Keine eigene Uhr-Abstraktion im Plugin — die des Cores genügt.
3. Die Umstellung der Bewertungslogik auf Zeitstempel ist **eigenes** Issue
   (`local_adele-issue-timed-condition-timestamps.md`); dieses hier ändert
   nur die Zeitquelle und ist verhaltensneutral.

## Akzeptanzkriterien

1. Im Plugincode (ohne `tests/`) kommen `time()`, `new DateTime()` und
   `strtotime()` für die aktuelle Zeit nicht mehr vor; eine Prüfung im CI
   (z. B. `grep` im Workflow) verhindert, dass sie zurückkehren.
2. PHPUnit-Tests für `timed`, `timed_duration` und `adhoc_task_helper` laufen
   mit `\frozen_clock` und prüfen Grenzen sekundengenau.
3. Bestehende PHPUnit-, Behat- und Playwright-Suiten bleiben grün.
