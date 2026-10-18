# Issue-Entwurf (local_adele): Gespeicherte Lernpfade enthalten Kanten doppelt – doppelte DOM-IDs im Graphen

> **Stand:** Entwurf (2026-10-18), noch nicht eingereicht. Gefunden bei der
> DOM-Abnahme von mod_adele #17 (Re-Audit 0735, TP-05). **Nicht** behoben:
> außerhalb des Umfangs von #17; Behebung erst nach Anlage des Issues.

## Befund (belegt)

1. In den Referenz-Lernpfaden der E2E-Fixtures
   (`local/tests/playwright/fixtures/learningpaths/local_adele_learning_paths.json`,
   Export einer realen Instanz) stehen Kanten **zweimal mit derselben ID**
   in `tree.edges`:
   - „Verzweigt, gestapelt B1": `dndnode_3dndnode_1` ×2, `dndnode_5dndnode_4` ×2
   - „Äquivalenzumformung (neu)": `dndnode_4dndnode_3` ×2, `dndnode_11dndnode_6` ×2
   Jeweils identische `source`/`target`/`sourceHandle`/`targetHandle`.
2. Beim Rendern zeichnet VueFlow beide Einträge; `components/edges/TransitionEdge.vue`
   setzt `:id="id"` auf das `<path>` → **dieselbe DOM-ID zweimal** auf der Seite
   (gemessen im Browser, E2E S17: `path.vue-flow__edge-path` in zwei
   `g.vue-flow__edge[data-id="dndnode_3dndnode_1"]`).
3. Kanten-IDs werden als `source + target` gebildet
   (`vue3/composables/addCustomEdge.ts:43`); eine zweite Verbindung derselben
   Knoten ergibt dieselbe ID.

## Nicht belegt

Wie die Duplikate entstanden sind (Editor beim erneuten Verbinden, Import,
ältere Version) – das ist aus den Daten allein nicht ableitbar.

## Folgen

- Ungültiges HTML (doppelte `id`), Hilfstechnologien und `aria-*`-Verweise
  können auf das falsche Element zeigen.
- Doppelte Kanten werden übereinander gezeichnet; Kantenlogik, die per ID
  sucht (`TransitionEdge.vue:117–119`), trifft nur das erste Element.

## Vorschlag

- Beim Speichern und beim Laden Kanten nach ID deduplizieren
  (bzw. nach `source`/`target`/Handles).
- Beim Verbinden im Editor keine zweite Kante mit gleicher ID anlegen.
- Upgrade-Schritt, der gespeicherte Pfade bereinigt; Test: Pfad mit
  doppelter Kante → nach Laden genau eine Kante, keine doppelte DOM-ID.

## Bezug

Die E2E S17 (`mod/tests/e2e/tests/stacked-courses.spec.ts`) prüft „keine
DOM-ID doppelt" deshalb außerhalb von `.vue-flow__edges`; nach Behebung
dieses Issues kann die Ausnahme entfallen.
