# Session 002 — Umsetzung, Wiederaufnahme nach lokal_adele-Upstream-Update, Host-Kurs-Einschreibung

**Ergebnis:** Rückfragen F-1…F-10 geklärt und Architektur festgeschrieben,
initiale Reconciliation-Engine (`enrol_adele` 0.1.1), Umbenennung und
Branch-Schema, korrekte Reapplikation auf lokal_adele-Upstream-Update samt
Produktions-Upgrade-Fix, neue Host-Kurs-Einschreibung für mod_adele-Optionen
2/3 (`enrol_adele` 0.1.2 / lokal_adele 0.4.5 / mod_adele 0.1.6).

> **Konvention — korrigiert gegenüber Session 001:** Ein Claude-Chat = eine
> Session, aber diese Grenze folgt dem tatsächlichen Chat-Fenster, nicht
> zwingend dem Kalendertag. Session 001 (2026-07-16) umfasst ausschließlich die
> Analyse- und Stub-Erstellungsarbeit vor der ersten sichtbaren Rückfragen-
> Antwort. Alles, was seither in diesem Chat-Fenster geschieht — auch über
> mehrere Tage und mehrere Arbeitsblöcke hinweg —, gehört zu **Session 002**
> und wird hier fortgeschrieben, nicht in weiteren Sitzungsdateien.
> Versionsnummern werden weiterhin nur bei funktionalen Änderungen erhöht.
> **Neu (Teil 5):** Ausgeliefert wird ausschließlich als Patch-ZIP je Plugin;
> die Dokumentation (Lasten-/Pflichtenheft, Arbeitsplan, Sitzungsprotokoll)
> wird nur noch im `enrol_adele`-ZIP unter `docs/` mitgeführt, nicht mehr als
> gesonderter Download.

---

## Teil 1 — Referenz-Plugins, Branch-Analyse, Architekturentscheidung

**Datum:** 2026-07-16
**Teilnehmer:** Ralf Erlebach, Claude
**Ergebnis:** Rückfragen F-1…F-10 geklärt, Architektur auf zustandslose
Reconciliation umgestellt (Grant-Tabelle verworfen), Lastenheft/Pflichtenheft
Fassung 2.0, Arbeitsplan

---

### 1. Analysierte Artefakte

| Artefakt | Version/Stand |
|---|---|
| `enrol_coursecompleted` | 2026060600 (Moodle 5.2) |
| `enrol_autoenrol` | 2025031400 (Moodle 4.5) |
| `enrol_oss` | 2026012201 (Moodle 5.1) |
| `enrol_campusonline` | 2026062201 (Moodle 5.1) |
| `local_adele` main | 0.4.2 — byte-identisch mit Session-001-Stand |
| `mod_adele` master | 0.1.4 — byte-identisch mit Session-001-Stand |
| `mod_adele`-Branches | `fix-enrolment-issue`, `fixing-ux-issue-mod_form`, `fix-linking-issues`, `adding-privacy-api` |

### 2. Befunde: die vier mod_adele-Branches

**`ralferlebach-fix-enrolment-issue` (Version 2026031600 — neuer als master)
ist der einzige zukunftsrelevante Branch.** Inhalt:

* Neue Einschreibeoption **3 = „Everyone who is subscribed to any node of the
  learning path"** (`enroll_any_nodes_participants()`) — genau die im Auftrag
  als „noch nicht implementiert" erwähnte Any-Node-Variante. In der
  Architektur als Option 3 fest eingeplant (A-15).
* Ruft `subscribe_user_to_learning_path($learningpath, $params)` **ohne
  `$courseid`** auf — nimmt die korrigierte User-Path-Identität vorweg. Gegen
  `local_adele` main (Signatur mit drei Pflichtparametern,
  `enrollment.php:65`) ist das ein fataler Fehler; **deshalb ist der Branch
  nicht integrierbar, bis dieses Vorhaben die local_adele-Seite liefert.**
  Das erklärt seinen Schwebezustand.
* Defensive Guards (`is_local_adele_available()`, Null-Prüfungen auf Records) —
  gleiche Philosophie wie unsere optionale Kopplung.
* `mod_form`: `participantslist` von `autocomplete` auf `select multiple
  size=3`, Option 3 ergänzt.
* Der Vergleichsfehler `participantslist == '1'` auf der rohen Kommaliste in
  `user_enrolment_created` (Zeile 105) ist **auch in diesem Branch noch
  unbehoben** → bleibt als A-14 bei uns.
* Kuriosum: `$plugin->release = '0.9'` bei `version = 2026031600` — beim
  Portieren die Versionierung von master fortführen, nicht die des Branches.

**Die drei übrigen Branches sind historisch** (Versionen 2024091800 bis
2024101400, alle älter als master 2025030400): `adding-privacy-api` (Privacy
ist längst in master, Branch kennt `completionlearningpathfinished` noch
nicht), `fix-linking-issues` und `fixing-ux-issue-mod_form` (mod_form-Stand von
master ist weiter). Keine Übernahme; im Lastenheft unter Abgrenzung vermerkt.

### 3. Befunde: die vier Referenz-Plugins

| Plugin | Übernommenes Muster |
|---|---|
| `enrol_coursecompleted` | Instanz-Identität über `customint1` = Quell-Entität (validiert unser Design); `manage.php` mit eigenen Capabilities; Hook `before_course_deleted`. **Anti-Pattern dokumentiert:** direktes `$DB->delete_records('enrol', …)` hinterlässt verwaiste `user_enrolments` — wir gehen über `delete_instance()`. |
| `enrol_autoenrol` | `ENROL_EXT_REMOVED_*`-Semantik, `role_unassign_all()` mit component/itemid, CLI-Sync |
| `enrol_campusonline` | **Das Kernmuster:** autoritative Quelle + idempotente Reconciliation (fehlend→einschreiben, suspendiert→aktivieren, unberechtigt→suspendieren) |
| `enrol_oss` | bestätigt Scheduled-Task-Sicherheitsnetz; sonst LDAP-spezifisch |

Keines der vier materialisiert Begründungen in einer eigenen Tabelle — zusammen
mit der Knoten-ID-Wiederverwendung (`getNodeId()` vergibt `höchste+1`, IDs
gelöschter Knoten werden recycelt) der Ausschlag für F-6.

### 4. Entscheidungen (Antworten auf F-1…F-10)

