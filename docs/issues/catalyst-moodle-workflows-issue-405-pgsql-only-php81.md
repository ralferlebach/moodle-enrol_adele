# Issue-Entwurf (catalyst-moodle-workflows, extern): Moodle 4.5 mit PostgreSQL nur auf PHP 8.1

> **Stand:** Entwurf (2026-11-22), für `Wunderbyte-GmbH/catalyst-moodle-workflows`
> (`.github/actions/matrix/matrix_includes.yml`). Nicht in den ADELE-Repositories behebbar.

## Befund (belegt, Matrixdatei und CI-Läufe 2026-11-20/21)

Die Matrix führt Moodle 4.5 dreimal: MariaDB mit PHP 8.3, PostgreSQL mit
PHP 8.1, MySQL mit PHP 8.1. Wer `min_php: '8.2'` setzt (ADELE seit
local_adele #11: PHP ≤ 8.1 wird nicht mehr getestet), verliert dadurch
PostgreSQL und MySQL ganz. Die Moodle-Plugin-CI aller drei ADELE-Plugins lief
deshalb nur noch als „MOODLE_405_STABLE - mariadb - php 8.3“; die DoDs von
#12–#17 verlangen aber Moodle 4.5 mit MariaDB **und** PostgreSQL.

## Umgehung in ADELE (`2026112102`)

Eigener Workflow `moodle-plugin-ci-pgsql.yml` in jedem Plugin:
PostgreSQL 16, PHP 8.3, dieselben Begleit-Plugins wie die Plugin-CI,
`moodle-plugin-ci install` und `phpunit`. Doppelte Pflege der Liste der
Begleit-Plugins (Kommentar in beiden Dateien).

## Vorschlag

Für Moodle 4.5 PostgreSQL und MySQL zusätzlich mit PHP 8.3 (oder 8.2)
führen, z. B.

```yaml
- { moodle-branch: 'MOODLE_405_STABLE', php: '8.3', node: '22.14', mariadb-ver: '10.6', pgsql-ver: '13', mysql-ver: '8.0', database: 'pgsql' }
- { moodle-branch: 'MOODLE_405_STABLE', php: '8.3', node: '22.14', mariadb-ver: '10.6', pgsql-ver: '13', mysql-ver: '8.0', database: 'mysqli' }
```

Danach kann der ADELE-Workflow `moodle-plugin-ci-pgsql.yml` entfallen, und
MySQL wäre ebenfalls wieder abgedeckt (heute eine offene Lücke).

## AK

- AK1 Ein Plugin mit `min_php: '8.2'` erhält für Moodle 4.5 Matrixzeilen
  für MariaDB, PostgreSQL und MySQL.

## DoD

- DoD1 Die expandierte Matrix eines Plugins mit `min_php: '8.2'` zeigt drei
  Datenbanken für MOODLE_405_STABLE (CI-Lauf).
