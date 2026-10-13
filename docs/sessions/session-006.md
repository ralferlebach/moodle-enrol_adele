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

## Teil 20 — Kette D, zwei weitere Fehler in der catquiz-Bedingung

- **Korrektur (nachträglich):** Hier stand, Teil 19 und `2026100607` stammten
  aus einem „parallelen Durchlauf". Das war eine unbelegte Vermutung, als
  Tatsache formuliert. Wahrscheinlicher: eigene Arbeitsstände aus einem
  Arbeitsfenster, das nicht ordentlich abgeschlossen und dokumentiert wurde.
  Inhaltlich wurde nichts überschrieben; Teil 20 baut auf Teil 19 auf.
- **Fehler 3 (Modus `all_quiz_global`, Voreinstellung der Seite):** Die
  Bedingung verlangte ein Prozentergebnis, auch wenn keine Prozentschwelle
  gesetzt war — eine reine Fähigkeitsschwelle war in diesem Modus **nie**
  erfüllbar. `uc09` lief nur im Modus `single_quiz` und verdeckte das.
  Jetzt: fehlende Art = leere Menge, und mindestens eine Schwelle muss
  konfiguriert sein.
- **Fehler 4 (gleicher Modus):** Der Vergleich mit der globalen Schwelle war
  strikt (`<`), zwei Zeilen über der Regel `>=` — am Grenzwert selbst
  scheiterte ein Versuch. Jetzt `<=`.
- **Nachweis:** `uc09`-Grenzwerttest in beiden Modi (6 Fälle); alter Code
  rot in genau „all_quiz_global: x" und „x + ε", neuer grün. E2E-Kette D mit
  echten Signalen grün.
- **Gefunden wurde Fehler 3 erst durch den Browserlauf:** Signale kamen an,
  der Knoten blieb offen; die Bedingung direkt aufgerufen, die Einstellung der
  Seite gelesen.

## Teil 21 — Kette M

- **M1/M2** in einem Test auf eigenem Pfad „E2E Laufende Änderung": Knoten 2
  zunächst ohne Bedingung (Lernende in T02) → Autorin fügt „Manual access"
  per Ziehen in den Bedingungseditor hinzu → Zugriff weg → Autorin entfernt
  die Bedingung → Zugriff zurück. Auf Anhieb grün.
- **Technik:** Eine leere Bedingungsfläche nimmt die erste Bedingung dort an,
  wo sie losgelassen wird (`RestrictionFlow.onDrop`, `nodes.length == 0`);
  `dragTo` auf die Fläche genügt. Entfernen wie in V2 (Papierkorb, zweimal
  speichern).


## Arbeitsregel ab Teil 22

Jedes Arbeitspaket wird abgeschlossen, bevor das nächste beginnt: Prüfung,
Protokolleintrag, Lieferung. Reicht das verbleibende Arbeitsfenster dafür
nicht, beginnt kein neues Paket; stattdessen wird der Zwischenstand als
„unterbrochen" mit offenen Schritten protokolliert und geliefert. Ursachen
werden nur als Tatsache genannt, wenn sie belegt sind.

## Teil 22 — M vervollständigt

