### Abnahmeprotokoll – Master-Issue (P0–P2), P0.1 (Plugin-Kompatibilität und CI-Matrix)
- Ursache: PR-Läufe aus dem Fork (`ralferlebach:main` → Wunderbyte) installierten die Begleit-Plugins vom Upstream-`main` (`github.repository_owner`), dem `enrol_state::is_participant()` fehlte → „Call to undefined method" (Run 38969547394; Einzelzuordnung der 19 Fehler: Job-Log nur angemeldet lesbar, **offen**).
- Behebung: `moodle-plugin-ci.yml` (alle drei): Begleiter vom Eigentümer des PR-Heads. Abhängigkeiten im Gleichschritt (`2026100734` für jedes Geschwister).
- Nachweis: `sibling_api_test` in allen drei Plugins (alle benutzten Geschwister-Klassen/-Methoden existieren; Untergrenzen = Freigabeversion) – grün auf PostgreSQL und MariaDB.
- Offen: grüner CI-Lauf für den finalen Commit in allen drei Repositories (PostgreSQL/MariaDB/MySQL); `mod`-Run 38969829550 (Fehler in allen DB-Jobs, Behat-Faildumps vorhanden) nach Übernahme erneut prüfen.
