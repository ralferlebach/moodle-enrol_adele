# Pflichtenheft `enrol_adele`

**Dokumentstatus:** Fassung 2.0 (ersetzt Entwurf 1.0; die Grant-Tabelle aus 1.0 ist verworfen)
**Stand:** 2026-07-23, fortgeschrieben bis Session 002, Teil 5
**Grundlage:** [Lastenheft 2.0](lastenheft.md), Analyse der Referenz-Plugins
`enrol_coursecompleted`, `enrol_autoenrol`, `enrol_oss`, `enrol_campusonline`
sowie des Branches `mod_adele/ralferlebach-fix-enrolment-issue`
**Betrifft:** `enrol_adele` 0.1.0 → 0.4.0

---

## 1. Architekturüberblick

### 1.1 Grundprinzip: Soll-Zustand in `local_adele`, Abgleich in `enrol_adele`

Die Architektur folgt dem Muster von `enrol_campusonline`: Es gibt eine
autoritative Wahrheitsquelle für den Soll-Zustand, und das enrol-Plugin gleicht
den Ist-Zustand **zustandslos und idempotent** dagegen ab.

```
Soll-Zustand (local_adele)                Ist-Zustand (Moodle-Kern)
──────────────────────────                ─────────────────────────
local_adele_path_user                     {enrol}          (Instanzen)
  status = 'active'                       {user_enrolments}
  json.user_path_relation[node].status
  json.tree.nodes[].data.course_node_id
              │                                    ▲
              │        enrol_adele                 │
              └──────► reconcile() ────────────────┘
                       (idempotent)
```

Es gibt **keine Grant-Tabelle** und **keine eigene Log-Tabelle** (Entscheidung
F-6). Beides wäre materialisierter Zustand, der von der Wahrheit abdriften
kann. Nachvollziehbarkeit entsteht über das Moodle-Standard-Logging: Die
Kern-Events `user_enrolment_created/updated/deleted` werden von den
`enrol_plugin`-Methoden automatisch ausgelöst; für die Massenoperationen der
Verwaltungsseite definiert `enrol_adele` eigene Events (Abschnitt 7.3), die im
Standard-Logstore landen.

Das löst zugleich das Knoten-ID-Problem: `getNodeId()` im Vue-Editor vergibt
`dndnode_<höchste+1>` und **verwendet IDs gelöschter Knoten wieder**. Persistierte
Knoten-Referenzen (wie Grants sie gebraucht hätten) könnten stillschweigend auf
einen anderen Knoten zeigen. Die zustandslose Reconciliation referenziert
Knoten nur flüchtig während eines Laufs — Wiederverwendung ist dann harmlos.

### 1.2 Identität der Einschreibeinstanz (unverändert aus 1.0)

```
enrol      = 'adele'
courseid   = Zielkurs-ID
customint1 = local_adele_learning_paths.id     (FIELD_LEARNINGPATHID)
name       = 'ADELE: <Name des Lernpfads>'
roleid     = Einstellung enrol_adele/roleid
```

Eine Instanz pro **Lernpfad × Zielkurs**. Weder `mod_adele.id` noch der
Hostkurs sind Teil der Identität. Dieses Muster ist durch
`enrol_coursecompleted` validiert (dort: `customint1` = beobachteter Kurs).
Instanzen werden ausschließlich vom Plugin selbst angelegt
(`can_add_instance()` = `false`) — lazy beim ersten Bedarf während einer
Reconciliation.

### 1.3 Zuständigkeiten der drei Plugins

| Komponente | Zuständigkeit |
|---|---|
| `local_adele` | Fachliche Wahrheit. Berechnet User-Paths und Knotenstatus; liefert die Soll-Zustands-Funktion (2.2); ruft `enrol_adele` nach jedem Recompute optional auf. |
| `enrol_adele` | Technische Ausführung: Instanzen, `user_enrolments`, Reconciliation, Observer für Hostkurs-Austragungen, Verwaltungsseite. Trifft keine fachliche Entscheidung. |
| `mod_adele` | Anzeige und Eintrags-Auslöser (Optionen 1/2/3). Schreibt weiterhin per `enrol_manual` in den **Hostkurs** ein (F-7). Fasst Zielkurse nie an. |

