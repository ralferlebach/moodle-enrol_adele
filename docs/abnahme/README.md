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
- CI-Korrektur 2026-11-21 `2026112100`: geändert nur `tests/behat/adele_basic_usage.feature` (ein Selektor), `vue3/tests/unit/behat_locators.spec.js` (Wächter erweitert) und die Versionen. Jest 885 (`npm test -- --ci`, Coverage-Gate eingehalten; Wächter 14, Gegenprobe mit alter Feature-Datei rot); `moodle-plugin-ci`-Statik im Moodle-Baum (19 Schritte, local/mod/enrol) ohne Befund. PHPUnit und E2E nicht erneut gelaufen (kein PHP- oder Vue-Code geändert; Stand `2026112000` siehe unten, PHPUnit in der CI am 2026-11-20 grün). **Nicht geprüft:** Behat (ChromeDriver nicht installierbar), MySQL; GitHub-CI am Commit steht aus.
- CI-Korrektur 2026-11-20 `2026112000`: Lokale Statik jetzt mit `moodle-plugin-ci` 4.5.11 im Moodle-Baum wie die CI (phplint, codechecker `--max-warnings=0`, phpdoc, validate, savepoints, mustache; mod zusätzlich grunt), ganze Plugins, Ausschlüsse wie im Workflow: local, mod, enrol ohne Befund; Gegenprobe mit der alten `lang/en/adele.php` rot. PHPUnit local 459 (1 übersprungen, 1 unvollständig wie bisher) / mod 147 / enrol 75 auf PostgreSQL 16 und MariaDB 10.11, Gruppe `adele_release` 70 auf beiden; Jest 880 (`npm test -- --ci`, Coverage-Gate eingehalten), davon neu `behat_locators.spec.js` (9; Gegenprobe mit altem `textInputs.vue` rot); Playwright E2E Chromium local A18 3/3 (mit neuer Namensprüfung), Referenzpfad und Kombinationen (6/6 zusammen mit A18), Playwright mod 4/4. Behoben: codechecker `LangFilesOrdering` (local, mod), Behat-Locator `goalsubjectplaceholder` (local). **Korrektur zum Prüfstand `2026111900`:** „phpcs … ohne Befund“ galt nur für Läufe außerhalb des Moodle-Baums auf den geänderten Dateien; dort schweigt der Sniff für Sprachdateien (belegt). **Nicht geprüft:** Behat (ChromeDriver nicht installierbar, s. o.), MySQL, Gesamtläufe E2E (seit `2026111900` nur `textInputs.vue` – ein `name`-Attribut – und die Reihenfolge der Sprachstrings geändert); GitHub-CI am Commit steht aus.
- Nacharbeiten 2026-11-19 `2026111900`: PHPUnit local 459 (1 übersprungen, 1 unvollständig wie bisher) / enrol 75 / mod 147 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 70; Konformitätsprüfung Privacy 12 (beide Datenbanken); Jest 871 (Coverage-Gate eingehalten); Playwright E2E Chromium local 83/83 (Gesamtlauf), mod 62 (letzter Gesamtlauf nach allen Korrekturen: 59 grün, R26 „Modus 2“ einmal rot – zwei gleichzeitige Anfragen gezählt –, 2 danach nicht gelaufen; `path-refresh.spec.ts` danach 10/10 und Modus 2 einzeln 3/3 grün; Ursache des einen roten Laufs **nicht belegt**, der Zähler des Tests erfasst jede Anfrage, deren Inhalt den Methodennamen enthält, also auch die Teilnehmerliste `…_relations`; der vorige Gesamtlauf und Firefox 12/12 waren für R26 grün), enrol 6/6; Firefox local A18 3/3, mod R26/F27/S17 12/12; Smoke local 17/17, mod 4/4, enrol 4/4; phpcs (moodle-cs 3.7), moodlecheck, actionlint 1.7.7 ohne Befund. Im ersten Gesamtlauf rot und behoben (Ursachen belegt): R3 und V1 (Seed `E2EM18` nahm student01 den Referenzpfad), E2 und Smoke #470 (entfernte Steuer-IDs, jetzt `data-testid`), P2/P3 und P1/P2-Rücknahme (gewollte Änderung #12 AK0-L2, Tests angepasst), S17 einmal von drei Läufen (Messung während der Viewport-Animation, jetzt nach Stillstand gemessen, danach 5/5 Chromium, 1/1 Firefox), Smoke-Reihenfolge (veraltete `fix.env`, `enrol_adele/lastreport` aus einem früheren Lauf). **Nicht geprüft:** Behat (kein Chrome/ChromeDriver installierbar: Download über die Allowlist blockiert, Installation eines heruntergeladenen Treibers nicht freigegeben), MySQL; GitHub-CI am Commit steht aus.
- #603 + Re-Audit 2026100736 `2026101800`: PHPUnit local 433 (1 übersprungen, 1 unvollständig wie bisher) / enrol 75 / mod 139 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 70; Konformitätsprüfung Privacy 12 (beide Datenbanken); Playwright mod 56/56, enrol 6/6, local 80/80 (Gesamtlauf, Chromium); Smoke 17/17; Behat `@mod_adele_use` 1 + `@mod_adele_index` 4 Szenarien (Behat-Seite neu initialisiert); Jest 836; phpcs, moodlecheck, actionlint ohne Befund. Danach geändert und gezielt nachgeprüft: `focusVisible` beim Einklappen per Escape (S17 2/2 Chromium, Jest), `@covers` am Test `path_refresh_readonly_test` (1/1). CI-Läufe 2026-10-18 (Stand `2026100736`, vom Auftraggeber geliefert): Codechecker local_adele 1 Warnung (behoben), E2E Firefox S17 1 Fehler (behoben, Firefox hier nicht ausführbar – Nachweis im nächsten CI-Lauf). Nicht geprüft: MySQL, Firefox (lokal).
- P2 + Re-Audit 0735 `2026100736`: PHPUnit local 429 (1 übersprungen, 1 unvollständig wie bisher) / enrol 75 / mod 139 auf PostgreSQL 16 und MariaDB 10.11; Gruppe `adele_release` 70; Moodle-Konformitätsprüfung Privacy 12 (beide Datenbanken); Playwright mod 56/56 (Gesamtlauf), enrol 6/6, local 77/78 im Gesamtlauf – R5 rot wegen der Suche im Rollendialog (>100 Personen, belegt), nach Korrektur der Hilfsfunktion beide betroffenen Spezifikationen 6/6; Smoke 17/17; Behat `@mod_adele_use` 1 + `@mod_adele_index` 4 Szenarien; Jest 827 (105 Suiten, Coverage-Gate eingehalten); phpcs, moodlecheck, actionlint ohne Befund. Ein erster mod-Gesamtlauf hatte H2 (`withdrawn-settings.spec.ts`) einmal rot (Abschlussknopf deaktiviert); Einzellauf und zweiter Gesamtlauf grün – Ursache **nicht belegt**. Nicht geprüft: MySQL, Firefox.

