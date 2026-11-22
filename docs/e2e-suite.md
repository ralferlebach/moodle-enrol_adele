# E2E-Suite des ADELE-Ökosystems

**Stand:** Session 006
**Grundlage:** [`testplan/AdeLe_E2E_Testplan_UserStories.md`](testplan/AdeLe_E2E_Testplan_UserStories.md)

Zwei Playwright-Suiten je Plugin, bewusst getrennt:

| Suite | Ort | Auslöser | Zweck |
|---|---|---|---|
| Smoke | `tests/playwright/`, `playwright.yml` | wie bisher | schnelle Rauchprobe |
| **E2E** | `tests/e2e/`, **`e2e.yml`** | **nur manuell** | ganze Ketten über alle drei Plugins |
| **Screenreader** | `local_adele/tests/a11y-screenreader/`, **`a11y-screenreader.yml`** | **nur manuell**, Linux-Instanz + Windows/NVDA über Tunnel | was NVDA tatsächlich vorliest |

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
playwright-report/   HTML-Report, jeder Test mit Screenshot (Videos: s. u.)
test-results/        Videos (video.webm) je Test (s. u.), Traces nur bei Fehlschlag
results.json         vollständiger Ergebnisbaum
junit.xml            dasselbe im JUnit-Format
run.log              Konsolenausgabe
environment.md       Moodle, PHP, exakter Commit jedes Plugins
summary.md           eine Zeile je Test mit Videopfad (auch als Job-Summary)
coverage.md/.json    Story-Abdeckung: Story -> Test -> Spec -> Status -> Video
moodle-server.log.gz Anfrageprotokoll
```

Videos entstehen für **jeden** Test, auch für bestandene (`video: 'on'`).
**Ausgeliefert** werden sie seit `2026112200` (Vorgabe 2026-11-22) so: bei
grünem (und bei abgebrochenem) Lauf alle; bei rotem Lauf **nur die der
fehlgeschlagenen Tests**. Der Schritt „Keep the videos of the failed tests
only“ (`if: failure()`) liest `results.json` und löscht jedes andere Video,
auch die Kopie im HTML-Report (`data/<SHA-1>.webm`, Abgleich über den
Inhalt) – dort zeigen bestandene Tests dann kein Video. Bei Wiederholungen
(nur Smoke, `retries: 1`) bleiben alle fehlgeschlagenen Versuche. Ist der Lauf
rot, ohne dass ein Test fehlschlug (z. B. Story-Abdeckung), bleibt kein Video;
fehlt `results.json`, bleiben alle. Liste der behaltenen Videos in
`summary.md` und der Job-Summary. Skript:
`tests/e2e/support/keep-failed-videos.mjs` (je Plugin); dieselbe Regel gilt im
Smoke-Workflow (`playwright.yml`, dazu JSON-Reporter) und im NVDA-Workflow.
Anmeldungen laufen im Hintergrund per HTTP und sind nicht Teil der Aufnahme.

## 3. Grundsätze der Suite

- **Keine Wiederholungen** (`retries: 0`). Eine Kette, die erst im zweiten
  Anlauf grün wird, lief auf dem Zustand des ersten.
- **Ein Worker.** Alle Ketten verändern dieselbe Instanz.
- **Realer Zugriff statt Datenbankeintrag.** `expectCourseOpen` /
  `expectCourseClosed` rufen die Kursseite auf; eine Zeile in der
  Teilnehmerliste gilt nicht als Beweis (Plan §2).
- **Und Browser und Server müssen übereinstimmen** (seit `2026100722`).
  `courseAccess()` gibt „offen" nur zurück, wenn die Seite **dieses** Kurses
  gerendert ist (URL, `body#page-course-view-*`, Klasse `course-<id>`,
  `M.cfg.courseId`), jemand angemeldet ist, der kein Administrator ist, und
  der Server ihn als aktiv eingeschrieben zählt. `expectCourseOpen*` verlangt
  zusätzlich eine aktive **ADELE**-Einschreibung (Nutzer- und
  Instanzstatus); eine manuelle oder andere Einschreibung zählt nicht.
  Verweigerung ist nur die Einschreibeseite dieses Kurses (Server: nicht
  eingeschrieben) oder eine bekannte Moodle-Meldung (`REFUSALS`).
  Login-Verlust, Serverfehler, fremder Kurs, unbekannte Fehlerseite und
  Widersprüche zwischen Browser und Server sind **Testfehler**.
- **Ein Artefakt je Lauf, immer.** Auch die Smoke-Workflows laden seit
  `2026100202` Report und Videos bei grünem **und** rotem Lauf hoch; vorher
  gab es bei Fehlschlag nur die fehlgeschlagenen Verzeichnisse ohne Video —
  also genau dann keine Aufnahme, wenn sie gebraucht wird. Seit `2026112200`
  enthält ein roter Lauf nur noch die Videos der fehlgeschlagenen Tests (§ 2).
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
- **Der Owner ist geschützt** (Regel B, seit `2026100721`). Seine Karte
  in der Bearbeiterliste hat keine Entfernen-Schaltfläche, und der Dienst
  lehnt das Entfernen mit `editorremoveowner` ab. Der Seed setzt die
  Bearbeiter der Fixture-Pfade bei jedem Lauf neu, den Owner eingeschlossen.
