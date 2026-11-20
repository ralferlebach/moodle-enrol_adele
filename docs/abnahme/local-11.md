### Abnahmeprotokoll – ralferlebach/moodle-local_adele #11 (PHP ≤ 8.1 aus der CI entfernen)
- Ziel-Repositories / Commit-SHA: local_adele, mod_adele, enrol_adele – Lieferung `2026111900`; Commit-SHA: **offen**
- **AK1** `moodle-plugin-ci.yml` (alle drei): `min_php: '8.2'` mit Kommentar „PHP 8.1 and older are not tested (local_adele #11)“. Die Reusable-Workflow-Expansion (Moodle-Plugin-CI) bildet die PHP-Zellen aus `min_php` bis zur höchsten von Moodle 4.5 unterstützten Version.
- **AK2** `playwright.yml` (alle drei): `php-version: '8.2'`.
- **AK3** `e2e.yml` (alle drei): Auswahl `8.1` entfernt; Beschreibung „tested are 8.2 and 8.3 (8.1 and older are not tested, local_adele #11)“.
- **AK4** `release-gate.yml` (alle drei): `php: ['8.2', '8.3']`.
- **AK5** Weitere Workflows: `load-jmeter.yml`, `load-k6.yml` (local) auf `8.2`; Suche `8\.1|7\.4|8\.0` über `.github/` aller drei Repositories ohne Treffer. Pflichtenheft 5.x: „PHP 8.2–8.3“. Funktionale Testarten unverändert (keine Job-Entfernung). actionlint 1.7.7 ohne Befund.
- **DoD1** (expandierte Jobs nur PHP ≥ 8.2) und **DoD2** (alle Testarten grün, keine Pflicht-Skips): nur in GitHub Actions nachweisbar – **offen**.
- Zusätzlich auf Auftrag (2026-11-19): kein Workflow läuft mehr nach Zeitplan; die drei `playwright.yml` hatten `schedule: cron '0 3 * * 1'` – entfernt, nur noch `workflow_dispatch` (Suche `schedule:|cron` über alle Workflows ohne Treffer).
- **Entscheidung: abnahmefähig** – codeseitig vollständig; DoD = CI am Commit, steht aus.