## Re-Audit 2026-11-19 (Stand `2026101800`) – Entscheidung je Issue, Lieferung `2026111900`

Regel des Auftrags: AK = Codeeigenschaft, DoD = automatische CI am Commit;
keine manuellen Nach-Deploy-Tests als DoD. Ohne CI-Lauf am Commit ist
deshalb nichts „schließbar“. „Abnahmefähig“: Code und automatisierte Tests
vollständig, lokal grün; es fehlt nur der CI-Nachweis. „Testseitig offen“:
ein Teil der verlangten automatisierten Tests fehlt oder lief hier nicht
(benannt).

| Issue | Entscheidung | Offen |
|---|---|---|
| local #11 (PHP ≤ 8.1) | abnahmefähig | CI: expandierte Jobs nur PHP ≥ 8.2, alle Testarten grün |
| local #12 (Gruppenranglisten) | abnahmefähig | CI-Matrix (MySQL hier nicht vorhanden) |
| local #13 (Nicht-Lernende) | abnahmefähig | CI-Matrix; „ohne Pfad“ nur PHPUnit/Jest (im Browser nicht dauerhaft herstellbar) |
| local #14 (Live-Marker) | testseitig offen | Lasttest großer Kohorten, CI-Matrix |
| local #15 (Knotenzähler) | testseitig offen | Rückkehr mit Viewport/Scroll, CI-Matrix |
| local #16 (mod: Gruppen) | testseitig offen | Gruppierung im Browser (nur PHPUnit), Behat hier nicht ausführbar, CI-Matrix |
| local #17 (mod: Live-Einstellungen) | abnahmefähig | CI-Matrix (Speichern/Wiederöffnen per Playwright M1) |
| local #18 (DOM-IDs) | abnahmefähig | CI (Chromium, Firefox, a11y) |
| Upstream #603 | testseitig offen (korrigiert, vorher „schließbar“) | CI am Fork-Commit, Integration und Test upstream |
| mod #17 | abnahmefähig | CI Chromium und Firefox (Firefox lokal jetzt grün) |
| gegenläufige Kante | Issue-Entwurf mit AK/DoD | Einreichen nach Duplikatabgleich |

Protokolle: `local-11.md`, `local-12-17.md`, `local-18.md`, `local-603.md`, `mod-17.md`.

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
