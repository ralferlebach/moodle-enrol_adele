# E2E-Suite des ADELE-Ökosystems

**Stand:** Session 006
**Grundlage:** [`testplan/AdeLe_E2E_Testplan_UserStories.md`](testplan/AdeLe_E2E_Testplan_UserStories.md)

Zwei Playwright-Suiten je Plugin, bewusst getrennt:

| Suite | Ort | Auslöser | Zweck |
|---|---|---|---|
| Smoke | `tests/playwright/`, `playwright.yml` | wie bisher | schnelle Rauchprobe |
| **E2E** | `tests/e2e/`, **`e2e.yml`** | **nur manuell** | ganze Ketten über alle drei Plugins |
| **Screenreader** | `local_adele/tests/a11y-screenreader/`, **`a11y-screenreader.yml`** | **nur manuell**, Windows | was NVDA tatsächlich vorliest |

## 1. Starten

GitHub → Repository → **Actions** → **E2E** → **Run workflow**. Der Eintrag
erscheint erst, wenn `e2e.yml` auf dem Default-Branch liegt (`main` bei
`enrol_adele` und `local_adele`, `master` bei `mod_adele`). Kein Push-Trigger,
kein Zeitplan.

| Eingabe | Bedeutung | Vorgabe |
|---|---|---|
| `grep` | nur Tests, deren Titel passt, z. B. `ADELE-E2E-C1` | alle |
| `php_version` | 8.1, 8.2, 8.3 | 8.3 |
| `sibling_ref` | Branch/Tag/Commit der beiden anderen Plugins | gleichnamiger Branch, sonst Default |
| `retention_days` | Aufbewahrung des Artefakts | 30 |

Moodle ist fest 4.5 (`MOODLE_405_STABLE`), Datenbank PostgreSQL 16.

**Wichtig:** Die E2E-Suiten von `mod_adele` und `enrol_adele` nutzen den
Fixture-Seed aus `local_adele`. Bis die Änderungen dieser Session in
`local_adele` upstream liegen, schlagen ihre Läufe im Schritt „Seed the
shipped fixtures" fehl – dann mit `sibling_ref` auf einen Branch zeigen, der
den Stand enthält.

## 2. Ergebnis

Jeder Lauf lädt **ein** Artefakt hoch, bei Erfolg, Fehlschlag und Abbruch:
`e2e-results-<plugin>-<run-id>-<attempt>`.

```
playwright-report/   HTML-Report, jeder Test mit Screenshot und Video
test-results/        Videos (video.webm) je Test, Traces nur bei Fehlschlag
results.json         vollständiger Ergebnisbaum
junit.xml            dasselbe im JUnit-Format
run.log              Konsolenausgabe
environment.md       Moodle, PHP, exakter Commit jedes Plugins
summary.md           eine Zeile je Test mit Videopfad (auch als Job-Summary)
moodle-server.log.gz Anfrageprotokoll
```

Videos entstehen für **jeden** Test, auch für bestandene (`video: 'on'`).
Anmeldungen laufen im Hintergrund per HTTP und sind nicht Teil der Aufnahme.

## 3. Grundsätze der Suite

- **Keine Wiederholungen** (`retries: 0`). Eine Kette, die erst im zweiten
  Anlauf grün wird, lief auf dem Zustand des ersten.
- **Ein Worker.** Alle Ketten verändern dieselbe Instanz.
- **Realer Zugriff statt Datenbankeintrag.** `expectCourseOpen` /
  `expectCourseClosed` rufen die Kursseite auf; eine Zeile in der
  Teilnehmerliste gilt nicht als Beweis (Plan §2).
- **Ein Artefakt je Lauf, immer.** Auch die Smoke-Workflows laden seit
  `2026100202` Report und Videos bei grünem **und** rotem Lauf hoch; vorher
  gab es bei Fehlschlag nur die fehlgeschlagenen Verzeichnisse ohne Video —
  also genau dann keine Aufnahme, wenn sie gebraucht wird.
- **Warteschlange abarbeiten statt warten.** `expectCourseOpenAfterTasks` und
  `expectCourseClosedAfterTasks`
  ruft `admin/cli/adhoc_task.php` begrenzt wiederholt auf (höchstens 60 s),
  weil Teile der Kette bewusst verzögert eingeplant werden (`local_adele`
  120 s, `enrol_adele` 300 s beim Entzug).
