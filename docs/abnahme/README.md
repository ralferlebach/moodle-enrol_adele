# Abnahmeprotokolle zum Master-Issue mod_adele #43

Je Einzelissue ein Protokoll nach Abschnitt 6 des Master-Issues, zum
Einfügen als Kommentar ins jeweilige Originalissue. Felder, die erst nach
der Übernahme in die Repositories belegt werden können (Commit-SHA,
CI-Run-Links für den finalen Commit, MySQL), sind ausdrücklich als offen
markiert. Lokal belegt: PostgreSQL 16 und MariaDB 10.11 (PHPUnit),
PostgreSQL (Behat, Playwright).

**Prüfstand je Lieferung**
- P0 `2026100734`: PHPUnit local 413 / enrol 72 / mod 89 auf PostgreSQL 16 und MariaDB 10.11; Playwright mod 41 / enrol 6 / local 78; Smoke 17; Behat 1 + 4 Szenarien.
- P1 `2026100735`: PHPUnit local 421 / enrol 72 / mod 116 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 36; Moodle-Konformitätsprüfung Privacy 12; Playwright mod 43 / enrol 6 / local 78; Smoke 17; Behat 1 + 4 Szenarien; Jest 749.