### 1.4 Abhängigkeitsrichtung (unverändert)

`enrol_adele` deklariert `local_adele` in `version.php`. Die Gegenrichtung
bleibt undeklariert: `local_adele` ruft ausschließlich über
`enrol_get_plugin('adele')` mit Null-Prüfung auf (L-Q-08). Ohne installiertes
`enrol_adele` verhält sich der Bestand wie bisher.

---

## 1a. Erweiterung: Host-Kurs-Instanzen (Session 002, Teil 4)

Bislang besaß `enrol_adele` ausschließlich **Zielkurs**-Einschreibungen. Mit
Teil 5 kommt eine zweite, unabhängige Instanzart hinzu: **Host-Kurs**-
Einschreibungen für die Optionen 2 („Startnode") und 3 („irgendeine Node") von
`mod_adele`. Auslöser: die Host-Kurs-Mitgliedschaft bei diesen beiden Optionen
ist eine *Folge* der Node-Kurs-Mitgliedschaft, keine eigenständige
Entscheidung — sie muss also ebenso entziehbar sein, wie sie gewährt wurde.
Bislang lief sie über `enrol_manual` und war damit weder revidierbar noch von
`enrol_adele` unterscheidbar (derselbe strukturelle Mangel, den `enrol_adele`
für Zielkurse ursprünglich beheben sollte, ticket #486).

**Identität:** `enrol = 'adele'`, `courseid` = Host-Kurs, `customint1` =
Lernpfad-ID (wie bei Zielkurs-Instanzen), zusätzlich `customint2` als
Unterscheidungsmerkmal:

```
customint2 = 1 (instance_manager::KIND_TARGET) — bestehende Zielkurs-Instanzen
customint2 = 2 (instance_manager::KIND_HOST)   — neue Host-Kurs-Instanzen
```

Ohne `customint2` würden sich Ziel- und Host-Instanz kollidieren, sobald ein
Kurs zufällig gleichzeitig Host UND Node-Kurs desselben Lernpfads ist
(Selbsteinbettung, Randfall).

**Mechanik — bewusst asymmetrisch zu Zielkurs-Instanzen:** Bei Zielkursen
leitet `reconciler::reconcile_user()` die Berechtigung selbst aus
`local_adele\enrol_state` ab (mengenbasiert über alle Knoten). Bei Host-Kursen
kennt ausschließlich `mod_adele` die Zuordnung Kurs → Option → Lernpfad;
`reconciler::reconcile_host_user(int $lpid, int $hostcourseid, int $userid,
bool $entitled)` nimmt die Berechtigung deshalb als Parameter entgegen und ist
rein mechanisch (anlegen/reaktivieren/suspendieren einer einzelnen Instanz,
kein Aggregieren über mehrere Kurse). Die Berechtigungsermittlung selbst lebt
in `mod_adele\mod_adele_observer::is_user_entitled_to_host_via_option()`: „hat
der Nutzer irgendeine Einschreibung (jede Methode, Suspendierung zählt —
konsistent mit F-4/A-8) in einem qualifizierenden Node-Kurs?"

**Auslöser (neu, `mod_adele`):** `user_enrolment_created` **und**
`user_enrolment_deleted`, site-weit registriert (nicht nur in Host-Kursen),
da der auslösende Kurs typischerweise ein *Node*-Kurs ist, nicht der Host-Kurs
selbst. Jedes Event berechnet die Berechtigung frisch aus dem aktuellen
Datenbankzustand neu (nicht aus dem einzelnen Event abgeleitet), da ein Node-
Kurs von mehreren Knoten gleichzeitig referenziert werden kann und ein Nutzer
mehrere qualifizierende Node-Kurs-Einschreibungen gleichzeitig halten kann.

**Kein automatischer Hard-Removal-Trigger auf der Host-Seite** über das
Suspendieren hinaus — `purge_host_user()` existiert als Baustein, ist aber
nicht verdrahtet (offener Punkt E-10, Abschnitt 8). Löschen eines Lernpfads
entfernt Host- wie Zielinstanzen gleichermaßen, da `purge_learning_path()`
nicht nach `customint2` filtert.

## 2. Soll-Zustand

### 2.1 Korrigierte User-Path-Identität (umgesetzt in `local_adele` 0.4.5)

```php
// local_adele\enrollment — neu, idempotent, race-sicher:
subscribe_user_to_learning_path($learningpath, $params);   // ohne $courseid
```

Eindeutigkeit: DB-Unique-Index `(user_id, learning_path_id)`, global — nicht
auf `status = 'active'` beschränkt. Das ist sicher, weil `status = 'archived'`
ausschließlich beim Löschen eines Lernpfads gesetzt wird (Abschnitt 2.4/A-3),
gemeinsam mit dem Löschen der `learning_path_id`-Zeile selbst; da Moodle
Primärschlüssel nicht wiederverwendet, kann keine künftige Subskription je auf
dieselbe `learning_path_id` treffen wie eine archivierte Zeile. `course_id` ist
aus Suche und Identität entfernt (`enrollment.php`, `buildsqlqueryuserpath()`).
Der bestehende Drei-Parameter-Aufrufer bleibt als dünner, ignorierender
Wrapper erhalten.

**Konflikt mit ticket #501 und seine Auflösung (Session 002, Teil 4):** Unabhängig von
diesem Projekt hat lokal_adele 0.4.4 im selben Zeitraum ticket #501 (Race
Condition beim Check-then-Insert) mit einem Unique-Index auf dem *alten*
Tripel `(user_id, course_id, learning_path_id)` behoben — dem genauen Modell,
das dieses Projekt ablöst. Die lokal_adele-Migration 2026072301 (Session 002,
Teil 4) baut auf 2026072200 auf: sie löscht den alten Dreier-Index, dedupliziert
verbliebene kursgebundene Duplikate für denselben Nutzer/Lernpfad (höchste ID
gewinnt, gleiche Begründung wie in 2026072200) und legt den neuen Zweier-Index
an. Die race-sichere Insert-Then-Catch-Logik aus #501 bleibt erhalten, nur auf
das neue Tripel angepasst.

