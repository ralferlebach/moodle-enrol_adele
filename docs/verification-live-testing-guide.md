# Verifikationsstand Runde 2 und Live-Testanleitung

**Stand:** Session 003, Teil 6
**Zweck:** Für jeden der sieben in Runde 2 als „plausibel, nicht einzeln
verifiziert" eingestuften Punkte dokumentieren, was inzwischen geklärt
wurde, was weiterhin offen ist, und — wo menschliche/Live-Testung
angezeigt ist — eine konkrete Anleitung dafür.

---

## Ehrliche Einordnung: was hat die Verifikation bisher verhindert?

Kurz: **Zeitpriorisierung, nicht technische Unmöglichkeit.** Alle sieben
Punkte ließen sich mit den bereits vorliegenden Mitteln (Code lesen,
`grep`, `install.xml` prüfen, Datei-Hashes vergleichen) tatsächlich
statisch klären — das ist in diesem Teil geschehen, in wenigen Minuten
und ohne neue Werkzeuge. In Runde 1/2 wurde die begrenzte
Verifikationszeit bewusst zuerst auf die P0-Befunde und die
sicherheitsrelevanten External-Function-Klassen verwendet, da dort der
größere Schaden bei Untätigkeit drohte. Die sieben Punkte hier sind
niedriger priorisiert worden — nicht, weil sie sich einer Prüfung
entzogen hätten, sondern weil die Zeit zuerst dorthin floss, wo IDOR- und
Zugriffskontroll-Lücken vermutet wurden (zu Recht, siehe G.3 und die drei
in G.8–G.10 gefundenen aktiven Bugs).

Echte, nicht nur zeitliche Grenzen bestehen dagegen für zwei der sieben
Punkte — dort reicht Code lesen nicht, siehe unten.

---

## Die sieben Punkte im Einzelnen

### 1. P1-8 — JSON ist unversioniert

**Jetzt geklärt (statisch, keine Live-Testung nötig):** Repo-weite Suche
nach `schemaversion`/`schema_version`/vergleichbaren Mustern in
`local_adele/classes` — kein Treffer. Bestätigt: kein Schema-Identifier,
keine Versionsnummer in den JSON-Feldern (`local_adele_learning_paths.json`,
`local_adele_path_user.json`). **Bestätigt wie im Review beschrieben.**

### 2. P1-9 (Detail über G.5 hinaus) — genaues Ausmaß des N+1-Problems

**Teilweise geklärt:** Das strukturelle Muster ist bereits über G.5
bestätigt (`get_records_sql()` statt Recordset, kein Batching). **Nicht
statisch klärbar:** wie stark sich das bei realistischen Datenmengen
tatsächlich auswirkt (Abfrageanzahl, Laufzeit) — das hängt von der
tatsächlichen Nutzer-/Lernpfadzahl einer echten Installation ab und lässt
sich nur durch Messung an einer befüllten Instanz beantworten.

→ **Live-Testung angezeigt, siehe Testanleitung A unten.**

### 3. P1-10 — jeder Enrolment-Event durchsucht alle Einbettungen

**Jetzt geklärt (statisch):**
`mod_adele\observer::sync_host_access_for_node_enrolment()` lädt mit
`$DB->get_records('adele', null, ...)` **ohne jede Einschränkung** —
wörtlich alle `mod_adele`-Aktivitäten site-weit, bei jedem einzelnen
Enrolment-Event. **Bestätigt wie im Review beschrieben**, sogar deutlicher
als vermutet (kein Kurs- oder Lernpfad-Filter überhaupt).

### 4. P1-11 — Lernpfadänderungen verarbeiten alle Nutzer synchron

**Jetzt geklärt (statisch):** `learning_path_update::updated_learning_path()`
läuft in einer synchronen `foreach`-Schleife über alle Nutzerpfade des
Lernpfads, im selben Request, ohne Ad-hoc-Task oder Batching — jeder
Durchlauf löst zusätzlich ein `user_path_updated`-Event pro Nutzer aus.
**Bestätigt wie im Review beschrieben.**

### 5. P1-12 — Aktivitätsspeicherung führt vollständige Teilnehmer-Sweeps aus

