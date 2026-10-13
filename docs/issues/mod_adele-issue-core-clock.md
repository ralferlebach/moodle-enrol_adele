# Issue-Entwurf: „Jetzt" konsequent über `\core\clock` lesen (mod_adele)

**Repository:** `Wunderbyte-GmbH/moodle-mod_adele`
**Labels (Vorschlag):** `refactoring`, `time`, `testability`
**Stand:** 2026-10-05, Session 006
**Begründung und Akzeptanzkriterien:** wie `local_adele-issue-core-clock.md`

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Aktuelle Zeit über `\core\di::get(\core\clock::class)` statt `time()`

## Befund

`mod_adele` verlangt Moodle 4.5; `\core\clock` steht zur Verfügung. Direkt aus
der Wanduhr gelesen wird an vier Stellen:

| Datei | Zeile | Zweck |
|---|---|---|
| `classes/local/host_policy.php` | 248 | prüft, ob eine Einschreibung **jetzt** aktiv ist (`timestart`/`timeend`) |
| `classes/local/host_policy.php` | 376 | ermittelt die **jetzt** aktiven Einschreibungen (`timestart`/`timeend`) |
| `lib.php` | 60 | Zeitstempel beim Anlegen der Aktivität |
| `lib.php` | 96 | Zeitstempel beim Aktualisieren der Aktivität |

## Vorschlag und Akzeptanzkriterien

Wie in `local_adele-issue-core-clock.md`: an allen vier Stellen die Core-Uhr,
verhaltensneutral in Produktion; PHPUnit belegt die zeitabhängigen Zweige in
`host_policy.php` mit `\frozen_clock`.