**Nebenbefund beim Reapplizieren (Session 002, Teil 4):** Der ursprüngliche SQL-Befehl der
2026072200-Migration (`DELETE ... WHERE id NOT IN (SELECT ... FROM (SELECT
...))`, verschachtelte Subquery auf derselben Tabelle) ist auf mindestens einer
produktiven Installation mit `dml_write_exception` gescheitert — ein bekanntes
MySQL/MariaDB-Fehlerbild (Error 1093), gegen das die Verschachtelung keine
Garantie bietet. Ersetzt durch zwei getrennte Anweisungen: eine lesende
`SELECT ... WHERE EXISTS (...)` (Selbst-Joins sind dort immer unproblematisch)
gefolgt von einem einfachen `DELETE ... WHERE id IN (<Liste>)`.

### 2.2 Die Soll-Zustands-Funktion

Die einzige Stelle, die das User-Path-JSON interpretiert, liegt in
`local_adele` (dort ist die JSON-Hoheit; Risiko „Strukturdrift" aus dem
Lastenheft):

```php
// local_adele\enrol_state — neu:
/**
 * Kurse, in denen der Nutzer laut Lernpfad-Zustand JETZT aktiv sein soll.
 * Leeres Array, wenn kein aktiver User-Path existiert.
 *
 * @return int[] Zielkurs-IDs
 */
public static function get_entitled_courseids(int $learningpathid, int $userid): array;
```