**Jetzt geklärt (statisch):** Drei Fundstellen in `mod_adele/classes/observer.php`
(Zeilen 356, 395, 436) rufen `get_enrolled_users()` für den gesamten Kurs
auf und verarbeiten anschließend jede/n Teilnehmer/in einzeln.
**Bestätigt wie im Review beschrieben.**

### 6. Abschnitt 9 — Frontend/Build

**Jetzt geklärt (statisch, drei Einzelbefunde):**

- **AMD-Artefakte:** SHA-256-Vergleich von `amd/src/app-lazy.js`,
  `amd/build/app-lazy.js`, `amd/build/app-lazy.min.js` und
  `amd/build/app-lazy.min.js.map` — alle vier **byteidentisch**
  (`68805e50...`). Bestätigt: Quelle, Build, Minifikat und Sourcemap sind
  wortwörtlich dieselbe Datei, kein echter Build-Schritt hat je
  stattgefunden.
- **`thirdpartylibs.xml`:** nicht vorhanden. Bestätigt.
- **`vue3/webpack.config.js`:** `publicPath: '/dist/'` sowie die
  veralteten `webpack-dev-server`-Optionen `noInfo`, `overlay`,
  `disableHostCheck`, `https`, `public` — alle wörtlich wie im Review
  zitiert vorhanden. Bestätigt.

**Nicht statisch klärbar:** ob der Frontend-Build mit echtem
Node/npm-Tooling tatsächlich (fehlerhaft oder funktionierend) läuft, und
ob der Dev-Server trotz der veralteten Optionen praktisch nutzbar ist.

→ **Live-Testung angezeigt, siehe Testanleitung B unten.**

### 7. Abschnitt 7 (Rest) — weitere Datenbankschema-Befunde

**Jetzt geklärt (statisch, über `db/install.xml`):**

- `local_adele_path_user.status`: `CHAR(255)` — bestätigt überdimensioniert
  für eine kleine Statusmenge (`active`/`archived`/…).
- `local_adele_learning_paths.visibility`: `INT(10)` — bestätigt
  überdimensioniert für einen booleschen/kleinen Enum-Wert.
- `mod_adele.adele.participantslist`: `CHAR(256)`, kommasepariert —
  bestätigt denormalisiert.
- `local_adele_path_user.course_id`: `NOTNULL`, aber **kein**
  Fremdschlüssel (im Gegensatz zu `user_id`, `learning_path_id`,
  `createdby`, die alle FKs haben) — bestätigt.
- Kein Revisions-/Schemaversion-Feld auf den JSON-Spalten — deckt sich mit
  P1-8.

**Positiver Gegenbefund, nicht im Review erwähnt:**
`local_adele_path_user` besitzt tatsächlich bereits einen Unique-Index
auf `(user_id, learning_path_id)` — die in G.18 dokumentierte fehlende
Eindeutigkeit betrifft also spezifisch `local_adele_lp_editors`, nicht das
gesamte Schema. Kein Widerspruch zum Review, aber eine Präzisierung: die
Lücke ist lokal, kein durchgängiges Muster.

---

## Zusammenfassung

| Punkt | Status |
|---|---|
| P1-8 | Bestätigt (statisch) |
| P1-9 (Detail) | Struktur bestätigt; Ausmaß braucht Live-Messung → Testanleitung A |
| P1-10 | Bestätigt (statisch), deutlicher als vermutet |
| P1-11 | Bestätigt (statisch) |
| P1-12 | Bestätigt (statisch) |
| Abschnitt 9 | Bestätigt (statisch); Build-Funktionsfähigkeit braucht Node/npm-Umgebung → Testanleitung B |
| Abschnitt 7 (Rest) | Bestätigt (statisch), ein positiver Gegenbefund (lp_editors-Lücke ist lokal) |

Von den ursprünglich elf offenen Punkten aus Runde 2 sind damit neun
vollständig statisch geklärt, zwei brauchen echte Live-Testung. Die
Arbeitsplan-Tabelle wurde um die Punkte G.20–G.25 für diese neu
bestätigten Befunde ergänzt.

---

## Testanleitung A — P1-9: tatsächliches Ausmaß des N+1-Problems

### Vorbereitung