- **Jede Kette hat eine Negativkontrolle** (Plan §2 G).
- **Fixtures werden hinterlassen, wie sie vorgefunden wurden.** Der Seed
  erkennt Lernpfade am Namen; eine Kette, die einen Pfad umbenennt und so
  liegen lässt, erzeugt beim nächsten Seed-Lauf eine zweite Kopie. R2 und R3
  stellen den Namen deshalb in einem `finally` wieder her.
- **Der letzte Bearbeiter ist geschützt.** Die Entfernen-Schaltfläche
  erscheint erst, wenn mindestens zwei Personen eingetragen sind; ein Pfad
  soll nicht ohne Bearbeiter zurückbleiben. Eine Kette, die ein Recht vergibt,
  kann es daher nicht immer vollständig zurücknehmen — der Seed leert die
  Bearbeitertabelle der Fixture-Pfade deshalb bei jedem Lauf.
- **Lernpfade nie roh aus der Datenbank löschen.** Die zugehörigen
  ADELE-Einschreibungen bleiben dann verwaist zurück, und spätere Ketten
  scheitern an Zugriffen, die niemand mehr erklären kann. Löschen gehört in
  die Oberfläche oder in die `purge_*`-Methoden von `enrol_adele`.
- **Zwei Leinwände, die gleich aussehen.** Die Knotenkachel führt über das
  Schloss-Symbol zu den ZUGANGSkriterien und über die Checkliste zu den
  ABSCHLUSSkriterien; der Kachelkörper öffnet die Abschlusskriterien. Beide
  Leinwände werden von derselben Komponente gerendert
  (`completion/CompletionControls.vue`, unterschieden über `props.condition`),
  sehen identisch aus und speichern den ganzen Lernpfad selbst. Wer die
  falsche bearbeitet, löscht klaglos die falsche Bedingung.
  (`restriction/RestrictionControls.vue` ist toter Code und wird nirgends
  eingebunden — nicht als Vorlage lesen.)
