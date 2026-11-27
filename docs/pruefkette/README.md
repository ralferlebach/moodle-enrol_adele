# Prüfkette vor jeder Lieferung (Stand 2026-11-23)

Anlass: Der Moodle-Plugin-CI-Lauf vom 2026-11-23 auf `2026112300` war rot
(codechecker in local und mod, grunt in mod), obwohl die lokale Prüfung grün
war. Ursachen (belegt):

1. **Andere Werkzeugversion.** Die CI installiert `moodle-plugin-ci` bei
   jedem Lauf neu (`composer create-project moodlehq/moodle-plugin-ci ^4`)
   und löst dabei auch die Abhängigkeiten neu auf. Am 2026-11-23 bekam sie
   4.5.12 mit moodle-cs 3.9.2 und PHP_CodeSniffer 4.0.4. Lokal lag noch eine
   einmal installierte 4.5.11 mit moodle-cs 3.7.0 und PHPCS 3.13.6. Die neue
   Regel `PSR12.Files.FileHeader` meldete in vier unveränderten Dateien je
   eine fehlende Leerzeile; die alte Version kannte die Regel nicht.
2. **Anderer Benutzer, andere Eingabe.** Die CI arbeitet als normaler
   Benutzer auf einem Git-Checkout. Lokal liefen die Prüfungen als root auf
   einer Kopie ohne `.git`. moodle-plugin-ci 4.5.12 (symfony/filesystem
   5.4.56) übernimmt beim Kopieren die Dateirechte. Die schreibgeschützten
   Git-Objekte bleiben daher schreibgeschützt, und grunt scheitert beim
   Zurückkopieren seiner Sicherung („Failed to copy …/.git/objects/pack/…
   Permission denied“). Als root fällt das nie auf. Lokal nachgestellt:
   4.5.11 grün, 4.5.12 als normaler Benutzer rot, mit `chmod -R u+w .git`
   grün.

## Aufbau

| Skript | Prüft |
|---|---|
| `ci_gate.sh` | Die statischen Schritte der Moodle Plugin CI so, wie die CI sie ausführt (Details unten) |
| `jest_ci.sh` | Die `run`-Schritte von `local_adele/.github/workflows/jest.yml` wörtlich und in ihrer Reihenfolge, auf einer frischen Kopie ohne `node_modules`: Bundle-Gate, Installation, Jest, Lint-Gate, Build-Probe |
| `package.sh` | Baut die ZIPs **nur**, wenn beide oben grün sind; sonst bricht es mit „PACKAGE REFUSED“ ab |
| `sync_work.sh` | Bringt den Arbeitsstand in die Testseite (startet den Webserver neu – **nie während eines Browserlaufs**) |

`ci_gate.sh` im Einzelnen:

1. **Werkzeug wie in der CI:** `moodle-plugin-ci` wird bei jedem Lauf frisch
   installiert, mit demselben Befehl wie in der CI. Gibt es dabei einen
   Fehler, ist das Gate rot; ein altes Werkzeug wird nicht ersatzweise
   genommen. Die Versionen werden ausgegeben.
2. **Eingabe wie in der CI:** Jedes Plugin wird so bereitgestellt wie der
   Checkout der CI: die Dateien der Lieferung (dieselben Ausschlüsse wie
   `package.sh`) plus ein Git-Repository, an seinem Platz im Moodle-Baum.
   Die Kopie der Testseite liegt so lange außerhalb des Baums.
3. **Benutzer wie in der CI:** Alle Schritte laufen als normaler Benutzer
   (`claude`), nicht als root.
4. **Optionen wie in der CI:** Sie werden aus der `moodle-plugin-ci.yml`
   des Plugins gelesen:
   - Ausschlüsse: `tests/fixtures` plus `ignore_paths`, dazu
     `mustache_ignore_names`, geschrieben als `.moodle-plugin-ci.yml` wie
     bei `install`;
   - `codechecker --max-warnings`;
   - grunt, außer bei `disable_grunt`;
   - phpmd nur als Bericht, die CI scheitert nicht daran;
   - die vorbereitenden Zeilen aus `extra_plugin_runners`, wo die CI sie
     ausführt (`GITHUB_WORKSPACE` mit dem Checkout in `./plugin`).
   - seit 2026-11-26 auch `ignore_names` und `codechecker_ignore_paths`
     wie der gemeinsame Workflow. `phpdocchecker_ignore_paths` wirkt dort
     nicht (moodle-plugin-ci liest `PHPDOC_IGNORE_PATHS`), also auch hier
     nicht.
5. Das Ergebnis lautet **GATE GREEN** oder **GATE RED**, der Exit-Code
   ist entsprechend gesetzt.

Belegt (2026-11-23), in beide Richtungen:

- **Auf dem ausgelieferten Stand `2026112300`** meldet das Gate genau die
  Befunde der CI:
  - dieselben vier Dateien mit derselben Regel;
  - grunt in mod mit „Failed to copy“.
- **Nach den Korrekturen** ist es grün.
- **Gegenprobe:** Mit einer der vier Dateien im alten Zustand und ohne die
  `chmod`-Zeile im Workflow ist es wieder rot.

## Was das Gate nicht abfangen kann

Die CI löst `^4` bei **jedem** Lauf neu auf. Erscheint zwischen meiner
Prüfung und deinem CI-Lauf eine neue Version von moodle-plugin-ci oder
moodle-cs, kann sie neue Regeln mitbringen. Das Gate zeigt die geprüften
Versionen; ein späterer CI-Lauf mit höheren Versionen ist daran erkennbar.
Abhilfe wäre, die Version in der CI festzuschreiben. Das betrifft den
gemeinsamen Workflow von Wunderbyte; siehe Issue-Entwurf
`issues/catalyst-moodle-workflows-pin-plugin-ci.md`.

## Ablauf vor jeder Lieferung (verbindlich)

1. PHPUnit auf PostgreSQL und MariaDB, voller Lauf.
2. Browser-Gesamtlauf wie in der CI (jede Suite auf frisch befüllter
   Testseite), **ohne** gleichzeitigen Abgleich.
3. `package.sh`; das schließt `ci_gate.sh` und `jest_ci.sh` ein.
4. Lieferung nur, wenn alles grün ist.
