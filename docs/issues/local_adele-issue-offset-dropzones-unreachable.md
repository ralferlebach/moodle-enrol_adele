# Issue-Entwurf: Versetzte Ablagezonen „Nachfolger"/„Vorgänger" entfernen — sie sind nicht zu treffen und nicht nötig

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`
**Stand:** 2026-10-07, Session 006 — gefunden bei der Umsetzung von local_adele #580
**GitHub:** noch nicht abgelegt

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

## Umsetzung (nach Ablage)

- `drawDropzone.ts`: Zone `child` nur, wenn der Knoten keinen Nachfolger
  hat; Zone `parent` nur, wenn er keinen Vorgänger hat oder der Vorgänger der
  Startmarker ist. Der Versatz um 500 Einheiten (`getOffsetX`) entfällt.
- Tests: Jest für `drawDropzone`; E2E — an einem Knoten mit Nachfolger
  erscheinen genau die erwarteten Zonen; die Erreichbarkeitsmessung von oben
  als Test (jede angebotene Zone ist zu treffen).

## Noch nicht gemessen

Ob der seitliche Knoten in jedem Fall dieselbe Struktur ergibt: Hat der
vorhandene Nachfolger selbst Nachfolger, teilt ein Knoten „parallel" dazu
vermutlich auch diese (gemessen ist bisher nur: parallel zu einem Knoten mit
einem Nachfolger → beide führen zu diesem Nachfolger). Eine Verzweigung, die
danach getrennt weiterläuft, wäre dann auf diesem Weg nicht dasselbe.
