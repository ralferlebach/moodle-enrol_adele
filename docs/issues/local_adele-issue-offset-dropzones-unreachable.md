# Issue-Entwurf: Versetzte Ablagezonen „Nachfolger"/„Vorgänger" entfernen — sie sind nicht zu treffen und nicht nötig

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`
**Stand:** 2026-10-07, Session 006 — gefunden bei der Umsetzung von local_adele #580
**GitHub:** local_adele #595

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Editor: Keine Ablagezone „Nachfolger" an einem Knoten, der schon einen Nachfolger hat, und keine Zone „Vorgänger" an einem Knoten mit Vorgänger

## Entscheidung (Auftraggeber, 2026-10-07)

- Hat ein Knoten bereits einen Nachfolger, wird die untere Zone
  („Nachfolger") **nicht mehr angeboten**. Hat er bereits einen Vorgänger
  (außer dem Startmarker), wird die obere Zone („Vorgänger") nicht mehr
  angeboten.
- Begründung: Dieselbe Struktur lässt sich über die seitliche Zone am
  vorhandenen Nachfolger bzw. Vorgänger anlegen (Knoten rechts daneben).
- Grundsatz, bereits mit #580 umgesetzt: Eine einmal erreichte Zone bleibt
  bestehen, auch wenn sich der Zeiger innerhalb der Zone einem anderen
  Knoten stärker annähert.

## Beobachtung

Hat ein Knoten bereits einen Nachfolger, zeichnet der Editor die Zone
„Nachfolger" nicht unter den Knoten, sondern 500 Einheiten nach rechts
versetzt — neben den vorhandenen Nachfolger. Dasselbe gilt für die Zone
„Vorgänger" eines Knotens, der schon einen Vorgänger hat. Diese versetzten
Zonen lassen sich nicht treffen: Führt man den Zeiger vom Knoten zur Zone,
verschwinden die Zonen oder springen an einen anderen Knoten, bevor der
Editor den Treffer anzeigt („Zum Verbinden ablegen").

Folge: Eine Verzweigung (ein Knoten mit zwei Nachfolgern) oder eine
Zusammenführung über die Zone „Vorgänger" lässt sich mit der Maus nicht
bauen.

## Messung

Playwright, echtes HTML5-Ziehen; der Zeiger springt zuerst auf den Knoten
(Zonen erscheinen dort) und wird dann in 14 Schritten zur Mitte der Zone
geführt. „erreicht" heißt: Die Zone zeigt den Treffer an und die Zonen
gehören noch zum Ausgangsknoten.

**Kette T01 → T02 → T03**

| Knoten | Vorgänger | Nachfolger | Parallel (rechts) | Alternative (links) |
|---|---|---|---|---|
| T01 | erreicht | **nicht** (Zonen weg) | erreicht | erreicht |
| T02 | **nicht** (Zonen weg) | **nicht** (Zonen weg) | erreicht | erreicht |
| T03 | **nicht** (Zonen weg) | erreicht | erreicht | erreicht |

**T01 und T02 parallel, beide vor T03**

| Knoten | Vorgänger | Nachfolger | Parallel (rechts) | Alternative (links) |
|---|---|---|---|---|
| T01 | erreicht | **nicht** (Zonen bleiben, kein Treffer) | erreicht | erreicht |
| T02 | erreicht | **nicht** (Zonen weg) | erreicht | erreicht |
| T03 | **nicht** (Zonen an anderem Knoten) | erreicht | erreicht | erreicht |

Nicht erreicht werden genau die Zonen, die versetzt gezeichnet werden.

Die Tabelle ist mit Version `2026100713` (vor #580) und mit `2026100714`
(nach #580) **identisch**; der Befund besteht also unabhängig von #580.

## Ursache

**Nicht durch Versuch belegt, nur nach Codelage:** `SidebarPath.vue` entfernt
die Zonen, sobald der Zeiger mehr als 500 Einheiten von der Mitte des
Knotens entfernt ist (`aboveDistance()`); die versetzte Zone beginnt
waagerecht 500 Einheiten neben dem Knoten (`drawDropzone.ts`, `getOffsetX`)
und liegt zusätzlich höher oder tiefer. Jeder Punkt auf ihr wäre damit
weiter als 500 entfernt.

## Nicht geprüft

- Ob der Tastaturweg (Dialog „Kurs einfügen", #575) dieselben Beziehungen
  anlegen kann.
- Andere Topologien als die beiden gemessenen.

## Umsetzung (Version `2026100717`)

- **Zonen nur, wo Platz ist** (`drawDropzone.ts`): „Nachfolger" nur an einem
  Knoten ohne Nachfolger; „Vorgänger" nur an einem Knoten ohne Vorgänger oder
  mit dem Startmarker als Vorgänger. Der Versatz um 500 Einheiten ist
  entfernt. Ein Knoten ohne die Listen `parentCourse`/`childCourse` wird wie
  einer mit leeren Listen behandelt.
- **Eine erreichte Zone bleibt** (`SidebarPath.vue`) — über die Ausnahme aus
  #580 (näherer Nachbarknoten) hinaus jetzt auch:
  - gegenüber dem **Startmarker**: Bei einem einzelnen Knoten liegt der
    Marker unter dem rechten Teil der Zone „parallel"; bisher verschwanden
    die Zonen, sobald der Zeiger diesen Teil erreichte (gemessen: ab etwa 70 %
    der Zonenbreite). Steht der Zeiger auf einer Zone, zählt der Marker
    darunter nicht als getroffen.
  - gegenüber den beiden **Abstandsregeln** (500 bzw. 550 Einheiten von der
    Knotenmitte).
- „Sind Zonen gezeichnet?" wird jetzt an der Zone „Alternative" abgelesen
  statt an „Vorgänger", die nicht mehr immer da ist.

### Tests

- Jest `drawDropzone.spec.ts`: 5 Fälle (der bisherige Test, der den Versatz
  festschrieb, ist ersetzt). Jest gesamt 370.
- E2E `editor-drop-zones.spec.ts` (4 Tests):
  1. Kette T01 → T02 → T03: angebotene Zonen je Knoten genau wie
     entschieden; jede angebotene Zone mit geführtem Zeiger (14 Schritte)
     erreicht, Zonen bleiben am Knoten.
  2. Einzelner Knoten: auf der Zone „parallel" bis über den Startmarker
     geführt — Zone bleibt, zeigt den Treffer, Marker nicht; Ablegen legt
     den Knoten an.
  3. T01 und T02 parallel vor T03: dieselbe Regel.
  4. Verzweigung über die seitliche Zone des vorhandenen Nachfolgers; danach
     läuft ein Arm getrennt weiter.
- **Gegenproben:** Tests 1 und 3 gegen `2026100716` rot („the zones offered
  at dndnode_1": eine Zone zu viel). Test 2 gegen den Stand ohne die
  Marker-Regel rot.
- **Nicht durch einen eigenen Test belegt:** der Vorrang der erreichten Zone
  vor den Abstandsregeln. In den gemessenen Anordnungen liegt nur der äußerste
  Rand einer Zone jenseits der 500 Einheiten, und die alte Regel entfernte die
  Zonen dort nach Codelage nur einmal kurz; ein Test dafür wäre nicht
  trennscharf.

## Gemessen: Ersetzt die seitliche Zone den zweiten Nachfolger?

- **Am Blatt: ja.** T01 → T02, dann T04 „parallel" zu T02: T01 → T02 und
  T01 → T04. Hängt man danach T03 an T02, folgt T03 nur T02 — die Arme laufen
  getrennt weiter.
- **In der Mitte: nein.** T01 → T02 → T03, dann T04 „parallel" zu T02: T04
  bekommt denselben Vorgänger **und denselben Nachfolger** (T01 → T04 → T03).
  Ein Arm, der neben einer schon weitergeführten Kette getrennt endet, lässt
  sich mit der Maus so nicht nachträglich anlegen.

Reihenfolge also: erst verzweigen, dann die Arme verlängern.

## Nicht geändert, nicht geprüft

- Der Tastaturweg (Dialog „Kurs einfügen", #575) bietet „nach" und „vor"
  weiterhin für jeden Knoten an, auch wenn dort schon ein Nachfolger bzw.
  Vorgänger ist — **so entschieden** (2026-10-08: „beibehalten"). Er bleibt
  der Weg für einen zweiten Nachfolger oder Vorgänger an beliebiger Stelle.
- Andere Anordnungen als die gemessenen; Ziehen von Hand; andere Browser als
  Chromium.