1. Eine Testinstanz mit realistischer Datenmenge — mindestens 500–1.000
   aktive Nutzerpfade über mehrere Lernpfade verteilt (Test-Seed-Skript
   oder anonymisierte Kopie einer echten Instanz).
2. Slow-Query-Log oder einen Query-Profiler aktivieren (Moodles
   `$CFG->debug = DEBUG_DEVELOPER` plus `$CFG->debugsqltrace` oder ein
   externes Tool wie Blackfire/Xdebug-Profiler).

### Testschritte

1. Den nächtlichen `reconcile_task` manuell auslösen
   (Website-Administration → Server → Aufgaben → Geplante Aufgaben →
   „Jetzt ausführen" bei „Lernpfad-Einschreibungen abgleichen").
2. Abfrageanzahl und Gesamtlaufzeit protokollieren.
3. Denselben Lauf mit doppelter Datenmenge wiederholen, Wachstum der
   Abfrageanzahl im Verhältnis zur Datenmenge vergleichen (linear? Höher?).

### Ist-Verhalten (Hypothese, zu bestätigen)

Abfrageanzahl wächst mindestens linear mit der Zahl aktiver
Nutzerpfad-Paare, da `get_records_sql()` alles auf einmal lädt und pro
Paar weitere Einzelabfragen folgen (siehe G.5).

### Soll-Verhalten (nach Umsetzung von G.5)

Abfrageanzahl bleibt durch Batching/Recordset auch bei wachsender
Datenmenge in einem praktikablen Rahmen; Gesamtlaufzeit wächst nicht
mehr überproportional.

### Ergebnis dieser Messung eintragen in

`arbeitsplan.md`, G.5-Eintrag, als konkreter Datenpunkt zur
Priorisierung.

---

## Testanleitung B — Abschnitt 9: Frontend-Build tatsächlich lauffähig?

### Vorbereitung

1. Node.js/npm in der laut `vue3/package.json` (falls vorhanden)
   erwarteten Version installieren.
2. `local_adele/vue3` auschecken.

### Testschritte

1. `npm install` im Verzeichnis `vue3/` ausführen — auf Fehler prüfen.
2. Den in `vue3/webpack.config.js` definierten Build-Befehl ausführen
   (z. B. `npm run build`, genauer Name aus `package.json` zu entnehmen).
3. Prüfen, ob sich die neu erzeugten `amd/build/`-Dateien tatsächlich vom
   `amd/src/`-Original unterscheiden (Hash-Vergleich wie oben).
4. Den Dev-Server starten (`npm run serve` o. ä.) und prüfen, ob er trotz
   der veralteten `webpack-dev-server`-Optionen (`disableHostCheck`,
   `https`, `public`) tatsächlich erreichbar ist.

### Ist-Verhalten (Hypothese, zu bestätigen)

Unklar, ob der Build-Prozess überhaupt fehlerfrei durchläuft — die
byteidentischen Artefakte deuten darauf hin, dass er entweder nie
ausgeführt oder sein Ergebnis nie tatsächlich übernommen wurde.

### Soll-Verhalten

`npm install`/Build laufen fehlerfrei durch; `amd/build/*.min.js`
unterscheidet sich von `amd/src/*.js` (tatsächliche Minifikation); die
`.map`-Datei ist eine gültige, zur Minifikation passende Source Map, nicht
identisch mit dem Quellcode.

### Bei Fehlschlag

Fehlermeldung/Stacktrace für die weitere Fehlersuche sichern und in einem
neuen Issue-Entwurf dokumentieren (Muster wie die bestehenden 15
Entwürfe unter `docs/issues/`).

---

## Zusätzlich empfohlen (nicht explizit angefragt, aber naheliegend)

Diese Session hat mehrere Codeänderungen ausgeliefert
(`G-Q1a`, `G.3`/`G.8`–`G.10`, `C.2`/`C.3`), die in dieser Umgebung
ausschließlich über `php -l` und manuelle Codeprüfung verifiziert werden
konnten — nie gegen eine echte Moodle-Instanz. Vor einem produktiven
Rollout sollte mindestens Folgendes an einer Testinstanz durchgespielt
werden:

- Die neue Verwaltungsseite (`enrol/adele/manage.php`): Aufruf über
  Website-Administration → Plugins → Einschreibungsmethoden →
  Lernpfad-Einschreibung, Tabelle korrekt befüllt, „Neu berechnen"/„Hart
  löschen" funktionieren inklusive Bestätigungsdialog und
  Hintergrund-Task-Schwellwert (200 Nutzer/innen).
- Die drei neu behobenen IDOR-Lücken (`update_lp_animations.php`,
  `update_user_path_relation.php`, `get_learningpath.php`): jeweils mit
  zwei Testnutzer/innen versuchen, fremde Daten zu lesen/ändern — muss
  jetzt verweigert werden, die eigene Aktion muss weiterhin funktionieren.
- `enrol_manual`-Rückfallpfade sind entfernt (G-Q1a): `enrol_adele`
  probeweise deaktivieren, prüfen, dass keine neuen Einschreibungen mehr
  entstehen und die `debugging()`-Meldung sichtbar ist (mit
  `$CFG->debug = DEBUG_NORMAL` oder höher).

## Testanleitung C — C.4: Restore-Hooks (Prüfkriterium 5)

Der Neu-Kurs-Fall (Requirement A-13: Restore in einen neuen Kurs enthält
weder ADELE-Instanz noch konvertierte `manual`-Einschreibung) wird seit
Session 003 Teil 17 automatisiert getestet
(`enrol_adele/tests/backup_restore_test.php`). Manuell zu prüfen bleibt
nur noch der **Same-Course-Fall** (Session 003 Teil 19,
`restore_instance()`s Ausnahme für `backup::TARGET_CURRENT_ADDING`/
`TARGET_CURRENT_DELETING`) — Moodles genaues Verhalten gegenüber bereits
bestehenden Instanzen beim Wiederherstellen „in diesen Kurs" (ADDING-Modus)
war ohne Live-Instanz nicht sicher genug verifizierbar, um dafür ebenfalls
einen automatisierten Test zu schreiben, ohne erneut Gefahr zu laufen,
eine falsche Annahme zu testen statt das tatsächliche Verhalten.

### Vorbereitung

1. Lernpfad mit mindestens einem Zielkurs und mindestens einer aktiven
   ADELE-Einschreibung (Zielkurs-Instanz mit `user_enrolments`).
2. Vollständige Kurssicherung des Zielkurses erstellen
   (Kurs → Weitere → Kurs wiederverwenden → Sichern).

### Testschritte

1. **Dieselbe Sicherung in denselben Ausgangskurs zurückspielen**
   (Kurs → Weitere → Kurs wiederverwenden → Wiederherstellen → „In diesen
   Kurs“ — das entspricht `TARGET_CURRENT_ADDING`).
2. Direkt im Anschluss (ohne auf den nächtlichen Task zu warten) prüfen,
   ob die ADELE-Einschreibungen im Kurs korrekt sind (weder verdoppelt
   noch verschwunden).
3. Zur Kontrolle zusätzlich den nächtlichen Reconcile-Task manuell
   auslösen und erneut prüfen — das Ergebnis sollte identisch bleiben.

### Ist-Verhalten (Hypothese, zu bestätigen)

Schritt 2: `restore_instance()` erkennt `TARGET_CURRENT_ADDING`, stößt
sofort `reconciler::reconcile_learning_path()` an — der Kurs sollte direkt
nach dem Restore korrekt dastehen, nicht erst nach dem nächtlichen Task.

### Soll-Verhalten

Schritt 2/3: identisches, korrektes Ergebnis in beiden Fällen — der
Unterschied zur vorherigen (Teil 10) reinen Skip-Strategie ist nur die
**Geschwindigkeit**, mit der der korrekte Zustand erreicht wird, nicht das
Endergebnis (Selbstheilung, F-6/L-Q-09, gilt weiterhin als Sicherheitsnetz
für beide Fälle).

### Bei Abweichung

Falls Schritt 2 eine sichtbare Lücke zeigt, die erst durch Schritt 3
behoben wird: die `get_target()`-Erkennung greift nicht wie erwartet
(z. B. falscher Konstantenwert, oder `TARGET_CURRENT_ADDING`/`_DELETING`
sind nicht die einzigen relevanten Same-Course-Targets) — Stacktrace/
Fehlermeldung sichern und melden, nicht selbst versuchen zu reparieren.