- **Der Seed nimmt keine Wirkung vorweg** (seit `2026100722`). Er entfernt
  vor dem erneuten Zuweisen eines Fixture-Lernenden dessen ADELE-
  Einschreibungen aus früheren Läufen; sonst sähe eine Kette Zugang, den
  dieser Lauf nie erteilt hat.
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
| §6 R3 | Lehrkraft-Rolle im Kurs → Editorzugriff als Assistent, eigener Pfad anlegbar; aus dem Kurs genommen → Zugriff **bleibt** (Entscheidung local_adele #503/#509: kein automatischer Entzug); Administrator entzieht die Systemrolle von Hand → kein Editor, Server legt keinen Pfad an | local | **umgesetzt**, 1 Test (6 Schritte) + 1 bekannter Fehler (Form der Ablehnung) |
| §6 R4a | Kollaborator bearbeitet genau den freigegebenen Pfad, beim anderen nur Ansehen | local | **umgesetzt**, 2 Tests |
| §6 R4b | Recht über die Suche im Editor vergeben und entziehen; letzter Bearbeiter geschützt | local | **umgesetzt**, 1 Test mit 6 Schritten |
| §7 E1 | Referenzpfad T01 → T02 → T03 ausschließlich per Drag&Drop, nach erneutem Öffnen Knoten **und Kanten** unverändert; Einzelknoten; Lernende ohne Editor | local | **umgesetzt**, 3 Tests |
| §6 R5 | parallele Berechtigungsgründe, alle 6 Zeilen der Matrix: Manager + Kollaboration; Assistent + Kollaboration (je Kollaboration bzw. Assistentenrolle entzogen); nur Kollaboration; nur Assistent (am Ende von R3); Eigentümer + Kollaboration | local | **umgesetzt**, 3 Tests + R3; die Zeile „Eigentümer + Kollaboration" ist ein bekannter Fehler (eigener Test, Issue-Entwurf) |
| §6 R6 | Eigentümerschaft über die Kronen-Schaltfläche übertragen, Bestand nach Neuladen, alter Eigentümer als Manager behält Zugriff | local | **umgesetzt**, 1 Test mit 5 Schritten |
| §16 H1 | Aktivität über das Formular anlegen → Hostkurs-Mitglieder im Eingangskurs, Außenstehende nicht; Pfad in der Aktivität sichtbar | mod | **umgesetzt**, 1 Test |
| §16/§27 H1b | Austritt aus dem Hostkurs → Zugriff entzogen, und nur für die abgemeldete Person | mod | **umgesetzt**, 1 Test |
| §16 H2 | zweite Teilnehmerquelle „Einschreibung im Startknoten-Kurs": Person aus dem Eingangskurs wird in den Hostkurs getragen, Außenstehende nicht | mod | **umgesetzt**, 1 Test |
| §27 V1 | Berechtigung → Einschreibung → realer Kurszugriff, Negativkontrolle, Persistenz | enrol | **umgesetzt**, 5 Tests |
| §27 V2 | Eingangsknoten löschen → Speicherverweigerung; Kriterium entfernen → Speichern gelingt; Zugriff endet; Pfad löschen | enrol | **umgesetzt**, 1 Test mit 7 Schritten |
| §7 E2 | A ODER B (Stapel), A UND B (paralleler Knoten, #584) per Drag&Drop; Klammerungen `(A UND B) ODER C` und `A UND (B ODER C)` je auf Bedingungs- und Knotenebene; Bedingung hinzufügen/entfernen; Änderungen verwerfen | local | **umgesetzt**, 2 + 5 Tests (`editor-combinations`, `editor-brackets`); 3 bekannte Fehler (Issue-Entwürfe) |
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
| §10 F1–F3 | Was Lernende am Knoten **lesen**, Zustand für Zustand: i-Symbol (Informationskarte), Sprechblase (Feedback) und deren Farbe. Vorgänger 1 von 1 und 2 von 2 (vorher, teilweise, erfüllt), manuelle Freigabe und manueller Abschluss (vorher, gesetzt, zurückgenommen), eigener Text der Autorin, Lernende mit Sprache Deutsch; mit Server-Testuhr: festes Zeitfenster vor/im/nach, offen an einem Ende, dieselben Zeiten für eine Lernende in UTC+9; F3 Zeitfenster UND/ODER Vorgänger (beide fehlen, nur A, nur B, erfüllt, Fenster vorbei). Keine Rohplatzhalter in irgendeinem gelesenen Text | local | **umgesetzt**, 10 Tests (`feedback-texts.spec.ts` 8, `zz-time-feedback.spec.ts` 2); kein bekannter Fehler mehr offen (siehe Nachträge `2026100709` bis `2026100711`); verschachtelte Kombinationen offen (brauchen die E2-Klammern) |
| §16 H2 (Rest) | dritte Quelle „beliebiger Knotenkurs" („any course of the learning path"), Kombination „dieser Kurs" + „Startknoten", Negativfall „nur späterer Knotenkurs" | mod | **umgesetzt**, 2 Tests plus 1 Schritt in H2 |
| §16 H3 | Host-Modi `hidden` (Teilnehmerliste: gesperrt, kein Zugang) und `none` (nicht eingeschrieben); `visible` über H2 | mod | **umgesetzt**, 2 Tests |
| §17 P1–P4 | Lernerdaten in der Kurseinbindung, ein Pfad zweifach eingebunden („Only own results" / „Results of all other participants"), zwei Lernende mit 1/3 und 2/3: P1 eigene Zeile, Status, Prozent — fremde Lernende weder auf der Seite noch in den Serverantworten; P2 beide Lernende mit korrekten Werten, Lehrkraft erreicht die Detailansicht; P3 Sichtbarkeit je Rolle wie implementiert (Admin, Lehrkraft mit/ohne Bearbeitungsrecht, Kollaborator, AdeLe-Manager, AdeLe-Assistent, Teilnehmer, Teilnehmer und Lehrkraft eines fremden Kurses); P4 fremde Zeile, fremde Detailansicht, direkte URL über jeden nennbaren Kontext | mod | **umgesetzt**, 4 Tests; drei Umgehungen gefunden und in `local_adele` behoben (Issue-Entwurf), PHPUnit `learner_data_embedding_test`; Nachtrag: Pfadrechte nur für den eigenen Pfad (Assistent eines anderen Pfads abgelehnt), P4-Schritt „niemand ohne Recht ändert einen fremden Lernerpfad"; local_adele #588: derselbe Pfad in einem zweiten Kurs (E2EP2, Lernende Gamma) — jede Kursliste zeigt nur die eigenen Lernenden, Pfadrecht über den Systemkontext alle; mod_adele #39: Lehrkraft ohne Bearbeitungsrecht bekommt die Lehrkraftansicht nur lesend (eigener Test: gleiche Knotenstatus, keine Master-Haken, kein „Save"; Speichern über den Dienst abgelehnt) — Kette P jetzt 5 Tests |
| local_adele #587 | Wer teilnimmt, entscheidet die Rolle: Lehrkräfte des Kurses stehen nicht in der Teilnehmerliste und kommen nicht in den Startkurs; Rolle „Student" auf Moodles Teilnehmerseite ergänzt → in der Liste, Startkurs offen; Rolle entfernt → sofort aus der Liste, Startkurs geschlossen | mod | **umgesetzt**, 1 Test (3 Schritte), `participants-by-role.spec.ts`; Schonfrist und Löschung des Lernstands in PHPUnit (`enrol_adele`, `participant_role_test`) |
| §18 Z1–Z4 | Gesamtabschluss, je Pfad ein Hostkurs mit Abschlussregel „Lernpfad abgeschlossen": Z1 regulär (vor dem letzten Schritt 2/3, Aktivität offen; danach 3/3 = 100 %, abgeschlossen), Z2 alternative Route (Raute, T03 nie besucht: 100 % und Abschluss), Z3 manueller Abschluss auf allen Knoten, Z4 Master-Abschluss; Z3/Z4 auch Rücknahme. Je Zustand Knotenstatus, Route, Prozent, Aktivitätsabschluss auf der Kursseite der Lernenden („To do"/„Done" mit Bedingung) und im Abschlussbericht der Lehrkraft | local | **umgesetzt**, 4 Tests; Regel in `mod_adele` dafür erst wirksam gemacht (`custom_completion`, Beobachter), PHPUnit `path_completion_test` |


## 5a. R3 und R5: Entzug der Assistentenrolle

