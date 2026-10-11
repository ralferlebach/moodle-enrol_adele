# Session 006 — #574/#575 Barrierefreiheit und Testbarkeit, Fixtures, E2E-Suite

**Zeitraum:** 2026-09-17 bis 2026-09-21 (ein Chat)
**Ausgangsstände (Wunderbyte-Upstream, frisch geklont nach Verwerfen eines
ersten Ansatzes):** `enrol_adele` 0.4.0 (2026082903, `main` @ `bfe465d`),
`local_adele` 0.5.5 (2026082902, `main` @ `1fdbb0c`), `mod_adele` 0.4.1
(2026083000, `master` @ `6c13528`)

---

## Teil 1 — Verworfener Ansatz

Ein erster Umbau der vorhandenen `playwright.yml` (manueller Start,
Vollexport) samt Userstory #492 wurde auf Wunsch des Auftraggebers
**verworfen**; die Repos wurden neu geklont. Erhalten blieben nur Erkenntnisse,
die später wieder auftauchten (Webserver im selben Schritt starten,
`bash -e` in `run`-Blöcken, Sitzungsfallen).

## Teil 2 — #575 und #574 in `local_adele`

Reihenfolge nach #575 selbst (Barrierefreiheit vor Test-Hooks), B3 nach der
dort empfohlenen Variante (Weg B).

- **Gemeinsame Zustandsableitung** `composables/useNodeStatus.js`: eine Quelle
  für Icon, Accessible Name und `data-status`; reicht den Backend-Status
  (`accessible`, `completed`, `not_accessible`, `closed`) unverändert durch,
  alles andere – auch die Fehlerzeichenkette „loop limit exceeded" – wird
  `unknown`.
- **B1/#574** Nodes (`CustomNode`, `CustomNodeEdit`, `CustomStagNodeEdit`,
  `OrCourses`): `data-testid`, `data-status`, `role="group"`, Name aus
  Kursname und Zustand.
- **B2/#574** Übersicht: Sichtbarkeit als `<button>` mit `aria-pressed`, alle
  Icon-Links als benannte Schaltflächen, kein `href=""` mehr; Kennungen
  `learningpath-row-<id>`, `-visibility-toggle-<id>`, `-create`; Editor
  `-title`, `-description`, `-save`, `-close`; verknüpfte `<label for>`.
- **B6** Kontraste gemessen: drei Zustandsfarben unter 3:1, minimal
  abgedunkelt (`#db8d31→#c37922`, `#63aa43→#59993c`, `#90b6ca→#5891af`);
  globaler Fokusindikator in `styles.css`.
- **B4** Live-Region `A11yLiveRegion.vue` im Wurzeltemplate; `announce` im
  Store, `newlyReachedNodes()` meldet nur Verbesserungen und schweigt beim
  ersten Laden. Wiederholte Ansagen per wechselndem geschütztem Leerzeichen
  (Leeren wirkt nicht, Vue bündelt beide Schreibvorgänge).
- **B3 Weg B** `LearningPathOutline.vue`: topologisch geordnete Liste mit
  Zustand, Voraussetzungen als Text und Kurslink nur wo der Graph ihn anbietet.
  Umschalter in `LearningPathView.vue` **und** `user_view/UserPath.vue` – die
  Lernendenansicht ist Letztere, zuerst war der Umschalter an der falschen
  Stelle.
- **B5** Einfügelogik von der Drag-Mechanik getrennt (`insertCourseNode`);
  Einfügen-Schaltfläche je Kurs, Dialog `KeyboardInsertDialog.vue` mit
  Zielknoten und Beziehung, Fokusfang, Rückgabe, Escape; `Entf` löscht den
  fokussierten Knoten über die vorhandene Routine.
  **Offen:** bereits platzierte Knoten per Tastatur umhängen.
- Versionsnummer `2026092100`, `release` unverändert `0.5.5` (Entscheidung
  des Auftraggebers: Datum heben, Namen nicht). Grund: neue Sprachstrings,
  die das Frontend über einen versionsabhängigen Cache lädt.

## Teil 3 — Fixtures aus `adele-test`, fest übernommen