| # | Entscheidung |
|---|---|
| F-1 | Geteilte Zielkurse: Einschreibung bleibt aktiv, solange irgendein Knoten den Kurs gewährt. → A-6; durch mengenbasierte Soll-Zustands-Ermittlung automatisch erfüllt. |
| F-2 | Asymmetrie bestätigt: Knoten gesperrt → deaktivieren; LP gelöscht oder Zugang über mod_adele verloren → löschen. → A-7 |
| F-3 | Bug A-14 beheben; Branches analysieren (Abschnitt 2); Option 3 in Architektur einplanen (A-15). |
| F-4 | Suspendierung im Hostkurs ist keine Austragung → keine Reaktion (A-8). |
| F-5 | Verwaltungsseite mit beiden Aktionen: „Neu berechnen" und „Hart löschen" (A-5). |
| F-6 | **Zustandslose Reconciliation. Keine Grant-Tabelle, keine Log-Tabelle** — Nachvollziehbarkeit über Moodle-Standard-Logging (Kern-Events + drei eigene Events für Massenoperationen). Größte Änderung gegenüber Pflichtenheft 1.0. |
| F-7 | Hostkurs-Einschreibungen bleiben `enrol_manual` (A-10). `enrol_adele` besitzt ausschließlich Zielkurs-Einschreibungen. |
| F-8 | Kein doppeltes Setting: Codeanalyse zeigt, dass `local_adele/enroll_as_setting` nur an den zwei Zielkurs-Einschreibestellen verwendet wird — es wandert vollständig zu `enrol_adele/roleid`, Altsetting deprecated mit einmaliger Wertübernahme. |
| F-9 | Bestandsdaten bleiben unangetastet; keine Migration. Lieferobjekt 6 aus Fassung 1.0 gestrichen. |
| F-10 | Annahme bestätigt, mit technischer Präzisierung: Damit wiederhergestellte Kurse wirklich keine ADELE-Einschreibungen tragen, müssen `restore_instance()` (Skip-Strategie) und `restore_user_enrolment()` (No-op) **aktiv** implementiert werden — sonst wandelt Moodle sie je nach Restore-Einstellung in `manual`-Einschreibungen um. Ausnahme: Restore in denselben Kurs, wenn der Lernpfad existiert und den Kurs führt → mappen; Reconciliation heilt den Rest. |

Konsequenz aus F-6 nebenbei: `db/install.xml` bleibt dauerhaft leer, der
Privacy-`null_provider` bleibt dauerhaft korrekt — die für 0.2.0 geplante
Privacy-Umstellung entfällt ersatzlos.

### 5. Neue Invariante

Damit Reconciliation und harte Löschung nicht gegeneinander arbeiten:

```
Einschreibungen existieren  ⇔  local_adele_path_user.status = 'active'
```

Jede harte Löschung muss zuerst den User-Path deaktivieren, sonst schreibt der
nächste Reconciliation-Lauf sofort wieder ein. Details Pflichtenheft 2.5.

### 6. Verbleibende offene Punkte

