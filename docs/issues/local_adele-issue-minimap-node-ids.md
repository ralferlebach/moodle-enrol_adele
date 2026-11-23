# Issue-Entwurf (local_adele): Die MiniMap setzt die Knoten-IDs ein zweites Mal auf die Seite

> **Stand:** gefunden und behoben in `2026112300` im Rahmen von
> ralferlebach/moodle-local_adele #18 (AK7); Entwurf nur zur Dokumentation –
> ein eigenes Issue ist nicht nötig, wenn #18 den Befund aufnimmt.

## Befund (belegt, 2026-11-23)

`@vue-flow/minimap` zeichnet jeden Knoten als `<rect id="<Knoten-ID>">`
(z. B. `dndnode_1`). Auf einer Kursseite mit mehreren ADELE-Aktivitäten
stehen dadurch dieselben IDs mehrfach auf der Seite. Das gilt immer dann,
wenn die MiniMap erscheint: `LearningPath.vue` zeigt sie erst, wenn der Fluss
breiter als 768 px ist. Gemessen auf dem E2E-Kurs `E2EM18` mit drei
Anwendungen im Classic-Theme: `dndnode_1`, `dndnode_2` und `dndnode_3` je
dreimal. Im Boost-Theme mit seinen Schubladen war der Fluss schmaler, es gab
keine MiniMap und keinen Fund. Deshalb blieb das bisher unbemerkt.

## Behebung

- `vue3/components/flowchart/MiniMapNodeShape.vue`: zeichnet den Knoten wie
  VueFlow, aber ohne ID. Die ID ist als Prop deklariert, damit sie nicht als
  Attribut durchgereicht wird.
- `LearningPath.vue`: gibt der MiniMap für jeden Knotentyp des Graphen den
  passenden Slot `node-<Typ>`.

## Tests

- Jest `tests/unit/components/flowchart/MiniMapNodeShape.spec.js`
- Playwright `local_adele` `dom-ids.spec.ts`, Fall „three ADELE applications…“,
  Schritt „Classic theme“ (vor der Behebung rot mit den drei IDs).
