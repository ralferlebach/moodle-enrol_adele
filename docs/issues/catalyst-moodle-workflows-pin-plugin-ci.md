# Issue-Entwurf (Wunderbyte-GmbH/catalyst-moodle-workflows): moodle-plugin-ci-Version festschreiben

> **Stand:** Entwurf 2026-11-23, zur Entscheidung (betrifft alle Plugins,
> die den gemeinsamen Workflow nutzen).

## Befund (belegt)

`ci.yml` installiert bei jedem Lauf `composer create-project
moodlehq/moodle-plugin-ci ^4` und löst dabei auch alle Abhängigkeiten neu
auf. Eine Neuerscheinung ändert so ohne jede Änderung im Plugin das Ergebnis
der CI. Am 2026-11-23 brachte das zwei Fehler:

- 4.5.12 mit moodle-cs 3.9.2 und PHP_CodeSniffer 4.0.4: neue Regel
  `PSR12.Files.FileHeader`, Fehler in Dateien, die seit Wochen unverändert
  sind;
- symfony/filesystem 5.4.56: `grunt` scheitert an schreibgeschützten
  Git-Objekten, siehe `moodle-plugin-ci-grunt-restore-readonly.md`.

## Vorschlag

Eine Eingabe `plugin_ci_version` mit festem Standard (z. B. `4.5.12`),
ausdrücklich angehoben per Pull Request. Dazu ein geplanter Lauf, der mit
`^4` prüft und vor neuen Regeln warnt, bevor sie die Plugin-CI rot machen.
Dann kommen Upgrades geplant und nicht mitten in einer Abnahme.
