# Issue-Entwurf: Paralleler Knoten geht nicht in die Bedingung des Nachfolgers ein

**Repository:** `Wunderbyte-GmbH/moodle_local_adele`
**Labels (Vorschlag):** `question`, `editor`, `semantics`
**Stand:** 2026-10-05, Session 006 — gefunden mit E2E-Kette E2

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Zone „Alternative node" (`dropzone_and`): Nachfolger bekommt den parallelen Knoten nicht als Vorgänger-Bedingung

## Beobachtung

Aufbau im Editor per Drag&Drop:

1. T01 auf die leere Fläche
2. T03 als Nachfolger von T01 (Zone „Successor node")
3. T02 auf die seitliche Zone von T01, beschriftet „Alternative node" (`dropzone_and`)

Gespeichert wird:

| Knoten | `parentCourse` | Zugangsbedingung |
|---|---|---|
| T01 (`dndnode_1`) | `starting_node` | – |
| T03 (`dndnode_2`) | `dndnode_1`, `dndnode_3` | `parent_courses`, `courses_id: [dndnode_1]`, `min_courses: 1` |
| T02 (`dndnode_3`) | `starting_node` | – |

T03 hängt **strukturell** an beiden Vorgängern (zwei Kanten), seine
**Zugangsbedingung** nennt aber nur T01. Ob T02 abgeschlossen ist, spielt für
den Zugang zu T03 keine Rolle.

## Ursache im Code

`vue3/composables/conditions/addAutoRestrictions.js`:

```js
} else if (relation == 'and') {
  return oldNode
}
```

Der Nachfolger wird für die Beziehung `and` unverändert zurückgegeben; ein
Jest-Test hält das ausdrücklich fest (*„should return oldNode unchanged when
relation is and"*). Für `parent` gilt Ähnliches: Eine vorhandene Bedingung
wird nicht verändert.

## Frage

Was soll die Zone bedeuten?

- **UND** (Name `dropzone_and`): Der Nachfolger verlangt beide Vorgänger —
  `courses_id: [dndnode_1, dndnode_3]`, `min_courses: 2`.
- **ODER / Alternative** (Beschriftung „Alternative node"): Einer der beiden
  genügt — `courses_id: [dndnode_1, dndnode_3]`, `min_courses: 1`.

Heute gilt keins von beidem; T02 ist für den Zugang wirkungslos. Zusätzlich
widersprechen sich Zonenname (`and`) und Beschriftung („Alternative node").

## Vorschlag

1. Semantik festlegen (UND oder ODER).
2. `addAutoRestrictions()` ergänzt beim Ablegen auf die seitliche Zone den
   neuen Knoten in der `parent_courses`-Bedingung jedes geteilten Nachfolgers
   und setzt `min_courses` entsprechend.
3. Beschriftung an die Semantik anpassen.

## Akzeptanzkriterien

1. Nach dem beschriebenen Aufbau nennt die Bedingung von T03 beide Vorgänger
   mit dem festgelegten `min_courses`.
2. Der Jest-Test für `relation == 'and'` prüft das neue Verhalten.
3. Die E2E-Kette `ADELE-E2E-E2` (`editor-combinations.spec.ts`): der
   `test.fixme` „the shared successor requires the parallel node as well"
   wird zu einem regulären Test und läuft grün.
