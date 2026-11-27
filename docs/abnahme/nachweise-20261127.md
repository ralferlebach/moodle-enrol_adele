# Nachweise zum Re-Audit vom 2026-11-27 (`0.8.0`, Version `2026112700`)

Grundlage ist „ADELE – Re-Audit und Nacharbeitsauftrag“ vom 27.11.2026 (Build
`2026112600`, 75/91 erfüllt). Das Nachweispaket `adele-0.8.0-nachweise.zip` enthält
die Protokolle; die Kommentare je Issue liegen in `adele-0.8.0-kommentare.zip`.

## Release

Alle drei Plugins haben `release = '0.8.0'`, `version = 2026112700`, die
Abhängigkeiten untereinander stehen auf `2026112700`, Reife BETA.

## Getesteter Stand = Repository

Die HEADs aus dem Re-Audit sind byte-gleich mit der Lieferung `2026112600`. Gelesen
mit `pruefkette/repovergleich.py` aus einem nur lesend geholten `origin/development`:

| Komponente | HEAD | Ergebnis |
|---|---|---|
| local_adele | `b8bd9b2` | 694/694 Dateien byte-gleich |
| mod_adele | `6b537b9` | 127/127 |
| enrol_adele | `f779f56` | 211/211 |

Die lokalen Läufe zu `2026112600` (`nachweise-20261126.md`) gelten damit genau für
diese Commits.

## Abschnitt 3 – Roster: Lebenszyklus und Lehrenden-Freigabe (#13 AK1.5)

**Entscheidung des Auftraggebers (2026-11-27):** Das Karenzfenster wird
hingenommen. Belegt: Die Karenz ist `enrol_adele\task\remove_user_path_adhoc::
DELAY_SECONDS` = 300 s, plus die Zeit bis zum nächsten Cron-Lauf. Während dieser
Zeit steht eine ausgetretene Person in hidden-Einbettungen noch auf der Liste.
Danach ist nichts mehr von ihr übrig. Das Fenster schützt den Lernverlauf bei
Resync-Blips.

**Externe Suspension, bewertet:** Im hidden-Modus ist die ADELE-Einschreibung
absichtlich suspendiert. Eine Suspension von außen ändert an ihr nichts und lässt
sich von ADELEs eigener nicht unterscheiden. Die Person bleibt Lernende, solange
ihr Startkurs sie trägt. Wer jemanden entfernen will, meldet ihn ab: Eine Abmeldung
von außen nimmt die Person sofort aus allen Sichten.

**Neuer Test `local/tests/roster_lifecycle_test.php`**, nur über echte Wege
(Observer, Abmeldung, aufgeschobene Aufgabe, Teacher-Grants), geprüft in
Teilnehmerliste, Live-Markern, Live-Zähler, Gruppenübersicht (gemessen und
Mitglieder) und Nicht-Lernenden:

| Fall | Ergebnis |
|---|---|
| fünf Lernende über den Startkurs, hidden | ADELE-Einschreibung suspendiert, alle in allen Sichten, Zähler 5/5/5 |
| Suspension von außen | bleibt Lernende |
| Abmeldung von außen | sofort aus allen Sichten |
| Austritt aus dem Startkurs | Löschaufgabe eingereiht, Laufzeit = Austritt + 300 s; bis dahin gelistet (angenommenes Fenster) |
| nach der Karenz | Host-Einschreibung und Snapshot gelöscht, in keiner Sicht, nicht auf dem Roster |
| parallele aktive Einschreibung, Austritt aus dem Startkurs | nach der Karenz auf dem Roster, aber „ohne Lernpfad“; Gruppe zählt sie als Mitglied, nicht als gemessen (2/2/3) |
| nur Lehrenden-Freigabe (kind 3), Lehrendenrolle | Personal: in keiner Lernendensicht, unter Nicht-Lernenden als `nonlearner`, Gruppe 1/1/1 |
| dieselbe mit Snapshot des Pfads | weiterhin Personal |

**Befund dabei, behoben:** Eine Person nur mit Lehrenden-Freigabe stand vorher als
„ohne Lernpfad“ in den Listen und zählte als Gruppenmitglied. Mit einem Snapshot
**erschien sie als Lernende** in der Teilnehmerliste. Ursache:
`enrol_state::get_nonparticipant_userids()` ließ ADELE-Einschreibungen und
`enrol_adele`-Rollen nie als eigenes Recht gelten. Jetzt zählt eine aktive Freigabe
(kind 3) samt ihren Rollen; `is_participant()` (Abonnement, Trägerschaft) ist
unverändert. Issue-Entwurf: `issues/local_adele-issue-teacher-grant-staff.md`.

