# Issue-Entwurf: Knoten per Tastatur „vor" einen Knoten mit Vorgänger eingefügt — liegt genau auf ihm

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`, `accessibility`
**Stand:** 2026-10-08, Session 006 — gefunden mit Testkette E2 (Klammerung)
**GitHub:** noch nicht abgelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Tastaturdialog „Kurs einfügen", Beziehung „vor": Der neue Knoten wird deckungsgleich über den Zielknoten gelegt, wenn dieser schon einen Vorgänger hat

## Beobachtung

Pfad T01 → T02 → T04. T03 über den Dialog „Kurs einfügen" **vor** T04
eingefügt. Gemessen (Position der Knoten im Editor):

```
dndnode_1 (T01)  translate(0px, 0px)
dndnode_2 (T02)  translate(0px, 450px)
dndnode_3 (T04)  translate(0px, 900px)
dndnode_4 (T03)  translate(0px, 900px)
```

T03 liegt exakt auf T04. Zu sehen ist nur noch T03; T04 und seine
Schaltflächen (Zugangsbedingungen, Abschluss, Bearbeiten, Löschen) sind
verdeckt. Auch nach Speichern und Neuladen.

Hat der Zielknoten noch keinen Vorgänger (er hängt am Startmarker), werden
die Knoten nach Codelage nach unten verschoben (`shiftNodesDown`), und es
überlappt nichts.

## Folge

Gerade der Tastaturweg — für Menschen gedacht, die nicht ziehen können, und
seit #595 der einzige Weg zu einem zweiten Vorgänger — hinterlässt einen
Knoten, der sich mit der Maus nicht mehr erreichen lässt.

## Vorschlag

Den neuen Vorgänger neben die vorhandenen Vorgänger setzen (eine Spalte
weiter), nicht auf den Zielknoten.

## Stand der Tests

`editor-brackets.spec.ts`, bekannter Fehler „a node placed before another
with the keyboard does not cover it". Der reguläre Test „node level: (A AND
B) OR C" erreicht die Schaltfläche des verdeckten Knotens deshalb nur über
ein direkt ausgelöstes Klick-Ereignis.