`ralferlebach/adele-test` @ `4927a19` nach
`local_adele/tests/playwright/fixtures/` **kopiert** (Auftraggeber: nicht aus
einem sich verändernden Repo beziehen). Neuer `seed_fixtures.php`:
Restore-API, Umschreiben der Kurs-IDs über den Kurznamen, Umschreiben der
`adele.learningpathid` in mitgebrachten Aktivitäten (nur frisch
wiederhergestellte Kurse), Abo über `enrollment::subscribe_user_to_learning_path()`,
wiederholbar.

Eigene Fehler, gefunden und behoben:

- `foreach ($x ?? [] as &$node)` iteriert eine **Kopie** – die Kurs-IDs wurden
  lautlos nicht umgeschrieben. Aufgefallen nur, weil ein Kurslink auf die
  Ursprungs-ID zeigte.
- Kurzname in Großbuchstaben gesucht → Nutzerkurs doppelt eingespielt.
- Beziehungen trugen nach erneutem Lauf alte IDs weiter (eigene Baumkopie).
- Ein Lernender auf allen vier Pfaden: Berechtigung vereinigt sich, er war
  über einen zweiten Pfad in Kursen, die der erste sperrt. Jetzt einer je Pfad
  plus Kontrollperson.
- Rücksetzer des Hostkurses traf zuerst die falsche Einschreibungsart und
  hätte die Pfad-Lernenden mitgelöscht.

## Teil 4 — E2E-Suite

Nach `AdeLe_E2E_Testplan_UserStories.md` (abgelegt unter `docs/testplan/`).
Eigenständig unter `tests/e2e/` je Plugin, Workflow `e2e.yml`, nur manuell,
Video für jeden Test, ein Artefakt immer. Beschreibung und Stand:
[`e2e-suite.md`](../e2e-suite.md).

Umgesetzt: R1 (`local_adele`), C1 Freigabeteil des E2E-Vertrags
(`enrol_adele`), H1 (`mod_adele`).

Gefunden und behoben:

- **Wackelnde Anmeldung** (etwa jeder dritte Lauf): eine AJAX-Anfrage der
  vorherigen Seite setzte nach der neuen Anmeldung das alte Sitzungscookie im
  gemeinsamen Cookie-Speicher. `loginAs` verlässt die Seite jetzt zuerst
  (`about:blank`). In Smoke- und E2E-Suiten aller drei Plugins übernommen.
- **Verzögerte Aufgaben**: ein Warteschlangenlauf reicht nicht; begrenzt
  wiederholtes Abarbeiten statt Warten.

## Verifikation (Abschluss, 2026-09-21)

| Prüfung | Ergebnis |
|---|---|
| phpcs `--standard=moodle --severity=1` | 0/0 in allen drei Plugins |
| `php -l` | sauber |
| Jest `local_adele` | 70 Suiten, 326 Tests grün (Ausgang 66/287) |
| actionlint `e2e.yml` ×3 | ohne Befund |
| actionlint `playwright.yml` | ein vorbestehender Shellcheck-Hinweis (SC2034), unverändert |
| Smoke `local_adele` | 14/14 |
| E2E `local_adele` / `mod_adele` / `enrol_adele` | 2/2, 1/1, 5/5; fünf bzw. zwei Wiederholungen stabil |
| Browser, gebautes Bündel | Namen, Zustände, Liste, Ansagen, Tastatureinfügen und -löschen bestätigt |

Umgebung: Moodle 4.5.14+ (Build 20260916), PHP 8.3.6, PostgreSQL 16, lokale
Nachbildung der Workflows ohne `setup-php`, Service-Container und Upload.

## Offen

- `local_adele` **zuerst** upstream einspielen: `mod_adele`/`enrol_adele`-E2E
  brauchen dessen Fixture-Seed.
- #575 B5: bestehende Knoten per Tastatur umhängen.
- Screenreader-Abnahme und Protokoll (manuell, beim Auftraggeber).
- E2E Phasen 1 (Rest) bis 4, siehe `e2e-suite.md` §5; Kette T blockiert durch
  fehlende steuerbare Testzeit.