| ID | Punkt | Blockiert |
|---|---|---|
| **R-1** | User-Path bei harter Löschung: Statuswechsel `inactive` (Empfehlung, Snapshot bleibt wie bei #446) oder Datensatz löschen? | nur Phase D (AP D.4) |
| **R-2** | `fix-enrolment-issue` in den Arbeitsbranch `ralferlebach-enrol-plugin` übernehmen (Empfehlung: ja — Option 3 und Signaturwechsel sind dort vorbereitet, getrennte Weiterführung erzeugt Doppelarbeit und Merge-Konflikte) oder separat lassen? | nur Phase D (AP D.6) |

Phasen B und C des Arbeitsplans sind von beiden unabhängig.

### 7. Geänderte/neue Dokumente

* `docs/lastenheft.md` → Fassung 2.0 (Anforderungen A-1…A-15)
* `docs/pflichtenheft.md` → Fassung 2.0 (Reconciliation statt Grants; Restore;
  Verwaltungsseite; Event-Matrix; Regelwerk A-4)
* `docs/arbeitsplan.md` → neu (Phasen A–D, Arbeitspakete, Reihenfolge)
* `README.md`, `CHANGELOG.md` → an Fassung 2.0 angepasst
* Code des Stubs: **unverändert** (weiterhin reine Installierbarkeit; die
  Roadmap-Änderung betrifft nur Doku und Planung)

---

## Teil 2 — Initiale Umsetzung (enrol_adele 0.1.1, local_adele 0.4.3, mod_adele 0.1.5)

**Ergebnis:** Reconciliation-Engine, A-4-Observer und die Aufrufer-Umstellung
in allen drei Plugins; auslieferbar als drei ZIPs.

## 1. Entscheidungen dieses Teils

| ID | Entscheidung |
|---|---|
| **R-1** | Bei Zugangsverlust nach A-4 wird die Zeile in `local_adele_path_user` **gelöscht**. Begründung des Auftraggebers: Bei erneuter Einschreibung re-deriviert sich der Fortschritt aus den im System vorliegenden Kurs- und Zeitzuständen. Bestätigt mit zwei dokumentierten Einschränkungen (Pflichtenheft 2.5): (a) manuelle Master-Overrides einer Lehrkraft leben nur im User-Path-JSON und gehen verloren; (b) `first_enrolled` wird neu gestempelt, zeitgesteuerte Restriktionsfenster beginnen von vorn. |
| **R-2** | Der Branch `ralferlebach-fix-enrolment-issue` fließt in den Arbeitsbranch ein: Option 3 („irgendein Knoten"), defensive Guards und der Aufruf der neuen Subscribe-Signatur sind in `mod_adele` 0.1.5 übernommen; der dort noch unbehobene Vergleichsfehler A-14 ist mitbehoben. |
| Versionierung | `mod_adele`: master trägt bereits 0.1.4, daher 0.1.5 (Moodle verlangt für Upgrades eine steigende Version). `local_adele` 0.4.3 und `enrol_adele` 0.1.1 wie beauftragt. |

## 2. Gelieferter Funktionsumfang

**enrol_adele 0.1.1** — `local\instance_manager` (Instanzen lazy je Lernpfad ×
Zielkurs, Rolle aus `enrol_adele/roleid` mit Übernahme aus dem Altsetting per
F-8); `local\reconciler` (`reconcile_user/learning_path/all`,
`purge_user/purge_learning_path`, alles idempotent, Löschung nur über
`delete_instance()`); Observer `user_enrolment_deleted` mit dem vollständigen
Regelwerk A-4 (Optionen 1/2/3, Rekursionsschutz, ADELE-Einschreibungen zählen
nie als tragend); nächtlicher Scheduled Task als Sicherheitsnetz; `sync()` in
der Plugin-Klasse; PHPUnit-Tests für die Prüfkriterien 1–3.

**local_adele 0.4.3** — neue Klasse `enrol_state` (Soll-Zustands-Funktion
`get_entitled_courseids()`, JSON-Hoheit bleibt hier; `request_reconcile()` /
`request_purge()` als optionale Kopplung nach L-Q-08);
`subscribe_user_to_learning_path()` mit optionalem `$courseid` (nur noch
Provenienz, Identität = Lernpfad × Nutzer, `buildsqlqueryuserpath()` ohne
`course_id`); Reconcile-Hook nach jedem Recompute (`relation_update`) und nach
`node_completion` (dort bleiben `first_enrolled`, Boundary-Scheduling und
Gruppenzuordnung aktiv); `delete_learning_path()` purgt zuerst und archiviert
die User-Path-Snapshots. Ohne installiertes `enrol_adele` greift überall das
unveränderte `enrol_manual`-Altverhalten.

**mod_adele 0.1.5** — Übernahme aus `fix-enrolment-issue`: Option 3 samt
Lang-Strings (en/de) und `mod_form`-Eintrag, defensive Guards; Bugfix A-14
(`explode` vor dem Options-Vergleich); alle Subscribe-Aufrufe ohne `courseid`;
Hostkurs-Einschreibung unverändert `enrol_manual` (A-10).

## 3. Verifikation

`php -l` und `moodle-cs` (Standard `moodle`) über alle neuen und geänderten
Dateien: null Fehler, null Warnungen. Die PHPUnit-Tests sind geschrieben, aber
in dieser Umgebung nicht ausgeführt (keine Moodle-Instanz) — erster Lauf in der
CI, sobald die Branches gepusht sind. Die CI-Workflows verweisen jetzt auf die
Arbeitsbranches `ralferlebach-enrol-plugin` beider Nachbar-Repos; bis diese
existieren, kann die Matrix nicht grün laufen.

## 4. Offen nach diesem Teil

Verwaltungsseite (A-5), Restore-Hooks (A-13), eigene Events, Behat — geplant
als 0.1.2, siehe Arbeitsplan (C.2–C.5). Deprecation von
`local_adele/enroll_as_setting` (D.5) und die Gesamtabnahme (D.8) folgen.

---

## Teil 3 — Umbenennung, Branch-Schema, blockierte Reapplikation

**Ergebnis:** Plugin-Anzeigename geändert, Arbeitsbranch-Schema auf `development`
vereinheitlicht (CI aller drei Plugins), lastenheft/pflichtenheft/README
nachgezogen. Die eigentlich beauftragte Reapplikation der local_adele-Änderungen
auf einen neueren Upstream-Stand konnte **nicht** durchgeführt werden — dazu
unten mehr.

### 1. Plugin-Umbenennung

Der Anzeigename von `enrol_adele` heißt jetzt nur noch „Lernpfad-Einschreibung"
(vorher „ADELE-Lernpfad-Einschreibung"), analog auf Englisch „Learning path
enrolment". Geändert: `pluginname`, `pluginname_desc`, `privacy:metadata`,
`reconciletask` sowie die beiden Capability-Beschreibungen (`adele:config`,
`adele:unenrol`) in `lang/en` und `lang/de`; README-Titel; der Admin-Menüpfad im
Pflichtenheft (Abschnitt 6). **Bewusst unverändert gelassen:** die
Instanzbezeichnung `„ADELE: {$a}"`, die pro Lernpfad im Kurs-Teilnehmerbereich
angezeigt wird — sie dient dort als visuelle Markierung, um ADELE-erzeugte
Einschreibungen von manuellen zu unterscheiden, und war nicht Gegenstand der
Anfrage. Die Komponenten-Kennung (`enrol_adele`, Namespace, Capability-Präfix
`enrol/adele:`) bleibt unangetastet — das ist Moodles technischer Bezeichner,
kein Anzeigename.

### 2. Branch-Schema

Alle drei Repositories arbeiten künftig auf einem Branch namens `development`
(statt des ursprünglich geplanten, nie angelegten `ralferlebach-enrol-plugin`).
Angepasst:

- `enrol_adele`/CI: beide Jobs, `extra_plugin_runners` zeigt jetzt auf
  `--branch development` für beide Nachbar-Repos.
- `local_adele`/CI: zusätzlich zur Branch-Umstellung ein Repository-Wechsel —
  der bisherige Verweis ging auf `Wunderbyte-GmbH/moodle-mod_adele` (Upstream,
  Branch `master`), jetzt auf `ralferlebach/moodle-mod_adele` (unseren Fork,
  Branch `development`), da lokal_adele 0.4.3 die neue
  `subscribe_user_to_learning_path()`-Signatur aus mod_adele 0.1.5 benötigt,
  die im Upstream-`master` nicht existiert.
- `mod_adele`/CI: `local_adele` fehlte dort bislang komplett als
  `extra_plugin_runner`, obwohl `version.php` schon immer eine harte
  Abhängigkeit deklariert (jetzt `2026071600`). Ergänzt:
  `--branch development ralferlebach/moodle_local_adele`. Ohne diese Ergänzung
  hätte die CI von mod_adele nie zuverlässig installieren können.

Alle drei YAML-Dateien wurden mit `actionlint` gegengeprüft (0 Befunde).

### 3. Blockierter Auftragsteil: Reapplikation auf neueren local_adele-Stand

Der Auftrag lautete, von einer angehängten, aktualisierten local_adele-Codebasis
ausgehend die Änderungen aus Teil 3 (Reconciliation-Hooks, `enrol_state`,
korrigierte User-Path-Identität, Lösch-Lifecycle) nachzuziehen. Die hochgeladene
Datei (`moodle-mod_adele-master_2_.zip`) enthielt jedoch:

- **mod_adele**, nicht local_adele — vermutlich ein Upload-Versehen.
- Byte-identisch mit dem bereits analysierten mod_adele-`master`-Stand
  (0.1.4, Version 2025030400) — enthält also ohnehin keine Änderung.

Zudem verwiesen die beiden mitgeteilten Branch-URLs für „local_adele" und
„mod_adele" beide auf `github.com/ralferlebach/moodle-mod_adele/tree/development`
— vermutlich ebenfalls ein Copy-Paste-Versehen, die lokal_adele-URL fehlt damit.

**Konsequenz:** `local_adele` bleibt in diesem Teil auf dem Stand von Teil 3
(0.4.3, basierend auf dem ursprünglich analysierten 0.4.2-Upstream); nur die
CI-Datei wurde aktualisiert (Abschnitt 2). Die eigentliche Reapplikation auf den
tatsächlich neueren Upstream-Stand steht aus, bis die korrekte Datei und die
korrekte Branch-URL vorliegen.

### 4. Ausgeliefert

- `enrol_adele` 0.1.1 (Umbenennung + CI-Fix; keine funktionale Codeänderung,
  daher keine Versionserhöhung)
- `mod_adele` 0.1.5 (nur CI-Fix; keine Codeänderung)
- `local_adele` 0.4.3 (nur CI-Fix; keine Codeänderung; Reapplikation offen)

### 5. Offene Punkte

| ID | Punkt |
|---|---|
| **E-8** | Korrekte, aktualisierte local_adele-Codebasis nachreichen. |
| **E-9** | Korrekte Branch-URL für `moodle_local_adele` bestätigen (die mitgeteilte URL zeigte auf `moodle-mod_adele`). |

---

## Teil 4 — Korrekte local_adele-Basis, Upgrade-Fix, Host-Kurs-Einschreibung

**Ergebnis:** local_adele 0.4.5 (Reapplikation der Teil-3-Architektur auf den
korrekten, neueren Upstream-Stand 0.4.4 + Fix eines produktiven
Upgrade-Blockers), enrol_adele 0.1.2 (Host-Kurs-Instanzen, zweite Instanzart
neben Zielkursen), mod_adele 0.1.6 (laufender Trigger für Optionen 2/3,
Host-Kurs-Einschreibung über enrol_adele statt enrol_manual).

### 1. Ausgangslage

Die zuvor hochgeladene Datei enthielt versehentlich `mod_adele` (byte-identisch
zum bereits bekannten Stand) statt der angekündigten local_adele-Aktualisierung.
Die diesmal hochgeladene Datei (`moodle_local_adele-main_6_.zip`) ist tatsächlich
lokal_adele 0.4.4 (2026072300) — neuer als der ursprünglich analysierte
0.4.2-Stand.

### 2. Produktiver Upgrade-Fehler (dml_write_exception)

Diagnostiziert: `db/upgrade.php`, Migrationsschritt 2026072200 (ticket #501),
löscht Duplikate über
`DELETE FROM {local_adele_path_user} WHERE id NOT IN (SELECT keepid FROM
(SELECT MAX(id) ... GROUP BY ...) keptrows)` — eine zweifach verschachtelte
Subquery auf derselben Tabelle innerhalb eines DELETE. Klassischer Auslöser für
MySQL/MariaDB-Fehler 1093 ("You can't specify target table ... for update in
FROM clause"); die Verschachtelung ist ein verbreiteter, aber kein
garantierter Workaround. Auf der Installation des Auftraggebers
(`moodle45_aliseadele`, MariaDB/MySQL über `mysqli_native_moodle_database`)
schlägt sie fehl, der Savepoint 2026072200 wird nie erreicht.

**Fix:** Ersetzt durch zwei getrennte Anweisungen — eine lesende
`SELECT id FROM t1 WHERE EXISTS (SELECT 1 FROM t2 WHERE ... AND t2.id > t1.id)`
(Selbst-Joins in einem SELECT sind unproblematisch) gefolgt von
`$DB->delete_records_list()` mit der so ermittelten ID-Liste. Sicher für
Installationen, die den alten Schritt bereits erfolgreich durchlaufen haben
(Savepoint bereits gesetzt → Block wird übersprungen, keine erneute Ausführung)
wie für solche, die daran hängengeblieben sind.

### 3. Architekturkollision mit ticket #501

Ticket #501 (unabhängig vom Auftraggeber bei Wunderbyte entstanden) behebt eine
Race Condition beim Anlegen von User-Paths, indem es einen Unique-Index auf
`(user_id, course_id, learning_path_id)` einführt — exakt das kursgebundene
Identitätsmodell, das dieses Projekt seit Teil 3 ablöst (Duplikate bei
Mehrfacheinbettung, ticket #433). Beide Fixes sind für sich genommen richtig,
lösen aber unterschiedliche Probleme auf unvereinbaren Datenmodellen.

**Auflösung:** Neuer Migrationsschritt 2026072301 baut auf 2026072200 auf:
löscht den alten Dreier-Index, dedupliziert die verbliebenen kursgebundenen
Duplikate je `(user_id, learning_path_id)` (höchste ID gewinnt, gleiche
Begründung wie 2026072200), legt den neuen Zweier-Index an. Die
race-sichere Insert-Then-Catch-Logik aus #501 (`enrollment.php`) bleibt
erhalten, nur auf das neue Tripel angepasst. `db/install.xml` zeigt für
Frischinstallationen direkt den Zielzustand.

### 4. Reapplikation der Teil-3-Architektur

Alle Integrationsstellen (`enrol_user_into_node()`, `node_completion.php`,
`delete_learning_path()`) waren von den übrigen, unabhängigen
Weiterentwicklungen der Codebasis (#492 Namenseindeutigkeit, #471/#472/#487
Besitzer-/Editoren-Modell, #493 Sprachumschaltung, #461 Fortschrittsberechnung,
#495/#496/#498 Quiz-Events) unberührt geblieben — Merge war deckungsgleich mit
Teil 3, keine Anpassung der lokal_adele-Logik selbst nötig außer an den
genannten drei Stellen plus `enrollment.php`/`buildsqlqueryuserpath()`.

### 5. Issues verifiziert

| Issue | Bezug |
|---|---|
| **#477** „Manuell entfernte Kursteilnehmer bleiben als Lernpfadnutzer" | = Anforderung A-4; durch das bestehende Regelwerk in `enrol_adele\observer` bereits gelöst, sobald ausgerollt |
| **#486** „eigene Einschreibemethode für AdeLe (enrol_adele)" | Ursprungsticket dieses gesamten Projekts — bestätigt die Zielrichtung |
| **#501** „Aktive Benutzer-Lernpfade nicht gegen Duplikate abgesichert" | siehe Abschnitt 3 — Konflikt aufgelöst, nicht einfach übernommen |

### 6. Neue Anforderung: Host-Kurs-Einschreibung über enrol_adele

Umgesetzt wie im Auftrag beschrieben:

- **Laufender statt einmaliger Trigger** für Optionen 2/3: neue Observer auf
  `user_enrolment_created` UND `user_enrolment_deleted` (letzterer neu bei
  mod_adele), site-weit registriert, da der auslösende Kurs typischerweise ein
  Node-Kurs ist, nicht der Host-Kurs. Jedes Event berechnet die Berechtigung
  frisch aus dem aktuellen DB-Zustand (nicht ereignisgebunden), da ein
  Node-Kurs von mehreren Knoten referenziert werden kann.
- **Host-Kurs-Einschreibung über `enrol_adele`** statt `enrol_manual`, für
  Fall 2/3. Neue zweite Instanzart in `enrol_adele` (`instance_manager::
  KIND_HOST`, unterschieden von `KIND_TARGET` über `customint2`), damit ein
  Kurs, der zufällig gleichzeitig Host und Node-Kurs desselben Lernpfads ist,
  zwei unabhängige Instanzen statt einer Kollision bekommt.
  `reconciler::reconcile_host_user()` ist rein mechanisch — die Berechtigung
  liefert `mod_adele`, da nur dort die Zuordnung Kurs → Option → Lernpfad
  bekannt ist.
- Fall 1 unverändert `enrol_manual` (Entscheidung F-7/A-10 bleibt bestehen).
- Ohne installiertes `enrol_adele`: Fallback auf den bisherigen
  `enrol_manual`-Sweep (L-Q-08).

Details siehe Pflichtenheft Abschnitt 1a.

### 7. mod_adele-Issue #11 — nicht abschließend geklärt

Der als Anhang verlinkte Screenshot war über die verfügbaren Werkzeuge nicht
abrufbar (URL-Länge). Recherche im Code ergab: kein `message_send()`-Aufruf in
`local_adele`; die vage Fehlermeldung „Message was not sent" ist ein generischer
Moodle-Kern-Hinweis, keine adele-eigene Meldung. Arbeitshypothese (unbestätigt):
Die e-Mail-„Willkommensnachricht"-Funktion von `enrol_manual`, ausgelöst durch
die allererste Einschreibung im Kurs, scheitert am Messaging-Subsystem der
Installation — passt zum „nur beim ersten Mal"-Muster, da bei jedem weiteren
Speichern niemand mehr neu eingeschrieben wird. Als offener Punkt E-11
dokumentiert; zur endgültigen Klärung wird der tatsächliche Fehlertext (Debug-
Modus aktivieren) oder ein alternativ zugänglicher Screenshot benötigt.

### 8. Neues GitHub-Issue (Dokumentationszweck)

Entwurf im Stil von #501 erstellt für den mod_adele-Sachverhalt aus Abschnitt 6
(laufender Trigger + Host-Kurs-Einschreibung über enrol_adele), als separates
kopierbares Markdown geliefert.

### 9. Ausgeliefert

- `enrol_adele` 0.1.2 (Host-Kurs-Instanzen, `reconcile_host_user()`/
  `purge_host_user()`, neuer Lang-String, Test für den Host-Lebenszyklus)
- `local_adele` 0.4.5 (Upgrade-Fix, Identitätsmigration abgeschlossen,
  Teil-3-Architektur reapplied)
- `mod_adele` 0.1.6 (laufender Options-2/3-Trigger, Host-Einschreibung über
  enrol_adele, neuer `user_enrolment_deleted`-Observer)
- mod_adele-Issue-Entwurf (Markdown, kopierbereit)

### 10. Offene Punkte nach diesem Teil

Siehe Pflichtenheft Abschnitt 8: E-10 (purge_host_user ohne automatischen
Trigger), E-11 (Issue #11 ungeklärt). Weiterhin unverändert offen: 0.1.2-Umfang
aus Teil 3 (Verwaltungsseite A-5, Restore-Hooks A-13, eigene Events) — durch die
Versionsnummer-Kollision mit den heutigen Host-Kurs-Änderungen wird dieser
künftige Umfang zu **0.1.3** verschoben.

---

## Teil 5 — Bugfix instance_manager.php, Session-Umstrukturierung, neue Lieferkonvention

**Ergebnis:** Der aus Teil 4 stammende, durch einen fehlgeschlagenen
Tool-Aufruf verursachte Defekt in `instance_manager.php` ist behoben und
gegenverifiziert; die Sitzungsdokumentation ist rückwirkend in Session 001
(reine Stub-Vorarbeit) und Session 002 (alles Weitere in diesem Chat-Fenster)
aufgeteilt; ab sofort werden ausschließlich Patch-ZIPs mit eingebetteter
Dokumentation ausgeliefert, keine gesonderten `.md`-Downloads mehr.

### 1. Bugfix

`instance_manager.php` enthielt nach Teil 4 nicht die vorgesehenen
`KIND_TARGET`/`KIND_HOST`-Konstanten: Der ursprüngliche Schreibversuch war an
einem clientseitig abgelehnten Tool-Aufruf (fehlendes Pflichtfeld) gescheitert,
*bevor* er ausgeführt wurde; ein direkt danach abgesetzter `php -l`-Aufruf hat
daraufhin nur die unveränderte Altdatei geprüft und fälschlich „0 Syntaxfehler"
gemeldet. Das zog reale Fehler nach sich: `reconciler.php` referenzierte
bereits `instance_manager::KIND_HOST`/`KIND_TARGET`, die es zu diesem
Zeitpunkt gar nicht gab — das Plugin wäre nicht lauffähig gewesen. Behoben
durch tatsächliches Neuschreiben der Datei plus anschließender inhaltlicher
Gegenprüfung (`grep`-Treffer der erwarteten Konstanten UND `php -l`, nicht nur
Tool-Erfolg).

**Lehre:** Nach jedem sicherheitsrelevanten Schreibvorgang wird künftig der
tatsächliche Dateiinhalt gegengelesen (Stichwort-`grep` auf erwarteten Inhalt),
nicht nur das Ergebnis des Schreibaufrufs selbst vertraut — insbesondere nach
einem vorherigen Fehler in derselben Sequenz.

### 2. Sitzungsdokumentation neu aufgeteilt

Auf Wunsch des Auftraggebers: Alles, was in diesem Chat-Fenster geschieht,
gehört zu **Session 002** — unabhängig vom Kalendertag. `session-001.md` ist
rückwirkend auf die reine Analyse- und Stub-Erstellungsarbeit (vor der ersten
sichtbaren Rückfragen-Antwort) gekürzt. Der bisherige Inhalt „Teil 2" bis
„Teil 5" von `session-001.md` bildet jetzt `session-002.md`, Teil 1 bis Teil 4;
dieser Eintrag ist Teil 5.

### 3. Neue Lieferkonvention

Ab sofort: ausschließlich Patch-ZIPs je Plugin, keine gesonderten
Dokument-Downloads mehr. Lastenheft, Pflichtenheft, Arbeitsplan und
Sitzungsprotokoll leben ausschließlich unter `enrol_adele/docs/` und werden
nur noch als Teil des `enrol_adele`-ZIPs sichtbar.

---

## Teil 6 — Repo-Hygiene vereinheitlicht, Issue-Entwurf-Auslieferung korrigiert

**Ergebnis:** `.gitignore`/`.gitattributes` aus einer vom Auftraggeber
bereitgestellten Vorlage auf alle drei Plugins ausgerollt; der in Teil 4
erstellte mod_adele-Issue-Entwurf war unter die neue „keine gesonderten
Downloads"-Regel gefallen und wurde erneut bereitgestellt.

### 1. Repo-Hygiene

Der Auftraggeber stellte eine projektweite `.gitignore`/`.gitattributes`-
Vorlage bereit (OS-/IDE-/Node-/Vue-/PHP-Artefakte, Binärdatei-Behandlung,
`export-ignore`-Liste für Entwickler-Artefakte). Ausgerollt auf alle drei
Repos:

- **`enrol_adele`**: Vorlage übernommen, ergänzt um `/build/` (Zielverzeichnis
  des eigenen Makefile-`zip`-Targets, in der Vorlage nicht enthalten).
- **`mod_adele`**: Vorlage komplett neu übernommen (vorher nur eine
  10-Byte-`.gitignore` mit `.DS_Store`, keine `.gitattributes`).
- **`local_adele`**: Auffällig — der zuletzt hochgeladene Upstream-Stand
  (main, 0.4.4) enthielt **weder** `.gitignore` **noch** `.gitattributes`,
  obwohl der ursprünglich analysierte 0.4.2-Stand noch eine `.gitignore`
  hatte. Beide Dateien ergänzt, obwohl nur `enrol_adele`/`mod_adele`
  ausdrücklich genannt waren — sonst hätte eines von drei Repos die
  vereinheitlichte Hygiene nicht mitgetragen.

**Korrektur beim Übernehmen:** Die Vorlage schrieb `makefile` (klein) in der
`export-ignore`-Liste; die tatsächliche Datei heißt `Makefile` (nur in
`enrol_adele` vorhanden). Auf einem case-sensitiven Dateisystem — wie es
`git archive`/GitHub Actions verwenden — hätte die kleingeschriebene Variante
nicht gegriffen. Groß-/Kleinschreibung entsprechend korrigiert.

Keine funktionale Änderung, keine Versionserhöhung in einem der drei Plugins.

### 2. Issue-Entwurf erneut bereitgestellt

Der in Teil 4 erstellte mod_adele-Issue-Entwurf (kopierbares Markdown für
GitHub) wurde nach Einführung der „keine gesonderten Downloads"-Regel
versehentlich nicht mehr mitgeliefert — die Regel war für die Projektdokumentation
(Lasten-/Pflichtenheft, Arbeitsplan, Sitzungsprotokoll) gedacht, die ohnehin
im `enrol_adele`-ZIP eingebettet ist, nicht für Artefakte wie einen
GitHub-Issue-Text, der zum Kopieren in eine fremde Oberfläche bestimmt ist und
sich nicht sinnvoll „nur im ZIP verstecken" lässt. Klargestellt: Ein Ausdruck
dieser Art bleibt weiterhin ein gesonderter Download; zusätzlich zur
Rückverfolgbarkeit liegt eine Kopie unter `enrol_adele/docs/issues/` im Repo.

---

## Teil 7 — Vier Rückfragen zum Host-Kurs-Verhalten, vier Issues, Arbeitsplan-Update

**Ergebnis:** Vier konkrete Lücken im bestehenden Host-Kurs-Mechanismus
(Fall 2/3) identifiziert und als Issue-Entwürfe dokumentiert; Arbeitsplan um
Phase F erweitert; ausführliche Neufassung von ticket #486 als
Referenzspezifikation erstellt. Keine Codeänderung in diesem Teil — reine
Analyse, Issue-Formulierung, Planung.

### Vier Rückfragen, vier Befunde

| # | Frage des Auftraggebers | Befund |
|---|---|---|
| 1 | Laufen Fall-2/3-Einschreibungen über `enrol_adele`, nicht mehr manuell? | **Bereits abgedeckt** — `reconcile_host_user()` und die Umstellung von `subscribe_user_course()` (Teil 4) leisten genau das. Kein neues Issue nötig. |
| 2 | Werden Nutzer, die den Lernpfad verlassen, auch aus Fall-2/3-Einschreibungen ausgetragen? | **Lücke bestätigt.** `purge_user()` räumt nur Zielkurs-Instanzen ab; `purge_host_user()` existiert (seit 0.1.2), wird aber nirgends aufgerufen — bereits als E-10 dokumentiert, jetzt mit Issue-Entwurf. |
| 3 | Können Lehrkräfte den Host-Kurs-Zugang bei Fall 2/3 abschwächen (verdeckt/keine Einschreibung)? | **Neue Lücke.** Es gibt keinen Konfigurationsweg; jede qualifizierende Node-Kurs-Mitgliedschaft erzwingt aktiven, sichtbaren Host-Kurs-Zugang. Neuer offener Punkt E-12. |
| 4 | Werden konkurrierende Einschreibungen bei Mehrfacheinbettung sauber priorisiert? | **Neue Lücke.** `sync_host_access_for_node_enrolment()` ruft `reconcile_host_user()` pro Embedding einzeln auf; teilen sich zwei Embeddings dieselbe Host-Instanz mit widersprüchlicher Berechtigung, gewinnt nicht-deterministisch der letzte Aufruf. Neuer offener Punkt E-13. |

### Vier Issue-Entwürfe (`docs/issues/`)

- `enrol_adele-issue-host-purge-on-leave.md` — neue Methode
  `purge_all_host_user()`, verdrahtet in `enrol_adele\observer`s
  A-4-Zweig.
- `enrol_adele-issue-host-visibility.md` — neues Aktivitäts-Setting
  `hostenrolmentmode` (`visible`/`hidden`/`none`) in `mod_adele`, erfordert
  Schema-Änderung.
- `enrol_adele-issue-host-priority.md` — Aggregation vor Anwendung statt pro
  Embedding; Prioritätsregel „großzügigste Option gewinnt", konsistent mit
  Entscheidung F-1/A-6.
- `local_adele-issue486-ausfuehrlich.md` — ausführliche Neufassung des
  ursprünglichen Stichpunkt-Tickets #486, als vollständige Referenz für das,
  was inzwischen tatsächlich gebaut wurde.

### Arbeitsplan

Neue Phase F ergänzt (vier Arbeitspakete F.1–F.4) mit
Reihenfolge-Empfehlung: F.1 zuerst (eigenständig, schließt eine
Sicherheitslücke), F.2 vor F.3 (Prioritätsregel braucht die
Sichtbarkeitsdimension), F.4 jederzeit unabhängig. Pflichtenheft Abschnitt 8
um E-12/E-13 ergänzt, E-10 auf „Issue formuliert" aktualisiert.

### Ausgeliefert

Nur Dokumente (vier Issue-Entwürfe + Arbeitsplan-/Pflichtenheft-Update), keine
neuen Plugin-ZIPs — kein Code wurde in diesem Teil geändert.

---

## Teil 8 — Erster echter CI-Lauf, kritischer Aktivierungs-Bug behoben

**Ergebnis:** Der erste tatsächliche CI-Lauf für `enrol_adele` (Moodle
4.5.12+, PHP 8.1.34, PostgreSQL 13.23) deckte zwei unabhängige, echte Bugs
auf. Beide behoben: `enrol_adele` 0.1.3, `local_adele` 0.4.6.

### Befund 1 — kritisch: Plugin war nie aktiviert

Moodle deaktiviert Einschreibe-Plugins standardmäßig, bis sie explizit in
`$CFG->enrol_plugins_enabled` eingetragen werden. `enrol_adele` hatte kein
`db/install.php`, das das nachholt — und da das Plugin bewusst kein
lehrkraft-seitiges „Instanz hinzufügen" besitzt (`can_add_instance() =
false`), gab es auch keine andere Stelle, an der ein Admin auf die fehlende
Aktivierung gestoßen wäre. `reconciler::is_active()` prüft
`enrol_is_enabled('adele')` und bricht bei `false` still ab — kein Fehler,
kein Log-Eintrag, einfach gar keine Wirkung. Erklärt alle drei
Testfehlschläge (`reconcile lifecycle shared course`,
`purge learning path is isolated`, `reconcile host user lifecycle`):
Jedes Mal derselbe Effekt — `reconcile_*()` lief leer durch.

**Fix:** `db/install.php` nach Vorbild von `enrol_coursecompleted`
(bereits bei der ursprünglichen Referenz-Plugin-Analyse dokumentiert), plus
Upgrade-Schritt 2026072302 für bereits installierte Standorte — wichtig,
da das eigene Test-Moodle des Auftraggebers (`moodle45_aliseadele`) bereits
eine ältere Version installiert hat und sonst dauerhaft inaktiv geblieben
wäre.

### Befund 2 — echter, vorbestehender local_adele-Bug, durch unseren Test aufgedeckt

`test_host_course_removal_rules()` löst über den echten Moodle-Data-
Generator eine Einschreibung im Hostkurs aus — das triggert real
`mod_adele_observer::user_enrolment_created`, welcher real
`local_adele\enrollment::subscribe_user_to_learning_path()` aufruft, was
wiederum ein echtes `user_path_updated`-Event auslöst und in
`relation_update::subscribe_user_starting_node()` läuft. Diese Funktion las
`$node['type']` **ohne** `??`-Fallback — im Gegensatz zur fast identischen
Prüfung wenige Zeilen darüber, die den Fallback korrekt setzt. Unser
synthetisches Test-Fixture (`plant_state()`) hatte nie ein `type`-Feld
gesetzt, weil unsere eigenen Reconciler-Tests es nie gebraucht hatten —
bis der Test über den echten Event-Kaskaden-Pfad lief.

**Fix, zweigleisig:** (a) Test-Fixture um `'type' => 'courseNode'` ergänzt
(der eigentliche Reconciler-Test soll sich nicht auf einen local_adele-Bug
verlassen müssen). (b) `relation_update.php` defensiv mit `?? ''`
nachgezogen, konsistent mit der Schwesterprüfung — ein echter, potenziell
auch produktiv auftretender Bug, den wir gefunden haben, während wir ohnehin
schon in der Datei arbeiteten.

### Ausgeliefert

`enrol_adele` 0.1.3, `local_adele` 0.4.6. `mod_adele` unverändert (0.1.6) —
eigene CI-Rückmeldung steht laut Auftraggeber noch aus, wird in der nächsten
Iteration behandelt.

---

## Teil 9 — `make check` reparieren, Makefile-Vorlage übernommen

**Ergebnis:** `make check` scheiterte, weil das alte Makefile dieses Target
schlicht nicht kannte (nur `checks`, Plural). Die vom Auftraggeber
bereitgestellte Makefile-Vorlage (Original für `mod_elang`) wurde für
`enrol_adele` angepasst und als neue Basis übernommen.

**Angepasst:** `PLUGIN_NAME`/`PLUGIN_REL` auf `enrol_adele`/`enrol/adele`;
`lint-mustache` und `fix-phpdoc` um Guards ergänzt, die sauber überspringen,
wenn `templates/` bzw. `tools/*.php` fehlen (enrol_adele hat keins von
beidem) — im Stil der bereits in der Vorlage vorhandenen Guards für
`amd/src/`. Die Pfaderkennung (`MOODLE_ROOT` zwei Ebenen über dem
Plugin-Checkout) funktioniert unverändert, da `enrol/adele` — wie
`mod/elang` — exakt zwei Ebenen unter der Moodle-Wurzel liegt.

**Bewusst ergänzt, nicht Teil der Vorlage:** `zip`/`clean`/`link`/`unlink` —
die Vorlage deckt nur Checks/Fixes/Tests ab, kein Paketieren. Da diese vier
Targets nicht mit der Vorlage kollidieren, wurden sie aus dem alten
Makefile übernommen (Variable `PLUGIN_DIR` dabei umbenannt zu
`PLUGIN_BASENAME`, da die Vorlage `PLUGIN_DIR` bereits mit anderer Bedeutung
— absoluter Pfad statt bloßem Verzeichnisnamen — belegt).

**Verifiziert:** `make -n` für alle zwölf Targets (fehlerfrei aufgelöst),
`make zip` real ausgeführt (korrekt strukturiertes ZIP mit `adele/` als
Wurzelordner), `make clean` räumt korrekt auf. Die Moodle-abhängigen
Targets (`lint-phpdoc`, `phpunit`, `lint-js`, `amd`) lassen sich in dieser
Umgebung mangels Moodle-Checkout nicht real ausführen, nur die
Pfadauflösung wurde per Dry-Run bestätigt.

Keine funktionale Änderung am Plugin, keine Versionserhöhung.

---

## Teil 10 — Zweiter CI-Rückkopplungs-Fix: Test-Isolation statt Plugin-Bug

**Ergebnis:** Nach den 0.1.3-Fixes lief ein echter CI-Durchlauf gegen
MariaDB (Moodle 4.5.12+, PHP 8.2.30) mit nur noch einem Fehlschlag:
`test_host_course_removal_rules`, „Failed asserting that false is not
false" bei der Prüfung der Zielkurs-Einschreibung direkt nach
`reconcile_user()`.

### Ursache

Kein Plugin-Bug, sondern eine Nebenwirkung des vorherigen Fixes: Der
`user_enrolment_created`-Aufruf des Tests löst — wie beabsichtigt — den
mod_adele-Observer aus, der über local_adele erneut subscribed. Das
triggert synchron local_adele's **echte** Completion-/Restriction-
Neuberechnung (`updated_single()`), die den von uns manuell gesetzten
„accessible"-Status überschreibt — unser Fixture-Knoten trägt keine echten
Bedingungsdaten, also berechnet die reale Pipeline typischerweise „noch
nicht zugänglich". Vor dem Type-Fix aus Teil 8 unterbrach der Absturz diese
Kaskade, bevor sie etwas überschreiben konnte — der jetzige Fehlschlag ist
also die vorherige stille Maskierung eines Test-Isolationsproblems, keine
neue Regression.

### Fix

Direkt vor dem eigenen `reconciler::reconcile_user()`-Aufruf des Tests wird
der Knotenstatus über den bereits vorhandenen `set_node_status()`-Helfer
erneut auf „accessible" gesetzt — unabhängig davon, was die Kaskade
zwischenzeitlich geschrieben hat. Reine Testkorrektur, keine funktionale
Änderung, **kein Versionsbump** (wie vom Auftraggeber verlangt).

### Ausgeliefert

`enrol_adele` weiterhin 0.1.3 (unverändert), nur `tests/reconciler_test.php`
gepatcht.

---

## Teil 11 — Abhängigkeitsanforderung gelockert (ohne Versionsbump)

**Meldung des Auftraggebers:** Auf der Moodle-Plugin-Übersichtsseite
„Nichtverfügbare fehlende Abhängigkeiten / Nicht im Plugin-Verzeichnis:
local_adele".

**Ursache:** `enrol_adele` 0.1.3 verlangte `local_adele` ≥ 2026072302
(0.4.6). Da `local_adele` ein privates, nicht auf moodle.org gelistetes
Plugin ist, kann Moodle bei einer nicht erfüllten Anforderung keinen
automatischen Installationslink anbieten und meldet stattdessen genau
diesen Text.

**Prüfung:** Der einzige Unterschied zwischen local_adele 0.4.5 und 0.4.6
ist der defensive `?? ''`-Fix in `subscribe_user_starting_node()` aus
Teil 8 — lokal_adele-interne Robustheit, auf die `enrol_adele` selbst nie
zugreift. Das tatsächlich nötige Minimum ist 0.4.5 (2026072301) — dort
wurde die Identitätsmigration abgeschlossen, auf die `enrol_state` und der
Reconciler wirklich angewiesen sind. `mod_adele` verlangt bereits seit
Teil 5 exakt diese Version — die Anforderung war also nur bei `enrol_adele`
unnötig strikt, nicht grundsätzlich falsch dimensioniert.

**Fix:** `version.php`s `$plugin->dependencies['local_adele']` auf
`2026072301` gelockert. `$plugin->version`/`$plugin->release` unverändert
(0.1.3/2026072302), wie verlangt.

**Wichtiger Vorbehalt:** Diese Lockerung behebt nur eine unnötig strikte
Anforderung. Ist lokal_adele auf der betroffenen Installation noch unter
0.4.5, funktioniert das Zusammenspiel weiterhin nicht korrekt — dafür
bräuchte es tatsächlich ein Upgrade von local_adele, das sich nicht durch
eine Versionsdeklaration umgehen lässt.

### Ausgeliefert

`enrol_adele` weiterhin 0.1.3 (Version/Release unverändert), nur
`version.php`s Abhängigkeitszeile gepatcht.

---

## Teil 12 — CI grün bestätigt, Issues #19–23 verankert, F.1/#21 umgesetzt

**Ergebnis:** Auftraggeber bestätigt: CI für `local_adele` und `enrol_adele`
beide grün. Die fünf zuvor als Entwürfe gelieferten Issues sind jetzt echte
Tickets im mod_adele-Repository (#19–23) — alle Referenzen in Pflichtenheft
und Arbeitsplan darauf umgestellt. Erste Umsetzung aus Phase F: `enrol_adele`
0.1.4 mit `purge_all_host_user()` (löst F.1 / mod_adele #21 / Pflichtenheft
E-10).

### Issues #19–23 zugeordnet

| # | Titel | Entspricht | Status |
|---|---|---|---|
| [#19](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/19) | Einschreibung in Lernpfad an beliebiger Stelle ("Fall 3") | unser Fall-3-Issue | bereits umgesetzt (Teil 3) |
| [#20](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/20) | Fortlaufende Einschreibung von neuen Lernpfad-Nutzern in den Host-Kurs | unser Live-Trigger-Issue | bereits umgesetzt (Teil 5) |
| [#21](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/21) | Austragung automatischer Einschreibungen beim Verlassen des Lernpfades | unser Purge-on-Leave-Issue (E-10) | **umgesetzt in diesem Teil** |
| [#22](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/22) | Ein-/Ausschreibungen im Fall 2 & 3 konfigurierbar machen | unser Visibility-Issue (E-12) | offen (Phase F.2) |
| [#23](https://github.com/Wunderbyte-GmbH/moodle-mod_adele/issues/23) | Konkurrierende Fall-2/3-Einbettungen berücksichtigen | unser Priority-Issue (E-13) | offen (Phase F.3) |

### Umsetzung F.1 / #21

`reconciler::purge_all_host_user(int $learningpathid, int $userid)` neu —
iteriert über alle `KIND_HOST`-Instanzen eines Lernpfads (nicht nur eine
bekannte, im Unterschied zum bestehenden `purge_host_user()`) und trägt den
Nutzer überall aus, wo eine Einschreibung besteht. Verdrahtet in
`enrol_adele\observer::user_enrolment_deleted()`s bestehenden A-4-Zweig,
direkt nach dem schon vorhandenen `purge_user()`-Aufruf.

Neuer Test `test_leaving_learning_path_purges_every_host_course()`: Lernpfad
mit Fall-1-Einbettung in Host1 (trägt) und einer zweiten, direkt geplanten
Host-Kurs-Berechtigung in Host2 (simuliert Fall 2/3, ohne die reale
mod_adele-Kaskade zu bemühen — die ist bereits in
`test_host_course_removal_rules()` abgedeckt). Austragung aus Host1 löst A-4
aus; geprüft wird, dass sowohl die Zielkurs- als auch die Host2-Einschreibung
verschwinden.

**Versionsbump diesmal berechtigt** (im Unterschied zu den letzten Runden):
echte funktionale Änderung, nicht nur Test- oder Konfigurationskorrektur.
`enrol_adele` 0.1.4 (2026072303).

### Ausgeliefert

`enrol_adele` 0.1.4. `local_adele`/`mod_adele` unverändert in diesem Teil.