- **Auf Nachfrage des Auftraggebers:** M war geliefert, deckte aber nur
  Knotenstatus und realen Zugriff ab; Feedback, Prozent und Einschreibung
  waren auf F/L verschoben — das war Aufschieben, kein Abschluss. Jetzt in
  jedem Zustand geprüft: Knotenstatus, Meldung auf der Infokarte des Knotens
  („Freely accessible" → Bedingung „manual" → wieder „Freely accessible"),
  Routenfortschritt (1/3, von beiden Änderungen unberührt), Einschreibung in
  T02 auf der Teilnehmerseite (Active → Suspended → Active), realer Zugriff.
  Zweimal grün.
- **Befund in den eigenen Fixtures (für Kette F):** Die Seed-Funktion
  `local_adele_fixture_single_condition()` kopiert den Feedbackknoten samt
  Autorentexten aus einer Bedingung des Referenzpfads. Bei C3 zeigt die
  Infokarte deshalb „Vorgängernode „" abschliessen und bis" statt eines zur
  manuellen Freigabe passenden Texts; nur der berechnete Teil stimmt. Betrifft
  alle so gebauten Fixture-Knoten. Wird mit Kette F behoben.
- **Zurückgenommene Vermutung:** Die „Go to course"-Schaltfläche schien
  unbeschriftet; sie trägt ihren Namen über `title`. Keine Lücke.

## Teil 23 — Kette L1, Zuordnung L2/L3/L4

- **L1** grün, zweimal: linearer Pfad „E2E Linear vier Schritte" (T01–T04),
  echter Abschluss je Schritt, in jedem Zustand Routenstand, Prozentwert,
  Beschriftung und Balkenbreite.
- **Eigener Fehler unterwegs:** Die erwartete Beschriftung bei 0 % stammte aus
  dem Jest-Test („No progress yet", eine Testzeichenkette); der echte String
  lautet „No progress". Erwartung jetzt aus dem Sprachpaket.
- **Zuordnung:** L2 ist durch Kette A erfüllt. L3 (manueller Abschluss)
  entspricht O1, L4 (Master-Abschluss) entspricht O3 — jeweils gemeinsam
  umzusetzen, damit nichts doppelt gebaut wird oder zwischen den Ketten
  verloren geht.

## Teil 24 — L3/O1 manueller Abschluss

- **L3 und O1 gemeinsam**, eigener Pfad „E2E Manueller Abschluss" (Knoten 1
  schließt nur über „Finish node manually" ab): regulär → von der Lehrkraft
  abgeschlossen → zurückgenommen. In jedem Zustand Knotenstatus, Folgeknoten,
  Routenstand und Prozent, realer Zugriff auf T02 und Einschreibung (nicht
  vorhanden → Active → Suspended). Zweimal grün.
- **Testtechnik:** Die Infokarte öffnet nur ein Klick auf das Symbol; die
  Zeichenfläche schneidet es teils ab. Der Test löst das Klickereignis direkt
  aus (`dispatchEvent`), gleicher Vue-Handler.
- **Befund 1 (Barrierefreiheit, Produkt):** Die Infokarte eines Knotens ist
  per Tastatur nicht erreichbar. Der Container ist fokussierbar
  (`tabindex="0"`), `handleFocus` hebt aber nur die z-Ebene an; öffnen geht
  nur per Mausklick (`NodeInformation.vue`). Nicht geändert, für die
  Entscheidung festgehalten.
- **Befund 2 (Meldung):** Die Infokarte von Knoten 1 lautet in allen drei
  Zuständen „manual completion recorded" — auch ohne gesetzten Abschluss.
  Ob das bei editorerstellten Pfaden ebenso ist oder aus der Fixture stammt,
  klärt Kette F; bis dahin prüft L3/O1 den Wortlaut bewusst nicht.
- **Eigene Umwege unterwegs:** großes Fenster und Fokus als Ersatz für den
  Klick ausprobiert, beides wirkungslos; erst der Blick in die Komponente
  zeigte, dass nur der Klick die Karte öffnet.

## Teil 25 — Arbeitsumgebung zurückgesetzt, Neuaufbau

- **Befund (2026-10-06, 23:12):** Der Container war zurückgesetzt:
  Arbeitskopien, Moodle, Datenbank, catquiz, phpcs, Playwright und alle
  Hilfsskripte in `/tmp` fehlten. Erhalten waren nur die Lieferungen
  `2026100612` und die Präsentation.
- **Wiederherstellung:** Arbeitskopien aus den vollständigen Archiven
  `2026100612` (inkl. `.github/`, `docs/`). Moodle neu geklont — jetzt
  **4.5.15 (Build 20261005)** statt 4.5.14+. catquiz-Ökosystem aus
  `ralferlebach/*` Zweig `ALiSe-v-1.2.0-legacy` mit Submodulen,
  `local_wunderbyte_table` von Wunderbyte `main`. `faketime`, `php8.3-soap`,
  Locale `en_AU.UTF-8` nachinstalliert.
- **Stolperstein Composer:** Archiv-Downloads von GitHub scheitern hinter dem
  Proxy („Could not authenticate against github.com"). Abhilfe:
  `composer install --prefer-source --no-plugins` (Git statt Archiv), Standards
  für phpcs von Hand registrieren.
- **Stolperstein Datenbank:** PostgreSQL läuft nach einer Pause der Umgebung
  nicht weiter; vor jedem Schritt `service postgresql start`.
- **Basismessung vor Weiterarbeit:** phpcs 0/0 in allen drei Plugins;
  E2E-Gesamtlauf auf 4.5.15 vor jeder neuen Kette, damit Abweichungen durch
  die neue Moodle-Version nicht neuen Tests zugeschrieben werden.

## Teil 26 — L4/O3 Master-Abschluss

- **Basismessung nach dem Neuaufbau:** E2E enrol 6, mod 7, local 33 grün auf
  Moodle 4.5.15; PHPUnit mod 14, enrol 53 grün. Composer für Moodle brauchte
  einen zweiten Anlauf: „fatal: cannot repack to clean up" beim automatischen
  Aufräumen von Git; mit `git config --global gc.auto 0` und ohne parallelen
  E2E-Lauf vollständig.
- **L4 und O3 gemeinsam**, eigener Pfad „E2E Master-Abschluss" (linear, die
  Lernende schließt nichts selbst ab): regulär → Master-Abschluss an Knoten 1
  (abgeschlossen, Knoten 2 frei, 1/3, T02 offen, Einschreibung Active) → an
  allen drei Knoten (3/3, 100 %) → überall entfernt (0/3, T02 zu,
  Einschreibung Suspended). Zweimal grün, auf Anhieb.
- **Befund (mod_adele):** Die Abschlussregel „Lernpfad abgeschlossen"
  (`completionlearningpathfinished`) wird angeboten, aber nie ausgewertet: keine
  `custom_completion`-Klasse, kein `update_state()` beim Pfadende; Moodle 4.5
  überspringt die Regel stillschweigend (`completionlib.php`, belegt im Core).
  Issue-Entwurf `issues/mod_adele-issue-completion-rule-learningpath-finished.md`.
  Der Plan-Punkt „mod_adele-Completion, falls gekoppelt" (O3) ist damit nicht
  prüfbar; nicht ungefragt implementiert.

## Teil 27 — Zufällig scheiternde Rollen-Ketten: Ursache im Produkt

- **Befund im Gesamtlauf:** R4a, R5, R6 rot, im Basislauf eine Stunde vorher
  grün. Gemeinsames Muster: Umbenennen und Speichern, danach zeigt die
  Übersicht den alten Namen.
- **Folgeschaden:** Der Seed erkannte Fixture-Pfade nur am Namen; ein Lauf,
  der beim Zurückbenennen scheiterte, hinterließ einen Pfad mit Suffix samt
  übertragenem Eigentum, und der Seed legte daneben einen Doppelgänger an.
  Jetzt dreistufig: gemerkte ID (`$CFG->dataroot/local_adele_e2e_fixturemap.json`),
  exakter Name, Name mit Suffix; der gefundene Pfad wird vollständig
  zurückgesetzt.
- **Erster Erklärungsversuch, unzureichend:** Seitenwechsel bricht das
  Speichern ab. Wartebedingung „Übersicht sichtbar" eingebaut — half nicht.
  Danach `saveLearningPath()`: wartet auf die Serverantwort und verlangt
  `error: false`. Bestand ebenfalls — der Server meldete Erfolg mit dem
  ALTEN Namen.
- **Ursache, belegt:** Der Editor lädt den Pfad beim Öffnen zweimal
  (`LearningPathList.editLearningpath` und `LearningpathsEdit.showForm`); eine
  verspätete zweite Antwort überschreibt den eingetippten Titel. Mit
  künstlich verzögerter zweiter Antwort deterministisch: erwartet „Neuer Name",
  erhalten „Linear A2". Produktfehler mit stillem Datenverlust.
- **Umgang:** Issue-Entwurf `issues/local_adele-issue-editor-title-overwritten.md`;
  Test `editor-title-overwrite.spec.ts` als `test.fixme` (Nachweis: als
  regulärer Test rot). Die Rollen- und Editor-Ketten warten vor dem Eintippen
  auf das Ende des Ladens (`waitForEditorLoaded`), damit der Fehler ihre
  eigentliche Prüfung nicht zufällig rot macht. Produkt nicht geändert.

**Offene Beobachtung:** Im Gesamtlauf nach Teil 27 scheiterte einmal die
H2-Kombinationskette (`mod`): Die Eingangskurs-Lernende erreichte den Hostkurs
nicht („enrolment-page"). Nicht reproduzierbar: Basislauf zweimal grün,
danach `mod` allein zweimal grün. Ursache nicht belegt; die Kette wird in den
folgenden Gesamtläufen beobachtet.

**Prüfstand `2026100613`:** PHPUnit local 322 (1 übersprungen, 1
unvollständig), enrol 53, mod 14; E2E enrol 6, mod 7 (zweimal allein; einmal
im Gesamtlauf rot, siehe oben), local 34 + 1 `fixme`; Rollen-/Editor-/M-Ketten
zweimal 17/17; phpcs 0/0.

## Teil 28 — O2 Master-Zugang

- **O2** auf zwei eigenen Pfaden. „E2E Master-Zugang" (linear): Varianten 1–4
  des Plans in der Reihenfolge, die jede Wirkung zeigt — Bedingung falsch +
  Master aus (gesperrt), + Master an (zugänglich, **nicht abgeschlossen**,
  0/3, Einschreibung Active), Master aus (gesperrt, Suspended), Master an und
  T01 abgeschlossen (Knoten 1 abgeschlossen, Knoten 2 nur zugänglich, 1/3),
  Master aus (bleibt zugänglich aus eigener Bedingung). „E2E Master-Zugang
  Zeit", Variante 5: Knoten 1 nur im Fenster 2030 offen; Master-Zugang öffnet
  ihn ohne Zeitsprung, Ausschalten schließt ihn wieder. Zweimal grün, auf
  Anhieb. Zentrale Aussage „Master-Zugang ist kein Abschluss" in jedem
  Schritt über Knotenstatus und Fortschritt geprüft.
- **Gemeinsame Hilfen:** `setMasterCheckbox()` und `enrolmentStatus()` in
  `support/fixtures.ts`; L4/O3 nutzt sie jetzt ebenfalls (erneut grün).

**Prüfstand `2026100700`:** E2E enrol 6, mod 7 (H2-Kombination unter
Beobachtung, diesmal grün), local 36 + 1 `fixme`; phpcs 0/0.

## Teil 29 — O4 beide Master-Haken

- **Erst gemessen, dann festgeschrieben** (Probelauf, Knoten 2 eines linearen
  Pfads, Lernende schließt nichts ab):

  | Zugang | Abschluss | Knoten 1/2/3 | Route |
  |---|---|---|---|
  | 0 | 0 | offen / gesperrt / gesperrt | 0/3 |
  | 0 | 1 | offen / abgeschlossen / offen | 1/3 |
  | 1 | 1 | offen / abgeschlossen / offen | 1/3 |
  | 1 | 0 | offen / zugänglich / gesperrt | 0/3 |

- **Offene Frage an den Auftraggeber (Zeile 0/1):** Der Plan überlässt sie der
  Implementierung. Ein Master-Abschluss an einem gesperrten Knoten schließt ihn
  ab und öffnet den Folgeknoten, ohne Master-Zugang; T02 und T03 werden offen.
  Ob das gewollt ist oder Abschluss den Zugang voraussetzen soll, ist nicht
  entschieden. Die Kette hält das Verhalten fest; bei anderer Entscheidung
  ändert sich nur diese Zeile.
- **O4** zweimal grün: alle Kombinationen mit Knotenstatus, Route und realem
  Zugriff auf T02/T03.

**Beobachtung H2-Kombination aufgeklärt:** Die gespeicherte Aktivität hatte
nur Quelle 1 — die zweite Auswahl („Startknoten") kam nicht im Formular an.
Beide früheren Fehlerbilder (Lernende nicht im Hostkurs; Klick auf eine
unsichtbare Option) hatten diese Ursache. Die Auswahlhilfe prüft jetzt das
versteckte `<select>`, das Moodles Autocomplete füllt und das gesendet wird,
und wiederholt die Auswahl begrenzt. Betrifft Moodles Autocomplete und die
Testbedienung, nicht ADELE. Danach `mod` zweimal 7/7 (58 s statt 120 s).

**Einzelner Fehlschlag E2 (A ODER B) im Gesamtlauf:** Der Knoten enthielt
„Testkurs 03 + 02" statt „01 + 02" — beim ersten Ziehen wurde der falsche Kurs
gegriffen. Die Ziehhilfen greifen nach Bildschirmposition; baut sich die
Kursliste der Seitenleiste in dem Moment noch um, liegt dort der Nachbar.
`editor.ts`: Vor dem Greifen warten, bis Anzahl der Einträge und Position der
Quelle zweimal unverändert sind; nach dem Ablegen prüfen, dass genau dieser
Kurs im Pfad gelandet ist (bei der ODER-Zone im Zielknoten). Danach E1/E2
dreimal 5/5.

**Unterbrechung:** Ein Gesamtlauf wurde mitsamt Datenbank abgebrochen
(Exit 137, Umgebung pausiert); `local` wiederholt.

**Prüfstand `2026100701`:** E2E enrol 6, mod 7, local 37 + 1 `fixme`
(50 Tests, ein Gesamtlauf vollständig grün); phpcs 0/0.

## Teil 30 — Kette P Lernerdaten, drei Umgehungen im Webservice behoben

- **Fixture:** Pfad „E2E Lernerdaten" (linear T01 → T02 → T03) mit zwei
  Lernenden, im Kurs E2EP **zweifach** eingebunden: „E2E P own results"
  (`userlist` 2) und „E2E P all results" (`userlist` 1). Dazu Lehrkraft mit
  und ohne Bearbeitungsrecht, Kollaborator dieses Pfads (als Teilnehmer im
  Kurs), sowie im Kurs E2EPX — der nichts einbindet — ein Teilnehmer und eine
  Lehrkraft ohne Bearbeitungsrecht. Seed: `local_adele_fixture_embed()`,
  `local_adele_fixture_person()`; `local_adele_fixture_host_course()` nimmt
  die Ergebnis-Sichtbarkeit als Parameter.
- **Erst gemessen, dann festgeschrieben.** Probelauf über alle Personen:
  Ansicht beider Einbindungen, beide Webservices in jedem nennbaren Kontext,
  direkte URLs. Lernstand im Test real erzeugt: Alpha schließt T01 ab (1/3,
  33,33 %, Rang 2), Beta T01 und T02 (2/3, 66,67 %, Rang 1).
- **Befund (gemessen, Sicherheit/Datenschutz):** Beide Webservices prüften
  Kursrechte gegen den vom Client übergebenen Kontext, ohne dass der Kurs den
  Pfad einbinden musste:
  1. Teilnehmer eines fremden Kurses ohne Lernpfad erhielt über den Kontext
     seines Kurses die vollständige Liste (Namen, Fortschritt, Rang).
  2. Lernende Alpha erhielt trotz „Only own results" die vollständige Liste
     über den Kontext des Knotenkurses T01, in den ADELE sie selbst
     eingeschrieben hat (#569 umgangen).
  3. Lehrkraft eines fremden Kurses erhielt Liste und Betas Snapshot samt
     E-Mail-Adresse.
  Aus dem Code, nicht gemessen, aber durch PHPUnit belegt: Der Modulkontext
  einer anderen ADELE-Aktivität desselben Kurses (anderer Pfad, „all results")
  hob „Only own results" auf. Systemkontext führte zu `codingerror`.
- **Behebung in `local_adele`:** `learning_paths::is_embedded_in_course()`;
  Kursrechte (`teacheredit`, eingeschrieben) zählen in
  `get_lp_user_path_relations` und `get_lp_user_path_relation` nur in einem
  Kurs, der den Pfad einbindet. Pfadbezogene Rechte (`check_access()`)
  unverändert. `only_own_results()` nimmt die Einstellung einer Aktivität nur,
  wenn sie den angefragten Pfad zeigt. Kontext außerhalb eines Kurses: kein
  `codingerror` mehr. Issue-Entwurf:
  `issues/local_adele-issue-learner-data-context-not-bound-to-embedding.md`.
- **PHPUnit:** neu `tests/security/learner_data_embedding_test.php` (8 Tests;
  Gegenprobe mit abgeschalteter Prüfung: 4 schlagen fehl).
  `user_path_relation_access_test` bindet den Pfad jetzt im Kurs ein.
  `issue569_userlist_privacy_test::test_unembedded_path_keeps_current_behaviour`
  hielt die vollständige Liste für nicht eingebundene Pfade als gewollt fest —
  das ist Umgehung 2; ersetzt durch
  `test_unembedded_path_has_no_roster_for_participants` (Ablehnung).
- **Kette P** (`mod_adele/tests/e2e/tests/learner-data.spec.ts`, 4 Tests,
  seriell):
  - **P1** eigene Knotenstatus, eigene Zeile mit Prozent, Route, Rang; die
    andere Lernende steht nicht auf der Seite **und** in keiner Antwort von
    `lib/ajax/service.php`, die die Seite beim Laden erhält.
  - **P2** „all results": beide Zeilen mit korrekten Werten; Lehrkraft sieht
    beide in beiden Einbindungen mit Link und öffnet Betas Detailansicht
    (Knotenstatus abgeschlossen / abgeschlossen / zugänglich).
  - **P3** wie implementiert:

    | Rolle | „own results" | „all results" | Liste vom Server | Betas Snapshot |
    |---|---|---|---|---|
    | Administrator | Lehrkraftansicht, beide | beide | beide | ja |
    | Lehrkraft mit Bearbeitungsrecht | Lehrkraftansicht, beide | beide | beide | ja |
    | Lehrkraft ohne Bearbeitungsrecht | Teilnehmeransicht, nur eigene Zeile | beide | beide | ja |
    | Kollaborator (als Teilnehmer im Kurs) | Teilnehmeransicht, nur eigene Zeile | beide | beide | ja |
    | AdeLe-Manager (in keinem Kurs) | kein Zugang zur Seite | kein Zugang | beide | ja |
    | AdeLe-Assistent (in keinem Kurs) | kein Zugang zur Seite | kein Zugang | beide | ja |
    | Teilnehmer | eigene Zeile | beide | eigene | nein |
    | Teilnehmer eines fremden Kurses | kein Zugang | kein Zugang | nein | nein |
    | Lehrkraft eines fremden Kurses | kein Zugang | kein Zugang | nein | nein |

  - **P4** als Teilnehmer: Liste über Einbindung, Kurs, gefälschte `userid`
    → nur eigene Zeile; über Knotenkurs, fremden Kurs, Systemkontext →
    abgelehnt. Betas Snapshot in jedem Kontext abgelehnt, der eigene bleibt
    lesbar. Direkte URL der Detailansicht zeigt den eigenen Pfad; der Editor
    lässt Teilnehmer nicht ein. Personen des fremden Kurses: abgelehnt.
- **Gemeinsame Hilfen** in `support/fixtures.ts`: `participantList()`,
  `callService()`.
- **Offene Fragen an den Auftraggeber** (im Issue-Entwurf unter „Nicht Teil
  dieser Behebung"):
  1. `check_access()` gibt jedem, der irgendeinen Pfad bearbeiten darf, die
     Lernerdaten **aller** Pfade — auch Assistenten und Kollaboratoren, die
     sonst auf ihre Pfade begrenzt sind. Begrenzen?
  2. Lehrkräfte werden durch die Einschreibung in den einbindenden Kurs selbst
     Lernende des Pfads und stehen mit 0 % und Rang in der Liste. Gewollt?
  3. Lehrkraft ohne Bearbeitungsrecht: serverseitig volle Leserechte, in der
     Oberfläche Teilnehmeransicht. Gewollt?
  4. Die Liste ist pfadweit: Bei Einbindung in mehreren Kursen zeigt sie auch
     die Lernenden der anderen Kurse.
  Die Kette prüft nur die beiden Lernenden und lässt die Zeilen des Personals
  unberührt; P3 hält 1 und 3 fest, wie sie sind.
- P dreimal grün (je 4/4, rund 60 s), der erste Lauf auf Anhieb.

**Prüfstand `2026100702`:** E2E enrol 6, mod 11, local 37 + 1 `fixme`
(54 Tests, ein Gesamtlauf vollständig grün); PHPUnit local 330 (1 übersprungen,
1 unvollständig, wie zuvor), enrol 53, mod 14; phpcs 0/0.

**Als Nächstes:** Kette Z (Gesamtabschluss) hängt an der wirkungslosen
Abschlussregel von `mod_adele` (Issue-Entwurf) — vor Z ist zu entscheiden, ob
die Regel behoben wird. Danach F (Feedbacktexte), Reste R3/R5, E2-Klammern.

## Entscheidungen des Auftraggebers (2026-10-07)

- **O4, Zeile 0/1:** Der Master-Abschluss soll immer wirken, unabhängig von
  Voraussetzungen. Das gemessene Verhalten (gesperrter Knoten wird
  abgeschlossen, Nachfolger öffnet) ist damit gewollt; die Kette bleibt, der
  Vorbehalt in `master-both.spec.ts` und in der Suite-Tabelle ist entfernt.
- **Produktfehler Editor-Titel und Abschlussregel `mod_adele`:** erst beheben,
  nachdem die Issues angelegt sind; der Auftraggeber meldet sich.
  **Abweichung, offen benannt:** Die Abschlussregel war zu diesem Zeitpunkt
  bereits umgesetzt (Teil 31, begonnen auf „Weiter" nach der Rückfrage zu Z).
  Sie ist in den Archiven ab `2026100703` enthalten; der Issue-Entwurf trägt
  die Umsetzung. Der Editor-Fehler ist unangetastet.
- **Zu Kette P:** (1) Lernerdaten fremder Pfade für Assistenten und
  Kollaboratoren: wird behoben. (2) Lehrkräfte als Lernende in der Liste:
  vorerst akzeptabel, langfristig ausschließen — Issue. (3) Lehrkraft ohne
  Bearbeitungsrecht soll alles sehen, aber nicht bearbeiten — Issue. (4) Liste
  auf die Schnittmenge Kurseinschreibung × Pfadeinschreibung beschränken —
  Issue. Umsetzung in Teil 32.

## Teil 31 — Abschlussregel in `mod_adele`, Kette Z

- **Entscheidung für Z:** Alle vier Stories des Plans verlangen den
  Aktivitätsabschluss. Die Regel `completionlearningpathfinished` war
  wirkungslos (Issue-Entwurf aus Teil 26); ohne sie wäre Z nicht prüfbar
  gewesen. Deshalb umgesetzt.
- **`mod_adele`:**
  - `classes/completion/custom_completion.php` — über diese Klasse wertet
    Moodle eigene Regeln aus; Beschreibung „Finish the learning path" /
    „Lernpfad abschließen" (`completiondetail:learningpathfinished`).
  - `classes/local/path_completion.php` — `is_finished()`: gespeicherter
    Lernerpfad, beste Route vollständig (`getnodeprogress()`, 100 %);
    `update_activities()`: `update_state(COMPLETION_UNKNOWN)` für jede
    Einbindung des Pfads mit der Regel, also in beide Richtungen.
  - `db/events.php` — Beobachter von `user_path_updated` mit Priorität −100:
    `local_adele` speichert den Pfad in seinem Beobachter desselben
    Ereignisses (Priorität 0), `mod_adele` liest danach.
- **PHPUnit `path_completion_test`** (4 Tests) über die echte Kette. Gegenprobe
  mit Priorität +100: Test schlägt fehl — die Reihenfolge ist festgehalten.
- **Fixture:** vier Pfade mit je eigener Lernender und eigenem Hostkurs
  (E2EZ1–E2EZ4), Aktivität mit Abschlussverfolgung und Regel. Seed:
  `local_adele_fixture_embed()` und `local_adele_fixture_host_course()` mit
  Parameter für die Regel. „E2E Abschluss manuell": alle drei Knoten nur
  manuell abschließbar.
- **Probelauf am lebenden System** (Master-Abschluss auf allen Knoten): Kursseite
  der Lernenden „To do" → „Done", Bedingung „Finish the learning path";
  Abschlussbericht „Not completed" → „Completed".
- **Kette Z** (`local_adele/tests/e2e/tests/final-completion.spec.ts`, 4 Tests):
  - **Z1** regulär: 0/3 → 2/3 (66,67 %, Aktivität offen) → 3/3 (100 %,
    abgeschlossen).
  - **Z2** alternative Route (Raute): T01, T02 abgeschlossen, T03 nie besucht —
    vor T04 2/3 und offen, nach T04 3/3 = 100 % und abgeschlossen; T03 bleibt
    „zugänglich".
  - **Z3** manueller Abschluss auf zwei Knoten (offen), dem dritten
    (abgeschlossen), Rücknahme (wieder offen).
  - **Z4** Master-Abschluss, gleiche Folge.
  Je Zustand drei Ebenen: Pfadansicht der Lernenden (Knotenstatus, Route,
  Prozent), Aktivitätsabschluss auf ihrer Kursseite, Abschlussbericht der
  Lehrkraft.
- **Gemeinsame Hilfen:** `activityCompletion()`, `completionReport()`. Erster
  Lauf: Z1/Z2 grün, Z3/Z4 scheiterten an der Hilfe — der Bericht nennt nach
  einer Rücknahme „Not completed" **mit Datum**; Hilfe normalisiert das jetzt.
  Danach zweimal 4/4.
- **Abhängigkeit, bewusst:** Die Lösung verlässt sich darauf, dass
  `local_adele` synchron im Beobachter speichert. Ein eigenes Ereignis
  „Lernerpfad gespeichert" wäre die sauberere Schnittstelle (im Issue-Entwurf
  vermerkt).

**Prüfstand `2026100703`:** E2E enrol 6, mod 11, local 41 + 1 `fixme`
(58 Tests, ein Gesamtlauf vollständig grün; O4 nach der Umformulierung einzeln
wiederholt, grün); PHPUnit local 330 (1 übersprungen, 1 unvollständig, wie
zuvor), enrol 53, mod 18; phpcs 0/0.

## Teil 32 — Folgepaket P: Pfadrechte, Schreibdienst, drei Issue-Entwürfe

- **Entscheidung 1 umgesetzt — Lernerdaten nur für den eigenen Pfad:**
  `learning_paths::has_path_authority()` (Administrator, `canmanage` im
  System, Editor **dieses** Pfads) ersetzt `check_access()` in beiden
  Lesediensten. Gemessen vorher: AdeLe-Assistent (Editor nur von „Linear A2",
  in keinem Kurs) erhielt Liste und Betas Snapshot; jetzt `nopermissions`.
- **Weiterer Befund (gemessen), gleiche Ursache wie Teil 30, schreibend:**
  `local_adele_save_user_path_relation` prüfte Kursrechte ebenfalls gegen den
  übergebenen Kontext. Eine Lehrkraft ohne Bearbeitungsrecht eines **fremden**
  Kurses setzte über den Kontext ihres Kurses den Master-Abschluss auf Betas
  Knoten 3 — Antwort `success: true`, Knoten abgeschlossen. Behoben: Kursrechte
  zählen nur im einbindenden Kurs. Dabei aufgefallen und mitbehoben: Der
  geschriebene Pfad steht im Payload (`route.learningpathId`), geprüft wurde
  der Parameter — beide müssen jetzt übereinstimmen.
- **Mitgemessen, nicht geändert (Entscheidung 3, Issue):** Die Lehrkraft ohne
  Bearbeitungsrecht des einbindenden Kurses kann über den Dienst speichern.
  Kollaborator des Pfads (als Teilnehmer im Kurs) und Assistent können es
  nicht.
- **PHPUnit:** `learner_data_embedding_test` 14 Tests; Gegenproben mit alter
  Logik (Pfadrecht = `check_access()`; Speichern ohne Einbindungsprüfung)
  schlagen je mit genau dem betroffenen Test fehl.
- **Kette P:** P3 „ADELE assistant (editor of another path)" abgelehnt; P4 neuer
  Schritt: Teilnehmer, Kollaborator, Assistent, Teilnehmer und Lehrkraft eines
  fremden Kurses senden die Änderung, die die Pfadansicht senden würde
  (Master-Abschluss auf Betas Knoten 3, aus Betas echtem Snapshot gebaut) —
  alle abgelehnt, Betas Stand unverändert.
- **Issue-Entwürfe** zu den Entscheidungen 2–4:
  `local_adele-issue-staff-subscribed-as-learners.md`,
  `mod_adele-issue-read-only-teacher-view.md` (enthält den Nebenbefund:
  `local/adele:canmanage` und `:assist` tragen den Archetyp `teacher`, also
  Lehrkraft **ohne** Bearbeitungsrecht),
  `local_adele-issue-roster-not-limited-to-course.md`. Der Entwurf zu Teil 30
  ist um den zweiten Teil ergänzt.
- **Testumgebung, Stolperstein:** Belegt: Nach dem Gesamtlauf zeigte der
  Webserver „Call to undefined method … has_path_authority()", obwohl die
  Datei die Methode enthielt (CLI fand sie); nach Neustart des Servers war der
  Fehler weg. **Vermutung, nicht belegt:** Der Server läuft unter libfaketime;
  die Zeit-Kette springt nach 2031 und zurück, und OPcache prüft Dateien erst
  nach Ablauf von `revalidate_freq` seiner Uhr wieder. Der lokale Server
  startet jetzt vorsorglich mit `opcache.revalidate_freq=0`. Betrifft nur das
  Ändern von Code zwischen Läufen in der lokalen Umgebung, nicht CI.

**Prüfstand `2026100704`:** E2E enrol 6, mod 11, local 41 + 1 `fixme`
(58 Tests, ein Gesamtlauf vollständig grün); PHPUnit local 336 (1 übersprungen,
1 unvollständig, wie zuvor), enrol 53, mod 18; phpcs 0/0.

## Abgleich Issue-Entwürfe ↔ GitHub (2026-10-07)

Geprüft über die GitHub-API (`local_adele`, `enrol_adele`) und die einzelnen
Issue-Seiten (`mod_adele`; die API ist für dieses Repository aus der
Arbeitsumgebung nicht erreichbar). Entwürfe dieser Session:

| Entwurf | Issue |
|---|---|
| `local_adele-issue-dropzones-only-during-drag.md` | local_adele #580 |
| `local_adele-issue-timed-condition-timestamps.md` | local_adele #581 |
| `local_adele-issue-core-clock.md` | local_adele #582 |
| `local_adele-issue-min-moodle-45.md` | local_adele #583 |
| `local_adele-issue-parallel-node-criterion.md` | local_adele #584 |
| `local_adele-issue-editor-title-overwritten.md` | local_adele #585 |
| `local_adele-issue-learner-data-context-not-bound-to-embedding.md` | local_adele #586 — der Issue-Text ist der erste Stand; der zweite Teil (Pfadrechte, Schreibdienst) steht nur im Entwurf |
| `local_adele-issue-staff-subscribed-as-learners.md` | local_adele #587 (Text ab „Beobachtung" identisch) |
| `local_adele-issue-roster-not-limited-to-course.md` | local_adele #588 (Text ab „Beobachtung" identisch) |
| `enrol_adele-issue-core-clock.md` | enrol_adele #11 |
| `mod_adele-issue-core-clock.md` | mod_adele #36 |
| `mod_adele-issue-participant-source-cascade.md` | mod_adele #37 |
| `mod_adele-issue-completion-rule-learningpath-finished.md` | mod_adele #38 — der Issue-Text ist der Stand vom 2026-10-06, ohne den Abschnitt „Umsetzung" |
| `mod_adele-issue-read-only-teacher-view.md` | noch nicht zugeordnet (Abruf von mod_adele #39 steht aus) |

Bestehende Issues, die die Arbeit aus Teil 30/32 berühren: local_adele #517
(Teilnehmerlisten an Aktivität, Kurs und Lernpfad binden — die Bindung an die
Einbindung ist umgesetzt), local_adele #516 (autorisierte und verwendete IDs
vereinheitlichen — für die Lernpfad-ID des Schreibdienstes umgesetzt, die
Nutzer-ID und die strukturierte Schnittstelle nicht), local_adele #523
(eigene Capabilities für Lehrenden- und Teilnehmeransicht — deckt sich mit dem
Entwurf zur lesenden Lehrkraftansicht).

## Teil 33 — local_adele #585: Editor überschreibt eingetippten Namen

- **Freigabe:** Issues angelegt (local_adele #585–#588, mod_adele #38, #39),
  Auftrag: umsetzen. Ab jetzt stehen bei jeder Lieferung die Issue-Nummern
  unmittelbar vor den Dateien.
- **Ursache (Teil 27, belegt):** zwei Ladevorgänge beim Öffnen; die spätere
  Antwort überschreibt die Eingabe.
- **Behebung:** Die Liste lädt nicht mehr, der Editor lädt einmal; das
  Formular (Editor und Nur-Lese-Ansicht) erscheint erst, wenn der angeforderte
  Pfad da ist. Überholte Antworten werden verworfen.
- **Build:** `npm ci --force`, `@babel/plugin-syntax-dynamic-import` ohne
  Eintrag in `package.json` nachinstalliert (wie der CI-Workflow), `npm run
  build`. Gegenprobe vor der Änderung: unveränderter Stand → identische Datei
  (md5 gleich). `package.json` und `package-lock.json` unverändert.
- **Tests:** Jest `LearningPathList.spec.js` 10/10 (1 neu). E2E
  `editor-title-overwrite.spec.ts` als zwei reguläre Tests (eine Anfrage;
  zurückgehaltene Antwort → kein Feld, danach bleibt die Eingabe).
  Rollen- und Editor-Ketten plus M zweimal 19/19.

**Prüfstand `2026100705`:** E2E enrol 6, mod 11, local 43 (60 Tests, kein
`fixme` mehr, ein Gesamtlauf vollständig grün); Jest 343/343; PHPUnit local
336 (1 übersprungen, 1 unvollständig, wie zuvor), enrol 53, mod 18; phpcs 0/0.

## Teil 34 — local_adele #588: Teilnehmerliste gehört dem Kurs

- **Umsetzung:** Die Liste einer Einbindung ist die Schnittmenge aus
  Kurseinschreibung und Pfadeinschreibung; der Rang wird in dieser Menge
  vergeben. Technisch ein Join auf `get_enrolled_sql()` in
  `get_learning_user_relations()`, gesetzt von `get_lp_user_path_relations`,
  sobald die Anfrage über einen einbindenden Kurs kommt. Pfadrecht ohne Kurs
  (Systemkontext): weiterhin pfadweit.
- **Suspendierte Einschreibungen zählen mit** (abweichend vom Vorschlag im
  Issue): Host-Modus `hidden` führt seine Lernenden suspendiert.
- **Einzelabruf und Schreibdienst:** Das Kursrecht einer Lehrkraft deckt nur
  Lernende ihres Kurses.
- **PHPUnit** `roster_course_scope_test` 7 Tests; Gegenprobe ohne
  Geltungsbereich: 3 schlagen fehl. Erster Lauf: 2 Fehler durch einen
  unvollständig gebauten Test-Snapshot (Knoten ohne `data`), nicht durch das
  Produkt; Snapshot vereinfacht.
- **Fixture/E2E:** E2EP2 mit Lernender „Privacy Gamma" auf demselben Pfad.
  Kette P prüft Gamma jetzt überall mit: in keiner Liste von E2EP; in E2EP2
  allein mit Rang 1; Manager über den Systemkontext sieht Alpha, Beta, Gamma;
  Lehrkraft von E2EP: Gammas Snapshot und Änderung abgelehnt. P zweimal 4/4.
- **Offen, zur Entscheidung:** Host-Modus `none` — dort sind die Lernenden der
  Quellen 2/3 nicht im Hostkurs eingeschrieben und stehen damit in keiner
  Kursliste mehr (abgeleitet, nicht gemessen; im Entwurf vermerkt).

**Prüfstand `2026100706`:** E2E enrol 6, mod 11, local 43 (60 Tests, ein
Gesamtlauf vollständig grün); PHPUnit local 343 (1 übersprungen, 1
unvollständig, wie zuvor), enrol 53, mod 18; phpcs 0/0.

## Teil 35 — mod_adele #39: Lehrkraft ohne Bearbeitungsrecht sieht alles, ändert nichts

- **Zwei Rechte statt einem:** neu `local/adele:viewparticipants` (sehen),
  `local/adele:teacheredit` nur noch ändern und ohne Archetyp `teacher`.
  Lesedienste prüfen „sehen oder ändern", der Schreibdienst nur „ändern" —
  der Umweg über `canmanage` im Kurskontext ist entfernt (den hält die
  Lehrkraft ohne Bearbeitungsrecht per Archetyp; gemessen in Teil 32: ihr
  Speichern wurde angenommen).
- **Ansicht:** `mod_adele\local\view_mode` wählt nach diesen Rechten;
  `mod/adele:addinstance` entscheidet nicht mehr (local_adele #523, Kriterium
  1). Die SPA bekommt `canedit="0"`; manuelle Freigabe, manueller Abschluss,
  Master-Haken und „Speichern" entfallen dann.
- **Upgrade:** Schritt `2026100707` nimmt `teacheredit` bei bestehenden Rollen
  des Archetyps `teacher` systemweit zurück. Auf der Testinstanz als echtes
  Upgrade gelaufen (`admin/cli/upgrade.php`), Ergebnis in
  `role_capabilities` nachgesehen: `teacher` hat nur noch `viewparticipants`,
  `editingteacher` und `manager` beide. PHPUnit-Umgebung neu initialisiert.
- **Abhängigkeit:** `mod_adele` verlangt `local_adele >= 2026100707`.
- **Tests:** PHPUnit local `view_and_edit_capability_test` (5), mod
  `view_mode_test` (4; erster Lauf scheiterte an unvollständigen
  Generatordaten des Tests), Jest `mod39_read_only_teacher.spec.js` (3),
  Jest gesamt 346/346. Kette P: 5 Tests, zweimal grün, der erste Lauf auf
  Anhieb.
- **Nicht Teil dieses Pakets:** weitere Capabilities aus local_adele #523,
  Bereinigung von `canmanage`/`assist` (local_adele #518, #507).

**Prüfstand `2026100707`:** E2E enrol 6, mod 12, local 43 (61 Tests, ein
Gesamtlauf vollständig grün); Jest 346/346; PHPUnit local 348 (1 übersprungen,
1 unvollständig, wie zuvor), enrol 53, mod 22; phpcs 0/0.

## Teil 36 — local_adele #587: Lehrkräfte sind keine Lernenden

- **Vorgefunden (gelesen, vor dem Entwurf einer eigenen Lösung):** Das
  Ökosystem hat die nötige Mechanik bereits. `enrol_adele\observer` kennt
  „getragen" (`is_user_carried()`), entfernt beim Verlassen sofort den Zugang
  und löscht den Lernerpfad nach fünf Minuten Schonfrist
  (`remove_user_path_adhoc`, enrol_adele #3); ein nächtlicher Abgleich räumt
  nicht getragene Abonnements nach. Die Regel „zählt als Mitglied" steht an
  zwei Stellen (`enrol_adele\observer`, `mod_adele\local\host_policy`).
  #587 erweitert diese Regel um „als Teilnehmer" und löst sie bei
  Rollenwechseln aus — kein neuer Status, keine zweite Abgleichslogik.
- **Kriterium:** `local/adele:participate` (Archetyp `student`), über eine
  eigene Rolle; `enrol_state::is_participant()` als eine Definition für alle
  drei Plugins.
- **Reihenfolge der Ereignisse (im Core nachgelesen):** `enrol_user()` löst
  `user_enrolment_created` aus und vergibt die Rolle **danach**. Zum Zeitpunkt
  des Einschreibe-Ereignisses nimmt also noch niemand teil; abonniert wird
  jetzt beim `role_assigned`.
- **Rollenwechsel:** `role_assigned` → Abonnement/Hostzugang (local, mod);
  `role_unassigned` → Verlassen wie bei Ausschreibung (enrol), Hostzugang neu
  bewertet (mod).
- **Liste:** lässt die Lehrkräfte des Kurses sofort weg.
- **Bestand:** Lehrkräfte mit Lernerpfad werden vom nächtlichen Abgleich
  entfernt. Risiko „Lernende unter einer Rolle ohne Archetyp" durch den
  Upgrade-Schritt entschärft (Bewerterrollen bekommen die Capability); als
  echtes Upgrade auf der Testinstanz gelaufen (Version zurückgesetzt, eine
  zweite Bewerterrolle gesetzt, Ergebnis nachgesehen, danach
  zurückgestellt).
- **Abweichung vom Issue:** „löschen nach Schonfrist" statt „suspendieren" —
  die bestehende Regel für das Verlassen, bewusst keine zweite.
- **Tests:** PHPUnit local 6, enrol 4; vier bestehende Tests angepasst (sie
  simulierten Einschreibungen ohne Rolle). Erster Gesamtlauf PHPUnit: genau
  diese vier schlugen fehl, sonst nichts. E2E `participants-by-role.spec.ts`
  mit Moodles eigenem Rolleneditor (Hilfe `setCourseRoles()`): dreimal grün,
  der erste Lauf auf Anhieb; Kette P daneben zweimal 5/5.

**Prüfstand `2026100708`:** E2E enrol 6, mod 13, local 43 (62 Tests, ein
Gesamtlauf vollständig grün); PHPUnit local 354 (1 übersprungen, 1
unvollständig, wie zuvor), enrol 57, mod 22; phpcs 0/0.

## Teil 37 — Kette F (Feedback und Sprache): begonnen, **unterbrochen**

Unterbrochen am 2026-10-07 für die Überarbeitung der Präsentation (Auftrag
des Auftraggebers). Stand in der Arbeitskopie, **noch nicht ausgeliefert und
nicht durch einen Gesamtlauf gedeckt**:

- **Fixture-Fehler behoben (Seed):** Die abgeleiteten Fixture-Pfade trugen
  die Texte des Ausgangspfads weiter — z. B. „… und {start_date} bis
  {end_date}" an Knoten ohne Zeitbedingung. `local_adele_fixture_editor_texts()`
  gibt jeder Bedingung die Standardtexte ihrer Klasse (`get_description()`,
  daraus lädt auch der Editor) und setzt die Feedbackknoten zusammen, wie der
  Editor es tut (`renderFeedback()` in `feedbackNode.vue` /
  `feedbackNodeRestriction.vue`). Betrifft nur die Fixtures.
- **Fixtures für F:** je eine eigene Lernende mit eigenem Hostkurs
  (E2EF1–E2EF8) auf den Bedingungen Vorgänger 1 von 1, 2 von 2, manuelle
  Freigabe, manueller Abschluss, Zeitfenster, Zeit UND/ODER Vorgänger, sowie
  eine Lernende mit Sprache Deutsch. Der Seed installiert dafür das deutsche
  Sprachpaket über Moodles eigenen Importer; ohne Netz entfällt der Teil.
- **Hilfe `completeCourse()`** findet Moodles Abschluss-Knopf jetzt über
  `data-action`/`data-toggletype` statt über die englische Beschriftung.
- **Probeläufe** (nur gelesen, nichts festgeschrieben). Rohdaten des zweiten
  Laufs: `docs/testplan/messungen/kette-f-probelauf-2026-10-07-teil2.txt`.
  Beobachtungen, **noch zu bestätigen**:
  1. Keine Rohplatzhalter in i-Symbol und Feedback, in keinem der gesehenen
     Zustände.
  2. Deutsch: vor dem Kursabschluss vollständig deutsch; **nach** dem
     Abschluss von T01 gemischt („Complete diesen Kurs", „successfully
     completed diesen Kurs").
  3. Vorgänger 2 von 2, einer erledigt: Der Text nennt weiterhin beide
     Vorgänger, nicht den fehlenden.
  4. Manueller Abschluss: i-Symbol sagt in jedem Zustand „manual completion
     recorded".
  5. Zeitfenster nur mit Ende: i-Symbol „Missing date to 13/03/26, 09:00".
  6. Festes Zeitfenster, vor Beginn: „From subscription you have access
     until …" — Wortlaut der Zeitdauer-Bedingung.
  7. UND, Vorgänger erledigt: Der Text nennt nur noch die Zeit — hier wird
     das Fehlende benannt.
  8. Im Probelauf blieb der Zeitknoten **im** Fenster gesperrt; der Probelauf
     hat nur die Ad-hoc-Warteschlange geleert, nicht das Vorgehen der Kette T
     nachgebildet — **nicht belastbar**.
- **Offene Schritte:** Zeit-Schritte wie in Kette T nachbilden und neu messen;
  Spec `feedback-texts.spec.ts` (F1/F2 je Bedingung über die Zustände, F3
  UND/ODER, Deutsch) schreiben; Zeit-Teile in eine `zz-`Datei; bestätigte
  Befunde als Issue-Entwürfe; Gesamtlauf; Auslieferung. Verschachtelte
  Kombinationen brauchen die E2-Klammern.

## Teil 38 — Präsentation überarbeitet (2026-10-07)

Auftrag des Auftraggebers, sechs Inhaltsfolien nach Vorgabe. Geliefert:
`AdeLe_Stand_2026-10-07_v2.pptx` (Titel + 6 Folien), Generator
`docs/praesentation/build_2026-10-07_v2.js`. Keine Plugin-Dateien geändert,
daher keine Issue-Nummern.

- **Folie 2** Testarten nach Zweck; Zahlen aus dem Gesamtlauf `2026100708`.
- **Folie 3** Rechtematrix (`ADELE_Rollen_Rechte_Soll-Ist`, Stand
  local_adele v2026031200) gegen PHPUnit, Behat, Playwright. Die Zuordnung
  stammt aus dem Lesen der Testdateien, nicht aus eigenen Läufen je Rolle.
- **Folie 4** Ansatz und Unterschied zu local_adele #470 (53 Karten, 27
  abgehakt — gezählt im Issue-Text).
- **Folie 5/6** Zählung nach den Abschnitten des Testplans: 43
  Teil-Userstories (C2 gestrichen), 37 umgesetzt, 3 teilweise (R3, R5, E2),
  3 offen (F1–F3). Die Schätzung „3–5 Sitzungen" ist eine Erwartung.
- **Folie 7** Befunde nach Art; als offen geführt: Infokarte per Tastatur,
  Ablagezonen (#580), Kontrastverstoß auf dem CI-Runner (Ursache unbelegt),
  Assistentenrolle, Feedbacktexte (Kette F, unbestätigt).
- **Prüfung:** `validate.py` ohne Befund; alle Folien gerendert und
  angesehen. Eigener Fehler unterwegs: Tabellenränder in Zoll statt Punkt
  (Tabellen füllten die Folie), behoben.

Kette F bleibt unterbrochen wie in Teil 37 beschrieben.

### Nachtrag zu Teil 38 — Fassung v3 (2026-10-07)

Auf Rückmeldung des Auftraggebers: Folie „Was seit August an der Software
geschehen ist" aus dem Erstentwurf unverändert wieder eingefügt (Folie 2;
die Oktober-Spalte endet dort weiterhin bei #584). Folie „Welche Tests die
Plugins absichern": k6 und JMeter als eigene Karten (Inhalt aus
`local_adele/tests/load` gelesen: drei Leseseiten, Voreinstellung 10 Nutzer,
30 s, Workflows nur von Hand), Jest in der Unit-Karte als eigene Zahl neben
PHPUnit; Schwerpunkt bleibt Unit, Behat, Playwright. Übrige Folien
unverändert. Geliefert: `AdeLe_Stand_2026-10-07_v3.pptx`, Generator
`docs/praesentation/build_2026-10-07_v3.js`; `validate.py` ohne Befund, alle
Folien gerendert und angesehen.

### Nachtrag zu Teil 38 — Fassung v4 (2026-10-07)

Schlussfolie „Nächster Schritt" ergänzt (Folie 9): alle bereits gelösten und
geschlossenen Issues zur Oberfläche als Regressionstests in Playwright,
Umsetzung Azadeh (Vorgabe des Auftraggebers). Die Liste der betroffenen
Issues ist nicht zusammengestellt; die Folie nennt deshalb keine Anzahl.
Übrige Folien unverändert. Geliefert: `AdeLe_Stand_2026-10-07_v4.pptx`,
Generator `docs/praesentation/build_2026-10-07_v4.js`; `validate.py` ohne
Befund, Folie gerendert und angesehen.

## Teil 39 — Kette F (Feedback und Sprache): umgesetzt; local_adele #581 Nachtrag Zeitzone

Wiederaufnahme von Teil 37. Version `2026100709` (alle drei Plugins).

- **Offener Punkt 8 aus Teil 37 widerlegt:** Der Zeitknoten öffnet im Fenster.
  Der Probelauf hatte den Status einmal gelesen; die Neubewertung ist eine
  Kette von Aufgaben. Mit wiederholtem Leeren der Warteschlange (wie Kette T)
  zeigt der Knoten „accessible". Kein Produktfehler.
- **Fixtures neu geschnitten:** Die Zeit-Fixtures teilten sich Pfad und
  Fenster mit T und O2 — ein Uhrsprung der einen Kette hätte die Aufgaben der
  anderen verbraucht. Jetzt eigene Pfade („E2E F Zeitfenster", „… Nur
  Beginn", „… Nur Ende", „… Zeit UND/ODER Vorgänger") mit eigenen Fenstern
  (2032, 2033). Dazu: zweite UND-Lernende („erst im Fenster"), eigener Text
  der Autorin (englische und deutsche Lernende), eine Lernende in UTC+9.
  Hostkurse heißen nach dem Schlüssel (`E2EF_<KEY>`) statt nummeriert.
- **Eigener Fehler:** Die nummerierten Hostkurse (`E2EF1` …) wanderten beim
  Einfügen einer Fixture samt ihren Mitgliedern zu einer anderen Fixture; eine
  Lernende stand danach zusätzlich auf einem fremden Pfad. Nur in der lokalen
  Testumgebung, nicht ausgeliefert; dort bereinigt.
- **Erst gemessen** (drei Probeläufe, Rohdaten des zweiten und dritten:
  `docs/testplan/messungen/kette-f-probelauf-2026-10-07-teil3.txt`), dann
  festgeschrieben: `feedback-texts.spec.ts` (6 Tests) und
  `zz-time-feedback.spec.ts` (2 Tests). Einzeln viermal grün (zweimal mit,
  zweimal ohne die bekannten Fehler), danach im Gesamtlauf.
- **Befunde, gemessen** — als Tests zu „bekannten Fehlern" (im Normallauf
  übersprungen, mit `ADELE_RUN_KNOWN_DEFECTS=1` rot) und als Issue-Entwürfe:
  1. *Mischsprache:* Deutsche Lernende lesen nach dem eigenen Kursabschluss
     „Complete diesen Kurs". Ursache belegt: Die Neuberechnung in ihrer
     Sitzung nimmt die gespeicherten (englischen) Vorlagen, füllt Platzhalter
     deutsch und vermerkt `feedback_lang = de` — gemessen wechselt der Vermerk
     genau bei ihrem Klick von `en` auf `de`; der Abruf rendert danach nicht
     mehr neu (#493).
  2. *Text der Autorin verschwindet:* Lernende mit anderer Sprache lesen den
     Standardtext statt des eigenen Textes (Abruf mit `$forcelangdefaults`).
  3. *Festes Fenster vor Beginn:* „From subscription you have access until …"
     am gesperrten Knoten.
  4. *Offenes Fenster:* „Missing date" im i-Symbol.
  5. *Manueller Abschluss:* i-Symbol „manual completion recorded" in jedem
     Zustand.
  6. *Tastatur:* i-Symbol und Sprechblase öffnen nur per Mausklick (bereits
     in Teil 24 beobachtet, jetzt als Test und Entwurf).
  7. *Frage, kein Fehler:* „2 von 2 Vorgänger", einer erledigt — der Text
     nennt weiter beide.
  Entwürfe: `issues/local_adele-issue-feedback-language-and-author-text.md`
  (1, 2), `-feedback-wording-time-and-manual.md` (3–5),
  `-node-info-feedback-keyboard.md` (6), `-feedback-partial-predecessors.md`
  (7). **Nicht behoben** — Fixes erst nach angelegten Issues (Abschnitt
  „Entscheidungen des Auftraggebers (2026-10-07)"). Alle vier Entwürfe sind
  auf GitHub noch nicht angelegt.
- **Eigener Fehler aus #581, behoben (local_adele #581, Nachtrag):**
  `time_value::display()` gab die Nutzer-ID an `core_date::get_user_timezone()`;
  die Funktion liest eine bloße Zahl als erzwungene Zeitzone, nicht als
  Nutzer. Folge: Start und Ende in der Zeitzone der Site statt der Lernenden;
  das Fensterende („until …") in der Zeitzone dessen, der die Neuberechnung
  auslöste. Gemessen mit einer Lernenden in Asia/Tokyo: Karte „10:00 to
  12:00", Sprechblase gleichzeitig „until 21:00". #581 hatte die Anzeige „in
  der Zeitzone der betroffenen Person" zugesagt; der damalige Test prüfte nur
  ohne abweichende Zeitzone. Behoben in `time_value::display()` und
  `relation_update::inbetweenfeedback()` (Nutzer-ID durchgereicht); PHPUnit
  vorher rot („19:00" erwartet, „10:00" erhalten), nachher grün; E2E liest
  jetzt überall 19:00/21:00. Bereits gespeicherte Texte korrigieren sich bei
  der nächsten Neuberechnung des Lernerpfads.
- **Abgeleitet, nicht gemessen:** Die Sätze zum Zeitfenster bleiben in der
  Sprache der letzten Neuberechnung (steht im Entwurf zu 1).
- **Offen:** verschachtelte Kombinationen (F3, letzter Punkt) — brauchen die
  E2-Klammern.

**Prüfstand `2026100709`:** E2E enrol 6, mod 13, local 51 bestanden und 6
übersprungen (die bekannten Fehler) — 70 bestandene Tests, ein Gesamtlauf;
PHPUnit local 355 (1 übersprungen, 1 unvollständig, wie zuvor), enrol 57,
mod 22; phpcs für die geänderten PHP-Dateien 0/0. Jest nicht erneut
gelaufen: kein Frontend-Code geändert, das Bündel ist das von `2026100708`.

## Teil 40 — Abgleich GitHub ↔ Rückmeldungen, Entscheidungen zu P (2026-10-07)

- **Neu angelegt und geprüft (API):** local_adele #589 (Sprache, Text der
  Autorin), #590 (Texte Zeitfenster/manueller Abschluss), #591 (Tastatur),
  #592 („n von m"); ab „Beobachtung" wortgleich mit den Entwürfen. mod_adele
  einzeln abgerufen: #37–#39 vorhanden, #40 nicht.
- **Nur im Entwurf, nicht auf GitHub:** zweiter Teil von #586; Abschnitte
  „Umsetzung" zu #587, #588, mod_adele #38; die Kommentar-Entwürfe zu #580
  und mod_adele #37 (jeweils 0 Kommentare). Ohne Issue: Kontrastverstoß nur
  auf dem CI-Runner; Zeitzonen-Nachtrag zu #581.
- **Ältere Entwürfe (G.x, Session 002/003):** laut `arbeitsplan.md` damals
  umgesetzt; nicht erneut geprüft.
- **Eigener Fehler:** Die Assistentenrolle stand in Protokoll (§5a der
  Suite-Doku, Teil 39) und Präsentation als offene Entscheidung. Entschieden
  ist sie seit 2026-08-18: #503 geschlossen („einmal vergebene AA-Rolle nicht
  wieder automatisch entziehen"), #509 „Kein Entzug realisieren". Die
  Kommentare hatte ich nicht gelesen. Folge: R3 prüft, dass die Rolle bleibt;
  die R5-Zeilen „Assistent entzogen" sind über den manuellen Entzug durch den
  Admin testbar. Der Entwurf `local_adele-issue-assistant-role-no-revocation.md`
  ist gegenstandslos.
- **Entscheidungen des Auftraggebers zu P (präzisiert):**
  1. Pfad bearbeiten und Pfad einsetzen sind getrennt: Einsicht in
     Lernerdaten haben die Lehrenden des Kurses, nicht die Pfaderstellenden.
     **Weicht vom ausgelieferten Stand ab** (`has_path_authority()` gibt
     Erstellenden und Mitarbeitenden die Lernerdaten ihres Pfads in allen
     Kursen, #586 zweiter Teil) — Rückbau offen, Rückfrage zu Admin/Manager
     gestellt.
  2. Lehrende erhalten keinen Lernenden-Status (#587, erfüllt); bei temporär
     umgestellter Rolle sehen sie die Lernendenansicht — „Rolle wechseln zu …"
     ist nicht gemessen, Rückfrage gestellt.
  3. Lehrendenansicht ohne Bearbeitungsrecht (mod_adele #39, erfüllt).
  4. Schnittmenge aus Pfad- und Kurseinschreibung (#588, erfüllt); die
     Quellen 2 und 3 schreiben in den Kurs ein.
- **Weitere Rückfragen gestellt:** #592 (Wortlaut), Host-Modus ohne
  Einschreibung, drei Abweichungen aus #587/#588, #580 Ablagezonen,
  Kontrastverstoß.

## Teil 41 — local_adele #589 und #590: Feedbacktexte, Sprache, Wortlaut

Ein Paket, weil beide denselben Code betreffen und #590 Punkt 3 erst durch
#589 auf bestehende Pfade wirkt. Version `2026100710`.

- **Befund beim Lesen, über das Issue hinaus:** Der Abruf in anderer Sprache
  (#493) war eine eigene Näherung der Neuberechnung. Er kannte bei
  UND-Ketten nur die erste Bedingung und ersetzte die Liste „was noch fehlt"
  durch den Standardtext dieser ersten Bedingung — aus dem Code gelesen, nicht
  vorab gemessen; der neue Vergleichstest (unten) deckt es ab.
- **Umbau:** Die Auswertung eines Knotens ist aus `updated_single()` nach
  `evaluate_node()` herausgelöst (reine Verschiebung, Suite danach
  unverändert grün bis auf die erwarteten sechs). Neuberechnung und Abruf
  benutzen denselben Code; die Neuberechnung läuft in der Sprache der
  Lernenden. Regeln: Standardtext immer aus den Sprachstrings, eigener Text
  immer wie gespeichert (Details in den beiden Issue-Entwürfen, Abschnitt
  „Umsetzung").
- **`informationedited`:** Beim manuellen Abschluss kann die Autorin einen
  eigenen Informationstext schreiben (`manual_check.vue`). Mein erster Stand
  behandelte das i-Symbol als „nie eigen"; ein bestehender Test
  (`hidden_feedback_visibility_test`) fiel, daraufhin im Editor nachgelesen
  und das vorhandene Kennzeichen berücksichtigt.
- **Bestehende Tests angepasst (6):** `save_learningpath_test` (2),
  `uc03_parent_courses_restriction_test`, `feedback_rendering_both_languages_test`
  (2), `hidden_feedback_visibility_test`. Drei schrieben die Mischung
  „gespeicherte deutsche Vorlage alten Wortlauts + englische Platzhalter"
  fest („[EN_216]1 of 2 courses erfolgreich bearbeiten") — genau das
  Verhalten, das #589 abstellt; drei nutzten Fixtures ohne die Felder, die
  der Editor setzt (`feedback_before_checkmark`, `informationedited`).
- **Neu:** `feedback_language_test.php`, 13 Tests; Gegenprobe mit dem alten
  `relation_update.php`: 12 rot. Deutsch im PHPUnit über ein minimales
  Sprachpaket im Testdatenverzeichnis (nur `langconfig.php`; die deutschen
  Strings kommen aus dem Plugin).
- **E2E:** fünf bekannte Fehler in reguläre Zusicherungen überführt, auf
  Anhieb grün; bleibt #591 (Tastatur).
- **Nicht geändert:** der String `node_restriction_before_timed` selbst
  („From subscription …") — er bleibt für `timed_duration`.

**Prüfstand `2026100710`:** E2E enrol 6, mod 13, local 52 bestanden und 1
übersprungen (bekannter Fehler #591) — 71 bestandene Tests, ein Gesamtlauf;
PHPUnit local 368 (1 übersprungen, 1 unvollständig, wie zuvor), enrol 57,
mod 22; phpcs für die geänderten PHP-Dateien 0/0. Jest nicht erneut gelaufen:
kein Frontend-Code geändert.

## Teil 42 — Weitere Entscheidungen des Auftraggebers (2026-10-07, während Teil 41)

- **Lernerdaten (Nachtrag zu #586):** Moodle-Administrator:innen ja,
  AdeLe-Manager nein, sonst nur Lehrende im Host-Kurs. Nachtrag formuliert:
  `issues/local_adele-issue-586-nachtrag-course-teachers-only.md`; der
  Auftraggeber legt ihn ab. Darin offen: Moodles eigene Rolle „Manager".
  Umsetzung nach #591.
- **Kontrastverstoß im CI:** Issue. Entwurf
  `issues/local_adele-issue-ci-contrast-violation-runner-only.md`, Ursache
  dort ausdrücklich als unbekannt.
- **#580 Ablagezonen:** Die Zonen sollen immer am jeweils nächstliegenden
  Knoten liegen. Umsetzung unter #580 offen.
- **Teilnahme und Bewertete Rollen:** soll von den Bewerteten Rollen der
  Instanz abhängen. Entwurf
  `issues/local_adele-issue-participation-by-graded-roles.md`; Umsetzung nach
  Ablage.
- **#588, suspendierte Einschreibungen:** Rückfrage des Auftraggebers, wozu
  ADELE die Suspendierung nutzt; beantwortet (siehe Antwort im Verlauf),
  Entscheidung offen.
- **Missverständnis geklärt:** „kein #40" in mod_adele hieß nur, dass seit
  dem letzten Abgleich kein neues Issue dazugekommen ist; es fehlt keines.
- **Noch offen:** „Rolle wechseln zu …" (Bedeutung), #592 (Wortlaut),
  Host-Modus ohne Einschreibung, Moodles Rolle „Manager", Löschen statt
  Suspendieren beim Verlassen (#587).

## Teil 43 — local_adele #591: i-Symbol und Sprechblase per Tastatur

Version `2026100711`.

- **Umsetzung:** Auslöser als benannte Schaltflächen mit Zustand
  (`NodeInformation.vue`, `UserInformation.vue`), `Escape` schließt; Details im
  Issue-Entwurf, Abschnitt „Umsetzung". Bündel neu gebaut.
- **Über das Issue hinaus:** `ExpandNodeInformation.vue` (i-Symbol an den
  Kurskarten eines Stapels) hatte dasselbe Muster und ist mitbehoben — nur mit
  Jest geprüft, nicht im Browser. `NodeFeedbackArea.vue` hat es ebenfalls,
  wird aber nirgends eingebunden; nicht angefasst.
- **Tests:** Jest 361/361 in 74 Suiten (neu: 15); E2E-Test aus den bekannten
  Fehlern in einen regulären überführt, auf Anhieb grün; Smoke-Suite samt axe
  14/14.
- **Eigener Fehler unterwegs:** Ein Jest-Fall („ohne Kursnamen") übergab
  `undefined` an einen Parameter mit Vorgabewert und prüfte deshalb den Fall
  mit Namen; an der Fehlermeldung gesehen, korrigiert.
- **Offen:** Screenreader-Abnahme (NVDA unter Windows).

**Prüfstand `2026100711`:** E2E enrol 6, mod 13, local 53 — 72 Tests, ein
Gesamtlauf vollständig grün, kein übersprungener Test; PHPUnit local 368
(1 übersprungen, 1 unvollständig, wie zuvor), enrol 57, mod 22; Jest 361/361
(74 Suiten); Smoke-Suite local 14/14.

## Teil 44 — Nachträge zu local_adele #586 (nur Lehrende des Kurses) und #588 (von Hand suspendiert)

Ein Paket: beide betreffen dieselben drei Dienste und die Liste.
Version `2026100712`.

- **Abgelegt:** Der Nachtrag steht als Kommentar unter #586 (API geprüft);
  #593 (Bewertete Rollen) ist angelegt. Zu #588: Entscheidung des
  Auftraggebers im Verlauf („von ADELE suspendierte zählen mit, von Hand
  suspendierte ausblenden").
- **#586-Nachtrag:** Pfadrechte geben keine Lernerdaten mehr; außerhalb eines
  einbindenden Kurses nur noch Administrator:innen. `has_path_authority()`
  (seit `2026100704`) entfällt wieder, ersetzt durch
  `has_learner_data_authority()`.
- **#588-Nachtrag:** Liste = aktive Einschreibung beliebiger Methode oder
  Einschreibung von ADELE (auch suspendiert). Der Pfad einer von Hand
  suspendierten Person bleibt unberührt (frühere Regel A-8).
- **Bestehende Tests, die das Alte festschrieben (4):** Pfad-Mitarbeitende
  lesen Lernerdaten (2), Assistent-Test (ging im neuen, breiteren Test auf),
  „suspendiert bleibt auf der Liste". Umgeschrieben, nicht gelöscht.
- **Lücke geschlossen:** Die PHPUnit-Tests nannten „Manager", benutzten aber
  den Administrator. Jetzt gibt es eine echte Rolle mit
  `local/adele:canmanage` — sie wird abgelehnt.
- **Gegenprobe:** neue Tests mit dem Code von `2026100711`: 4 rot.
- **E2E:** Kette P 5/5 mit den neuen Erwartungen (Kollaborator als
  Teilnehmer: nur die eigene Zeile, kein fremder Lernerpfad — vorher
  angenommen, dann gemessen); `mod_adele` gesamt 14/14.
- **Eigener Fehler unterwegs:** Die neue Prüfung in H3 las die erste Spalte
  der Liste (eine laufende Nummer) statt des Namens; an der Fehlermeldung
  gesehen, auf die gemeinsame Hilfe `participantList()` umgestellt.
- **Nicht gemessen:** Host-Modus „keine Einschreibung" und die Lernpfad-Liste
  (Entscheidung dazu steht aus).

**Prüfstand `2026100712`:** E2E enrol 6, mod 14, local 53 — 73 Tests, ein
Gesamtlauf vollständig grün; PHPUnit local 369 (1 übersprungen, 1
unvollständig, wie zuvor), enrol 57, mod 22; phpcs für die geänderten
PHP-Dateien 0/0. Jest nicht erneut gelaufen: kein Frontend-Code geändert.

## Teil 45 — local_adele #593: Teilnahme folgt den Bewerteten Rollen

Version `2026100713`; `mod_adele` verlangt jetzt `local_adele` ≥ `2026100713`.

- **Regel:** Lernende:r ist, wer im Kurs aus eigenem Recht eine der
  Bewerteten Rollen der Instanz hat. Die Capability
  `local/adele:participate` aus #587 entfällt wieder.
- **Über den Issue-Text hinaus nötig:** Der Text sagte „der nächtliche
  Abgleich zieht nach". Beim Nachlesen: Der Abgleich **entfernt** nur; niemand
  abonniert Personen nach, die eine Rolle schon haben, wenn diese erst später
  zählt. Dafür die Hintergrundaufgabe in `mod_adele` (Details im Entwurf).
- **Risiko in der Gegenrichtung, im Issue so nicht benannt:** Eine Rolle mit
  Archetyp „student", die **nicht** unter den Bewerteten Rollen steht, nahm
  über die Capability teil und tut es jetzt nicht mehr. Das Upgrade nennt
  solche Rollen, entscheidet aber nichts.
- **Tests:** siehe Entwurf; `issue587_participants_test` umgeschrieben (der
  Verbots-Fall der Capability entfällt mit ihr).
- **Belegt:**
  - Upgrade der Testinstanz von `2026100712` auf `2026100713`: Die Capability
    ist danach weder in `capabilities` noch in `role_capabilities` vorhanden
    (je 0 Zeilen); keine Meldung, weil dort nur „student" sie hatte und
    Bewertete Rolle ist.
  - Speichern der echten Einstellung (`admin_setting_special_gradebookroles`,
    Rolle „teacher" hinzugefügt) auf der Testinstanz: 43 Lernpfad-Aktivitäten,
    43 Aufgaben in der Warteschlange. Einstellung danach zurückgesetzt,
    Aufgaben gelöscht.
  - PHPUnit nach der Änderung: local 370 (einer mehr: die Upgrade-Meldung),
    enrol 57, mod 25 (drei neue in `graded_roles_test`).
- **Nicht geprüft:** das Verhalten auf einer Instanz mit sehr vielen
  Aktivitäten (eine Aufgabe je Aktivität; Laufzeit nicht gemessen).
- **Prüfstand `2026100713`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 53 (73, keiner übersprungen); PHPUnit local 370
  (1 übersprungen, 1 unvollständig — beide wie bisher), enrol 57, mod 25.
  Ausgeliefert unter local_adele #593.

## Teil 46 — local_adele #580: Ablagezonen folgen dem Zeiger

Version `2026100714`. Entscheidung des Auftraggebers: „Immer der topologisch
nächstliegende Knoten ist am einfachsten im Handling zu verstehen."

- **Umsetzung:** `zoneOwner.ts` (Entscheidung, mit Jest-Test) und
  `SidebarPath.vue::onDrag`. Regel und die zwei Ausnahmen (Zeiger auf einer
  Zone; Marge von 30 Einheiten gegen Hin- und Herspringen) stehen im
  Kommentarentwurf `issues/local_adele-issue-580-comment.md`.
- **Eigener Fehler unterwegs, vor der Auslieferung bemerkt:** Die erste
  Fassung der Prüfhilfe `zonesAround()` verglich nur die Höhe. Knoten
  derselben Reihe konnte sie nicht unterscheiden; der Test der Ausnahme lief
  deshalb auch mit ausgebauter Ausnahme grün. Aufgefallen durch die
  Gegenprobe, behoben (feste Lage der Zone relativ zum Knoten), Gegenprobe
  danach rot an der richtigen Stelle.
- **Erster Testentwurf verworfen:** Er wollte die Ausnahme an der
  Nachfolger-Zone eines Knotens mit Nachfolger zeigen. Diese Zone ist nicht
  zu treffen — auch mit dem Stand vor #580 nicht (gemessen, siehe unten).
  Der Test nutzt jetzt die Zone „parallel" bei zwei Knoten nebeneinander.
- **Belegt:**
  - Gegenprobe 1: neuer Test „follow" gegen Stand `2026100713` → rot („the
    child zone never appeared next to dndnode_2"); die übrigen fünf
    Editor-Tests mit der strengeren Prüfung grün.
  - Gegenprobe 2: Variante ohne Ausnahme 1 gebaut und nur auf der
    Testinstanz eingespielt → Test „stays under the pointer" rot („the zones
    must still be those of T01"). Danach der ausgelieferte Stand wieder
    eingespielt (Dateivergleich mit der Arbeitskopie).
  - Erreichbarkeit mit geführtem Zeiger, 2 Topologien × 3 Knoten × 4 Zonen:
    alt und neu identisch, 17 von 24 erreicht.
  - Jest 366 (5 neue), sieben Editor-Tests grün.
- **Neuer Befund, nicht behoben (Regel: erst nach Ablage):** Versetzt
  gezeichnete Zonen sind nicht zu treffen. Entwurf
  `issues/local_adele-issue-offset-dropzones-unreachable.md`. Ursache dort
  ausdrücklich nur nach Codelage, nicht durch Versuch belegt.
- **Nicht geprüft:** Ziehen von Hand in einem echten Browser; andere
  Browser als Chromium; Topologien über die zwei gemessenen hinaus.
- **Prüfstand `2026100714`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 55 (75, keiner übersprungen); PHPUnit local 370
  (1 übersprungen, 1 unvollständig — beide wie bisher), enrol 57, mod 25;
  Jest 366. PHPUnit lief nach dem Gesamtlauf gesondert: Der Lauf selbst
  brach dort ab, weil die Testumgebung nach dem Versionswechsel nicht neu
  eingerichtet war (mein Versäumnis im Ablauf, kein Befund am Code).
  Ausgeliefert unter local_adele #580.

## Teil 47 — Ketten R3 und R5 vollständig; zwei neue Befunde

Version `2026100715`. Nur Tests und Dokumentation, keine Produktänderung.

- **Ausgangslage:** R3 prüfte nur die Vergabe, R5 drei von sechs Zeilen. Als
  Grund stand im Suite-Dokument, der Entzug der Assistentenrolle sei
  ungeklärt. Das war falsch (im Abgleich mit GitHub in Teil 40 bemerkt):
  entschieden seit 2026-08-18, kein automatischer Entzug.
- **Umgesetzt:** R3 mit sechs Schritten; R5 mit allen sechs Zeilen. „Entzug
  der Assistentenrolle" heißt in den Tests: Administrator nimmt die
  Systemrolle auf Moodles Seite weg.
- **Korrektur an einer bestehenden Aussage:** Die Zeile „nur Kollaborator,
  Kollaboration entzogen → Zugriff entfällt" galt als umgesetzt, war aber an
  einer Person geprüft, die zugleich Assistent war (Editor blieb
  erreichbar). Jetzt an einer Person ohne jede Rolle: Pfad nicht mehr
  gelistet, Server antwortet `nopermissions`.
- **Befund 1 (belegt):** Eigentümer nimmt sich von der Bearbeiterliste → der
  eigene verborgene Pfad wird ihm nicht mehr angezeigt. Datenbank nach dem
  Test: `createdby` unverändert, in `local_adele_lp_editors` nur die zweite
  Person. Passende Codestellen im Entwurf benannt. Als bekannter Fehler
  markiert; Entwurf mit Frage (Regel A oder B).
- **Befund 2 (belegt):** `save_learningpath.php` wirft eine nicht importierte
  Ausnahmeklasse; die Ablehnung beim Anlegen endet als PHP-Fehler
  (`Class "local_adele\external\required_capability_exception" not found`,
  direkt in PHP nachgestellt). Es wird nichts angelegt. Suche über
  `classes/`: nur diese Datei betroffen. Als bekannter Fehler markiert.
- **Unterwegs:**
  - Erste Fassung der Aufräumschritte des Eigentümer-Tests las die Liste,
    bevor sie geladen war, und ließ den Testpfad liegen; behoben, per
    erneutem Lauf geprüft (kein Rest).
  - Auf der Testinstanz lag ein Pfad aus einem früheren, abgebrochenen Lauf
    der Kette R6 („Linear A2 nach Eigentumswechsel …"), Eigentümer die
    Assistenten-Person. Er ließ eine zu weit gefasste Zusicherung („kein Pfad
    gelistet") fehlschlagen. Zusicherung auf den betroffenen Pfad
    eingeschränkt, Rest gelöscht. Wann er entstanden ist, habe ich nicht
    nachvollzogen.
- **Beides wird erst nach Ablage behoben.**
- **Prüfstand `2026100715`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 56 bestanden und 2 übersprungen (die zwei
  bekannten Fehler; mit `ADELE_RUN_KNOWN_DEFECTS=1` gesondert gelaufen: beide
  rot an der erwarteten Stelle); PHPUnit local 370 (1 übersprungen, 1
  unvollständig — wie bisher), enrol 57, mod 25. Ausgeliefert ohne
  Issue-Nummer (nur Tests und Dokumentation); die zwei Entwürfe warten auf
  Ablage.

## Teil 48 — local_adele #594: Ablehnung beim Anlegen als PHP-Fehler; Entscheidung zu den versetzten Ablagezonen

Version `2026100716`.

- **#594** (abgelegt vom Auftraggeber, Text entspricht dem Entwurf): fehlender
  Import in `save_learningpath.php` ergänzt. PHPUnit-Test zuerst geschrieben
  und rot gesehen („Class … not found"), nach der Änderung grün. Der
  E2E-Test ist kein bekannter Fehler mehr.
- **Versetzte Ablagezonen — Entscheidung des Auftraggebers (2026-10-07):**
  „Wenn es einen Nachfolger gibt, dann keine untere (zusätzliche)
  Ablagezone, wenn es einen Vorgänger gibt, dann keine (zusätzliche) obere
  Ablage mehr." Dazu der Grundsatz: „Eine einmal erreichte Zone soll
  bestehen bleiben, auch wenn innerhalb der Zone sich der Mauszeiger einem
  anderen Knoten stärker annähert" — das ist die Ausnahme 1 aus #580,
  bereits ausgeliefert. Entwurf entsprechend umgeschrieben.
- **Missverständnis, festgehalten:** Der Auftraggeber meldete „ist angelegt,
  bitte mitfixen" mit dem Link auf #594, unmittelbar nach dem Gespräch über
  die Ablagezonen. #594 ist aber der Befund zum Dienst. Der Entwurf zu den
  Ablagezonen ist auf GitHub nicht zu finden (#595–#598 existieren nicht,
  geprüft). Umgesetzt wurde deshalb nur #594; die Zonen warten auf ihre
  Nummer.
- **Nicht gemessen** (Probe vom Auftraggeber unterbrochen, nicht
  nachgeholt): ob ein Knoten „parallel" zum vorhandenen Nachfolger in jedem
  Fall dieselbe Struktur ergibt wie ein zweiter Nachfolger.
- **Prüfstand `2026100716`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 57 bestanden und 1 übersprungen (bekannter
  Fehler „Eigentümer von der Bearbeiterliste genommen"); PHPUnit local 372
  (2 neue; 1 übersprungen, 1 unvollständig — wie bisher), enrol 57, mod 25.
  Ausgeliefert unter local_adele #594.

## Teil 49 — local_adele #595: Zonen nur, wo Platz ist; eine erreichte Zone bleibt

Version `2026100717`. Issue vom Auftraggeber abgelegt, Text entspricht dem
Entwurf. Frage des Auftraggebers dazu: „Ist da auch der
‚Beharren in der Zone'-Mechanismus enthalten?"

- **Antwort, nach Messung:** Mit #580 nur zum Teil — dort blieb die Zone
  gegenüber einem näheren Nachbarknoten. Zwei weitere Regeln entfernten
  Zonen unter dem Zeiger: der Startmarker unter der Zone und die
  Abstandsgrenzen. Beides ist jetzt mit #595 nachgezogen.
- **Befund dabei (belegt):** Einzelner Knoten, Zoom 0,69: Zone „parallel"
  450–750 Einheiten rechts der linken Knotenkante, Startmarker ab 600. Zeiger
  bei 10/30/50 % der Zonenbreite: Treffer; bei 70/90 %: Zonen weg.
- **Umsetzung, Tests, Gegenproben:** im Entwurf
  `issues/local_adele-issue-offset-dropzones-unreachable.md`, Abschnitt
  „Umsetzung".
- **Verworfen:** Ein erster Test wollte den Vorrang vor den Abstandsregeln an
  einem einzelnen Knoten zeigen und scheiterte am Startmarker — so kam der
  Befund oben zustande. Für die Abstandsregeln gibt es keinen eigenen Test
  (Begründung im Entwurf).
- **Prämisse des Auftraggebers geprüft** („der Vorgänger oder Nachfolger kann
  rechts davon einen Alternativ-Knoten anlegen mit derselben Funktion"): am
  Blatt ja, in der Mitte einer Kette nein (der parallele Knoten teilt auch
  den Nachfolger). Als Test 4 festgehalten, soweit es gilt.
- **Offen:** Tastaturdialog bietet „nach"/„vor" weiter überall an.
- **Prüfstand `2026100717`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 61 bestanden und 1 übersprungen (bekannter
  Fehler „Eigentümer von der Bearbeiterliste genommen"); PHPUnit local 372,
  enrol 57, mod 25; Jest 370. Ausgeliefert unter local_adele #595.

## Teil 50 — Entscheidung: Tastaturdialog bleibt (2026-10-08)

- Frage: Soll der Dialog „Kurs einfügen" (#575) der Mausregel aus #595
  folgen und „nach"/„vor" nur anbieten, wo noch kein Nachfolger/Vorgänger
  ist? Antwort des Auftraggebers: „Bitte beibehalten, das macht ja absolut
  Sinn!" Der Tastaturweg bleibt damit der Weg für einen zweiten Nachfolger
  oder Vorgänger an beliebiger Stelle.

## Teil 51 — Entscheidungen 2026-10-08; Belege des Kontrasttests

Version `2026100718`. Nur Test und Dokumentation.

- **Eigentümer-Befund:** Regel B (der Eigentümer steht immer auf der
  Bearbeiterliste). In den Entwurf eingetragen samt Umsetzungsplan; wartet
  auf Ablage. Codelage dazu: `ownership::set_owner()` trägt den neuen
  Eigentümer schon heute in die Liste ein.
- **#580:** schließen; Abschlusskommentar
  `issues/local_adele-issue-580-closing-comment.md`. Punkt 2 neu erfasst
  (`issues/local_adele-issue-fit-view-after-drop.md`). Punkt 3 (Beschriftung)
  ist durch #584 erledigt — die Zone „Alternative node" verhält sich so
  („einer genügt", `min_courses` 1). Das hatte ich in der Rückfrage noch als
  offen geführt; beim Nachlesen von Teil 32 als erledigt erkannt.
- **Kontrastverstoß:** Variante a (Workflow neu starten, dann entscheiden)
  und Belege am Test. `issue574-575-accessibility.spec.ts` legt jetzt bei
  jedem Lauf `axe-results.json` ab, bei Befunden zusätzlich je Befund JSON
  mit gemessenen und berechneten Werten, ein Bild des Elements und ein
  Seitenbild mit Markierung. Gegenprobe mit erzwungenem Verstoß: alle Belege
  da. Smoke-Suite danach vollständig grün (14).
- **E2-Klammern:** beide Ebenen (Knoten und Bedingungen), je beide Formen.
  Folgt als eigenes Paket.
- Für den Smoke-Lauf auf der Testinstanz fehlten die Node-Module unter
  `tests/playwright/`; mit `npm ci` nachinstalliert (nur Testinstanz).
- **Prüfstand `2026100718`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 61 bestanden und 1 übersprungen (bekannter
  Fehler „Eigentümer von der Bearbeiterliste genommen"); Smoke-Suite
  `local_adele` 14 (gesondert, vor dem Gesamtlauf); PHPUnit local 372, enrol
  57, mod 25. Ausgeliefert ohne Issue-Nummer (Test und Dokumentation zum
  Kontrastverstoß, Entwürfe).

## Teil 52 — E2-Klammerungen auf Knoten- und Bedingungsebene; drei Befunde

Version `2026100719`. Nur Tests und Dokumentation, keine Produktänderung.

- **Umgesetzt:** `editor-brackets.spec.ts`, 5 Tests (Inhalt im
  Suite-Nachtrag). Die Struktur wird am gespeicherten Pfad geprüft.
- **Bedingungsebene, belegt am gespeicherten JSON:** Spalten nebeneinander =
  ODER, Kette untereinander = UND. `A UND (B ODER C)` ist nur ausmultipliziert
  darstellbar; das gilt laut Entscheidung als Testvariante.
- **Knotenebene:** `(A UND B) ODER C` lässt sich mit der Maus nicht bauen —
  seit #595 gibt es keine zweite Vorgänger-Zone, und die Bedingungen
  „Nach Vorgängerknoten"/„Bestimmter Knoten abgeschlossen" erlauben keine
  Teilmenge der Vorgänger. Gebaut als Folge A → B plus C per Tastatur „vor"
  X, X verlangt 1 von 2.
- **Befunde (alle belegt, je bekannter Fehler + Entwurf):**
  1. „Vor" eingefügter Vorgänger fehlt in der Bedingung des Knotens
     (gespeichert: `courses_id: [dndnode_2]` bei Vorgängern 2 und 4).
  2. Vorgängerzahl im Bedingungs-Editor vor dem Speichern veraltet („1 / 1",
     Kopfzeile nennt zwei Vorgänger).
  3. Per Tastatur „vor" eingefügter Knoten liegt deckungsgleich auf dem
     Zielknoten (gemessene Positionen im Entwurf).
- **Unterwegs:**
  - „Start and end time" ließ das Speichern scheitern (Datum fehlt; Moodle-
    Dialog „Incomplete criterion"). C ist jetzt „Certain node completed" mit
    gewähltem Knoten. Die Speicher-Hilfe nennt eine Ablehnung seither mit
    ihrem Text, statt nur auf den Zeitablauf zu laufen.
  - Der Bedingungs-Editor verschiebt seine Fläche, sobald die Zonen
    erscheinen; die Hilfe zoomt deshalb heraus und zielt mit Nachmessen.
  - Kopfkommentar in `editor-combinations.spec.ts` korrigiert (Beschriftung
    passt seit #584).
- **Nicht geprüft:** was Lernende aus den Kombinationen bekommen — Kette F3.
- **Prüfstand `2026100719`** (Gesamtlauf nach dem Abgleich der Auslieferungskopie):
  E2E enrol 6, mod 14, local 66 bestanden und 4 übersprungen (die bekannten
  Fehler: Eigentümer von der Bearbeiterliste, die drei aus diesem Teil; die
  drei neuen gesondert mit `ADELE_RUN_KNOWN_DEFECTS=1` gelaufen: rot an der
  erwarteten Stelle); PHPUnit local 372, enrol 57, mod 25. Ausgeliefert ohne
  Issue-Nummer (Tests und Dokumentation); drei Entwürfe warten auf Ablage.

## Teil 53 — CI rot (local_adele): Codechecker und PHPDoc-Checker (2026-10-12)

Version `2026100720`. Grundlage: Logs des CI-Laufs vom 2026-10-11 (drei
Matrix-Jobs, Moodle 4.5, pgsql/mariadb/mysqli), vom Auftraggeber übergeben.

- **Befund (belegt aus den Logs):** In allen drei Jobs schlugen zwei Schritte
  fehl, alles andere war grün (PHPUnit 372, Behat 12 Szenarien, Jest 370):
  1. `codechecker --max-warnings=0`: 36 Warnungen
     `moodle.PHPUnit.TestCaseCovers.Missing` in fünf Testdateien
     (`issue587_participants_test`, `security/learner_data_embedding_test`,
     `roster_course_scope_test`, `save_learningpath_refusal_test`,
     `view_and_edit_capability_test`).
  2. `phpdoc`: `feedback_language_test::condition has incomplete parameters
     list`.
- **Ursache 1 (belegt):** Die Dateien haben `@covers` auf Klassenebene, aber
  zwischen Klassenkommentar und `final class` stehen PHP-Attribute
  (`RunTestsInSeparateProcesses`, `PreserveGlobalState`). Der Sniff sucht
  den Kommentar nur unmittelbar über der Klasse (`TestCaseCoversSniff`,
  „first non skip token must be end of phpdoc comment") und findet ihn so
  nicht. Gegenprobe: Attribute über den Kommentar gesetzt → keine Warnung.
- **Ursache 2 (belegt):** `@param array $feedback` bei `?array $feedback`.
- **Eigener Fehler:** Alle fünf Dateien stammen aus dieser Session. Geprüft
  hatte ich mit phpcs auf die Arbeitskopie außerhalb des Moodle-Baums; dort
  greift der Sniff nicht (er erkennt Testdateien nur im Moodle-Baum). Den
  PHPDoc-Checker hatte ich lokal gar nicht.
- **Behoben:** Attribute in den fünf Dateien über den Klassenkommentar
  verschoben (mit Begründung im Code); Typ korrigiert.
- **Ablauf ergänzt:** phpcs jetzt über alle drei Plugins **im Moodle-Baum**
  der Testinstanz; `local_moodlecheck` v1.3.2 (dieselbe Version wie in der
  CI) auf der Testinstanz installiert und vor jeder Auslieferung über alle
  drei Plugins. Beide nachgestellt: zuerst dieselben Befunde wie die CI,
  nach der Korrektur keine.
- **Prüfstand `2026100720`:** PHPUnit local 372, enrol 57, mod 25; phpcs im
  Moodle-Baum über alle drei Plugins ohne Befund; PHPDoc-Checker über alle
  drei ohne Befund. E2E nicht erneut gelaufen: geändert sind nur
  Kommentare und Attributpositionen in PHPUnit-Dateien.
  Ausgeliefert als „local_adele: CI-Fix" (kein Issue).

## Teil 54 — E2E-Issue, Paket 1: die vier bekannten Fehler behoben (2026-10-12)

Version `2026100721`. Auftrag: E2E-Issue „Vollständige, nachweiswirksame
Playwright-E2E-Abdeckung der User-Stories R–Z" (vom Auftraggeber
übergeben). Entscheidungen 2026-10-12: Der Auftrag des Issues genügt für die
Produktfixes der vier bekannten Fehler (die Entwürfe müssen nicht erst
abgelegt werden); `tests/e2e` bleibt nur manuell auslösbar.

- **Regel B (Eigentümer):** Dienst lehnt das Entfernen ab
  (`editorremoveowner`; ein anderer Bearbeiter scheitert schon vorher an
  `nopermissions`), kein Knopf auf der Eigentümerkarte, Upgrade-Schritt
  trägt fehlende Eigentümer nach. **Befund dabei:** Auf der Testinstanz
  fehlten 41 Eigentümer auf ihren Listen — die Fixtures setzten die
  Bearbeiter fixer Pfade ohne den Eigentümer (Administrator). Fixtures
  folgen jetzt der Regel; R4b/R5 entsprechend angepasst (Schritt „der letzte
  Bearbeiter ist geschützt" geht in „der Eigentümer bleibt" auf).
- **„Vor" eingefügter Vorgänger fehlt in der Bedingung:** behoben
  (`addAutoRestrictions`, Fall `parent`).
- **Veraltete Vorgängerzahl:** Die im Entwurf vermutete Ursache (Store) war
  nicht die ganze. Nach dem ersten Fix blieb der E2E-Test rot; eigentliche
  Ursache: Beim parallelen Knoten wurden die Listen der Nachbarn nicht
  nachgezogen (der Nachfolger kannte den neuen Vorgänger erst nach Speichern
  und Neuladen). Behoben mit `linkParallelNode()`; beide Änderungen bleiben.
- **Knoten „vor" verdeckt den Zielknoten:** neuer Vorgänger neben die
  vorhandenen. Die Testhilfe klickt wieder echt; `dispatchEvent('click')`
  ist aus den E2E-Hilfen verschwunden.
- **Tests:** PHPUnit `owner_stays_editor_test` (5); Jest +15 (385);
  Gegenproben für die PHPUnit- und Jest-Tests gegen den alten Code rot.
  Die vier vormals bekannten Fehler laufen als reguläre E2E-Tests.
- **Prüfstand `2026100721`:** E2E enrol 6, mod 14, local 70 bestanden,
  keine übersprungen, keine bekannten Fehler mehr; PHPUnit local 377,
  enrol 57, mod 25; Jest 385; phpcs im Moodle-Baum und PHPDoc-Checker über
  alle drei Plugins ohne Befund.
  Ausgeliefert als „local_adele: E2E-Issue (User-Story-Vervollständigung),
  Paket 1".

## Teil 55 — E2E-Issue, Paket 2: strenger Kurszugriff, Story-Abdeckung, Mutationen (2026-10-12)

Version `2026100722`. Umgesetzt aus dem E2E-Issue: §3A (Kurszugriff),
§3C (kein `--pass-with-no-tests`, Story-Abdeckungsmatrix, Abbruch bei
übersprungen/fixme/flaky) und aus §4 je eine Mutation pro kritischer
Domäne. Nur Testcode, Seed und Workflow geändert; kein Produktcode.

- **`courseAccess()` streng** (alle drei bytegleichen `support/fixtures.ts`):
  „offen" nur bei gerenderter Seite des richtigen Kurses, angemeldeter
  Nicht-Administrator und serverseitig aktiver Einschreibung
  (`enrolmentState()`, liest `user_enrolments` und Instanzstatus per CLI mit
  der Server-Testuhr). Verweigerung nur als Einschreibeseite dieses Kurses
  oder bekannte Meldung; alles andere ist Testfehler.
  `expectCourseOpen*` verlangt zusätzlich eine aktive ADELE-Einschreibung
  (`accessThroughAdele()`).
- **Story-Abdeckung:** Tags `@story:<ID>` an allen 90 E2E-Tests (local 70,
  mod 14, enrol 6), `stories.json` je Plugin, `story-coverage.mjs` erzeugt
  `coverage.md/.json` und bricht ab bei übersprungenen, fixme-, flaky- oder
  roten Tests, fehlendem oder unbekanntem Tag und (ungefiltert) fehlender
  Pflicht-Story. Workflow-Schritt „Check the story coverage" in allen drei
  `e2e.yml`; `--pass-with-no-tests` entfernt. actionlint ohne Befund.
- **Eigener Fehler, behoben:** Der JSON-Reporter schreibt Tags ohne „@";
  die erste Fassung von `story-coverage.mjs` erwartete „@story:" und meldete
  deshalb für enrol „keine Tags". Liest jetzt beide Schreibweisen.
- **Befund (Seed nahm Wirkung vorweg):** Mutation „`enrol_user()`
  unterdrückt" ließ V1 zunächst grün. Ursache belegt: Der Seed setzte die
  Zuweisung der importierten Referenzpfade neu, ließ aber die
  ADELE-Einschreibungen früherer Läufe stehen; der Reconciler reaktivierte
  sie nur. Seed ruft jetzt `reconciler::purge_user()` vor dem erneuten
  Zuweisen; danach V1 rot wie erwartet.
- **Mutationsproben** (Tabelle in `docs/e2e-suite.md`, Nachtrag
  `2026100722`): Zugang (Testhilfe, Produkt, Fremdeinschreibung), Entzug,
  Prozent, Feedback, Rolle, Datenschutz, Abschluss, Bedienbarkeit — jede
  macht den zuständigen Test rot, mit der fachlich passenden Meldung.
  Bedienbarkeit im ersten Versuch grün, danach dreimal rot; Ursache des
  ersten Versuchs nicht belegt (siehe e2e-suite).
- **Offen aus dem Issue (folgende Pakete):** Kettenlücken nach §2 (u. a.
  R1/R2 Speichern+Wiederöffnen+neue Sitzung, E1 vollständiger Referenzpfad,
  E2 Laufzeit-Wahrheitstabelle, Zeitgrenzen T, Master-2×2 mit Rücknahmen,
  echte CAT-Journey D, H Quelle×Modus, P alle Rollen), Isolierung der
  `zz-`-Reihenfolge, `waitForTimeout`-Abbau, deterministische Titel statt
  `Date.now()`. „Verbindliches Gate": Auslöser bleibt nach Entscheidung des
  Auftraggebers manuell.
- **Nachtrag zu `2026100721` (eigener Fehler):** `tests/owner_stays_editor_test.php`
  hatte nach dem Lizenzkopf zwei Leerzeilen; phpcs meldet das als Fehler
  („Boilerplate comment must be followed by a single blank line"). Mein
  Prüfschritt zeigte nur die letzten Zeilen der phpcs-Zusammenfassung und
  übersah die Fehlerzeile; `2026100721` ist mit diesem Befund ausgeliefert
  worden. Behoben in `2026100722`; der Prüfschritt wertet jetzt die
  TOTAL-Zeile aus.
- **Prüfstand `2026100722`:** E2E enrol 6, mod 14, local 70 bestanden,
  Story-Abdeckung aller drei Plugins „complete" (Pflicht-Stories je mit
  bestandenem Test, kein Test ohne Tag); PHPUnit local 377, enrol 57,
  mod 25; Jest unverändert 385 (kein Vue-Code geändert); phpcs im
  Moodle-Baum und PHPDoc-Checker über alle drei ohne Befund; actionlint
  ohne Befund.
  Ausgeliefert als „local_adele: E2E-Issue (User-Story-Vervollständigung),
  Paket 2".

## Teil 56 — E2E-Issue, Paket 3: Isolierung, Zeitmodell, Kettenlücken (2026-10-12)

Version `2026100723`. Aus dem E2E-Issue §3B (Isolierung, keine pauschalen
Wartezeiten, echte Klicks, deterministische Namen) und §2 (Kettenlücken
R1/R2, R3–R6 direkt, E1, C1, L1, A, M, O4, Z, T, D). Kein Produktcode
geändert; Seed, Testhilfen, Specs, Workflow, eine PHPUnit-Datei.

- **Isolierung:** `reseedFixtures()` vor jeder Spec-Datei (Zeit-Dateien:
  vor jedem Test); `zz-`-Präfix entfällt (`time-conditions`,
  `time-feedback`). Gesamtlauf local 78 Tests in 36,5 min (vorher 70 in
  rund 22 min; Mehrkosten durch Seeds und neue Tests).
- **Befund, Ursache belegt:** V2 („Zugang endet, wo der Kurs den Pfad
  verlässt") war im ersten Lauf mit Reseed rot. Der Referenz-Lernende
  (`student01`) stand aus einem früheren Lauf zusätzlich auf Linear A2 und
  „Verzweigt gestapelt B1" und hatte über Linear A2 eine aktive
  ADELE-Einschreibung in T01 (Datenbank geprüft). Seed entfernt jetzt alle
  anderen Fixture-Lernenden von den importierten Pfaden; danach V2 grün.
  Wie `student01` auf die beiden Pfade kam, ist nicht ermittelt.
- **Befund, Ursache belegt:** Die Testhilfe las den Leinwand-Transform von
  `.vue-flow__viewport`; das Element trägt keinen (Messung). Gemessen wird
  jetzt `.vue-flow__transformationpane`. Erst damit ließ sich „Leinwand in
  Ruhe" statt fester Wartezeiten verwenden.
- **Befund:** Mit echten Klicks war das i-Symbol von Knoten 1 in der
  Lernendenansicht (Standard-Viewport) nicht klickbar – es lag unter der
  Navigationsleiste, weil die Leinwand ein fester Rahmen ist. Die Ketten
  holen den Pfad jetzt mit der eigenen Schaltfläche „Ansicht anpassen" ins
  Bild (`fitPathView()`), wie eine Person es tun würde.
- **Befund (Produktverhalten, zur Entscheidung):** Eine Assistenz, die als
  Bearbeiter eines verborgenen Pfads eingetragen ist, den sie nicht angelegt
  hat, sieht ihn in der Übersicht nicht (`learning_paths.php`, #472).
- **Neue Tests:** `roles-direct-access` (5), `roles-own-and-foreign-paths`
  (2), `editor-reference-path` (1), PHPUnit `timed_duration_boundaries_test`
  (5; Gegenprobe mit verschobenem Periodenende rot).
- **Erweitert:** C1 (Negativperson je Variante über denselben Gastkurs,
  Feedback nennt fehlenden Vorgänger), L1 (realer Zugang je Stufe), A (neue
  Sitzung, nicht gewählte Alternative), M (unbeteiligte Person auf anderem
  Pfad behält T02), O4 (Prozentwert je Kombination), Z (Startzustand in
  Z2–Z4; Z1: Autor hängt T04 an – nicht mehr abgeschlossen, nach T04 wieder
  abgeschlossen), D (als Vertragstest gekennzeichnet).
- **Nachgezogen im selben Paket:** E2-Laufzeit-Wahrheitstabelle (PHPUnit
  `uc23`, acht Zeilen), T-UND/ODER-Wahrheitstabellen (`uc21`, `uc22`),
  H Quelle „jeder Kurs" × hidden/none und Quereinschreibung #37 (E2E, mod).
  F: jede Lesung der Kette meldet sich neu an (`learnerReads`), damit ist
  „neue Sitzung" je Zustand bereits abgedeckt. P: die vorhandenen Ketten
  decken Rollen, `contextid` und Schreibversuche ab (keine Änderung).
- **Befund, Ursache belegt:** Z1 (Pfad wird um T04 verlängert) war in einem
  Gesamtlauf rot: Der Abschluss von T04 aus dem gezielten Lauf davor bestand
  noch (Datenbank). Seed vergisst jetzt Abschlüsse in allen Fixture-Kursen.
- **Beobachtung, Ursache nicht belegt:** ein Lauf mit Z3/Z4 rot
  („Not completed <7. Oktober>" aus `completionReport`), der nächste grün.
- **Offen aus §2:** echte CAT-Journey im Browser – in den Fixtures gibt es
  keinen kalibrierten Itempool; D bleibt als Vertragstest gekennzeichnet.
  „Verbindliches Gate": Auslöser bleibt nach Entscheidung manuell.

## Teil 57 — Jest-Issue, Teil 1: Fixtures, Matrizen P0, Coverage-Gate (2026-10-13)

Version `2026100723` (zusammen mit Teil 56 ausgeliefert). Auftrag:
Jest-Issue „Systematische Jest-Testschicht".

- **Fixtures** `vue3/tests/unit/fixtures/`: `nodeFactory`, `feedbackFactory`,
  `learningPathFactory`, `relationFactory`, `storeFactory` (Strings
  antworten mit ihrem Schlüssel, wenn ein Test keinen Text vorgibt),
  `conditionFactory`, `merge`, `knownDefect`. Keine Produktlogik.
- **Matrizen (P0):** `useStatusMessage` (Tabelle aller Codes, Teil- und
  Fehldaten, reaktiver Lebenszyklus), `MasterConditions` (2×2, beide
  Richtungen, Unabhängigkeit, Altdaten), `ProgressBar` (Grenzwerte, jeder
  Status × 0/Mitte/100; Werte außerhalb 0..100 als *ist*-Verhalten
  festgehalten – Spezifikation offen), neue Specs `feedbackNode`,
  `timed_dates` (Komponente und Store-Prüfung stimmen überein),
  `parent_node_completed`, `UserInformation`/`UserFeedbackBlock`
  (Fallback before_active→before_valid, ODER zwischen Spalten,
  Abschlusstexte je Zustand).
- **Befund (Entwurf `local_adele-issue-feedback-first-letter.md`):**
  `UserFeedbackBlock.capitalize()` verwirft den großgeschriebenen ersten
  Text. Test als `knownDefect` (übersprungen, sichtbar).
- **Befund, Ursache belegt:** Mit `coverageProvider: "v8"` lieferten zwei
  Läufe desselben Codes verschiedene Abdeckung (ProgressBar.vue 100 % und
  87,87 %, useStatusMessage.js 98,59 % und 67,6 %), auch `--runInBand`.
  Umgestellt auf Istanbul (`babel`): zweimal identisch. Die Zahlen sind
  niedriger, weil Istanbul die kompilierten Templates mitzählt.
- **Coverage-Gate** in `jest.config.js`: global die gemessene Basis
  (abgerundet), eigene Gates für die Matrix-Dateien. `npm test` scheitert,
  wenn die Abdeckung sinkt.
- **CI:** neuer Workflow `jest.yml` lädt Testreport, Log und lcov hoch; der
  Lauf in „Moodle Plugin CI" bleibt als Gate.
- **README:** Abschnitt „Tests: which layer proves what".
- **ESLint:** Für `vue3/` gibt es keine ESLint-Konfiguration im Repository
  (ESLint 9 findet keine `eslint.config.*`); nicht geprüft.
- **Mutationsproben (Jest):** `d`/`e` vertauscht → 6 rot; before_valid vor
  before_active → 3 rot; Master-Abschluss an Master-Zugang gebunden → 5 rot;
  UND-Verbinder entfernt → 3 rot; Balkenbreite halbiert → 26 rot. Jeweils
  zurückgesetzt, danach grün.
- **Jest:** 82 Suiten, 509 bestanden, 1 übersprungen (bekannter Fehler);
  vorher 385.
- **Offen (folgende Teile):** CustomNodeEdit-Matrix (A2), manuelle Zustände
  (C), feedbackNodeRestriction (D3), UserList/LearningPathList/userSearch
  (F3, G), Completion-Conditions (I), Graphstruktur (J), #574/#575 (L/M),
  Mutationsproben der Jest-Matrizen.
- **Prüfstand `2026100723` (Teile 56 und 57):** E2E enrol 6, mod 17,
  local 78 bestanden (Gesamtlauf nach dem letzten Seed-Stand),
  Story-Abdeckung in allen drei „complete"; PHPUnit local 402, enrol 57,
  mod 25; Jest 509 bestanden, 1 übersprungen (bekannter Fehler), Gate grün,
  zweimal gleich; phpcs im Moodle-Baum und PHPDoc-Checker ohne Befund;
  actionlint (inkl. `jest.yml`) ohne Befund. Kein Vue-Quellcode geändert,
  daher kein neuer Build.
  Ausgeliefert als „local_adele: E2E-Issue Paket 3 + Jest-Issue Teil 1".

## Teil 58 — Jest-Issue, Teil 2: Matrizen P1, drei Befunde (2026-10-13)

Version `2026100724`. Nur Jest-Tests, Coverage-Gate und Doku geändert; kein
Produktcode, kein PHP, keine E2E-Datei.

- **Neue Matrizen:** `CustomNodeEdit` (Sicht × Status × Zeitbedingung ×
  Feedback-Einstellungen; #574-Hooks und #575-Rolle/Name; manuelle Controls
  nur in der Lehrendensicht, nicht ohne Bearbeitungsrecht), manuelle
  Freigabe/Abschluss (je false→true→false, 2×2, Unabhängigkeit),
  `timed_duration` (Startmodus × Einheit × Menge, Store-Prüfung gleich,
  „0" als echte Wahl), `parent_courses` (1/1, 1/2, 2/2, 2/3), `specific_course`,
  `modQuiz` (Schwelle 0/49/50/51/100, außerhalb geklemmt), `catQuiz` (ohne
  catquiz, Test wählen, leeren, gespeicherter Wert, Schwelle setzen),
  Graphstruktur des Pfad-Editors (A, A→B, A∧B, A∨B, (A∧B)∨C, A∧(B∨C), keine
  doppelten Bezüge), `LearningPathList` (Sicht × Eigentum, ownerless),
  `userSearch` (Sicht × Hinzufügen/Entfernen/Übertragen, kein Treffer,
  Warnung, doppelt, Abbruch), `UserList` (0/Teil/100/Alternativroute ×
  Lehrende/Lernende mit „alle"/„nur eigene"), `feedbackNodeRestriction`.
- **Befunde (Entwürfe, Tests als `knownDefect`):**
  - `local_adele-issue-unknown-status-shows-play.md`: Knoten mit
    unbekanntem Status zeigt Play und Kurslink, `data-status` sagt
    „unknown".
  - `local_adele-issue-manual-checkboxes-share-id.md`: manuelle Freigabe
    und manueller Abschluss eines Knotens tragen dieselbe ID; das Label
    „Finish node manually" schaltet die Freigabe.
  - `local_adele-issue-editor-list-optimistic.md`: Bearbeiterliste ändert
    sich vor (und ohne) Antwort des Servers.
- **Festgehalten, nicht bewertet:** `catQuiz` – ein Eintrag mit der ID 0
  zählt als keine Wahl, der Elternskalen-Zweig ist über die Auswahl nicht
  erreichbar (der Server bietet keine ID 0 an). `feedbackNodeRestriction`
  nennt verborgene Bedingungen in der Information, `feedbackNode` nicht.
- **Mutationsproben (Jest):** Ring bei jeder Bedingung → 12 rot; Assistenz
  dupliziert fremde Pfade → 2 rot; Einheit 0 als leer → 1 rot; eine Zahl
  zu wenig angeboten → 5 rot; manueller Abschluss an die Freigabe gebunden
  → 5 rot. Jeweils zurückgesetzt.
- **Coverage-Gate:** weitere Dateien mit eigenem Gate; der globale Boden ist
  neu gemessen (Jest misst ihn ohne die Dateien mit eigenem Gate) und
  zweimal gleich bestätigt.
- **Jest:** 94 Suiten, 688 bestanden, 6 übersprungen (bekannte Fehler);
  nach Teil 1: 509.
- **Offen im Jest-Issue:** Store-Matrizen (`store.spec`/`store.mutations`),
  Zeitanzeige `TimeWarning` mit Fake-Timern (K), weitere #575-Semantik (M:
  `aria-expanded`/`aria-pressed`/Fokus in Dialogen), CI-Gate-Nachweis auf
  GitHub (lokal nicht prüfbar).
- **Prüfstand `2026100724`:** Jest 688 bestanden, 6 übersprungen, Gate grün
  (zweimal); PHPUnit local 402, enrol 57, mod 25; phpcs im Moodle-Baum und
  PHPDoc-Checker ohne Befund; E2E enrol 6 bestanden (Upgrade-Probe). Die
  übrigen E2E-Suiten nicht erneut: weder Produktcode noch E2E-Dateien
  geändert (Stand `2026100723`: mod 17, local 78).
  Ausgeliefert als „local_adele: Jest-Issue Teil 2".