- **Kachel-Schaltflächen heißen jetzt etwas.** Schloss, Checkliste, Stift und
  Papierkorb tragen seit `local_adele` 2026100101 ein `aria-label` aus
  Funktion und Kursname (#575 B2); vorher stand der Name nur im `title` und
  war über die Rolle nicht auffindbar. Die Ketten greifen sie darüber, nicht
  über Symbolklassen.
- **Bedienelemente ohne Zusage.** Moodles Teilnehmerliste bindet ihren
  Abmelde-Klick per AMD-Modul erst nach dem Markup; ein früher Klick bleibt
  wirkungslos, und keine Wartezeit repariert das. H2 folgt deshalb dem Link
  (Moodles Weg ohne JavaScript), statt zu klicken.

## 4. Fixtures

Liegen in `local_adele/tests/playwright/fixtures/` (Herkunft:
`ralferlebach/adele-test`, Commit `4927a19`; Kopie, kein Checkout). Geladen
durch `seed_fixtures.php`:

| Rolle | Konto | Zweck |
|---|---|---|
| je Lernpfad ein Lernender | `ADELE_FIXTURE_LEARNER_<PFAD>` | Linear A1, Linear A2, Verzweigt B1, Äquivalenzumformung |
| Negativkontrolle | `ADELE_FIXTURE_CONTROL_USER` | auf keinem Pfad |
| Hostkurs-Mitglieder | `ADELE_FIXTURE_HOST_MEMBER_1/2` | Kurs `E2EHOST` |
| Hostkurs-Außenstehender | `ADELE_FIXTURE_HOST_OUTSIDER` | in keinem Host- und keinem Knotenkurs |
| Eingangskurs-Lernende | `ADELE_FIXTURE_ENTRY_LEARNER` | nur im Eingangskurs T01, auf keinem Pfad |
| zweiter Hostkurs | `E2EHOST2` (`ADELE_FIXTURE_HOST_COURSE_2`) | bewusst leer, für die Quelle „Startknoten-Kurs" |
| Adele-Manager | `fx_adele_manager` | Systemrolle `adelemanager` |
| Adele-Assistent | `fx_adele_assistant` | Systemrolle `adeleassistant`, Bearbeiter **nur** an „Linear A2" |
| Lehrkraft | `fx_teacher` | ohne Rolle und ohne Einschreibung; die Einstellung `enrollassistant` steht auf der Lehrkraft-Rolle |

Ein Lernender je Pfad, nicht einer auf allen: Die Berechtigung vereinigt sich
über die Pfade, ein Lernender auf allen vier wäre über einen zweiten Pfad in
Kursen, die der erste noch sperrt.

Der Seed ist wiederholbar und setzt den Hostkurs zurück (Aktivitäten entfernt,
ADELE-Einschreibungen der Hostkurs-Personen endgültig zurückgenommen).

## 5. Umsetzungsstand gegenüber dem Plan

| Plan | Kette | Plugin | Stand |
|---|---|---|---|
| §6 R1 | Administrator verwaltet alle Lernpfade (anlegen, umbenennen, duplizieren, löschen, je mit Neuladen) + Negativkontrolle | local | **umgesetzt**, 2 Tests |
| §6 R2 | Manager bearbeitet einen fremden Lernpfad; Lernende sehen keine Verwaltung | local | **umgesetzt**, 2 Tests |
| §6 R3 | Lehrkraft-Rolle im Kurs → Editorzugriff als Assistent, eigener Pfad anlegbar | local | **umgesetzt**, 1 Test; Entzug des Zugriffs siehe „Offen" |
| §6 R4a | Kollaborator bearbeitet genau den freigegebenen Pfad, beim anderen nur Ansehen | local | **umgesetzt**, 2 Tests |
| §6 R4b | Recht über die Suche im Editor vergeben und entziehen; letzter Bearbeiter geschützt | local | **umgesetzt**, 1 Test mit 6 Schritten |
| §7 E1 (Teil) | Pfad im Editor anlegen, Kurs per echtem Drag&Drop ablegen, speichern, verlassen, erneut öffnen; Lernende ohne Editor | local | **umgesetzt**, 2 Tests; Verketten weiterer Knoten siehe §5c |
| §6 R5 | parallele Berechtigungsgründe: 3 von 6 Zeilen der Matrix (Manager + Kollaboration, Assistent + Kollaboration, nur Kollaboration) | local | **umgesetzt**, 2 Tests; die übrigen Zeilen siehe §5a |
| §6 R6 | Eigentümerschaft über die Kronen-Schaltfläche übertragen, Bestand nach Neuladen, alter Eigentümer als Manager behält Zugriff | local | **umgesetzt**, 1 Test mit 5 Schritten |
| §16 H1 | Aktivität über das Formular anlegen → Hostkurs-Mitglieder im Eingangskurs, Außenstehende nicht; Pfad in der Aktivität sichtbar | mod | **umgesetzt**, 1 Test |
| §16/§27 H1b | Austritt aus dem Hostkurs → Zugriff entzogen, und nur für die abgemeldete Person | mod | **umgesetzt**, 1 Test |
| §16 H2 | zweite Teilnehmerquelle „Einschreibung im Startknoten-Kurs": Person aus dem Eingangskurs wird in den Hostkurs getragen, Außenstehende nicht | mod | **umgesetzt**, 1 Test |
| §27 C1 | Berechtigung → Einschreibung → realer Kurszugriff, Negativkontrolle, Persistenz | enrol | **umgesetzt**, 5 Tests |
| §27 C2 | Eingangsknoten löschen → Speicherverweigerung; Kriterium entfernen → Speichern gelingt; Zugriff endet; Pfad löschen | enrol | **umgesetzt**, 1 Test mit 7 Schritten |
| §7 E2 | logische Kombinationen (UND, ODER, Klammerung) per Drag&Drop | local | offen – siehe §5c |
| §6–9 | Zugangs- und Abschlussbedingungen | local | offen |
| §10 T1–T5 | Zeitgrenzen | local | offen – **blockiert** durch fehlende steuerbare Testzeit (Plan §20) |
| §11–15 | Feedback, Fortschritt, manuelle Abschlüsse, Routing | local | offen |
| §16 H2 (Rest) | dritte Quelle „beliebiger Knotenkurs" und Kombinationen | mod | offen |
| §16 H3, §17 | Host-Modi (sichtbar, verborgen, keine), Result Visibility, Datenschutz | mod | offen |


## 5a. Offene Punkte aus R3 und R5

Die Kette prüft bisher nur die Hinzunahme: Kursrolle vergeben → Editorzugriff.
Der Plan verlangt zusätzlich, dass der Zugriff beim Entfernen der Kursrolle
wieder entfällt, sofern keine andere Quelle existiert. Im Code legt
`enrollment::assign_assistant_to_role()` die Systemrolle `adeleassistant` nur
an; ein Gegenstück zum `role_unassigned`-Ereignis gibt es in `db/events.php`
nicht. Bevor daraus ein Test oder eine Fehlermeldung wird, ist zu klären, ob
der Entzug gewollt ist — bisher **nicht geprüft**, also auch nicht behauptet.

Für diesen Punkt liegt bereits ein Entwurf vor:
[`issues/local_adele-issue-assistant-role-no-revocation.md`](issues/local_adele-issue-assistant-role-no-revocation.md).

Dieselbe Lücke betrifft drei Zeilen der R5-Matrix: „Assistent + Kollaborator,
Assistent entzogen", „nur Assistent, Assistent entzogen" und „Eigentümer +
Kollaborator". Die ersten beiden setzen den Entzug einer Systemrolle voraus,
den das Plugin nicht als Gegenstück zur automatischen Vergabe anbietet; die
dritte verlangt einen Eigentümer ohne Managerrolle, den die Fixtures derzeit
nicht vorsehen. Umgesetzt sind die drei Zeilen, die sich ohne diese Klärung
eindeutig entscheiden lassen.

## 5b. Screenreader-Suite (NVDA)

Die einzige Suite, die prüft, was ein Screenreader **sagt**. Jest belegt das
Attribut im Markup, axe-core belegt, dass keine Regel verletzt ist — beides
sagt nichts darüber, ob der Name auch vorgelesen wird. Hier liest NVDA die
Seite, und der Test liest NVDAs Sprachprotokoll.

- **Nur Windows.** `@guidepup/playwright` wirft beim Import auf jeder anderen
  Plattform „No available supported screen readers", noch bevor ein Test
  eingesammelt wird. Ein `skip` hilft deshalb nicht; das Verzeichnis darf
  niemals in den `testDir` einer anderen Suite geraten.
- **Eigene Playwright-Version:** Guidepup verlangt 1.57 oder neuer, die
  übrigen Suiten sind auf 1.49.1 festgelegt. Eigenes Verzeichnis, eigenes
  Lockfile, keine Wechselwirkung.
- **Kein Headless, ein Worker.** Ein Screenreader hängt an einer sichtbaren
  Sitzung und existiert nur einmal.
- **Keine Wiederholungen.** Ein Lauf, der erst im zweiten Anlauf grün wird,
  sagt nichts darüber, was eine Person beim ersten Mal gehört hätte.

**Der Workflow baut keine Moodle-Instanz.** Service-Container gibt es nur
unter Linux, und NVDA nur unter Windows. Der Lauf richtet sich deshalb gegen
eine **bestehende** Instanz:

| Eingabe/Geheimnis | Bedeutung |
|---|---|
| `base_url` | Adresse der Instanz, auf der die drei Plugins und die Fixtures liegen |
| `ADELE_A11Y_USER`, `ADELE_A11Y_PASSWORD` | Konto auf dieser Instanz (Repository-Secrets) |

Im Artefakt liegt neben Report und Video das **Sprachprotokoll** je Test
(`nvda-spoken-phrases.txt`, als Anhang im HTML-Report). Es ist das, was eine
Durchsicht zuerst lesen sollte; das Video zeigt denselben Lauf von außen.

Umgesetzt ist bisher `ADELE-SR-01`: Die Übersicht wird vorgelesen, und die
Bedienelemente werden mit ihrem Namen angesagt — der Sichtbarkeitsschalter
zusätzlich mit seinem Zustand (gedrückt/nicht gedrückt). Das ist die
Gegenprobe zu #575 B2.

**Noch nie gelaufen.** Diese Suite ist in der Linux-Umgebung der Entwicklung
nicht ausführbar; geprüft sind bisher nur Typen, Konfiguration und Workflow
(actionlint). Der erste echte Lauf muss auf einem Windows-Runner erfolgen.

## 5c. Drag&Drop: was geht und was nicht

Echtes HTML5-Drag&Drop ist mit Playwright möglich — anders als seinerzeit mit
Behat. Es funktioniert aber nur in einer Form:

- **`locator.dragTo(ziel)` wirkt**, wenn das Ziel ein echtes Element ist und
  in dessen Mitte abgelegt wird. So entsteht der erste Knoten auf der leeren
  Leinwand (Ziel: der Startmarker `starting_node`).
- **Die Maus-API wirkt nicht.** `mouse.down/move/up` löst hier kein
  HTML5-Ziehen aus, weil die Sidebar-Einträge `draggable`-Elemente sind. Es
  entstehen weder Ablagezonen noch ein Knoten.
- **`targetPosition` außerhalb des Zielelements bricht ab** (Playwright
  wartet auf einen Punkt, den es nicht treffen kann).

- **Chromes Drag-Schnittstelle hilft auch nicht.** Mit
  `Input.setInterceptDrags` und `Input.dispatchDragEvent` liesse sich mitten
  im Ziehen zielen, aber `Input.dragIntercepted` feuert nicht: Playwright
  fängt das Ziehen bereits selbst ab.

Dadurch ist das **Anhängen an einen vorhandenen Knoten noch ungelöst**: Die
Ablagezonen (`dropzone_parent`, `dropzone_child`, `dropzone_and`,
`dropzone_or`) entstehen erst *während* des Ziehens in `SidebarPath.onDrag()`
und nur, wenn der Zeiger zugleich den Startmarker schneidet. Ein Ziel, das
beim Start des Ziehens noch nicht existiert, kann `dragTo` nicht ansteuern.

Offen ist damit der größere Teil von E1 (mehrere Knoten, Verbindungen,
Bedingungen, Zeitwerte, UND/ODER, Feedback) und ganz E2. Für die Verkettung
gibt es zwei Wege, zwischen denen zu entscheiden ist:

Erfasst als local_adele #580
([`issues/local_adele-issue-dropzones-only-during-drag.md`](issues/local_adele-issue-dropzones-only-during-drag.md)).

1. **Produktseitig**: die Ablagezonen einblenden, sobald ein Ziehen beginnt,
   statt erst bei Schnittmenge mit dem Startmarker. Dann existiert das Ziel
   vor dem Ziehen und `dragTo` trifft es. Das hilft auch Nutzern, die heute
   raten müssen, wohin sie ziehen dürfen — eine Testkennung allein genügt
   **nicht**, weil die Zonen im DOM ohnehin ein `data-id` tragen; das Problem
   ist der Zeitpunkt, nicht die Benennung.
2. **Testseitig**: die Verkettung über die Tastaturroute aus #575 B5 abdecken
   und für Drag&Drop nur das Ablegen auf der leeren Leinwand prüfen. Dann
   bleibt die Drop-Verarbeitung bei bestehenden Knoten ungetestet.

## 5d. Zeit in Tests (Vorbereitung der Kette T)

Geprüft am 2026-10-05, zwei sich ergänzende Wege:

- **Prozesszeit verschieben (`libfaketime`)** — für die E2E-Ketten. Nur die
  PHP-Prozesse (Webserver, CLI-Task-Runner) laufen unter `LD_PRELOAD`; die
  Zeit lässt sich zur Laufzeit über eine Datei umstellen
  (`FAKETIME_TIMESTAMP_FILE`, `FAKETIME_NO_CACHE=1`). Gemessen: `time()` und
  `new DateTime()` folgen, Moodles Task-Manager hält eingeplante Aufträge
  unter verschobener Zeit für fällig, die Zeitbedingung `timed` ist
  sekundengenau steuerbar. PostgreSQL und Browser bleiben auf echter Zeit;
  die Plugins nutzen kein `NOW()` in SQL, und das Frontend liest die Uhr nur
  für die Bedienung.
- **Systemuhr stellen (`date -s`)** — technisch möglich, aber **ungeeignet**:
  Mit verstellter Uhr bricht TLS sofort („certificate has expired"), damit
  auch Git, npm, die GitHub-API und der Artefakt-Upload.
- **Moodle-Uhr im Code (`\core\clock`)** — **umgesetzt** in `2026100501`:
  local_adele #582, enrol_adele #11, mod_adele #36. Alle drei Plugins lesen
  „jetzt" nur noch über `\core\di::get(\core\clock::class)`; je ein
  Wächtertest (`tests/clock_usage_test.php`) verhindert Rückfälle, Tests mit
  `mock_clock_with_frozen()` prüfen die zeitabhängigen Entscheidungen.

Dabei gemessen: Das Ende eines Zeitfensters wirkt heute **ausschließlich**,
obwohl der Code `>=` prüft — eine Folge des Vergleichs geparster
Zeichenketten. Erfasst als local_adele #581
(`issues/local_adele-issue-timed-condition-timestamps.md`).
Die Kette T soll die Grenzwertsemantik erst festschreiben, wenn sie dort
entschieden ist.

## 6. Lokal ausführen

```bash
cd /pfad/zu/moodle
ADELE_SEED_I_KNOW=1 php local/adele/tests/playwright/seed_fixtures.php > /tmp/fix.env
php admin/cli/adhoc_task.php --execute
cd local/adele/tests/e2e && npm ci && npx playwright install chromium
set -a; . /tmp/fix.env; set +a
CI=1 npx playwright test
```

Webserver und Testlauf im **selben** Shell-Aufruf starten (siehe
`prompt-templates/environment-setup.md`, §8b).