- `npm run lint` in `local_adele` ist vorbestehend defekt (ESLint 9 ohne
  `eslint.config.js`).
- Die Lernendenansicht im Test ist nur über eine `mod_adele`-Aktivität
  erreichbar; ein Test dafür gehört in `mod_adele`.


## Teil 5 — Zeit: Moodle 4.5, `\core\clock`, Issues #580–#583

**Issues:** local_adele #580 (Ablagezonen), #581 (Zeitstempel), #582 (Core-Uhr),
#583 (Mindestversion); enrol_adele #11, mod_adele #36 (Core-Uhr).

- **#583 umgesetzt (`2026100500`):** `$plugin->requires = 2024100700` in
  `local_adele` und `enrol_adele`; die alte Begründung für 4.1 entfernt.
- **#582, enrol #11, mod #36 umgesetzt (`2026100501`):** Alle drei Plugins
  lesen „jetzt" über `\core\di::get(\core\clock::class)`, auch in
  `db/install.php` und `db/upgrade.php` (nur Zeitstempel, verhaltensneutral).
  Je Plugin ein Wächtertest gegen direkte Uhrzugriffe und Verhaltenstests mit
  eingefrorener Uhr.
- **Herkunft:** Der Umbau lag bei Wiederaufnahme der Arbeit bereits in den
  Arbeitskopien, ohne dass er einem in dieser Session sichtbaren Schritt
  zuzuordnen war. Er wurde deshalb wie fremde Arbeit geprüft: Diff gegen die
  Lieferung `2026100500` gelesen, Wächtertest mit eingeschleustem `time()`
  gegengeprüft (schlägt an), unabhängige Suche ohne Treffer, vollständige
  PHPUnit-Suiten und alle E2E-Ketten grün.
- **Fixture-Seed:** ein doppelt eingefügter Block (Kontrollperson und
  Hostkurs-Mitglieder) entfernt; er stammte aus einer Blockverschiebung in
  Teil 4 und verschob die Rollenzuordnung um eine Person. In `2026100500`
  noch enthalten, ohne Testfolgen, weil die Ketten die Rollen aus den
  Variablen lesen.

**Offen:** #581 (Zeitstempel, Grenzwertsemantik — Vorschlag halboffenes
Intervall, Entscheidung im Issue nicht abrufbar), #580 (Ablagezonen).

**Prüfstand:** PHPUnit enrol 53, mod 11, local 309 (5 übersprungen: catquiz
nicht installiert); E2E local 13/13, mod 3/3, enrol 6/6; phpcs 0/0.


## Teil 6 — local_adele #581: Zeitstempel statt Zeichenketten

- **Neu:** `classes/helper/time_value.php` — Umrechnung gespeicherter Werte
  (Ganzzahl, numerische Zeichenkette, Altformat `Y-m-d\TH:i` in der
  Site-Zeitzone mit `!`-Format, also Sekunden = 0), Fensterzustand,
  Anzeige über `userdate()` in der Zeitzone der betroffenen Person,
  Migration eines Baums.
- **Bewertung** (`timed.php`): ein Uhrzugriff je Knoten, Ganzzahlvergleich,
  halboffenes Intervall. `inbetween_info` trägt jetzt Zeitstempel statt
  formatierter Zeichenketten. `isvaliddate()` bleibt als öffentliche Methode
  unverändert stehen, wird aber nicht mehr verwendet.
- **Neuplanung** (`adhoc_task_helper.php`) und **Rückmeldetext**
  (`relation_update.php`) lesen dieselben Zeitstempel; `strtotime()` und
  `date()` entfallen dort.
- **Frontend:** `composables/timeValue.js`; `timed_dates.vue` speichert
  Sekunden, zeigt Ortszeit des Browsers; `DateInfo.vue` und
  `NodeInformation.vue` formatieren Zeitstempel (vorher: Serverzeit als UTC
  gelesen bzw. Sekunden als Millisekunden).
