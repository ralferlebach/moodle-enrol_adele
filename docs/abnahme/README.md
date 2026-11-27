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
- Re-Audit 2026-11-26 `2026112600` (geprüft 2026-11-27): geändert PHP (local `learning_paths::roster_members_sql`), Vue-Quellen und Bundle (Store `groupchoice`, `NonLearners.vue`, `TeacherView.vue`, `useStatusMessage.js`, `NodeInformation.vue`), k6-Skript und Workflow `load-k6.yml`, Tests und Fixtures, Doku, Versionen. Gate (`ci_gate.sh`, moodle-plugin-ci 4.5.13 frisch wie in der CI, moodle-cs 3.9.2, PHPCS 4.0.4): local, mod und enrol je phplint, codechecker, phpdoc, validate, savepoints, mustache, mod grunt grün; es fing vorher 30 Formatbefunde im neuen Rostertest ab. Jest-Workflow Schritt für Schritt grün (898 Tests), Bundle-Gate grün, Lint-Gate 85 Befunde (Baseline 88 → 85). PHPUnit local 512 / mod 149 / enrol 75 / `adele_release` 70 auf PostgreSQL 16 und MariaDB 10.11 grün; Gegenprobe Rostertest auf altem Code 3/3 rot. Lokal CI-nah (jede Suite frisch geseedet): Smoke local 17/17, mod 4/4, enrol 3/4 (Seitenumbruch der lokalen Liste, Test korrigiert, danach 4/4); E2E Chromium local 83/84 (`running-path-change` M2 einmal rot, Ursache offen, danach 5/5 grün), mod 66/66 (mit G5), enrol 6/6; Firefox local 4/4, mod 12/12. **Nicht geprüft:** Behat, MySQL, NVDA (Windows), k6 selbst (kein Binary), GitHub-CI auf `development` steht aus.
- CI-Korrektur 2026-11-23 `2026112301`: geändert nur vier PHP-Dateien (Leerzeile nach dem Kopfblock, `PSR12.Files.FileHeader`), mod `moodle-plugin-ci.yml` (Umgehung grunt/`.git`), Smoke- und E2E-Workflows (Hinweis auf den geprüften Branch), Doku (`pruefkette/`), Versionen. Neue Prüfkette `ci_gate.sh` (moodle-plugin-ci 4.5.12, moodle-cs 3.9.2, PHPCS 4.0.4 frisch wie in der CI, Checkout mit `.git`, Benutzer ohne root): local, mod und enrol je phplint, codechecker, phpdoc, validate, savepoints, mustache und mod grunt grün; phpmd berichtet wie in der CI. Auf `2026112300` meldete sie genau die CI-Befunde. Jest-Workflow Schritt für Schritt grün. PHPUnit erneut: local 509 / mod 149 / enrol 75 / `adele_release` 70 auf PostgreSQL und MariaDB grün. Browserläufe nicht wiederholt: Seit `2026112300` (dort alles grün) hat sich kein ausführbarer Code geändert, nur Leerzeilen. **Nicht geprüft:** die Workflows auf GitHub.
- Re-Audit 2026-11-22 (21:53) `2026112300`: geändert PHP (local: catquiz-Bedingung mit Testnaht; Tests und Fixtures in local/mod), Vue (`MiniMapNodeShape.vue`, `LearningPath.vue`, `NotFound.vue`), ESLint-Regelsatz Vue 3 und Baseline 88, Bundle, k6-Plan, Seed und `load-k6.yml`, E2E-Specs, Doku, Versionen. PHPUnit local 509 / mod 149 / enrol 75, Gruppe `adele_release` 70, je auf PostgreSQL 16 und MariaDB 10.11 – **erstmals ohne übersprungene oder unvollständige Fälle**; Jest 892 (111 Suites); die Schritte von `jest.yml` wörtlich auf frischer Kopie (Bundle-Gate, Installation, Jest, Lint-Gate, Build-Probe) grün; `moodle-plugin-ci`-Statik 19/19 ohne Befund. Lokal CI-nah (jede Suite auf frisch geseedeter Seite): Smoke local 17/17, mod 4/4, enrol 4/4; E2E Chromium local 84/84 (A18 mit Classic-Theme), mod 65/65 (neu G4 ×2), enrol 6/6; Firefox local 4/4, mod 12/12. Gegenproben rot: G4 ohne Gruppierungsfilter, Kontexttest ohne Pfadprüfung, Formulartest ohne Feldregel, Classic-Schritt vor der MiniMap-Behebung, catquiz-Fall unter der Schwelle. Zuordnung aller offenen Punkte: `nachweise-20261123.md`. Ein erster Gesamtlauf wurde abgebrochen und verworfen (Webserver durch meinen Abgleich neu gestartet, Sitzung Teil 81). **Nicht geprüft:** Behat, MySQL, NVDA (Windows), k6/JMeter selbst (Live-Aufrufe aus einer angemeldeten Seite gegen 300 Lernende geprüft), GitHub-CI am Commit.
- Jest-Workflow 2026-11-22 `2026112201`: geändert nur `jest.yml` (Bundle-Gate vor `npm install`, übrige Prüfungen auch bei rotem Gate), Doku, die Versionen. Anlass: Lauf 45034960566 rot im Bundle-Gate, weil `npm install --save-dev …` die gehashte `package.json` veränderte (belegt, nachgestellt; Bundle und Quellen waren richtig). Lokal die Schritte von `jest.yml` wörtlich auf frischer Kopie: Gate, Installation, Jest 889 (110 Suites), Lint-Gate, Build-Probe grün; Gegenprobe rot. **Nicht geprüft:** der Lauf auf GitHub.
- Videoauslieferung 2026-11-22 `2026112200`: geändert nur Workflows (`e2e.yml`, `playwright.yml` je Plugin, `a11y-screenreader.yml`), die Smoke-Konfiguration (JSON-Reporter), ein neues Skript `tests/e2e/support/keep-failed-videos.mjs` je Plugin, Doku und die Versionen. Roter Lauf: nur die Videos der fehlgeschlagenen Tests im Artefakt; grüner und abgebrochener Lauf: alle. Lokal geprüft am synthetischen Lauf (E2E- und Smoke-Konfiguration, mit Wiederholung) und an den Randfällen (keine `results.json`, falsche Aufrufe); YAML parsbar; `moodle-plugin-ci`-Statik 19/19 ohne Befund. Kein PHP- oder Vue-Code geändert, Test-Suites daher nicht erneut. **Nicht geprüft:** die Workflows auf GitHub, der NVDA-Schritt unter Windows.
- Re-Audit 2026-11-21 Abend und Entscheidung 2026-11-22 `2026112102`: geändert PHP (mod/local, u. a. `cohort`, `group_progress`, `get_lp_user_path_relations`, `mod_form`, `lib.php`), Vue-Quellen und Bundle, Tests und Fixtures, Doku, die Versionen. PHPUnit local 504 (1 übersprungen, 1 unvollständig wie bisher) / mod 148 / enrol 75, Gruppe `adele_release` 70, je auf PostgreSQL 16 und MariaDB 10.11; Jest 889 (110 Suites); Bundle-Gate (`check-bundle.js`) und Lint-Gate (`lint-gate.js`, 92 Befunde, keiner über der Baseline) grün; `moodle-plugin-ci`-Statik 19/19 ohne Befund. Lokal CI-nah (jede Suite auf frisch geseedeter Seite, Server neu gestartet): Smoke local 17/17, mod 4/4, enrol 4/4; E2E Chromium local 84/84, mod 63/63, enrol 6/6; Firefox local 4/4, mod 12/12. Gegenprobe: ohne die neue Teilnehmerliste ohne Gruppen 3 PHPUnit-Tests rot. Prüfstand-Fund (belegt, nur lokal): der lang laufende `php -S` führte nach dem Abgleich noch alten Klassencode aus; erst ein Neustart lud den neuen (Grund nicht belegt; die CI startet den Server je Lauf neu) – siehe Sitzung Teil 78. **Nicht geprüft:** Behat, MySQL, NVDA (Windows), k6/JMeter selbst, Last-Snapshot; GitHub-CI am Commit steht aus.
- E2E-Korrektur 2026-11-21 `2026112101`: geändert nur Testcode (Einschreibe-Helfer local/mod, R26-Zähler, A18-Vorprüfung für SR-01) und die Versionen. Lokal CI-nah (jede Suite auf frisch geseedeter Seite): Smoke local 17/17, mod 4/4, enrol 4/4; E2E Chromium local 84/84, mod 62/62, enrol 6/6; Firefox local 4/4, mod 12/12; die vier CI-roten Tests einzeln grün; R26 30/30. Zwei lokale Einzelausfälle „Browser geschlossen“ (Ursache nicht belegt, in Wiederholungen nicht aufgetreten, in der CI nie) – siehe Sitzung Teil 77. Last: `seed_large.php` und der k6-Plan als Node-Nachbau (10 Nutzer, 30 s): 0 Fehler, p95 611 ms. Jest 885; `moodle-plugin-ci`-Statik ohne Befund. **Nicht geprüft:** Behat, MySQL, NVDA (Windows), k6/JMeter selbst; PHPUnit nicht erneut (kein PHP geändert). GitHub-CI am Commit steht aus.
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
| local #11 (PHP ≤ 8.1) | abnahmefähig | CI: expandierte Jobs nur PHP ≥ 8.2, alle Testarten grün (seit `2026112300` lokal ohne Skip/Incomplete) |
| local #12 (Gruppenranglisten) | abnahmefähig (seit `2026112600`; `userlist` Variante A freigegeben 2026-11-26) | AK-Text AK0-L2 in GitHub übernehmen (`issues/local_adele-12-16-ak-userlist-entwurf.md`); CI-Matrix |
| local #13 (Nicht-Lernende) | abnahmefähig (ADELE-Einschreibungsstatus im Roster korrigiert `2026112600`; E2E G5) | CI-Matrix; „ohne Pfad“ nur PHPUnit/Jest (im Browser nicht dauerhaft herstellbar) |
| local #14 (Live-Marker) | abnahmefähig (seit `2026112300`: 300-Lernende-Test, k6 mit Live-Aufrufen) | CI-Matrix, k6-Lauf |
| local #15 (Knotenzähler) | abnahmefähig (seit `2026112102`; Rückweg mit Ausschnitt/Scroll/Fokus: E2E L3) | CI-Matrix |
| local #16 (mod: Gruppen) | abnahmefähig (seit `2026112600`; Variante A; Gruppierungen im Browser: E2E G4) | AK-Text AK0-M4 in GitHub übernehmen; CI-Matrix |
| local #17 (mod: Live-Einstellungen) | geschlossen (Re-Audit 2026-11-22) | – |
| local #18 (DOM-IDs) | abnahmefähig (MiniMap-IDs behoben `2026112300`) | CI (Chromium, Firefox, a11y); DoD2 Feedback-Dialog: Entscheidung, ob er bleibt |
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
