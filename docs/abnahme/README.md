# Abnahmeprotokolle zum Master-Issue (P0–P2) und zum Re-Audit 0735

Je Einzelissue ein Protokoll nach Abschnitt 6 des Master-Issues, zum
Einfügen als Kommentar ins jeweilige Originalissue. Felder, die erst nach
der Übernahme in die Repositories belegt werden können (Commit-SHA,
CI-Run-Links für den finalen Commit, MySQL), sind ausdrücklich als offen
markiert. Lokal belegt: PostgreSQL 16 und MariaDB 10.11 (PHPUnit),
PostgreSQL (Behat, Playwright).

**Prüfstand je Lieferung**
- P0 `2026100734`: PHPUnit local 413 / enrol 72 / mod 89 auf PostgreSQL 16 und MariaDB 10.11; Playwright mod 41 / enrol 6 / local 78; Smoke 17; Behat 1 + 4 Szenarien.
- P1 `2026100735`: PHPUnit local 421 / enrol 72 / mod 116 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 36; Moodle-Konformitätsprüfung Privacy 12; Playwright mod 43 / enrol 6 / local 78; Smoke 17; Behat 1 + 4 Szenarien; Jest 749.
- #603 + Re-Audit 2026100736 `2026101800`: PHPUnit local 433 (1 übersprungen, 1 unvollständig wie bisher) / enrol 75 / mod 139 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 70; Konformitätsprüfung Privacy 12 (beide Datenbanken); Playwright mod 56/56, enrol 6/6, local 80/80 (Gesamtlauf, Chromium); Smoke 17/17; Behat `@mod_adele_use` 1 + `@mod_adele_index` 4 Szenarien (Behat-Seite neu initialisiert); Jest 836; phpcs, moodlecheck, actionlint ohne Befund. Danach geändert und gezielt nachgeprüft: `focusVisible` beim Einklappen per Escape (S17 2/2 Chromium, Jest), `@covers` am Test `path_refresh_readonly_test` (1/1). CI-Läufe 2026-10-18 (Stand `2026100736`, vom Auftraggeber geliefert): Codechecker local_adele 1 Warnung (behoben), E2E Firefox S17 1 Fehler (behoben, Firefox hier nicht ausführbar – Nachweis im nächsten CI-Lauf). Nicht geprüft: MySQL, Firefox (lokal).
- P2 + Re-Audit 0735 `2026100736`: PHPUnit local 429 (1 übersprungen, 1 unvollständig wie bisher) / enrol 75 / mod 139 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 70; Moodle-Konformitätsprüfung Privacy 12 (beide Datenbanken); Playwright mod 56/56 (Gesamtlauf), enrol 6/6, local 77/78 im Gesamtlauf – R5 rot wegen der Suche im Rollendialog (>100 Personen, belegt), nach Korrektur der Hilfsfunktion beide betroffenen Spezifikationen 6/6; Smoke 17/17; Behat `@mod_adele_use` 1 + `@mod_adele_index` 4 Szenarien; Jest 827 (105 Suiten, Coverage-Gate eingehalten); phpcs, moodlecheck, actionlint ohne Befund. Ein erster mod-Gesamtlauf hatte H2 (`withdrawn-settings.spec.ts`) einmal rot (Abschlussknopf deaktiviert); Einzellauf und zweiter Gesamtlauf grün – Ursache **nicht belegt**. Nicht geprüft: MySQL, Firefox.

## Re-Audit 0735 – Entscheidung je Issue (2026-10-18)

Kategorien des Auftrags: **codeseitig offen / testseitig offen /
abnahmefähig / schließbar**. „Schließbar“: alle AKs mit Datei/Funktion/Zeile
oder Testfall belegt, positive und negative Pfade lokal grün auf
PostgreSQL 16 und MariaDB 10.11. „Abnahmefähig“: Code und Tests
vollständig, ein Teil des Nachweises braucht eine Umgebung, die hier nicht
verfügbar ist (benannt). CI ist nicht Teil des Auftrags.

| Issue | TP | Entscheidung | Offen / Hinweis |
|---|---|---|---|
| mod #43 | TP-01 | schließbar | – |
| mod #26 | TP-02 | schließbar | Firefox-Gegenlauf der E2E im Workflow vorgesehen |
| mod #27 | TP-03 | schließbar | wie #26 |
| mod #32 | TP-04 | **abnahmefähig** | AK 3 „alle Browser“: Firefox-Lauf (lokal nicht installierbar) |
| mod #17 | TP-05/08 | **abnahmefähig** (seit CI 2026-10-18) | Firefox: sichtbarer Fokus nach Escape war rot, behoben in `2026101800`; grüner Firefox-Lauf steht aus. Doppelte Kanten: #603 |
| mod #44 | TP-06 | schließbar | – |
| mod #29 | TP-06 | schließbar | – |
| mod #41 | TP-06 | schließbar | – |
| mod #33 | TP-06 | **abnahmefähig** | MySQL und erster Gate-Lauf der Matrix |
| mod #37 | TP-07 | schließbar | – |
| mod #30 | TP-07 | schließbar | – |
| mod #23 | TP-07 | schließbar | – |
| mod #22 | TP-07 | schließbar | – |
| mod #21 | TP-07 | schließbar | – |
| mod #20 | TP-07 | schließbar | Fallback-AK durch Architekturentscheidung entfallen |
| mod #19 | TP-07 | schließbar | – |
| enrol #7 | TP-07 | schließbar | – |
| enrol #11 | TP-07 | schließbar | – |
| mod #31 | TP-07 | schließbar | – |
| mod #11 | TP-07 | schließbar | – |
| local #603 | Re-Audit 2026100736 A | schließbar | Lieferung `2026101800`, Protokoll `local-603.md` |

MySQL wurde in keinem Lauf geprüft (hier nicht vorhanden); die Matrix in
`release-gate.yml` enthält es.