Definition „berechtigt": Der User-Path hat `status = 'active'`, und der Kurs
gehört zu mindestens einem Knoten, dessen
`user_path_relation[<nodeid>].feedback.status` in
`{accessible, completed}` liegt. (`completed` zählt mit, weil ein
abgeschlossener Kurs weiterhin einsehbar bleiben muss; A-6/F-1 sind damit
automatisch erfüllt, da über *alle* Knoten aggregiert wird.)

### 2.3 Der Reconciliation-Algorithmus (Kern von `enrol_adele`)

```php
// enrol_adele\reconciler::reconcile_user(int $learningpathid, int $userid): void
```

1. `$entitled = local_adele\enrol_state::get_entitled_courseids($lpid, $userid)`
2. `$instances` = alle Instanzen `enrol='adele' AND customint1=$lpid`
   (nur aktivierte; deaktivierte Instanzen werden übersprungen, Moodle-üblich).
3. Für jeden Kurs in `$entitled` ohne Instanz: Instanz anlegen (lazy, 1.2).
4. Für jede Instanz:
   * Kurs ∈ `$entitled`, keine `user_enrolment` → `enrol_user(…, ENROL_USER_ACTIVE)`
   * Kurs ∈ `$entitled`, `user_enrolment` suspendiert → `update_user_enrol(…, ENROL_USER_ACTIVE)`
   * Kurs ∉ `$entitled`, `user_enrolment` aktiv → `update_user_enrol(…, ENROL_USER_SUSPENDED)`
   * sonst → nichts.

Jeder Zweig ist idempotent (L-Q-09); ein doppelter Lauf ist ein No-op. Der
Algorithmus deckt A-1 und A-2 vollständig ab — inklusive geteilter Zielkurse
(A-6), weil `$entitled` mengenbasiert über alle Knoten entsteht, nicht pro
Knoten.

Erweiterungen:

```php
reconcile_learning_path(int $learningpathid): void   // alle aktiven User-Paths des LP
reconcile_all(progress_trace $trace): void           // Scheduled Task, Sicherheitsnetz
```

### 2.4 Harte Löschung

```php
// enrol_adele\reconciler::purge_user(int $learningpathid, int $userid): void
// enrol_adele\reconciler::purge_learning_path(int $learningpathid): void
```

`purge_user` (A-4): `unenrol_user()` an jeder Instanz des Lernpfads, an der der
Nutzer eingeschrieben ist. **Voraussetzung:** Der Aufrufer hat zuvor den
User-Path deaktiviert (2.5), sonst schriebe die nächste Reconciliation sofort
wieder ein.

`purge_learning_path` (A-3, A-5): Alle Instanzen mit `customint1 = $lpid`
suchen und über `delete_instance()` des Plugins entfernen — Moodle räumt dabei
`user_enrolments` und die zugehörigen Rollenzuweisungen ab. Kein direktes
`$DB->delete_records('enrol', …)` wie in `enrol_coursecompleted`
(`hook_listener.php:49`) — das hinterlässt verwaiste `user_enrolments` und
dient hier als dokumentiertes Anti-Pattern.

`unenrol_user()` löscht keine Bewertungs- oder Aktivitätsdaten aus der
Datenbank (L-Q-07); bei Wiedereinschreibung stellt Moodle Bewertungen gemäß
`recovergradesdefault` wieder her.

### 2.5 Kopplung an den User-Path-Status

Damit Reconciliation und harte Löschung nicht gegeneinander arbeiten, gilt die
Invariante:

```
Einschreibungen existieren  ⇔  User-Path status = 'active'
```

