# E2E-Suite des ADELE-Ökosystems

**Stand:** Session 006
**Grundlage:** [`testplan/AdeLe_E2E_Testplan_UserStories.md`](testplan/AdeLe_E2E_Testplan_UserStories.md)

Zwei Playwright-Suiten je Plugin, bewusst getrennt:

| Suite | Ort | Auslöser | Zweck |
|---|---|---|---|
| Smoke | `tests/playwright/`, `playwright.yml` | wie bisher | schnelle Rauchprobe |
| **E2E** | `tests/e2e/`, **`e2e.yml`** | **nur manuell** | ganze Ketten über alle drei Plugins |

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
| je Lernpfad ein Lernender | `student01`–`student04` | Linear A1, Linear A2, Verzweigt B1, Äquivalenzumformung |
| Negativkontrolle | `student05` | auf keinem Pfad |
| Hostkurs-Mitglieder | `student06`, `student07` | Kurs `E2EHOST` |
| Hostkurs-Außenstehender | `student08` | nicht in `E2EHOST` |
| Adele-Manager | `fx_adele_manager` | Systemrolle `adelemanager` |
| Adele-Assistent | `fx_adele_assistant` | Systemrolle `adeleassistant`, Bearbeiter **nur** an „Linear A2" |

Ein Lernender je Pfad, nicht einer auf allen: Die Berechtigung vereinigt sich
über die Pfade, ein Lernender auf allen vier wäre über einen zweiten Pfad in
Kursen, die der erste noch sperrt.

Der Seed ist wiederholbar und setzt den Hostkurs zurück (Aktivitäten entfernt,
ADELE-Einschreibungen der Hostkurs-Personen endgültig zurückgenommen).

## 5. Umsetzungsstand gegenüber dem Plan

| Plan | Kette | Plugin | Stand |
|---|---|---|---|
| §3 R1 | Admin verwaltet alle Lernpfade (anlegen, umbenennen, duplizieren, löschen, je mit Neuladen) + Negativkontrolle | local | **umgesetzt**, 2 Tests |
| §3 R2 | Manager benennt einen fremden Lernpfad um; Lernende sehen keine Verwaltung | local | **umgesetzt**, 2 Tests |
| §3 R3 | Assistent bearbeitet genau den Pfad, für den er Bearbeiter ist; beim anderen nur Ansehen | local | **umgesetzt**, 2 Tests |
| §27 Freigabeteil | Berechtigung → Einschreibung → realer Zugriff; gesperrter Knoten ohne Zugriff; Kontrollperson ohne Effekt; Persistenz; Kursübersicht | enrol | **umgesetzt**, 5 Tests |
| §16 H1 | Aktivität über das Formular anlegen → Hostkurs-Mitglieder im Eingangskurs, Außenstehende nicht; Pfad in der Aktivität sichtbar | mod | **umgesetzt**, 1 Test |
| §16/§27 H2 | Austritt aus dem Hostkurs → Zugriff entzogen, und zwar nur für die abgemeldete Person | mod | **umgesetzt**, 1 Test |
| §27 Entzugsteil C2 | Eingangsknoten löschen → Speicherverweigerung; Zugangskriterium entfernen → Speichern gelingt; Zugriff endet; Pfad löschen → Zugriff endet endgültig; je mit Negativkontrolle | enrol | **umgesetzt**, 1 Test mit 7 Schritten |
| §18 Accessibility | Tastaturroute, Live-Region, Namen, axe-core | local (Smoke) | als `issue574-575-accessibility.spec.ts` in der Smoke-Suite |
| §3 R4–R6 | Lehrkraft vergibt Bearbeitungsrechte über die Oberfläche, Kollaboratoren | local | offen |
| §4–5 E1, E2 | Referenzpfad per echtem Drag&Drop | local | offen |
| §6–9 | Zugangs- und Abschlussbedingungen | local | offen |
| §10 T1–T5 | Zeitgrenzen | local | offen – **blockiert** durch fehlende steuerbare Testzeit (Plan §20) |
| §11–15 | Feedback, Fortschritt, manuelle Abschlüsse, Routing | local | offen |
| §16 H3–H4, §17 | weitere Teilnehmerquellen, Host-Modi, Result Visibility, Datenschutz | mod | offen |


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
