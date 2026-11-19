# Issue-Entwurf (local_adele): Verbinden in Gegenrichtung legt eine Rückwärtskante an (Zyklus im Lernpfad)

> **Stand:** Entwurf (2026-10-18), noch nicht eingereicht. Gefunden beim
> Umsetzen von local_adele #603 (E2E `editor-duplicate-edges.spec.ts`).
> **Nicht** behoben – außerhalb von #603.

## Befund (belegt, gemessen im Browser 2026-10-18)

Pfad T01 → T02 im Editor (Kante `dndnode_2dndnode_1`, Quelle `dndnode_1`,
Ziel `dndnode_2`). Zieht man die Verbindung **vom Ziel-Griff von T02 zum
Quell-Griff von T01** (also in Gegenrichtung), zeigt der Graph danach
**zwei** Kanten. `LearningPath.vue::handleConnect()` tauscht Quelle und Ziel
anhand des Knotens, an dem der Zug begann (`store.state.startnode`), und
`addCustomEdge(target, source)` vertauscht nochmals: Es entsteht eine Kante
`dndnode_1dndnode_2` von T02 nach T01 – eine andere Verbindung (Gegenrichtung),
also kein Duplikat im Sinne von #603, aber ein Zyklus T01 → T02 → T01.

## Nicht belegt

Wie sich Zugangsregeln und Eltern-/Kind-Listen (`recalculateParentChild`)
bei einem solchen Zyklus verhalten; ob der Server ihn beim Speichern
annimmt.

## Vorschlag

- Beim Verbinden keine Kante anlegen, die zu einem Zyklus führt
  (insbesondere nicht die Gegenrichtung einer bestehenden Kante); Hinweis an
  die Person.
- Serverseitig beim Speichern Zyklen abweisen oder melden.
- Tests: Gegenrichtung, längerer Zyklus, gültige Verzweigung bleibt möglich.