- **Upgrade 2026100502:** migriert Lernpfade und Nutzerpfadkopien,
  idempotent. Hinweis im README von `local_adele` (Abschnitt „Upgrade notes").
- **Nebenfund:** `timed_test.php` setzte voraus, dass das echte Datum
  zwischen 2024 und Ende 2026 liegt, und wäre am 2027-01-01 rot geworden;
  jetzt mit eingefrorener Uhr.
- **Entscheidung ohne Rückmeldung aus dem Issue:** halboffenes Intervall, wie
  im Issue vorgeschlagen; entspricht dem bisher beobachteten Verhalten.

**Prüfstand:** PHPUnit local 315 (5 übersprungen, catquiz); neu
`timed_timestamps_test.php` 6 Tests/51 Zusicherungen; Jest 72 Suiten/338
Tests, `timeValue.spec.js` grün in UTC, Europe/Berlin, America/New_York und
Asia/Tokyo; Durchstich im Browser: Altbestand korrekt angezeigt, nach dem
Speichern als Zahl abgelegt; E2E local 13/13, mod 3/3, enrol 6/6.


## Teil 7 — #581 Nachtrag: `timed_duration` angeglichen, Testdaten bereinigt

- **Angleichung (Entscheidung des Auftraggebers):** `timed_duration` ist
  jetzt ebenfalls halboffen (`Beginn ≤ jetzt < Ende`), über
  `time_value::window_state()`; `inbetween_info` trägt Zeitstempel, Anzeige
  über `time_value::display()`.
- **Regression aus Teil 6 behoben:** `relation_update::inbetweenfeedback()`
  las das Fensterende seit #581 über `time_value::to_timestamp()`,
  `timed_duration` lieferte aber noch `d.m.Y H:i`-Text, den die Hilfsklasse
  nicht als Zeit erkennt — der Rückmeldetext „Zugang bis …" wäre für
  relative Fristen leer geblieben. Abgesichert durch
  `test_feedback_names_the_end_for_both_kinds_of_window`; Gegenprobe mit dem
  alten Zustand schlägt an.
- **`timed_test.php`:** Die festen Jahreszahlen (2024–2026, 2099) waren reine
  Testdaten, die nur deshalb nötig erschienen, weil der Test gegen die echte
  Uhr lief. Jetzt relativ zu einem eingefrorenen Bezugszeitpunkt formuliert;
  keine Datumsbegrenzung mehr.

**Prüfstand:** PHPUnit local 316 (5 übersprungen, catquiz), Jest 338,
E2E local 13/13, mod 3/3, enrol 6/6, phpcs 0/0.


## Teil 8 — #580 und die E2E-Ketten E1/E2

- **#580, Begründung widerlegt:** Echtes Drag&Drop auf bestehende Knoten ist
  ohne Produktänderung testbar (Technik in `tests/e2e/support/editor.ts`,
  Messungen in `docs/e2e-suite.md` §5c). Kommentarentwurf zur Korrektur des
  Issues: `issues/local_adele-issue-580-comment.md`. Als Bedienpunkte bleiben
  „Zonen hängen am zuerst passierten Knoten" und „Ansicht wird nach dem
  Ablegen nicht eingepasst".
- **E1 vollständig:** Referenzpfad T01 → T02 → T03 per Drag&Drop, geprüft
  nach erneutem Öffnen über Knoten **und** Kanten.
- **Eigener Fehler, rechtzeitig gesehen:** Die erste E1-Fassung lief grün,
  obwohl T03 an T01 statt an T02 hing — die Kanten waren nur ausgegeben, nicht
  geprüft. Jetzt geprüft, und die Hilfsfunktion kontrolliert, an welchem
  Knoten die Zonen liegen.
- **E2:** A ODER B (Stapel) und A UND B (paralleler Knoten) grün. **Befund:**
  Der gemeinsame Nachfolger bekommt den parallelen Knoten nicht in seine
  Vorgänger-Bedingung (`addAutoRestrictions()` gibt ihn für `and` unverändert
  zurück, ein Jest-Test hält das fest). Nicht festgeschrieben, sondern als
  `test.fixme` sichtbar gehalten; Issue-Entwurf
  `issues/local_adele-issue-parallel-node-criterion.md`. Dazu widersprechen
  sich Zonenname (`and`) und Beschriftung („Alternative node").
- **Werkzeugfehler:** `pkill -f "<muster>"` traf die eigene Befehlszeile und
  beendete den Aufruf selbst; Abhilfe: Klammer-Schreibweise (`[n]ode_modules`).
- **Regelverstoß:** Einen Probe-Lernpfad (ohne Abonnenten) direkt per SQL
  gelöscht, entgegen der eigenen Regel; kein Schaden, der Folgepfad blieb
  stehen.

**Prüfstand:** E2E local 16 grün + 1 `fixme`, zweimal hintereinander; E1-Datei
dreimal grün. Produktcode unverändert.


## Teil 9 — CI nach Umbenennung, #584, Benennung der Ketten

- **CI:** Upstream heißt `local_adele` jetzt `moodle-local_adele`, `mod_adele`
  hat `main` statt `master`. `moodle-plugin-ci add-plugin --branch master …`
  brach damit die Plugin-CI von `local_adele` und `enrol_adele`. Alle
  Workflows (Plugin-CI, Smoke, E2E) holen die Begleiter jetzt einheitlich vom
  Eigentümer des laufenden Repositorys, gleichnamiger Branch, sonst `main`.
  Geprüft: Auflösung für Wunderbyte und Fork über `development`, `main` und
  einen unbekannten Branch; Klonen beider Eigentümer; actionlint ohne Befund.
- **#584 umgesetzt:** Zone „Alternative node" — jeder geteilte Nachfolger
  nimmt den parallelen Knoten in seine Vorgänger-Bedingung auf; `min_courses`
  bleibt unverändert (Voreinstellung 1, wie im Bedingungs-Editor). Gegenprobe:
  E2E mit altem Bündel rot (`dndnode_3` fehlt), mit neuem grün. Die Prüfung
  liest die **gespeicherte** Bedingung, weil `parent_courses.vue` beim Öffnen
  ohnehin alle Vorgänger einsetzt und die Anzeige daher nichts beweist.
- **Umbenennung:** Die `enrol`-Ketten heißen jetzt V1/V2 (zentraler
  E2E-Vertrag, Plan §27); `C` bleibt der Plan-Kette „Zugangsbedingungen".

**Prüfstand:** Jest 341; E2E local 16/16; E2-Gegenprobe rot→grün.


## Teil 10 — CI-Prüfung nach der Umbenennung

Echte Läufe in den Forks (`development`, Stand `2026100600`):

| Plugin | Plugin-CI | Playwright smoke |
|---|---|---|
| local_adele | grün (vorher rot) | **rot**: 1 Kontrastverstoß (axe `color-contrast`) |
| enrol_adele | grün (vorher rot) | grün |
| mod_adele | grün | grün |

Der Kontrastverstoß tritt nur auf dem Runner auf: lokal grün, isoliert wie
auch in der vollständigen Suite in CI-Reihenfolge. Das CI-Bildschirmfoto zeigt
nichts Auffälliges; der Trace enthält nur die bestandenen axe-Prüfungen. Der
Test meldet deshalb jetzt Selektor, Markup, gemessene Farben, Verhältnis,
Anforderung und Schrift — gegengeprüft mit einem erzwungenen Verstoß. Ein
erneuter Lauf über die API war nicht möglich (Token ohne Actions-Schreibrecht,
HTTP 403).


## Teil 11 — Kette T (Zeit)

- **T1/T2** (festes Fenster) und **T3** (relative Frist) im Browser über die
  Server-Testuhr; neue Fixture-Pfade „E2E Zeitfenster" und „E2E Zeitdauer"
  mit eigenen Lernenden.
- **Eigener Fehler, im Seed:** Die erste Zeitbedingung war aus der **zweiten**
  Bedingung eines Fixture-Knotens kopiert und trug deren Verkettung
  (`parentCondition: ['condition_1']`); die Auswertung beginnt aber nur bei
  `starting_condition`, also blieb der Knoten trotz erfüllter Bedingung
  gesperrt. Gefunden, indem Bedingung und Gesamtlogik getrennt ausgewertet
  wurden.
- **Seiteneffekt von Zeitsprüngen:** siehe `e2e-suite.md` §5e; Reihenfolge
  in der Kette und Dateiname `zz-` legen ihn fest.
- **Werkzeug:** Ein `pgrep -f` mit Klammermuster traf erneut den eigenen
  Aufruf, weil dieselbe Befehlszeile das Muster weiter hinten enthielt; der
  Server wird jetzt über den Port beendet (`fuser -k 8001/tcp`).
- **Workflows:** `e2e.yml` installiert `faketime` und startet den Server unter
  der Testuhr (alle drei Plugins, damit die Vorlagen gleich bleiben).

**Offen:** T4/T5 (Zeit UND/ODER Vorgänger) — brauchen den echten Abschluss
eines Kurses, der mit Kette C kommt.

**Prüfstand:** E2E local 18/18 (davon T 2); CLI-Gegenprobe der Übergänge.

## Teil 12 — Präsentation, Kette H begonnen, Befund Kettenreaktion

- **Präsentation** für das Meeting am 7. Oktober (7 Folien, PPTX, außerhalb
  der Plugins geliefert).
- **H vorbereitet (noch nicht geliefert):** Seed legt die leeren Hostkurse
  E2EHOST3–6 an (je ein Szenario: Quelle 3, Modus hidden, Modus none,
  Quellen 1+2 kombiniert) und eine Lernende nur in T03 (`student10`).
- **Befund:** Teilnehmerquellen werten auch Einschreibungen aus, die ADELE
  selbst angelegt hat. `student10`, von Hand nur in T03, landete über die
  Einbettungen des Fixture-Kurses `l01` auf drei Pfaden und in T01. Ursache:
  `mod_adele_observer::user_enrolment_created()` reagiert auf jede
  Einschreibungsmethode. Issue-Entwurf
  `issues/mod_adele-issue-participant-source-cascade.md`; Entscheidung offen.
  Folge für H: Negativprüfungen der Quellen 2 und 3 nutzen bis zur
  Entscheidung die Person ohne jede Einschreibung (`student08`).

## Teil 13 — mod_adele #37 umgesetzt, Kette H vervollständigt

- **#37 (als [BUG] eingestellt → ADELE-eigene Einschreibungen zählen nicht):**
  Beim Nachlesen stellte sich heraus, dass Quellen 2/3 und die A-4-Prüfung das
  schon taten; mein Issue-Text war an einem Punkt zu weit gefasst (Korrektur
  im Kommentarentwurf `issues/mod_adele-issue-37-comment.md`). Die Lücke war
  Quelle 1 an zwei Stellen: `mod_adele` (Ereignis und Speichern) und
  `local_adele\enrollment::enrolled()`. Beide nötig, einzeln gegengeprüft;
  PHPUnit `participant_source_test.php`.
- **Weitere Beobachtung, nicht geändert:** `enrollment::enrolled()` fragt die
  gewählte Teilnehmerquelle nicht ab (Hostkurs-Mitglieder werden für jede
  Einbettung abonniert). Im Kommentarentwurf zur Entscheidung gestellt.
- **H:** Quelle 3, Kombination 1+2, Negativfall „nur späterer Knotenkurs",
  Host-Modi hidden und none. Seed: Hostkurse 3–6, Knotenkurs-Lernende.
- **Eigene Fehlvermutung:** Den plötzlich auftauchenden Rundgang hielt ich
  zunächst für eine Folge der Zeitreise der T-Kette; die Zeitstempel zeigten
  das Gegenteil (der Rundgang war schon immer fällig, der Klick gewann bisher
  nur das Rennen). Rundgänge in den Seeds abgeschaltet.

**Prüfstand:** PHPUnit mod 14, enrol 53, local 316; E2E enrol 6, mod 7,
local 18.

## Teil 14 — Kette C

- **C1** (1 von 1, 1 von 2, 2 von 2) und **C3** (manuelle Freigabe und
  Rücknahme) im Browser grün, mit echtem Kursabschluss bzw. echter Freigabe
  durch die Lehrkraft. Mechanik vorher auf der Kommandozeile gegengeprüft.
- **C2 offen:** Das Produkt kennt keine Zugangsbedingung „in einem
  Referenzkurs eingeschrieben". `specific_course` bedeutet „bestimmter Knoten
  dieses Pfads abgeschlossen"; der dritte Planschritt (Einschreibung entfernt
  → wieder gesperrt) ist damit nicht abbildbar, weil ein Kursabschluss nicht
  zurückgenommen wird. Rückfrage an den Auftraggeber.
- **Fehler unterwegs, alle in der Testtechnik:** fehlendes `introeditor` bei
  `create_module()`; zwei falsch vermutete Sprachschlüssel; doppelter
  Aktivitätslink (Kursindex); zu früher Klick auf „Mark as done".

### Teil 14, Nachtrag — Autocomplete im Aktivitätsformular

- **Befund:** Die H-Kombinationskette scheiterte im Gesamtlauf, einzeln nie.
  Die Hilfe `chooseInAutocomplete()` suchte die Option seitenweit; Moodle
  stellt auch bereits gewählte Einträge als Optionen dar.
- **Lösung:** Option nur in der Vorschlagsliste des eigenen Feldes
  (`aria-controls`), danach das Feld mit `Tab` verlassen — das schließt die
  Liste, sonst schluckt sie den nächsten Klick (z. B. „Speichern").
- **Eigener Fehlgriff dazwischen:** Leeren des Feldes nach der Auswahl.
  Beim Einzel-Autocomplete „Chosen Learning Path" ist der Text im Feld die
  Auswahl selbst; das Pflichtfeld blieb leer, sechs Ketten scheiterten.
  Am Bildschirmfoto erkannt, zurückgenommen.
- **Werkzeug, dritte Wiederholung:** `pgrep`/`pkill` mit Muster traf die
  eigene Befehlszeile. Prozesse nur noch über Prozessnummer oder Port beenden.

**Prüfstand:** E2E enrol 6, mod 7 (zweimal hintereinander), local 22.

## Teil 15 — T4/T5

- **T4/T5** in einem Test: zuerst alle Schritte auf echter Zeit (nichts,
  nur Vorgänger), dann ein Sprung ins Fenster (nur Zeit, beides). Vorher die
  Wahrheitstafeln beider Pfade auf der Kommandozeile gegengeprüft, alle acht
  Zeilen wie im Plan.
- **Wechselwirkung gefunden und behoben:** Mit gemeinsamem Fenster öffnete
  der Sprung von T4/T5 den Kurs von T1/T2 vorzeitig. Eigenes Fenster 2031 für
  T4/T5.
- **Offen:** die Feedbacktexte („genau die fehlende Voraussetzung") — gehören
  zur Kette F.


## Teil 16 — C2 gestrichen

Entscheidung des Auftraggebers: **C2 (Kurszugehörigkeit) aus dem Plan
streichen.** Im Testplan durch einen Vermerk mit Begründung ersetzt, in
`e2e-suite.md` als gestrichen geführt. Kette C ist damit vollständig (C1, C3).

## Teil 17 — Kette A, Fortschrittsbalken, Stand D

- **A** in vier Varianten grün (nur T02, nur T03, beide, keine): T04-Status
  in der Pfadansicht, realer Zugriff, Routenstand und Prozentwert in der
  Liste der Aktivität. Vorher auf der Kommandozeile gegengeprüft.
- **Produktfehler (Barrierefreiheit, #575-Umfeld):** `aria-valuenow` des
  Fortschrittsbalkens war nicht gebunden. Behoben, Jest mit Gegenprobe.
- **D zurückgestellt:** `local_catquiz` ist in der Testumgebung nicht
  installiert. Die Abschlussbedingung `modquiz` (Moodle-Test, Schwelle in
  Prozent, erreicht bei ≥) erlaubt die Grenzwertprüfung x−ε / x / x+ε, aber
  keine CAT-Diagnose. Rückfrage an den Auftraggeber.

## Teil 18 — local_catquiz in der Testumgebung (Kette D, Vorbereitung)

- **Entscheidung des Auftraggebers:** `local_catquiz` samt Abhängigkeiten
  installieren; für Moodle 4.5 die Legacy-Zweige.
- **Zweige:** Im Wunderbyte-Upstream gibt es keinen Legacy-Zweig. In den
  Forks `ralferlebach/*` heißt er **`ALiSe-v-1.2.0-legacy`** (local_catquiz,
  mod_adaptivequiz, adaptivequizcatmodel_catquiz). `local_wunderbyte_table`
  von Wunderbyte `main` (verlangt 4.5).
- **Abhängigkeiten:** local_catquiz → local_wunderbyte_table,
  mod_adaptivequiz, adaptivequizcatmodel_catquiz (Subplugin unter
  `mod/adaptivequiz/catmodel/catquiz`). Dazu zwei **Git-Submodule** in
  local_catquiz: `catquizcentralhub/host` und `/client`.
- **Eigener Fehler:** Erster Klon ohne Submodule → leere Verzeichnisse →
  Moodles Plugin-Manager scheitert an fehlender `version.php`, 155 Fehler in
  der local_adele-Suite. Neu geklont mit `--recurse-submodules`.
- **Bestehensregel der ADELE-Bedingung `catquiz`:** Fähigkeitswert der Skala
  `>=` Schwelle (`catquiz.php`, `check_scale`). Für D: x−ε scheitert, x und
  x+ε bestehen.
- **Signale:** ADELE liest `local_catquiz_attempts` (Instanz, Kurs, Person,
  JSON mit `personabilities`). Muster für künstliche Signale steht bereits in
  `tests/usecases/uc09_catquiz_completion_test.php` — auch local_catquiz
  selbst legt Testversuche direkt in der Datenbank an.

## Teil 19 — catquiz in CI, nur Moodle 4.5, zwei Fehler in der catquiz-Bedingung

- **Entscheidungen des Auftraggebers:** catquiz-Abhängigkeiten vorerst aus
  den Zweigen `ralferlebach/*` `ALiSe-v-1.2.0-legacy`; `local_adele` ist nur
  für Moodle 4.5 freigegeben.
- **Nur 4.5:** Die 5.x-Jobs der Plugin-CI aller drei Plugins entfernt;
  `$plugin->supported = [405, 405]` in allen drei (enrol und mod brauchen
  local_adele). Mit einer Zeile wieder zu öffnen.
- **catquiz in CI:** Plugin-CI von local_adele installiert mod_adaptivequiz,
  adaptivequizcatmodel_catquiz und local_catquiz und zieht danach die
  Submodule nach (`add-plugin` klont ohne). Der frühere, abgeschaltete Block
  scheiterte an genau diesen fehlenden Submodulen. Die E2E-Workflows klonen
  dieselben vier Plugins mit `--recurse-submodules`; der Block wurde lokal
  echt ausgeführt.
- **Fehler in ADELE (catquiz.php), gefunden durch die nun laufenden Tests:**
  1. Der Anteil richtiger Antworten wurde für jeden Versuch berechnet, auch
     ohne Prozentschwelle; catquiz wirft dabei für Versuche ohne
     Fortschrittsdatensatz einen `TypeError`, der die ganze Neuberechnung
     des Pfads abbrach. Jetzt nur bei Bedarf und abgefangen (`debugging`).
  2. `$bestattemptpescale` war nicht initialisiert (Fehler, sobald kein
     Versuch die Schwelle erreicht) und wurde gegen ein Array statt gegen
     dessen Skalenwert verglichen (bessere spätere Versuche nie übernommen).
- **D, PHPUnit:** Grenzwerttest x−ε / x / x+ε in `uc09` (Regel
  Fähigkeit ≥ Schwelle). `catquiz_test::test_get_completion_status_is_true`
  (Prozentschwelle) als unvollständig markiert — braucht Fortschrittssignale
  über die catquiz-API.

**Prüfstand:** PHPUnit local 319 (1 übersprungen, 1 unvollständig), enrol 53,
mod 14.