**Gegenprobe** mit `enrol_state.php` und `learning_paths.php` von `b8bd9b2`:
- der Austrittstest grün, die Lebenszyklus-Semantik bestand also schon;
- der Lehrenden-Test rot: `nopath` statt `nonlearner`;
- mit Snapshot in der Teilnehmerliste gelistet.

Auf `0.8.0` grün auf PostgreSQL 16 und MariaDB 10.11.

## Abschnitt 4 – CI auf `development` (nur gelesen)

Die Push-Läufe auf `b8bd9b2`, `6b537b9` und `f779f56` sind grün: Moodle Plugin CI
und PostgreSQL in allen drei Repositories, dazu Jest in local. Heute manuell
gestartet und grün:
- local: Smoke, k6, JMeter;
- mod: Smoke und zweimal E2E;
- enrol: Smoke und E2E.

Der Screenreader-Lauf in local lief noch. Run-URLs stehen im Nachweispaket unter
`ci/`.

**Nicht gelesen:** Jobs, Tests, Skips, Summaries und Artefakte. Die Actions-API ist
nicht freigegeben, die Detailseiten waren rate-limitiert. **Lücke:** local E2E ist
auf `b8bd9b2` nicht gelaufen, der letzte Lauf ist vom 21.11.

## Abschnitt 6 – M2

Neuer lokaler Gesamtlauf auf `0.8.0`, CI-nah. Jede Suite lief frisch geseedet, ohne
Abgleich während des Laufs. Je Suite gesichert wurden Bildschirmfotos, Videos,
Traces bei Fehlern und der Ausschnitt des Serverlogs:

| Suite | Ergebnis |
|---|---|
| Smoke local / mod / enrol | 17/17, 4/4, 4/4 |
| E2E Chromium local / mod / enrol | 84/84 (mit M1/M2), 66/66, 6/6 |
| E2E Firefox local / mod | 4/4, 12/12 |

Serverlog: keine PHP-Warnung, -Notice oder -Fatal und keine Debug-Ausgabe. Die
Treffer der Suche nach „exception“ sind Seitenaufrufe des Moduls
`core/notification/exception`.

M2 war damit 1-mal rot in 7 Läufen: im Gesamtlauf vom 27.11. früh, danach 3/3
einzeln, 2/2 nach `roles-transfer-ownership`, 1/1 im Gesamtlauf `0.8.0`. Die
Ursache bleibt **unbelegt**; die Artefakte des roten Laufs waren überschrieben. Die
Prüfkette sichert jetzt jede Suite (`pruefkette/e2e_all2.sh`), ein erneuter Fehler
käme mit Video, Trace und Serverlog. Nichts an M2 wurde geändert, auch kein
Timeout.

## Abschnitt 7 – #18

- **CatQuiz, Entscheidung des Auftraggebers (2026-11-27):** Unterstützt wird derzeit
  nur `ALiSe-v-1.2.0-legacy`; später `migration-zu-moodle-5.x`, sobald ADELE mit
  Moodle 5.x läuft. `main` wird nicht unterstützt, kein Fixauftrag. Nachgeprüft in
  nur lesenden Klonen: `id="dropdownMenuButton"` steht nur auf `main` (`53c92ee`),
  nicht auf `ALiSe-v-1.2.0-legacy` (`53b809e`) und nicht auf
  `migration-zu-moodle-5.x` (`e7d07d7`).
- **FeedbackModal:**
  - Der Auftraggeber hat Bildschirmfotos erhalten (im Nachweispaket unter
    `lokal/18-feedbackmodal/`).
  - Der Dialog „Feedback bearbeiten“ sitzt im Abschlusskriterien-Editor.
  - Er hat 0 Öffner auf der Seite, und `store.state.feedback` setzt im Code
    nichts.
  - Das Feedback wird im Feedback-Knoten des Editors bearbeitet.
  - Die Entscheidung über Entfernen oder Behalten **steht aus**. Der Dialog ist
    unverändert, #18 DoD2 bleibt offen.

## Prüfstand `0.8.0`

Siehe `README.md`, Zeile „Re-Audit 2026-11-27“.