Entschieden seit 2026-08-18 (local_adele #503 geschlossen, Kommentar zu #509:
„Kein Entzug realisieren"): Das Plugin vergibt die Systemrolle
`adeleassistant` automatisch und nimmt sie **nie** selbst zurück. Der Plan
(§6 R3, letzte Zeile) liest sich anders; die Ketten halten deshalb beides
fest:

- Die Rolle überlebt das Entfernen der Kursrolle (R3, Schritt 5).
- Der Weg, sie zu beenden, ist Moodles eigene Seite „Systemrollen zuweisen"
  (R3, Schritt 6; R5 „Assistent + Kollaborator, Assistent entzogen").
  Testhilfe: `setSystemRole()` in der gemeinsamen `support/fixtures.ts`.

Der frühere Entwurf `issues/local_adele-issue-assistant-role-no-revocation.md`
ist damit gegenstandslos (Vermerk im Entwurf).

**Zwei bekannte Fehler aus diesen Ketten** (Tests laufen nur mit
`ADELE_RUN_KNOWN_DEFECTS=1`):

- Eigentümer von der Bearbeiterliste genommen → Pfad weg
  (`issues/local_adele-issue-owner-loses-path-with-editor-row.md`).
- Anlegen ohne Recht wird mit einem PHP-Fehler abgelehnt
  (`issues/local_adele-issue-save-learningpath-refusal.md`) — **behoben mit
  `2026100716`, local_adele #594.**

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

**Der Workflow baut seine Instanz selbst** (seit `2026100726`, nach dem
Vorbild von local_stackmatheditor `a11y-nvda.yml`). Keine Eingabe einer
Adresse, keine Secrets. Zwei Jobs:

| Job | Runner | Aufgabe |
|---|---|---|
| `site` | ubuntu-24.04 | baut, seedet (`seed.php` + `seed_fixtures.php`) und betreibt dieselbe Instanz wie `e2e.yml`, öffnet einen temporären Cloudflare-Tunnel, setzt `wwwroot` darauf (`sslproxy`), übergibt Adresse und Seed-Werte als Artefakt `nvda-site` und bleibt oben, bis `nvda-done` erscheint |
| `nvda` | windows-2025 | Guidepup-Setup 0.24.1, `npm ci`, NVDA passend zur installierten Bibliothek, wartet auf `nvda-site`, prüft die Erreichbarkeit über den Tunnel, führt die Suite aus, lädt das Ergebnis hoch und meldet `nvda-done` |

Eingaben: nur der Titelfilter (`grep`), die Geschwister-Referenz
(`sibling_ref`) und die Aufbewahrungsdauer. **Feste Versionen:**
`@guidepup/guidepup` 0.33.0 mit `@guidepup/playwright` 0.19.1 und
`@guidepup/setup` 0.24.1 – die Kombination, die Guidepups eigenes
Playwright-Projekt fährt. Mit einer neueren Bibliothek (das Lockfile hatte
0.35.0) installiert das Setup ein NVDA, das die Integration nicht findet.

Im Artefakt liegt neben Report und Video (roter Lauf: nur die der
fehlgeschlagenen Tests, § 2) das **Sprachprotokoll** je Test
(`nvda-spoken-phrases.txt`, als Anhang im HTML-Report, und als
`transcripts/*.txt` mit Commit, Datum und Adresse im Kopf). Es ist das, was eine
Durchsicht zuerst lesen sollte; das Video zeigt denselben Lauf von außen.

Umgesetzt ist bisher `ADELE-SR-01`: Die Übersicht wird vorgelesen, und die
Bedienelemente werden mit ihrem Namen angesagt — der Sichtbarkeitsschalter
zusätzlich mit seinem Zustand (gedrückt/nicht gedrückt). Das ist die
Gegenprobe zu #575 B2.

**Noch nie gelaufen.** Diese Suite ist in der Linux-Umgebung der Entwicklung
nicht ausführbar; geprüft sind Workflow (actionlint), Lockfile-Auflösung und
die Übergabedatei gegen eine echte Seed-Ausgabe (genau eine
`ADELE_BASE_URL`, Konto aus dem Seed). Tunnel und Windows-Job zeigt erst
der erste Lauf auf GitHub.

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

## Nachtrag `2026100709`: Kette F, bekannte Fehler, Zeitzone

- **Lesen statt wirken:** Kette F prüft keine Wirkung (das tun C, T, L, O),
  sondern die Texte. Hilfe `nodeTexts()` (gemeinsame `support/fixtures.ts`)
  liefert Status, Informationskarte, Feedback und Farbe der Sprechblase eines
  Knotens; `support/feedback.ts` (nur `local_adele`) die Satzbausteine und die
  Schritte der Kette.
- **Eigene Fixtures:** je Zustandskette eine Lernende mit eigenem Hostkurs
  (`E2EF_<KEY>`, Aktivität „E2E F <key>"). Die Zeit-Fixtures haben eigene
  Pfade und eigene Fenster (2032, 2033), später als alle Fenster der Kette T;
  `zz-time-feedback` läuft nach `zz-time-conditions`. Alle Lernenden der Kette
  haben eine feste Zeitzone (UTC, eine Asia/Tokyo), damit die gelesenen Daten
  nicht von der Zeitzone der Site abhängen. Das deutsche Sprachpaket
  installiert der Seed über Moodles Importer; ohne Netz überspringen sich die
  deutschen Tests ausdrücklich.
- **Bekannte Fehler** (`knownDefect` in `support/feedback.ts`): Tests, die
  beschreiben, was Lernende lesen **sollten**, und heute scheitern. Im
  Normallauf übersprungen (`test.fixme`), mit `ADELE_RUN_KNOWN_DEFECTS=1`
  ausgeführt — dann rot, bis der Fehler behoben ist. Derzeit sechs:
  Mischsprache nach eigener Aktion; Text der Autorin verschwindet bei anderer
  Sprache; „manual completion recorded" ohne Abschluss; „From subscription you
  have access until …" vor einem festen Fenster; „Missing date" bei offenem
  Fenster; i-Symbol und Sprechblase nicht per Tastatur. Issue-Entwürfe:
  `issues/local_adele-issue-feedback-language-and-author-text.md`,
  `-feedback-wording-time-and-manual.md`, `-node-info-feedback-keyboard.md`;
  als Frage `-feedback-partial-predecessors.md`.
- **Die zeitbezogenen bekannten Fehler stehen am Anfang ihrer Datei:** nach
  der Kette darunter stehen alle Lernenden hinter ihrem Fenster, und die Tests
  wären grün, ohne etwas gesehen zu haben (so im ersten Lauf geschehen).
- **Zeitzone (local_adele #581, Nachtrag):** Daten in den Texten erschienen
  in der Zeitzone der Site statt in der der Lernenden, das Fensterende in der
  Zeitzone dessen, der die Neuberechnung auslöste. Behoben; PHPUnit
  `timed_timestamps_test::test_dates_are_shown_in_the_learners_time_zone`,
  E2E über die Lernende in UTC+9.

## Nachtrag `2026100710`: local_adele #589 und #590

- Fünf der sechs bekannten Fehler sind behoben und stehen als reguläre
  Zusicherungen in `feedback-texts.spec.ts` (7 Tests) und
  `zz-time-feedback.spec.ts` (2 Tests): Deutsch bleibt Deutsch nach der eigenen
  Aktion; der Text der Autorin erreicht Lernende anderer Sprache; i-Symbol des
  manuellen Abschlusses; kein „From subscription …" am gesperrten festen
  Fenster; offene Fenster als Wörter. Die Texte der Zeit-Kette sind jetzt
  vollständig festgeschrieben (vorher teils nur „enthält").
- Bekannter Fehler bleibt einer: i-Symbol und Sprechblase per Tastatur
  (local_adele #591).

## Nachtrag `2026100711`: local_adele #591

- Der letzte bekannte Fehler ist behoben: `feedback-texts.spec.ts` hat einen
  achten Test, der i-Symbol und Sprechblase über Rolle und Namen findet und
  nur mit der Tastatur bedient. Die Suite führt keinen bekannten Fehler mehr;
  der Schalter `ADELE_RUN_KNOWN_DEFECTS` und `knownDefect` bleiben für
  künftige Befunde.

## Nachtrag `2026100712`: Nachträge zu local_adele #586 und #588

- **Kette P:** AdeLe-Manager und Kollaborator des Pfads erhalten vom Server
  keine Lernerdaten mehr (P3-Tabelle, P4-Schreibversuche auch über den
  Systemkontext); der Administrator weiterhin, außerhalb eines Kurses den
  ganzen Pfad.
- **Neuer Test** in `participants-by-role.spec.ts`: Einschreibung auf Moodles
  Teilnehmerseite suspendieren → von der Lernpfad-Liste des Kurses
  verschwunden, Pfad bleibt (Startkurs offen); reaktivieren → wieder da.
  Hilfe `setEnrolmentStatus()` in der gemeinsamen `support/fixtures.ts`.
- **H3 „hidden"** prüft zusätzlich: Die von ADELE suspendiert geführte Person
  steht auf der Lernpfad-Liste des Kurses.
- `mod_adele` hat damit 14 Tests.

## Nachtrag `2026100713`: local_adele #593

- Kein neuer E2E-Test: Die Regel „Bewertete Rollen" ist eine globale
  Einstellung der Instanz; sie im Lauf umzustellen, würde alle anderen Ketten
  berühren. Abgesichert in PHPUnit (`issue587_participants_test`,
  `graded_roles_test`) und durch eine einmalige Probe mit der echten
  Einstellungsklasse (Protokoll Teil 45).
- Die bestehenden Tests in `participants-by-role.spec.ts` laufen unverändert
  und bestätigen die Regel für die Standardrollen.

## Nachtrag `2026100714`: local_adele #580

- **Zwei neue Tests** in `editor-drag-and-drop.spec.ts` (Kette E1): Die
  Ablagezonen folgen dem Zeiger von Knoten zu Knoten; eine Zone, die einem
  Nachbarknoten näher liegt als dem eigenen, bleibt unter dem Zeiger.
- **Testhilfe `support/editor.ts`:** Das Ziehen ist in Schritte zerlegt
  (`startDrag`, `dragOverNode`, `dragOntoZone`, `abandonDrag`);
  `zonesAround()` stellt fest, zu welchem Knoten die gezeichneten Zonen
  gehören — über die feste Lage der Zone „parallel" relativ zu ihrem Knoten,
  damit auch Knoten derselben Reihe unterschieden werden. `dropCourseAt()`
  prüft das jetzt bei jedem Ablegen und kann mit `via` an einem anderen
  Knoten vorbeiziehen. Der Umweg über den Startmarker ist entfallen.
- `local_adele` hat damit 55 E2E-Tests.
- **Befund ohne Test:** Versetzt gezeichnete Zonen (zweiter Nachfolger,
  zweiter Vorgänger) sind nicht zu treffen — vor wie nach #580. Entwurf:
  `issues/local_adele-issue-offset-dropzones-unreachable.md`. Ein Test folgt
  mit der Entscheidung.

## Nachtrag `2026100715`: Ketten R3 und R5 vollständig

- **R3** um zwei Schritte verlängert (aus dem Kurs genommen: Rolle bleibt;
  Systemrolle von Hand entzogen: Zugriff weg, auch am Server).
- **R5**: neuer Test „without the assistant role the collaboration still
  opens its path - and without that, nothing is left" (zwei Zeilen der
  Matrix an einer Person ohne jede Rolle; stellt Rolle und Bearbeiterliste
  am Ende wieder her). Die Zeile „nur Kollaboration" war bisher an einer
  Person geprüft, die zugleich Assistent war.
- **Zwei bekannte Fehler**, je ein eigener Test (siehe §5a). Die Suite hat
  damit wieder übersprungene Tests: `local_adele` 56 bestanden, 2
  übersprungen.
- Neue Testhilfen in `support/fixtures.ts` (in allen drei Plugins gleich):
  `setSystemRole()`, `unenrolFromCourse()`.

## Nachtrag `2026100716`: local_adele #594

- Der bekannte Fehler „Ablehnung als PHP-Fehler" ist behoben; sein Test
  läuft regulär. `local_adele`: 57 bestanden, 1 übersprungen (Eigentümer von
  der Bearbeiterliste genommen — wartet auf Entscheidung und Ablage).

## Nachtrag `2026100717`: local_adele #595

- **Neue Datei `editor-drop-zones.spec.ts`** (4 Tests): welche Zonen ein
  Knoten anbietet, dass jede angebotene Zone mit geführtem Zeiger erreichbar
  ist, dass eine erreichte Zone auch über dem Startmarker bleibt, und der Weg
  zur Verzweigung über die seitliche Zone.
- **Testhilfe:** `offeredZones()`, `leadOntoZone()` (führt den Zeiger in
  Schritten vom Knoten zur Zone — ein Sprung sagt über Erreichbarkeit wenig).
- `local_adele`: 61 bestanden, 1 übersprungen.

## Nachtrag `2026100719`: E2-Klammerungen

- **Neue Datei `editor-brackets.spec.ts`** (5 Tests + 3 bekannte Fehler).
  Bedingungsebene (Zugangsbedingungen von T02): `(A UND B) ODER C` direkt,
  `A UND (B ODER C)` ausmultipliziert, Hinzufügen/Entfernen, Verwerfen ohne
  Speichern. Knotenebene: `A UND (B ODER C)` = T01 parallel zu einem Stapel
  T02|T03 vor T04, „2 von 2"; `(A UND B) ODER C` = T01 → T02 → T04 und T03
  per Tastatur vor T04, „1 von 2". Geprüft am **gespeicherten** Stand.
- **Neue Testhilfe `support/conditions.ts`** (nur `local_adele`): Bedingungs-
  Editor öffnen, Bedingung auf Zone ziehen, Knoten wählen, Anzahl
  Vorgänger, löschen, speichern (meldet eine Ablehnung mit ihrem Text),
  verwerfen; gespeicherte Spalten/Ketten und Knoten lesen; Tastatureinfügen.
- **Drei bekannte Fehler** (Entwürfe in `issues/`): Vorgänger „davor" fehlt
  in der Bedingung; veraltete Vorgängerzahl vor dem Speichern; Knoten „davor"
  verdeckt den Zielknoten.
- Was Lernende aus diesen Kombinationen bekommen, prüft F3 (folgt).
- `local_adele`: 66 bestanden, 4 übersprungen.


## Nachtrag `2026100721`: die vier bekannten Fehler behoben

- Die drei `knownDefect`-Tests in `editor-brackets.spec.ts` und der in
  `roles-parallel-permissions.spec.ts` laufen als reguläre Tests; kein Test
  nutzt mehr `knownDefect`.
- `openAccessCriteria()` klickt echt; `dispatchEvent('click')` ist aus den
  E2E-Hilfen verschwunden. Ein verdeckter Knoten macht den Test rot.
- `local_adele`: 70 bestanden, keiner übersprungen.

## Nachtrag `2026100722`: strenger Kurszugriff und Story-Abdeckung

- **`courseAccess()` streng**, `enrolmentState()` liest die Einschreibungen
  serverseitig, `accessThroughAdele()` verlangt die ADELE-Einschreibung
  (siehe Abschnitt 3). Alle drei Kopien von `support/fixtures.ts` sind
  bytegleich.
- **Story-Tags.** Jeder E2E-Test nennt die Stories, die er belegt:
  `test('…', { tag: ['@story:R3'] }, …)`. Die Pflicht-Stories je Plugin
  stehen in `tests/e2e/stories.json`.
- **`support/story-coverage.mjs`** (in allen drei Plugins gleich) liest
  `results.json` und schreibt `coverage.md`/`coverage.json`. Es schlägt fehl
  bei: übersprungenen, `fixme`-, `flaky`- oder roten Tests, Tests ohne
  Story-Tag, unbekannten Tags und – bei ungefilterten Läufen – Pflicht-Stories
  ohne bestandenen Test. Der Workflow-Schritt „Check the story coverage"
  läuft immer und schreibt die Matrix in die Job-Zusammenfassung.
- **`--pass-with-no-tests` entfernt.** Ein Filter, der nichts trifft, ist
  ein roter Lauf. Gefilterte Läufe sind diagnostisch: Sie prüfen die
  Pflicht-Stories nicht (`--filtered`) und gelten nicht als Abnahme.
- **Mutationsproben** (jeweils auf der Testinstanz eingespielt, Lauf, dann
  zurückgesetzt; danach Gesamtlauf grün):

  | Domäne | Mutation | Rot wird |
  |---|---|---|
  | Zugang (Testhilfe) | `courseAccess()` liefert immer „offen" | V1 „gesperrter Knoten", V1 „Person ohne Pfad" |
  | Zugang (Produkt) | `reconciler`: `enrol_user()` unterdrückt | V1 „erreichbarer Kurs", „neue Sitzung", „Übersicht" |
  | Zugang (fremd) | Lernender zusätzlich manuell eingeschrieben, ADELE unterdrückt | V1 „erreichbarer Kurs" (`open-not-through-adele`) |
  | Entzug | `reconciler`: Suspendieren und Abmelden unterdrückt | V2 „Berechtigung endet" |
  | Prozent | `getnodeprogress()`: `round(50 * …)` statt `100` | L1 „1/4" (12,5 statt 25) |
  | Feedback | `relation_update`: Abschlusstext nicht übernommen | F1/F2 „Vorgänger 1 von 1" |
  | Rolle | `save_learningpath`: Prüfung beim Anlegen entfernt | R3 „Server nennt fehlendes Recht" |
  | Datenschutz | `get_lp_user_path_relations`: Eigene-Ergebnisse-Filter entfernt | P1 „nicht die andere Person" |
  | Abschluss | `path_completion::is_finished`: `>` statt `>=` | Z1 „regulärer Abschluss" |
  | Bedienbarkeit | `pointer-events: none` auf den Knoten-Schaltflächen | E2 „(A UND B) ODER C" |

  Die Bedienbarkeits-Probe war im **ersten** Versuch grün, in drei
  folgenden Versuchen rot (Playwright: „<span> intercepts pointer events").
  Vor dem zweiten Versuch war belegt, dass Moodle das veränderte Stylesheet
  ausliefert; ob es das beim ersten schon tat, ist nicht belegt.
- **Befund aus der Zugangs-Mutation:** Der Seed ließ ADELE-Einschreibungen
  der importierten Referenzpfade aus früheren Läufen stehen. Mit
  unterdrücktem `enrol_user()` blieb V1 deshalb zunächst grün. Behoben im
  Seed (`purge_user()` vor dem erneuten Zuweisen); danach rot wie erwartet.

## Nachtrag `2026100723`: Isolierung, Zeitmodell, echte Klicks, R1/R2, E1 vollständig

- **Jede Datei startet vom Seed** (`reseedFixtures()` in `support/fixtures.ts`,
  `test.beforeAll` in jeder Spec-Datei; die beiden Zeit-Dateien vor **jedem**
  Test). Die Variablen des Seeds werden im laufenden Prozess übernommen.
  Damit hängt kein Test mehr von der Reihenfolge ab; die Zeit-Dateien heißen
  jetzt `time-conditions.spec.ts` und `time-feedback.spec.ts` (vorher `zz-…`,
  um zuletzt zu laufen). Kosten: rund zehn Sekunden je Datei.
- **Seed:** entfernt außer früheren ADELE-Einschreibungen des zugewiesenen
  Lernenden (`2026100722`) jetzt auch **alle anderen Fixture-Lernenden** von
  den importierten Pfaden. Befund: Der Referenz-Lernende von Linear A1 stand
  zusätzlich auf Linear A2 und „Verzweigt gestapelt B1" und behielt über
  Linear A2 eine aktive Einschreibung in T01; V2 („Zugang endet") konnte so
  nicht grün werden.
- **Namen statt Zeitstempel:** `runLabel('E2 ODER')` → „E2 ODER · run<id>"
  (Run-ID aus `ADELE_E2E_RUN_ID`, im Workflow `run<run_id>-<attempt>`).
  Kein `Date.now()` mehr in Titeln.
- **Zeitmodell an einer Stelle** (`support/timing.ts`): die verbleibenden
  Pausen folgen dem Editor (Zeiger-Abtastung 100 ms in `SidebarPath.vue`,
  Zoom-Sperre 500 ms in `RestrictionFlow.vue`/`CompletionFlow.vue`) oder
  sind ein Abwesenheitsfenster bei absichtlich zurückgehaltener Antwort
  (#585). Feste Wartezeiten für Fit-/Zoom-Animationen sind ersetzt durch
  „Leinwand in Ruhe" (Transform der `.vue-flow__transformationpane`); die
  4 s nach dem Tippen des Titels durch `networkidle`.
- **Befund dabei:** Die frühere Hilfe las den Transform von
  `.vue-flow__viewport` – das Element trägt keinen; gemessen wird jetzt die
  Transformationsebene.
- **Echte Klicks überall:** kein `dispatchEvent` mehr in den E2E-Hilfen und
  -Specs (Infokarte, Sprechblase, Master- und Abschluss-Kästchen). Weil die
  Leinwand ein fester Rahmen ist, holt `fitPathView()` den ganzen Pfad
  vorher über die eigene „Ansicht anpassen"-Schaltfläche ins Bild (gemessen:
  das i-Symbol von Knoten 1 lag sonst unter der Navigationsleiste).
  Der Zoom-Schieberegler wird per Tastatur bedient; die Anzeige daneben
  bestätigt den Wert.
- **Neu `roles-direct-access.spec.ts`** (R3/R6, 5 Tests): Kontroll-Lernende,
  Referenz-Lernende und Assistenz schicken jede ändernde Anfrage direkt an
  den Server (umbenennen, verbergen, sich selbst eintragen, Bearbeiter
  lesen, Eigentum übernehmen, duplizieren, löschen) – jede wird abgelehnt,
  und der gespeicherte Pfad ist danach unverändert (Name, Owner,
  Sichtbarkeit, Bearbeiter, Anzahl Pfade). Lesen: der eigene Pfad ja, ein
  fremder nicht. Positivkontrolle: der Manager darf.
- **Neu `roles-own-and-foreign-paths.spec.ts`** (R1/R2): Administrator auf
  einem Pfad des Managers, Manager auf einem eigenen (in der Oberfläche
  angelegt). Sichtbar schalten, umbenennen, gespeichert (Server), Reload,
  wieder öffnen, Assistenz als Bearbeiter, deren eigene neue Sitzung,
  zurücknehmen, Owner bleibt, wieder neue Sitzung – jeweils am Server und
  in der Oberfläche geprüft.
- **Neu `editor-reference-path.spec.ts`** (E1): T01 → T02 → T03 per Drag&Drop
  **mit** Konfiguration: Abschluss je Knoten, Vorgänger T02/T03, für T03
  zusätzlich UND Zeitfenster (2030-01-15 10:00–12:00 UTC, im Browser
  eingetippt) und eigener Autorentext. Gespeichert, neue Sitzung, wieder
  geöffnet; jeder Knoten, jede Kante, jede Bedingung, jeder Wert und der
  Text einzeln geprüft – am Server und im Editor.
- **Beobachtung (zur Entscheidung):** Eine Assistenz, die als Bearbeiter
  eines **verborgenen** Pfads eingetragen ist, den sie nicht angelegt hat,
  sieht ihn in der Übersicht nicht (`learning_paths.php`, #472) und kommt
  damit nicht an ihn heran. Die Kette R1/R2 schaltet den Pfad deshalb
  sichtbar, bevor sie ihn teilt.
- **Erweiterte Ketten:** C1 – je Variante eine zweite Person auf demselben
  Pfad (über denselben Gastkurs, Seed `local_adele_fixture_negative_person`),
  die nichts abschließt und gesperrt bleibt; das Feedback nennt den noch
  fehlenden Vorgänger. L1 – realer Zugang je Stufe (nächster Kurs offen,
  übernächster zu). A – neue Sitzung zählt dieselbe Route; die nicht
  gewählte Alternative bleibt unerledigt; T04 nennt gesperrt beide
  Alternativen. M – eine unbeteiligte Person auf einem anderen Pfad mit T02
  behält ihren Zugang durch beide Änderungen. O4 – Prozentwert der
  Fortschrittsanzeige je Kombination. Z – Startzustand in Z2–Z4; Z1: der
  Autor hängt T04 an den abgeschlossenen Pfad, der Abschluss geht zurück
  (3/4, 75 %), nach T04 wieder 100 %.
- **D ist ein Vertragstest** und heißt so: das CAT-Ergebnis wird als Signal
  eingespielt, keine Browser-Journey durch einen CAT-Test.
- **PHPUnit `timed_duration_boundaries_test`:** relative Frist (ab Zuweisung
  und ab erster Einschreibung) sekundengenau um Beginn und Ende, für zwei
  Personen mit verschiedenem Start; im Browser prüft T3 nur beidseits des
  Neuberechnungs-Puffers.
- **H (mod_adele):** Quelle „jeder Kurs des Pfads" jetzt auch mit den Modi
  „hidden" (gelistet, gesperrt, Kurs zu) und „none" (nicht eingeschrieben);
  neue Gastkurse E2EHOST7/8. Neu E2EHOST9: eine Einschreibung, die ADELE
  selbst erzeugt (Quelle „Startknoten"), trägt niemanden auf einen weiteren
  Pfad, der im selben Kurs für „diesen Kurs" eingebettet ist (#37).
  Mutationsprobe: rot erst, wenn beide Schutzregeln im Beobachter fehlen
  (Einschreibemethode „adele" und `has_foreign_enrolment`) – jede allein
  genügt; rot schon im Vorbedingungsschritt.
- **PHPUnit-Wahrheitstabellen:** Zeitfenster UND/ODER Vorgänger je sechs
  Zeilen (`uc21`, `uc22`), die Klammerung (P UND T) ODER M zur Laufzeit mit
  allen acht Zeilen (`uc23`). Gegenprobe: Zeitbedingung invertiert – genau
  die zwei Zeilen rot, in denen die Zeit entscheidet.
- **Seed:** vergisst bei Fixture-Lernenden die Abschlüsse in **allen**
  Fixture-Kursen (T01, T02, …), nicht nur in den Kursen ihres Pfads. Befund:
  Z1 hängt T04 an; der Abschluss von T04 aus dem Lauf davor war noch da
  (Datenbank geprüft), der verlängerte Pfad war sofort wieder fertig.
- **Beobachtung, nicht reproduziert:** Ein Lauf von `final-completion`
  meldete für Z3 und Z4 im Bericht „Not completed <Datum vom 7. Oktober>"
  statt „Not completed"; der nächste Lauf war grün. Ursache nicht belegt.

## Nachtrag `2026100725`: kein Test in zwei Dateien

- **Anlass:** Im Fork lief `2026100724` (Run 38391579634) mit zwei
  liegengebliebenen Vorgängerdateien (`zz-time-conditions.spec.ts`,
  `zz-time-feedback.spec.ts`, umbenannt in `2026100723`). Sie liefen als
  zweite, veraltete Fassung derselben Tests und waren rot (5); die 78
  aktuellen Tests waren grün. Beide Dateien sind im Fork entfernt
  (2026-10-13).
- **Neu:** `support/unique-tests.mjs` und der Workflow-Schritt „Check that no
  test exists twice" (vor der Suite, alle drei Plugins). Liste aus
  `npx playwright test --list --reporter=json`; derselbe Titel in zwei
  Dateien → Fehler mit den Dateinamen, die Suite startet nicht.
- **Smoke (`tests/playwright`, local_adele):** B4-Lernendenzugang aus
  PR #8 (Fork) aufgenommen; siehe Protokoll Teil 59.
- **Behat (mod_adele):** `tests/behat/mod_adele_use.feature` aus PR #16 –
  Aktivität anlegen, Pfad wählen, Teilnehmendenliste (nur bewertete
  Rollen), Pfad eines Lernenden mit gesperrten Knoten. Läuft in Moodle
  Plugin CI von mod_adele, nicht im E2E-Workflow.

## Nachtrag `2026100726`: Rücknahmen als Negativkontrollen, Abnahmeregel

- **Neu `withdrawn-settings.spec.ts` (mod_adele, 4 Tests):** Einstellungen,
  die schon gewirkt haben, werden im Formular derselben Aktivität
  zurückgenommen – Hostmodus (H3, für „Startknoten" und „irgendein Kurs"),
  eine Teilnehmerquelle (H2), „Teilnehmerergebnisse" (P1/P2). Geprüft
  werden Anzeige, Server und echter Zugang, Unbeteiligte und die Rückkehr.
  Seed: `E2EHOST10`–`12`, `fx_h_dual`, `fx_h_source`, Einbettung
  „E2E P switchable results".
- **Formular-Hilfen** liegen jetzt in `mod_adele/tests/e2e/support/host.ts`
  (vorher in `host-course-participants.spec.ts`), dazu `editEmbedding`,
  `embeddingCmid`, `storedEmbedding`.
- **Abnahmeregel:** `stories.json` → `withdrawal`; eine dort genannte Story
  ist nur abgenommen mit einem bestandenen Test, der `@withdrawal` trägt.
- **Festgehaltene Regeln (offen zur Entscheidung):** Entfernen einer
  Quelle wirkt erst nach nächtlichem Abgleich + 300 s (enrol_adele #3);
  „none" nach aktiver Einschreibung sperrt statt zu löschen (#587).

## Nachtrag `2026100727`: die zwei Regeln entschieden und getestet

- **Quelle entfernt → Entzug beim Speichern** (Entscheidung 2026-10-13).
  Kette B prüft das ohne Abgleich und ohne Uhr; die Verzögerung von
  enrol_adele #3 gilt nur noch für Einschreibungs-Ereignisse.
- **„none" → sperren, nach der Aufbewahrungsfrist löschen; „hidden" bleibt.**
  Kette A schiebt die Testuhr je einmal um Frist + 1 Tag, lässt den
  nächtlichen Abgleich laufen und prüft beides: „hidden" bleibt, „none"
  verschwindet; die eigene Einschreibung der zweiten Person bleibt.
- Die Einträge „offen zur Entscheidung" im Nachtrag `2026100726` sind damit
  erledigt.

## Nachtrag `2026100728`: gelöscht ist weg, Neustart aus dem Kursstand, D

- **H1 „gelöscht ist sauber weg"** (mod, `withdrawn-settings.spec.ts`):
  Löschen entzieht sofort; Wiederherstellen aus dem Papierkorb schreibt
  wieder ein, mit dem Stand aus den Kursen (T01 abgeschlossen).
- **Neues Abo = faktischer Lernstand** (Entscheidung 2026-10-15), auch in
  Kette B geprüft.
- **D:** ein späterer, schlechterer CAT-Versuch nimmt nichts zurück
  (Bestergebnis); die anderen Lernenden bleiben unverändert.

## Nachtrag `2026100729`: Kurszugriff in der Lehrendenansicht (TP1)

- **Neu mod `teacher-course-access.spec.ts`, Stories K1–K5** (in
  `stories.json` verlangt; K4 als Rücknahme mit `@withdrawal`): Kurse der
  aktiven Kachel mit Status, Link nur bei Zugang, neuer Tab; Ablehnung des
  Dienstes für Lernende und fremde Kontexte; Entzug außerhalb von ADELE
  nimmt den Link beim nächsten Aktivieren; Tastatur, Escape, Fokus;
  nichts davon für Lernende und im Pfadeditor.
- **Fixture KZ** (local `seed_fixtures.php`): Gastkurs `E2EKZ` mit „Verzweigt,
  gestapelt B1", zwei Lehrpersonen (mit und ohne Bearbeitungsrecht) in T01
  und T03, nicht in T02; je Lauf wiederhergestellt. Exporte
  `ADELE_FIXTURE_KZ_*`.

## Nachtrag `2026100730`: die Einstellung des Kurszugriffs (#600, TP3)

- **Neu mod `teacher-course-access-settings.spec.ts`, Stories K6–K8** (K7
  als Rücknahme mit `@withdrawal`): zugelassene Modi und Standard der
  Website über Moodles Administrationsseite; neue Aktivität übernimmt den
  Standard, bestehende behält ihren Modus; ein entzogener Modus bleibt
  gespeichert, ist im Formular markiert, wird beim Speichern nicht still
  geändert, ein eingeschleuster Wert nicht gespeichert, und die
  Lehrendenansicht wirkt als „Nur vorhandener Zugang" – zurückgegeben
  wirkt er wieder; Lehrperson ohne Bearbeitungsrecht kann die Einstellung
  nicht ändern.
- **Seed:** setzt `teachercourseaccessmodes` und
  `teachercourseaccessdefault` je Lauf auf den Auslieferungszustand
  (`existing_only`).
- `support/host.ts` `storedEmbedding()` liest auch `teachercourseaccessmode`.

## Nachtrag `2026100731`: „Zugang anfragen" (#600, TP2)

- **Neu mod `teacher-course-access-request.spec.ts`, Stories K9–K12** (K11
  und K12 als Rücknahmen mit `@withdrawal`): Anfrage an die verantwortliche
  Person des Zielkurses mit bearbeitetem Text, beim Empfänger genau so
  angekommen, keine Einschreibung; Abbrechen (Escape, Abbrechen) sendet
  nichts; ohne Ansprechperson kein „Senden", sondern Kontakt und Text zum
  Kopieren; gefälschte Aufrufe und entzogener Modus abgelehnt, nichts
  angekommen. `beforeEach` mit Neu-Seed: jede Kette beginnt ohne
  Mitteilungen und mit den Einstellungen des Auslieferungszustands.
- **Seed:** `fx_kz_owner` (Trainer/in in T02), Anfrage-Einstellungen
  zurückgesetzt; Exporte `ADELE_FIXTURE_KZ_OWNER(_ID)`,
  `ADELE_FIXTURE_KZ_LEARNER_ID`.
- **Neu `support/teacheraccess.ts`:** Website-Einstellungen, Modus der
  Aktivität, wirksamer Modus, empfangene Anfragen (Server), Aktivieren des
  KZ-Stapels.

## Nachtrag `2026100732`: Einschreibung über enrol_adele (#600, TP4)

- **Neu mod `teacher-course-access-enrol.spec.ts`, Stories K13–K18** (K14,
  K15, K17, K18 als Rücknahmen mit `@withdrawal`): Einschreibung als
  Teilnehmer/in nach Bestätigung über enrol_adele mit der Rolle der Website;
  gefälschte Aufrufe abgelehnt; entzogener Modus nimmt die Einschreibung
  zurück (eigene Kursmitgliedschaft bleibt); Richtlinie 2 (Herabsetzen ohne
  Befugnis nimmt die höhere Einschreibung, kein Heraufsetzen), 1 (gesperrt,
  Wert bleibt) und 3 (Delegation, zurück zu 2 nimmt die Einschreibung);
  gelöschte Aktivität nimmt ihre Einschreibungen mit.
- **Seed:** Richtlinie 2, Rollen je Einschreibemodus nach Archetyp,
  Berechtigungstabelle geleert; `fx_kz_teacher` als Bearbeiter/in des
  KZ-Pfads (siehe Issue-Entwurf `mod_adele-issue-form-path-not-editable.md`).
- **`loginAs` (alle drei Plugins):** eine vom vorigen Seitenstand ausgelöste
  Navigation (Weiterleitung auf die Anmeldung) wird zu Ende geführt, dann
  wird die Seite erneut verlassen; vorher brach H3 daran einmal ab.

## Nachtrag `2026100734`: ADELE-Einschreibungen sind keine Quelle (#37, Master-Issue P0–P2, Paket P0)

- **Neu mod `adele-source-shared-node.spec.ts`, Story H4** (`@withdrawal`,
  in `stories.json` unter `required` und `withdrawal`): zwei Pfade teilen den
  Knotenkurs T01. Eine Einbettung „Linear A2" (Startknoten und „irgendein
  Kurs") in `E2E37B` nimmt die ADELE-Einschreibung, die `fx_37_learner` über
  den Pfad in `E2E37A` in T01 erhalten hat, weder live noch nach dem
  nächtlichen Abgleich als Quelle: kein Abo, Kurs `E2E37B` zu. Gegenprobe:
  eigene T01-Einschreibung über die Teilnehmerseite → Abo, Kurs offen.
  Rücknahme: nur die manuelle Methode in T01 beendet → Kurs zu, die
  ADELE-Einschreibung in T01 bleibt.
- **Hilfen in der Spec:** `nightlySweep()` (geplanter Task
  `\enrol_adele\task\reconcile_task`), `enrolAsStudent`, `unenrolMethod()`
  (wählt die Methode über `[data-enrolinstancename]`).
- **Seed (local `seed_fixtures.php`), Block S37** vor dem KZ-Block:
  `fx_37_learner` nur in `E2E37A` (bettet „E2E Linear vier Schritte" mit
  Quelle 1 ein), `E2E37B` leer; Einschreibungen und Aktivitäten beider Kurse
  werden bei jedem Seed zurückgesetzt. Exportiert
  `ADELE_FIXTURE_S37_{HOST_A,HOST_B,LEARNER,LEARNER_NAME,PATH_A,PATH_B,T01}`.
- **Behat neu mod `mod_adele_index.feature`** (`@mod_adele_index`, 4
  Szenarien) für `index.php` (#31); Lauf mit
  `--tags=@mod_adele_index` neben `@mod_adele_use`.

## Nachtrag `2026100735`: Node-Kurs-Einschreibungen nach dem Speichern (#20, #21)

- **Neu mod `node-enrolment-after-save.spec.ts`, Story H5** (`@withdrawal`,
  in `stories.json` unter `required` und `withdrawal`):
  - Quelle „any course" in `E2E20`: erst die Aktivität über das Formular,
    dann tritt `fx_20_any` einem späteren Knoten bei → Abo, Gastkurs über
    enrol_adele (Teilnehmerseite: genau eine Methode, nicht „Manual"); zweite
    Mitgliedschaft (Startknoten), die erste endet → bleibt drin; die letzte
    endet → `adele:1`, Kurs zu, Abo wartet; Karenzzeit über die Testuhr →
    weder Einschreibung noch Abo; Außenstehende/r nie getragen.
  - Quelle „starting node" in `E2E20S`: späterer Knoten allein → nichts;
    Startknoten → drin; Austritt und Rückkehr in der Karenzzeit → aktiv und
    nach der Karenzzeit nicht entfernt.
- **Seed (local), Block S20:** `fx_20_any`, `fx_20_start`, leere Kurse
  `E2E20`/`E2E20S`, Pfad „E2E Linear vier Schritte"; Rücksetzen aller
  Einschreibungen der beiden in den Kursen des Pfads und den Gastkursen,
  ihrer Abos und offener `remove_user_path_adhoc`-Aufgaben. Exportiert
  `ADELE_FIXTURE_S20_{HOST_ANY,HOST_START,PATH,START_COURSE,LATER_COURSE,ANY,ANY_NAME,START,START_NAME}`.
- **`support/host.ts`:** `nightlySweep()`, `enrolAsStudent()`,
  `unenrolMethod()` aus `adele-source-shared-node.spec.ts` hierher
  verschoben (von H4 und H5 genutzt).

## Nachtrag `2026100736`: Aktualisierung, Auto-Fokus, gestapelte Kurse, erste Einbettung (Master-Issue P0–P2, Paket P2)

- **Neu mod `path-refresh.spec.ts`:** Story R26 (`@story:R26`, 5 Tests:
  Modus 0, 1, 2 mit langsamem Server, 3, fehlschlagender Server) und F27
  (`@story:F27`, 2 Tests: Lernenden-Fokus nach Abschluss in einem zweiten
  Tab, Ansicht bleibt bei unveränderten Abfragen; Auto-Fokus aus). Setzt
  die Einstellungen per `admin/cli/cfg.php` und stellt die Standards am
  Ende wieder her.
- **Neu mod `stacked-courses.spec.ts`:** Story S17 (ausgeklappter Stapel
  `E2EKZ`, Kurs-ID, Name, Tastatur, erneutes Ausklappen).
- **Neu mod `first-embedding.spec.ts`:** Story S11 (neuer Kurs je Lauf).
- **Seed (local), Block S26:** `fx_26_learner` auf „Linear A1" über
  `E2E26`; Abschlüsse, Pfad-Datensatz und ADELE-Einschreibungen werden
  zurückgesetzt. Exportiert `ADELE_FIXTURE_S26_{HOST,LEARNER,PATH,NODES}`.
- **`playwright.config.ts` (mod):** Projekt `firefox` für R26, F27, S17,
  aktiv bei `ADELE_E2E_FIREFOX`; `e2e.yml` installiert dann Firefox.
- `stories.json`: S11, S17, R26, F27 unter `required`, F27 unter
  `withdrawal`.

### Nachtrag `2026100736`, Re-Audit 0735 (2026-10-18)

- **Seed S26 geändert:** eigener Pfad „E2E26 Linear“ (Kopie von „Linear A1“
  ohne die abgelaufenen Zeitfenster der Knoten 2 und 3) statt einer
  Einbettung von „Linear A1“ – diese nahm den Referenz-Lernenden beim
  nächsten Seed vom Pfad. Neu `fx_26_teacher` (Trainer/in in E2E26, keine
  Pfadbearbeitung); Exporte zusätzlich `ADELE_FIXTURE_S26_{TEACHER,LEARNER_ID,CM}`.
- **Seed S27P (neu):** „E2E27 Parallel“ – der S26-Pfad mit Knoten 3 neben
  Knoten 2 (beide warten nur auf Knoten 1); `fx_27p_learner` über `E2E27P`.
  Exporte `ADELE_FIXTURE_S27P_{CM,LEARNER}`.
- **Seed S17 (neu):** „E2E17 Vertauscht“ – Kopie von „Verzweigt, gestapelt
  B1“ mit den ersten beiden Kursen des Stapels in umgekehrter Reihenfolge;
  `fx_17_learner` über `E2E17`. Exporte `ADELE_FIXTURE_S17_{CM,LEARNER,ORDER}`.
- **Seed KZ:** Workaround für mod_adele #43 entfernt – die Bearbeiterzeile
  der Trainerin am Pfad wird gelöscht; K16 speichert das Formular ohne
  Pfadrecht.
- **Seed S11:** eigene Pfade „E2E11 Erster/Zweiter Pfad“; ältere
  `E2E11-*`-Kurse werden gelöscht.
- **`path-refresh.spec.ts`:** neu „teacher default (Testfall 1, 2, 6)“,
  „Testfall 8“ (Ziehen nicht unterbrochen) und „Testfall 4“ (parallele
  nächste Knoten gemeinsam im Blick); `afterEach` entfernt Routen
  (`unrouteAll`), Hilfsfunktionen `settled`, `inView` (sichtbarer Teil im
  Fenster), `lookAway`, `learnerFinishes`, `teacherOpensLearner`.
- **`stacked-courses.spec.ts`:** Karte mit Rolle, eindeutiger ID,
  Überschrift und sichtbarer Beschreibung (Kurzname, Kurs-ID, Status);
  keine doppelte DOM-ID außerhalb der Kanten (Issue-Entwurf
  `local_adele-issue-duplicate-edges.md`); Ausklappknopf mit
  `aria-expanded`/`aria-controls`, Escape aus einem Panel, sichtbarer
  Fokus, erneutes Ausklappen per Enter; zweiter Test mit vertauschter
  Reihenfolge.
- **`support/fixtures.ts::setSystemRole()` (alle drei Plugins):** Suche
  im Rollenzuweisungs-Dialog Taste für Taste (`pressSequentially`). Befund
  (belegt, Gesamtlauf 2026-10-18): mit den neuen Fixture-Personen hat die
  Website 109 mögliche Personen; Moodle zeigt ab 100 nur „Zu viele
  Benutzer – bitte Suche verwenden“, und ein per `fill()` gesetzter
  Suchtext startet die Suche nicht (sie reagiert auf Tastenereignisse).
  R5 war dadurch rot, mit der Änderung grün.

## Nachtrag `2026101800`: eine Kante je Verbindung (local_adele #603)

- **Neu local `editor-duplicate-edges.spec.ts`** (Story E1, 2 Tests):
  erneutes Verbinden zweier verbundener Knoten → eine Kante im Graphen, eine
  gespeichert; gespeicherter Referenzpfad mit Kopien („Verzweigt, gestapelt
  B1“, vom Seed wie exportiert geschrieben: 7 Kanten) → 5 gezeichnet, ein
  `path`-Element je Verbindung, keine DOM-ID doppelt im Graphen, beim Laden
  nichts geschrieben.
- **`stacked-courses.spec.ts`:** Ausnahme für `.vue-flow__edges` entfernt –
  die ganze Seite ohne doppelte DOM-ID.
- Der Seed schreibt die Referenzpfade weiterhin wie exportiert (mit Kopien);
  so prüfen die Ketten auch die Darstellung historischer Daten.

## Nachtrag `2026111900`: DOM-IDs, Gruppen, Live-Aktivität (local_adele #12 – #18)

- **Neu local `dom-ids.spec.ts`** (Story A18, 3 Tests, Chromium **und**
  Firefox): ganze Seite ohne doppelte DOM-ID (Übersicht, Editor mit allen
  Dialogen, Kursseite `E2EM18` mit drei Anwendungen); jede `for`/`aria-*`/
  `list`/`data-target`/Marker-Referenz zeigt auf genau ein Element derselben
  Anwendung; Knoten- und Tutorial-Dialog per Tastatur (Name, Fokus, Labels,
  Escape, Fokus zurück, axe). Firefox-Projekt in local (`grep @story:A18`);
  `e2e.yml` von local und enrol installieren Firefox wie mod.
- **`editor-duplicate-edges.spec.ts`:** prüft jetzt die ganze Seite; die
  Kanten-IDs tragen das Präfix der Anwendung (`adele1-…`).
- **Nachtrag `2026112000`:** A18 „Editor“ prüft zusätzlich, dass die Felder
  `goalnameplaceholder` und `goalsubjectplaceholder` über `name` erreichbar
  sind (Behat-Locator, CI 2026-11-20).
- **Neu mod `groups-live.spec.ts`** (Stories G16, L17; 6 Tests): Fixture
  `E2EG16` (Pfad „E2E Gruppen G16“, Gruppen Rot/Blau, Aktivitäten
  „getrennt“ mit Live-Markern und „sichtbar“ ohne). G1–G3 Ranglisten,
  Mittelwerte, Gruppenwahl, Ablehnung fremder Gruppen, Nicht-Lernende, Nutzlast
  der Server-Antworten ohne fremde Namen; L1 Marker und Zähler nach echtem
  Kursaufruf, Aktualisierung per Polling ohne Neuladen, Zähler → Namen →
  Pfad der Person zentriert auf den Knoten, Escape/Fokus; L2 ausgeschaltet;
  M1 Aktivitätseinstellungen. `beforeEach` setzt die Fixture zurück
  (Knotenstatus der Schnappschüsse, Live-Aktivität, Schalter): ein Kursaufruf
  berechnet den Schnappschuss aus den echten Kursabschlüssen neu.
- **Selektoren:** Teilnehmerliste über `data-testid="adele-userlist-*"`
  (E2E und Behat) statt `#adele-userlist-*`; Stapel-Schaltfläche über
  `data-testid="stack-toggle-<Knoten>"`; IDs mit Präfix werden mit
  `[id*=…]`/`[id$=…]` oder dem Ausdruck `^adele\d+-…$` geprüft.
- **Seed:** Fixtures `E2EM18` (drei Einbettungen auf einer Kursseite) und
  `G16` (Variablen `ADELE_FIXTURE_G16_*`, `ADELE_FIXTURE_M18_HOST`).
- **P1/P2/P3 an #12 angepasst (Verhaltensänderung, verlangt von AK0-L2):**
  ohne Gruppen sehen Teilnehmende auch bei „Ergebnisse aller Teilnehmenden“
  nur die eigene Zeile (`learner-data.spec.ts` P2/P3). Damit die Rücknahme
  „alle → nur eigene“ (P1/P2, `withdrawn-settings.spec.ts`) weiter etwas
  ändert, hat die umschaltbare Einbettung im Seed getrennte Gruppen; Alpha
  und Beta teilen eine – „alle“ zeigt die Gruppenrangliste, „nur eigene“
  schließt sie.
- **Seed-Fund (belegt):** `E2EM18` bettete zunächst den Referenzpfad
  „Linear A1“ ein; das Entfernen der alten Aktivitäten beim nächsten Seed
  nahm student01 den Pfad (Rücknahme nicht getragener Abonnements). Jetzt
  eigene Kopien „E2E M18 Linear“/„E2E M18 Verzweigt“.
- **Reihenfolge lokal:** Die Smoke-Suites lesen `fix.env`; nach den E2E-Läufen
  (die neu seeden und Pfad-IDs ändern) zuerst `seed.sh`. Der Smoke-Test „hat
  noch nicht gelaufen“ (enrol) setzt voraus, dass kein Abgleich lief
  (`enrol_adele/lastreport` leer) – in CI frische Site.

### Nachtrag 2026-11-21 (`2026112101`)

- **Einschreiben über Moodles Dialog:** nur noch über `enrolInDialog`
  (mod `support/host.ts`, local `support/enrol.ts`). Der Helfer prüft die
  Auswahl im Autocomplete, wiederholt sie notfalls per Tastatur und prüft
  Moodles Antwort (`count: 1`). Anlass: CI-Lauf, in dem der Klick auf den
  Vorschlag verloren ging und das Formular ohne Nutzer abgeschickt wurde.
- **R26:** der Zähler wartet vor dem Zurücksetzen, bis keine Anfrage der
  vorigen Seite mehr offen ist.
- **A18:** neuer Test „overview: the controls ADELE-SR-01 listens for …“ –
  Vorprüfung des NVDA-Laufs im Accessibility-Baum.
- **Lokaler Stand:** jede Suite auf frisch geseedeter Seite, Smoke vor E2E,
  wie in der CI (eine Seite je Workflow).

### Nachtrag 2026-11-22 (`2026112102`)

- **L3 (mod, `groups-live.spec.ts`):** der Rückweg von einer lernenden
  Person – vom Marker und per Tastatur über Zähler und Namen: Gruppe,
  Ausschnitt des Graphen, Scrollposition und Fokus wie verlassen (#15 AK3.5,
  DoD3.4). G2 prüft den Mittelwert über alle Lernenden („Mittel über N
  Lernende“).
- **A18 (local, `dom-ids.spec.ts`):** kein `dropdownMenuButton` auf der
  Seite, ADELE-Link der Navigation ohne ID (#18 AK4/DoD3).
- **E2 (local, `editor-combinations.spec.ts`):** Kursdialog eines Stapels per
  Tastatur – eigener Titel, Felder mit eigenen Labels, Escape, Fokus zurück
  zum Öffner, keine doppelte ID (#18 DoD2).
- **Server-Uhr:** Ketten, in denen nur Aufgaben in der Zukunft laufen müssen
  (H1–H3 Ablauffrist, Karenzfrist nach dem Speichern), verschieben nur die
  Uhr des Aufgabenlaufs (`cliClockEnv()`); die Uhr des Webservers bleibt.
  Wo der Webserver die verschobene Zeit sehen muss (local T, F), verlässt
  die Seite vor jedem Wechsel die Website (`moveServerClock()`). Die
  Uhr-Datei wird atomar geschrieben (Anlass: CI 2026-11-21, Webserver ab
  einem Uhr-Rücksprung ohne Antwort).
- **Diagnose:** bei Fehlern zeigen die E2E-Workflows offene Anfragen des
  Webservers, PHP-Prozesse, laufende Transaktionen und wartende Sperren.
- **P1/P2/P3 – „Teilnehmer sehen“ nur ohne Gruppen (Entscheidung
  2026-11-22, ersetzt die Anpassung vom 2026-11-19 oben):** ohne Gruppen
  zeigt „Ergebnisse aller anderer Teilnehmer“ Teilnehmenden die Lernenden der
  Aktivität, ohne Link; der Server schickt fremde Zeilen ohne ID und
  Benutzernamen (`learner-data.spec.ts` P2/P3). Die umschaltbare Einbettung
  hat im Seed wieder **keine** Gruppen; die Rücknahme „alle → nur eigene“
  (`withdrawn-settings.spec.ts`) prüft das ohne Gruppen und schaltet danach
  auf getrennte Gruppen: das Feld ist verborgen, ein gespeichertes „nur
  eigene“ ändert nichts (die Gruppenrangliste zeigt beide), zurück ohne
  Gruppen ist das Feld wieder da und „nur eigene“ gilt.
