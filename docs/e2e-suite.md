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
| `grep` | nur Tests, deren Titel passt, z. B. `ADELE-E2E-V1` | alle |
| `php_version` | 8.1, 8.2, 8.3 | 8.3 |
| `sibling_ref` | Branch/Tag/Commit der beiden anderen Plugins | gleichnamiger Branch, sonst Default |
| `retention_days` | Aufbewahrung des Artefakts | 30 |

Moodle ist fest 4.5 (`MOODLE_405_STABLE`), Datenbank PostgreSQL 16.

**Begleit-Plugins:** Alle Workflows holen die beiden anderen Plugins vom
selben Eigentümer wie das laufende Repository (Wunderbyte-Upstream oder
Fork), vom gleichnamigen Branch, sonst von `main`. Alle drei Repositories
heißen `moodle-<typ>_adele` und haben `main` als Standard-Branch (seit
`2026100600`; vorher `moodle_local_adele` und für `mod_adele` `master`).

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
| Zeitfenster-Lernende | `fx_time_learner` | Pfad „E2E Zeitfenster": Eingangsknoten nur 2030-01-15 10:00–12:00 UTC offen |
| Fristen-Lernende | `fx_duration_learner` | Pfad „E2E Zeitdauer": Eingangsknoten einen Tag ab Zuordnung offen |
| Knotenkurs-Lernende | `student10` | nur manuell in T03 (späterer Knoten von „Linear A1"); Quelle 3 |
| Hostkurse 3–6 | `E2EHOST3`–`E2EHOST6` | leer, je ein Szenario: Quelle 3, Modus hidden, Modus none, Quellen 1+2 |
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
| §7 E1 | Referenzpfad T01 → T02 → T03 ausschließlich per Drag&Drop, nach erneutem Öffnen Knoten **und Kanten** unverändert; Einzelknoten; Lernende ohne Editor | local | **umgesetzt**, 3 Tests |
| §6 R5 | parallele Berechtigungsgründe: 3 von 6 Zeilen der Matrix (Manager + Kollaboration, Assistent + Kollaboration, nur Kollaboration) | local | **umgesetzt**, 2 Tests; die übrigen Zeilen siehe §5a |
| §6 R6 | Eigentümerschaft über die Kronen-Schaltfläche übertragen, Bestand nach Neuladen, alter Eigentümer als Manager behält Zugriff | local | **umgesetzt**, 1 Test mit 5 Schritten |
| §16 H1 | Aktivität über das Formular anlegen → Hostkurs-Mitglieder im Eingangskurs, Außenstehende nicht; Pfad in der Aktivität sichtbar | mod | **umgesetzt**, 1 Test |
| §16/§27 H1b | Austritt aus dem Hostkurs → Zugriff entzogen, und nur für die abgemeldete Person | mod | **umgesetzt**, 1 Test |
| §16 H2 | zweite Teilnehmerquelle „Einschreibung im Startknoten-Kurs": Person aus dem Eingangskurs wird in den Hostkurs getragen, Außenstehende nicht | mod | **umgesetzt**, 1 Test |
| §27 V1 | Berechtigung → Einschreibung → realer Kurszugriff, Negativkontrolle, Persistenz | enrol | **umgesetzt**, 5 Tests |
| §27 V2 | Eingangsknoten löschen → Speicherverweigerung; Kriterium entfernen → Speichern gelingt; Zugriff endet; Pfad löschen | enrol | **umgesetzt**, 1 Test mit 7 Schritten |
| §7 E2 | A ODER B (Stapel), A UND B (paralleler Knoten, geteilter Nachfolger nennt beide Vorgänger, #584) per Drag&Drop | local | **umgesetzt**, 2 Tests; Klammerungen offen |
| §6–9 | Zugangs- und Abschlussbedingungen | local | offen |
| §9 T1–T3 | festes Zeitfenster (vorher zu, innerhalb offen, danach zu) und relative Frist (ab Zuordnung ein Tag) über die Server-Testuhr | local | **umgesetzt**, 2 Tests |
| §9 T4–T5 | Zeit UND bzw. ODER abgeschlossener Vorgänger: alle vier Zeilen beider Wahrheitstafeln, mit echtem Kursabschluss; je Pfad zwei Lernende („schließt zuerst ab" / „erst im Fenster") | local | **umgesetzt**, 1 Test (4 Schritte); Prüfung der Feedbacktexte („genau die fehlende Voraussetzung") folgt mit Kette F |
| §8 C1 | Vorgänger 1 von 1, 1 von 2, 2 von 2 mit echtem Kursabschluss („Mark as done"); je Schritt Knotenstatus in der Pfadansicht der Lernenden und realer Kurszugriff | local | **umgesetzt**, 3 Tests; Variante „Vorgänger + Zeit" = T4 |
| §8 C2 | Kurszugehörigkeit | – | **gestrichen** (2026-10-06, Entscheidung des Auftraggebers): keine solche Zugangsbedingung im Produkt |
| §8 C3 | Manuelle Freigabe: nicht freigegeben → freigegeben → zurückgenommen, durch die Lehrkraft in der Pfadansicht der Lernenden | local | **umgesetzt**, 1 Test (5 Schritte) |
| §14 A | Alternative Routen (Raute T01 → T02/T03 → T04): nur T02, nur T03, beide, keine; T04-Status und realer Zugriff, nicht gewählte Alternative nicht Pflicht (Route 3/3 = 100 %), Fortschritt als Routenstand und Balkenwert | local | **umgesetzt**, 4 Tests; Feedbacktexte → Kette F |
| §15 M | Laufenden Pfad ändern: M1 Bedingung „Manual access" hinzufügen → Knoten gesperrt, Meldung nennt die Bedingung, Fortschritt unverändert, Einschreibung suspendiert, Zugriff weg; M2 entfernen → alles zurück, Einschreibung wieder aktiv | local | **umgesetzt**, 1 Test (5 Schritte) |
| §11 L1 | Linearer Pfad mit vier Schritten, echter Kursabschluss je Schritt: 0/4 bis 4/4 je mit Routenstand, exaktem Prozentwert (`aria-valuenow`), Beschriftung des Balkens und Balkenbreite; dazu Status des abgeschlossenen und des nächsten Knotens | local | **umgesetzt**, 1 Test (5 Zustände) |
| §11 L2 | Alternativknoten senken den Prozentwert nicht, 100 % auf dem gültigen Weg erreichbar | local | **abgedeckt durch Kette A** (`alternative-routes.spec.ts`) |
| §11 L3 / §12 O1 | Manueller Abschluss: regulär → von der Lehrkraft gesetzt → zurückgenommen; Knotenstatus, Folgeknoten, Routenstand/Prozent, realer Zugriff, Einschreibung (– → Active → Suspended) | local | **umgesetzt**, 1 Test (5 Schritte); Wortlaut der Meldung offen → Kette F |
| §11 L4 / §12 O3 | Master-Abschluss: an Knoten 1 (abgeschlossen, Folgeknoten frei, 1/3, Zugriff, Einschreibung Active), an allen Knoten (Gesamtabschluss 3/3, 100 %), überall entfernt (0/3, Zugriff weg, Einschreibung Suspended) | local | **umgesetzt**, 1 Test (4 Schritte); Aktivitätsabschluss von mod_adele → Kette Z4 |
| §12 O2 | Master-Zugang: Bedingung falsch/wahr × Master aus/an, Ausschalten mit Rückfall auf die Bedingung, Kombination mit Zeitbedingung; Master-Zugang erzeugt nie einen Abschluss (Knotenstatus, Fortschritt), Einschreibung Active/Suspended, realer Zugriff | local | **umgesetzt**, 2 Tests |
| §12 O4 | Beide Master-Haken in allen vier Kombinationen: Knotenstatus, Route, realer Zugriff auf T02/T03; Zeile „nur Abschluss": schließt gesperrten Knoten ab, öffnet Nachfolger — so gewollt (Entscheidung 2026-10-07: Master-Abschluss wirkt unabhängig von Voraussetzungen) | local | **umgesetzt**, 1 Test (5 Schritte) |
| §13 D | Diagnose über ein CAT-Ergebnis: Fähigkeit x−ε / x / x+ε (x = 2,5) gegen die Schwelle einer echten catquiz-Skala; je Lernende Knotenstatus, realer Zugriff auf den Folgeknoten, Routenfortschritt | local | **umgesetzt**, 3 Tests (nur mit catquiz-Ökosystem, sonst übersprungen); PHPUnit-Grenzwerttest in `uc09` für beide Prüfmodi; Feedbacktexte → Kette F |
| §11–15 | Feedback, Fortschritt, manuelle Abschlüsse, Routing | local | offen |
| §16 H2 (Rest) | dritte Quelle „beliebiger Knotenkurs" („any course of the learning path"), Kombination „dieser Kurs" + „Startknoten", Negativfall „nur späterer Knotenkurs" | mod | **umgesetzt**, 2 Tests plus 1 Schritt in H2 |
| §16 H3 | Host-Modi `hidden` (Teilnehmerliste: gesperrt, kein Zugang) und `none` (nicht eingeschrieben); `visible` über H2 | mod | **umgesetzt**, 2 Tests |
| §17 P1–P4 | Lernerdaten in der Kurseinbindung, ein Pfad zweifach eingebunden („Only own results" / „Results of all other participants"), zwei Lernende mit 1/3 und 2/3: P1 eigene Zeile, Status, Prozent — fremde Lernende weder auf der Seite noch in den Serverantworten; P2 beide Lernende mit korrekten Werten, Lehrkraft erreicht die Detailansicht; P3 Sichtbarkeit je Rolle wie implementiert (Admin, Lehrkraft mit/ohne Bearbeitungsrecht, Kollaborator, AdeLe-Manager, AdeLe-Assistent, Teilnehmer, Teilnehmer und Lehrkraft eines fremden Kurses); P4 fremde Zeile, fremde Detailansicht, direkte URL über jeden nennbaren Kontext | mod | **umgesetzt**, 4 Tests; drei Umgehungen gefunden und in `local_adele` behoben (Issue-Entwurf), PHPUnit `learner_data_embedding_test`; Nachtrag: Pfadrechte nur für den eigenen Pfad (Assistent eines anderen Pfads abgelehnt), P4-Schritt „niemand ohne Recht ändert einen fremden Lernerpfad"; local_adele #588: derselbe Pfad in einem zweiten Kurs (E2EP2, Lernende Gamma) — jede Kursliste zeigt nur die eigenen Lernenden, Pfadrecht über den Systemkontext alle |
| §18 Z1–Z4 | Gesamtabschluss, je Pfad ein Hostkurs mit Abschlussregel „Lernpfad abgeschlossen": Z1 regulär (vor dem letzten Schritt 2/3, Aktivität offen; danach 3/3 = 100 %, abgeschlossen), Z2 alternative Route (Raute, T03 nie besucht: 100 % und Abschluss), Z3 manueller Abschluss auf allen Knoten, Z4 Master-Abschluss; Z3/Z4 auch Rücknahme. Je Zustand Knotenstatus, Route, Prozent, Aktivitätsabschluss auf der Kursseite der Lernenden („To do"/„Done" mit Bedingung) und im Abschlussbericht der Lehrkraft | local | **umgesetzt**, 4 Tests; Regel in `mod_adele` dafür erst wirksam gemacht (`custom_completion`, Beobachter), PHPUnit `path_completion_test` |


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

## 5c. Drag&Drop im Editor

Echtes HTML5-Drag&Drop läuft mit Playwright, auch das Anhängen an
bestehende Knoten. Die Technik steckt in `local_adele/tests/e2e/support/editor.ts`
(`dropFirstCourse`, `dropCourseAt`, `fitCanvas`). Was gemessen wurde:

- **Rohe Maus-API** (`mouse.down/move/up`): löst kein HTML5-Ziehen aus, kein
  einziges Drag-Ereignis kommt an.
- **`locator.dragTo()`**: wirkt nur auf Ziele, die vor dem Ziehen
  existieren — reicht für den ersten Knoten (Startmarker), nicht für die
  Zonen eines bestehenden Knotens.
- **Was trägt:** Ziehen mit `locator.hover()` + `mouse.down()` beginnen und
  mit `pane.hover({ position, force: true })` führen. Dann kommen echte
  Drag-Ereignisse mit richtigen Koordinaten an, die Zonen erscheinen, und
  die Trefferprüfung des Editors arbeitet korrekt.
- **Drei Stolpersteine**, alle in der Hilfsfunktion behandelt: Die Ansicht
  wird nach dem Ablegen nicht neu eingepasst (daher vor jedem Ziehen
  „Ansicht einpassen" plus Herauszoomen); die Zonen bleiben am zuerst
  passierten Knoten hängen (daher Sprung direkt auf das Ziel und Prüfung,
  dass die Zone auf der richtigen Seite **dieses** Knotens liegt); und die
  Seite darf nicht scrollen (daher Fenster 1920 × 1800).
- `locator.boundingBox()` **wartet**, bis ein Element existiert — für eine
  noch nicht gezeichnete Zone bis zum Testende. Die Hilfsfunktion fragt
  deshalb vorher `count()` ab.

Zu #580: Die Testbarkeit ist damit **kein** Grund mehr; Kommentarentwurf
zur Korrektur: `issues/local_adele-issue-580-comment.md`.

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

**#581 umgesetzt (`local_adele` 2026100502):** Die Zeitbedingung `timed`
speichert und vergleicht Unix-Zeitstempel; Altbestand wird beim Upgrade in
der Site-Zeitzone umgerechnet. Das Fenster ist **halboffen**
(`Beginn ≤ jetzt < Ende`) und damit sekundengenau prüfbar
(`tests/timed_timestamps_test.php`). Die Kette T kann diese Semantik jetzt
festschreiben.

Seit `2026100503` folgt auch die relative Bedingung `timed_duration`
derselben Regel (`Beginn ≤ jetzt < Ende`, Entscheidung des Auftraggebers)
und liefert ebenfalls Zeitstempel. Beide Zeitbedingungen haben damit eine
gemeinsame Semantik.

## 5e. Die Server-Testuhr in der Praxis

- **Umsetzung:** `libfaketime` wird nur in die PHP-Prozesse geladen
  (Webserver, CLI-Task-Runner), gesteuert über eine Datei
  (`ADELE_FAKETIME_LIB`, `ADELE_FAKETIME_FILE`). Die Tests verschieben die Zeit
  mit `setServerTime()`, `drainTaskQueue()` läuft unter derselben Uhr, und ein
  `afterEach` stellt sie mit `resetServerTime()` zurück. Ohne Testuhr
  überspringt sich die Kette ausdrücklich.
- **Genauigkeit:** Die Bewertung ist sekundengenau (#581, PHPUnit). Durch das
  ganze System greift ein Übergang aber erst, wenn local_adele seine
  Neuauswertung ausführt — eingeplant auf Grenze **plus 120 s**. Die
  Browserkette prüft deshalb eine Minute vor und gut zwei Minuten nach jeder
  Grenze.
- **Zeitsprünge wirken systemweit.** Wird die Uhr vorgestellt, laufen beim
  nächsten Abarbeiten **alle** bis dahin fälligen Aufträge, auch die anderer
  Lernender und Pfade. Ein Sprung ins Jahr 2030 hatte den Schließauftrag der
  relativen Frist ausgeführt; nach dem Zurückstellen gab es keinen Auftrag,
  der den Knoten wieder öffnete. Daher: innerhalb der Kette der kürzere
  Sprung zuerst, und die Datei heißt `zz-time-conditions.spec.ts`, damit sie
  in jeder Suite **zuletzt** läuft.
- **Bedingungsstruktur in Fixtures:** Eine Bedingung wird nur ausgewertet,
  wenn sie eine Kette beginnt (`parentCondition: ['starting_condition']`)
  und auf ihren Feedbackknoten zeigt. Der Seed baut Einzelbedingungen deshalb
  mit `local_adele_fixture_single_condition()` aus den Knoten der Fixtures.

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

## Nachtrag `2026100603`: Rundgänge und Teilnehmerquellen

- **Rundgänge abgeschaltet:** Die Seeds setzen alle Moodle-Rundgänge
  (`tool_usertours`) auf inaktiv. Ein offener Rundgang legt eine Abdeckung
  über die Seite; ob der Klick des Tests oder der Rundgang zuerst kommt, ist
  ein Wettlauf. Er gehört zu Moodle, nicht zu ADELE.
- **#37:** Teilnehmerquellen zählen nur Einschreibungen, die ADELE nicht
  selbst angelegt hat. Die Negativprüfung von Quelle 2 nutzt deshalb auch die
  Person, die nur in einem späteren Knotenkurs steht.

## Nachtrag `2026100604`: Kette C, echter Kursabschluss

- **Echter Abschluss:** Jeder Fixture-Kurs schließt über ein Forum mit
  manuellem Abschluss ab. `completeCourse()` klickt „Mark … as done"; Moodle
  benennt die Schaltfläche nach der Aktivität („Mark Ankündigungen as done").
  Der Klick-Handler kommt mit Moodles JavaScript etwas später als die
  Schaltfläche — ein früher Klick verpufft. Die Hilfe wartet auf Ruhe und
  klickt erneut nur, solange der Zustand nachweislich nicht umgesprungen ist.
- `runCompletion()` führt Moodles `completion_regular_task` aus und arbeitet
  danach die Warteschlange ab, beides unter der Testuhr.
- **Knotenstatus aus Sicht der Lernenden:** `nodeStatus()` öffnet die
  Aktivität und liest `data-status`. Jede C-Variante hat dafür einen eigenen
  Hostkurs mit nur ihrer Lernenden, sonst würden sich die Varianten über
  Quelle 1 gegenseitig abonnieren.
- **Abschlüsse sind dauerhaft:** Der Seed löscht vor jedem Lauf die
  Aktivitäts-, Kriterien- und Kursabschlüsse dieser Lernenden in den Kursen
  ihres Pfads.

## Nachtrag `2026100605`: T4/T5 und getrennte Zeitfenster

- **Struktur von UND und ODER** in den Fixtures wie im Bedingungseditor
  (`RestrictionFlow.vue`): Bei UND hängt die zweite Bedingung unter der
  ersten (`source_and` → `target_and`), eine Kette, ein Feedbackknoten. Bei
  ODER beginnt sie eine eigene Kette (`parentCondition: starting_condition`)
  mit eigenem Feedbackknoten, verbunden über `source_or` → `target_or`.
  Seed-Funktion `local_adele_fixture_two_conditions()`.
- **Jede Zeitkette hat ihr eigenes Fenster.** T1/T2 liegen im Fenster
  2030-01-15, T4/T5 im Fenster 2031-01-15. Teilen sich zwei Ketten ein
  Fenster, verbraucht der Zeitsprung der einen die Neuauswertungsaufträge
  der anderen (gemessen: T4/T5 vor T1/T2 öffnete den T1/T2-Kurs vorzeitig).
  Reihenfolge in der Datei: T3 (ein Tag), T1/T2 (2030), T4/T5 (2031).

## Nachtrag `2026100606`: Kette A und Fortschrittsbalken

- **Fortschritt** misst `learning_paths::getnodeprogress()` an der besten
  Route, nicht an allen Knoten. In der Raute (zwei Routen à drei Knoten)
  heißt das 1/3, 2/3, 3/3 = 33,33 / 66,67 / 100 %, und die nicht gewählte
  Alternative zählt nicht mit.
- **Produktfehler behoben:** Der Fortschrittsbalken schrieb
  `aria-valuenow="{{ props.progress }}"` — in Vue-Attributen wird so nichts
  eingesetzt, Screenreader bekamen den wörtlichen Text. Jetzt gebunden
  (`:aria-valuenow`), Jest-Test mit Gegenprobe. Die Kette A liest den Wert
  über genau dieses Attribut.

## Nachtrag `2026100608`: Kette D und catquiz-Signale

- **Signale statt durchgespielter Tests:** `tests/e2e/support/catquiz_signal.php`
  erzeugt, was ein echter adaptiver Test hinterlässt: einen
  mod_adaptivequiz-Versuch mit Fragen-Nutzung, die catquiz-Ergebniszeile mit
  `personabilities` und das Ereignis `\mod_adaptivequiz\event\attempt_completed`
  als die Lernende — darauf reagiert local_adele. Die Testaktivität in T01 legt
  der Seed über den Generator von mod_adaptivequiz an, die Skala als echten
  catquiz-Datensatz.
- **Nur mit catquiz:** Der Seed legt Kette D nur an, wo das Ökosystem
  installiert ist; die Kette überspringt sich sonst ausdrücklich.

## Nachtrag `2026100613`: Speichern, Editor-Laden, Seed-Identität

- **`saveLearningPath(page, button)`** (support/fixtures): klickt „Speichern"
  und wartet auf die Antwort von `local_adele_save_learningpath`, verlangt
  `error: false`. „Übersicht wieder sichtbar" beweist nichts: Der Editor fordert
  die Liste an, bevor das Speichern geantwortet hat.
- **`waitForEditorLoaded(page)`**: vor jedem Eintippen in den Editor. Der
  Editor lädt einen Pfad beim Öffnen zweimal; eine späte zweite Antwort
  überschreibt den eingetippten Titel (Produktfehler, sichtbar gehalten durch
  `editor-title-overwrite.spec.ts` als `test.fixme`, Issue-Entwurf
  `issues/local_adele-issue-editor-title-overwritten.md`).
  **Stand `2026100705` (local_adele #585):** behoben — der Pfad wird einmal
  geladen, das Formular erscheint erst danach. `editor-title-overwrite.spec.ts`
  läuft als zwei reguläre Tests; die Suite hat kein `fixme` mehr.
  `waitForEditorLoaded()` bleibt als Vorsicht in den Ketten.
- **Seed-Identität der Fixture-Pfade:** gemerkte IDs in
  `$CFG->dataroot/local_adele_e2e_fixturemap.json`, dann exakter Name, dann
  „Name mit Suffix" — ein abgebrochener Umbenennungslauf vergiftet die
  folgenden Läufe nicht mehr.