* Knoten zu → User-Path bleibt aktiv, nur `$entitled` schrumpft → Suspendierung.
* Zugangsverlust nach A-4 → User-Path wird deaktiviert **und** `purge_user()`.
* Lernpfad gelöscht → User-Paths werden deaktiviert/archiviert **und**
  `purge_learning_path()`. (Snapshots bleiben laut #446 bewusst erhalten.)

**Entschieden (R-1):** Die Deaktivierung nach A-4 erfolgt als **Löschung** des
`local_adele_path_user`-Datensatzes. Kursabgeleiteter Fortschritt re-deriviert
sich bei erneuter Einschreibung aus den im System vorliegenden Kurs- und
Zeitzuständen. Zwei bewusst akzeptierte Einschränkungen: (a) manuelle
Master-Overrides einer Lehrkraft leben nur im User-Path-JSON und gehen
verloren; (b) `first_enrolled` wird beim Wiedereintritt neu gestempelt,
zeitgesteuerte Restriktionsfenster beginnen von vorn. Beim Löschen eines
ganzen Lernpfads bleiben die Snapshots dagegen erhalten (Status `archived`,
konsistent mit #446).

---

## 3. Ereignis- und Auslöser-Matrix

| Auslöser | Ort | Reaktion |
|---|---|---|
| User-Path neu berechnet (Events, zeitgesteuerte Ad-hoc-Tasks) | `local_adele\relation_update` nach dem Persistieren | `reconcile_user($lpid, $userid)` — ersetzt `enrol_user_into_node()` (Zeile 231/1104 ff.) und die Stelle in `node_completion.php:70–112` |
| Knoten öffnet/schließt sich | ist ein Spezialfall der Zeile darüber — es gibt kein eigenes „Node closed"-Event | dito |
| `user_enrolment_created` im Hostkurs | Observer `mod_adele` (bestehend, Bugfix A-14) | User-Path anlegen/aktivieren (Optionen 1/2/3), dann `reconcile_user()` |
| `user_enrolment_deleted` im Hostkurs | **neuer** Observer in `enrol_adele` | Regelwerk A-4, Abschnitt 4 |
| `user_enrolment_updated` (Suspendierung im Hostkurs) | — | **keine Reaktion** (A-8/F-4): Suspendierte gelten weiterhin als „subscribed" |
| Lernpfad gelöscht | `learning_paths::delete_learning_path()` | User-Paths deaktivieren, `purge_learning_path()` |
| Lernpfad bearbeitet (Knoten/Kurse geändert) | `learnpath_updated`-Event (bestehend) | `reconcile_learning_path()` |
| Scheduled Task (täglich, konfigurierbar) | `enrol_adele\task\reconcile_task` | `reconcile_all()` — Sicherheitsnetz gegen verpasste Events |
| Verwaltungsseite | `enrol_adele` Admin-UI | „Neu berechnen" → `reconcile_learning_path()`; „Hart löschen" → `purge_learning_path()` |

**Rekursionsschutz:** Beide Enrolment-Observer ignorieren Events, deren
Einschreibeinstanz `enrol = 'adele'` ist (Selbsteinbettung: Zielkurs =
Hostkurs).

---

## 4. Regelwerk Zugangsverlust über den Hostkurs (A-4)

Die Einschreibeoptionen (`{adele}.participantslist`, Kommaliste, Mehrfachauswahl):

| Option | Bedeutung | Wirkung auf A-4 |
|---|---|---|
| `1` | Mitgliedschaft im einbettenden Kurs | trägt den Nutzer nur, solange er dort Mitglied ist |
| `2` | Mitglied in einem Startknoten-Kurs | von Hostkurs-Austragung **unberührt** |
| `3` | Mitglied in irgendeinem Knoten-Kurs (aus Branch `fix-enrolment-issue`, A-15) | von Hostkurs-Austragung **unberührt** |

Algorithmus bei `user_enrolment_deleted` für Nutzer *U* im Kurs *H*:

1. Instanz des Events ist `enrol = 'adele'` → abbrechen (Rekursionsschutz).
2. Alle `{adele}`-Aktivitäten in *H* ermitteln → betroffene Lernpfade.
3. Für jeden betroffenen Lernpfad *L* prüfen, ob *U* noch getragen wird:
   a. Existiert eine andere Einbettung von *L* mit Option 1 in einem Kurs, in
      dem *U* noch eingeschrieben ist (Suspendierung zählt als eingeschrieben,
      A-8)? → getragen.
   b. Hat irgendeine Einbettung von *L* Option 2, und ist *U* in einem
      Startknoten-Kurs von *L* eingeschrieben? → getragen.
   c. Hat irgendeine Einbettung von *L* Option 3, und ist *U* in irgendeinem
      Knoten-Kurs von *L* eingeschrieben? → getragen. **Achtung:** Zielkurs-
      Einschreibungen der ADELE-Instanz selbst zählen hier *nicht* als tragend,
      sonst hielte sich der Zugang zirkulär selbst am Leben.
4. Getragen → nichts tun.
5. Nicht getragen → User-Path von *U* für *L* deaktivieren (R-1), dann
   `purge_user($lpid, $userid)`.

Dieses Regelwerk lebt in `enrol_adele` (Observer), die Prüfungen a–c als
abfragbare Hilfsfunktion, damit die Verwaltungsseite und Tests sie
wiederverwenden können.

---

## 5. Plugin-Klasse, Restore, Rolle

### 5.1 Plugin-Klasse (Ergänzungen zum Stub)

Der Stub aus 0.1.0 bleibt gültig (`roles_protected()`, `allow_unenrol()` /
`allow_manage()` / `can_add_instance()` = `false`). Hinzu kommen:

* `sync(progress_trace $trace)` — Einstieg für CLI/Task, delegiert an
  `reconcile_all()`.
* `get_instance_name()` — bereits vorhanden; Name wird beim Anlegen auf
  `ADELE: <Lernpfadname>` gesetzt und bei Umbenennung des Lernpfads im Zuge der
  Reconciliation nachgezogen.

### 5.2 Backup & Restore (A-13/F-10)

Erwartung: Duplizierte/wiederhergestellte Kurse enthalten keine
ADELE-Einschreibungen, denn kein Lernpfad kann den neuen Kurs bereits
referenzieren. Damit das technisch garantiert ist, werden die Hooks aktiv
implementiert (Vorbild: `enrol_coursecompleted`, aber mit Skip- statt
Map-Strategie):

```php
public function restore_instance(...) {
    // Ausnahme: Restore in DENSELBEN Kurs (gleiche courseid) und der
    // referenzierte Lernpfad existiert und führt diesen Kurs als Knotenkurs
    // → auf bestehende/neue Instanz mappen; sonst: überspringen.
}
public function restore_user_enrolment(...) {
    // No-op. Die nächste Reconciliation stellt berechtigte Nutzer wieder her.
}
```

Ohne diese aktive Implementierung würde Moodle die Einschreibungen je nach
Restore-Einstellung in `manual` umwandeln — genau die Vermischung, die dieses
Vorhaben beseitigt.

### 5.3 Rolleneinstellung (A-11/F-8)

`local_adele/enroll_as_setting` hat exakt zwei Verwendungsstellen
(`node_completion.php:106`, `relation_update.php:1143`) — beide werden durch
den Reconciler ersetzt. Damit:

* `enrol_adele/roleid` (Standard: Student-Archetyp) ist die einzige Einstellung
  für die Zielkurs-Rolle; sie wird beim Anlegen jeder Instanz als
  `enrol.roleid` gesetzt.
* `local_adele/enroll_as_setting` wird als deprecated markiert und in einem
  späteren `local_adele`-Release entfernt. Beim Upgrade übernimmt `enrol_adele`
  einen dort gesetzten Wert einmalig als Startwert.

---

## 6. Verwaltungsseite (A-5)

Admin-Seite unter *Website-Administration → Plugins → Einschreibungsmethoden
→ Lernpfad-Einschreibung*
(`enrol/adele/manage.php`), Capability `enrol/adele:config`. Vorbilder:
`enrol_coursecompleted/manage.php` (Aufbau, Capability-Prüfung) und
`enrol_campusonline` (Admin-Tabellen).

Tabelle über alle Lernpfade mit ADELE-Instanzen:

| Spalte | Quelle |
|---|---|
| Lernpfad (ID, Name) | `local_adele_learning_paths`; gelöschte Pfade mit verbliebenen Instanzen werden als „verwaist" markiert — genau die Altfälle, die A-3 künftig verhindert |
| Zielkurse | Instanzen mit `customint1 = lpid` |
| Einschreibungen aktiv / suspendiert | Aggregat über `user_enrolments` |
| Aktion „Neu berechnen" | `reconcile_learning_path()` |
| Aktion „Hart löschen" | Bestätigungsdialog → User-Paths deaktivieren → `purge_learning_path()` |

Beide Aktionen laufen als Ad-hoc-Task, wenn die Nutzerzahl eine Schwelle
überschreitet (UI bleibt responsiv), und lösen die Events aus 7.3 aus.

---

## 7. Ausbaustufen, API, Events

### 7.1 Ausbaustufen

Versionsnummern werden nur bei funktionalen Änderungen erhöht (Konvention,
Session 001).

| Version | Inhalt | Status |
|---|---|---|
| **0.1.0** | Stub: installiert sich, Plugin-Klasse, Capabilities, `enrol_adele/roleid`, Privacy `null_provider`, CI, Doku. | geliefert |
| **0.1.1** | Instanzverwaltung + `reconciler` (`reconcile_user/learning_path/all`, `purge_user/learning_path`); Scheduled Task; `sync()`; Observer `user_enrolment_deleted` inkl. Regelwerk Abschnitt 4; PHPUnit-Kerntests. Dazu die Aufrufer-Umstellung: `local_adele` 0.4.3 (Soll-Zustands-Funktion, Reconcile-Hooks, User-Path-Identität, Lösch-Lifecycle) und `mod_adele` 0.1.5 (Bugfix A-14, Option 3, neue Subscribe-Signatur). | geliefert |
| **0.1.2** | Verwaltungsseite (A-5); Restore-Hooks (A-13); eigene Events (7.3); Behat. | offen |
| **später** | Deprecation `local_adele/enroll_as_setting` (D.5); Entfernen des Drei-Parameter-Wrappers; Gesamtabnahme (D.8). | offen |

Durch A-9 (keine eigene Tabelle) bleibt `db/install.xml` dauerhaft leer und der
Privacy-Provider dauerhaft ein `null_provider` — die in Fassung 1.0 geplante
Umstellung auf einen Metadata-Provider entfällt.

### 7.2 API-Vertrag zwischen den Plugins

```php
// local_adele (Soll-Zustand, JSON-Hoheit):
local_adele\enrol_state::get_entitled_courseids(int $lpid, int $userid): array;

// enrol_adele (Ausführung):
enrol_adele\reconciler::reconcile_user(int $lpid, int $userid): void;
enrol_adele\reconciler::reconcile_learning_path(int $lpid): void;
enrol_adele\reconciler::reconcile_all(progress_trace $trace): void;
enrol_adele\reconciler::purge_user(int $lpid, int $userid): void;
enrol_adele\reconciler::purge_learning_path(int $lpid): void;

// Aufrufmuster in local_adele (optionale Kopplung, L-Q-08):
if ($plugin = enrol_get_plugin('adele')) {
    \enrol_adele\reconciler::reconcile_user($lpid, $userid);
}
```

### 7.3 Eigene Events (Standard-Logstore, keine eigene Tabelle)

| Event | Auslöser |
|---|---|
| `\enrol_adele\event\learning_path_reconciled` | „Neu berechnen" (Verwaltungsseite, Task) |
| `\enrol_adele\event\learning_path_purged` | „Hart löschen", Lernpfad-Löschung |
| `\enrol_adele\event\user_access_revoked` | Regelwerk A-4 hat gegriffen |

Die Einzeloperationen sind bereits durch die Kern-Events
(`user_enrolment_created/updated/deleted`) abgedeckt.

---

## 8. Offene Punkte

| ID | Punkt | Status |
|---|---|---|
| ~~R-1~~ | A-4/harte Löschung des User-Path | erledigt: Datensatz wird gelöscht; Einschränkungen dokumentiert in 2.5 |
| ~~R-2~~ | Branch `ralferlebach-fix-enrolment-issue` | erledigt: in `mod_adele` 0.1.5 eingeflossen (Option 3, Guards, Signaturwechsel); A-14 dort mitbehoben |
| ~~O-1~~ | `course_id`-Übergangsphase | erledigt: Spalte bleibt vorerst befüllt, verliert aber jede fachliche Bedeutung; Wrapper-Deprecation (2.1) |
| ~~O-2~~ | Bestandsdaten | erledigt (F-9): unangetastet |
| ~~O-3~~ | Knoten-ID-Stabilität | erledigt: IDs werden nach Löschung wiederverwendet → zustandslose Architektur gewählt, keine persistierten Knoten-Referenzen |
| ~~O-4~~ | Rolleneinstellung | erledigt (F-8): wandert zu `enrol_adele/roleid`, Abschnitt 5.3 |
| ~~O-5~~ | Gruppenzuordnung | erledigt: außen vor (Lastenheft Abschnitt 4) |
| ~~O-6~~ | Austragungs-Propagation bei Option 1 | erledigt (F-2/F-4): Regelwerk Abschnitt 4 |
| **E-10** | `purge_host_user()` existiert, ist aber an keinen automatischen Trigger geknüpft (z. B. Embedding/Aktivität gelöscht). Baustein für eine künftige Verwaltungsseiten-Aktion. | offen |
| **E-11** | mod_adele-Issue #11 ("Message was not sent" beim ersten Anlegen eines Lernpfads in einem Kurs): Ursache nicht abschließend verifiziert (Screenshot technisch nicht abrufbar). Arbeitshypothese: Willkommensnachricht der ersten Einschreibung im Kurs (`enrol_manual`-Setting „Willkommensnachricht senden") scheitert am Messaging-Subsystem — erklärt das „nur beim ersten Mal"-Muster, ist aber nicht bestätigt. | offen, ungelöst |

---

## 9. Prüfkriterien (Abnahme)

1. **A-1/A-2/A-6:** Lernpfad mit Knoten A→101, B→102, C→101. Öffnen von A
   schreibt in 101 ein; Schließen von A bei offenem C lässt 101 aktiv;
   Schließen von C suspendiert 101; Wiederöffnen reaktiviert dieselbe
   `user_enrolment` (kein neuer Datensatz).
2. **A-3:** Löschen des Lernpfads entfernt alle seine Instanzen samt
   `user_enrolments`; ein zweiter Lernpfad auf Kurs 101 und eine parallele
   `manual`-Einschreibung bleiben unberührt.
3. **A-4:** Nutzer in zwei Hostkursen (beide Option 1): erste Austragung folgenlos,
   zweite löscht. Option-2- oder Option-3-getragene Nutzer: Hostkurs-Austragung
   folgenlos. Suspendierung im Hostkurs: folgenlos (A-8).
4. **A-5:** Verwaltungsseite listet Lernpfade inkl. verwaister; „Neu berechnen"
   repariert manuell suspendierte ADELE-Einschreibungen; „Hart löschen" räumt
   restlos ab und die nächste Reconciliation schreibt **nicht** wieder ein.
5. **A-13:** Kurs-Duplikat und Restore in neuen Kurs enthalten weder
   ADELE-Instanzen noch daraus konvertierte `manual`-Einschreibungen.
6. **L-Q-08:** Deinstallation/Deaktivierung von `enrol_adele` lässt
   `local_adele`/`mod_adele` fehlerfrei weiterlaufen.
7. **L-Q-09:** Jede Operation doppelt ausgeführt = identisches Ergebnis.
8. **L-Q-03:** CI grün auf allen Matrizen, Code-Checker null Warnungen.
