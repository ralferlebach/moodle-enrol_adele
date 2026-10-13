# Issue-Entwurf: Ablagezonen erscheinen erst mitten im Ziehen

**Repository:** `Wunderbyte-GmbH/moodle_local_adele`
**Labels (Vorschlag):** `enhancement`, `accessibility`, `testability`
**Stand:** 2026-10-02, Session 006

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Ablagezonen im Lernpfad-Editor einblenden, sobald ein Ziehen beginnt

## Beobachtung

Beim Ziehen eines Kurses aus der Seitenleiste entstehen die Ablagezonen
(`dropzone_parent`, `dropzone_child`, `dropzone_and`, `dropzone_or`) erst
*während* der Zeigerbewegung, und nur unter einer zusätzlichen Bedingung: In
`SidebarPath.onDrag()` werden sie gezeichnet, wenn es einen nächstgelegenen
Knoten gibt **und** der Zeiger zugleich den Startmarker (`starting_node`)
schneidet.

```js
let startingNodeIntersecting = checkIntersetcion(event, startingNode)
if (closestNode.value && startingNodeIntersecting) {
  if (dropzoneShown()) {
    const newDrop = await drawDropzone(closestNode.value, store);
    addNodes(newDrop.nodes);
    ...
```

## Warum das stört

**Für Autorinnen und Autoren.** Beim Aufnehmen eines Kurses ist nicht
sichtbar, wo er abgelegt werden darf. Die möglichen Ziele erscheinen erst,
wenn man zufällig in den richtigen Bereich gerät. Das ist Raten statt
Bedienen, und es betrifft jede Person, die einen Pfad aufbaut.

**Für die Testbarkeit.** Ein Ziel, das es beim Start des Ziehens noch nicht
gibt, lässt sich nicht ansteuern. Damit ist das Anhängen eines Kurses an
einen bestehenden Knoten derzeit nicht automatisiert prüfbar — und genau
darauf liegt der fachliche Kern des Editors (Reihenfolge, UND/ODER,
Vorgänger).

Geprüft und jeweils gescheitert (Playwright 1.49.1, Chromium):

| Ansatz | Ergebnis |
|---|---|
| `locator.dragTo(knoten)` auf die Kachelmitte | kein Knoten; die Mitte ist keine Ablagezone |
| `dragTo` mit `targetPosition` außerhalb des Elements | bricht mit Zeitüberschreitung ab |
| Maus-API (`mouse.down/move/up`, 8 Zwischenschritte) | löst kein HTML5-Ziehen aus, da die Einträge `draggable`-Elemente sind |
| Chrome DevTools Protocol, `Input.setInterceptDrags` + `dispatchDragEvent` | `Input.dragIntercepted` feuert nicht, weil Playwright das Ziehen selbst abfängt |

Was **funktioniert**, ist das Ablegen auf der leeren Leinwand: Dort ist der
Startmarker ein echtes Element, und `dragTo` trifft ihn. Genau dieser Teil
ist in `local_adele/tests/e2e/tests/editor-drag-and-drop.spec.ts` als Kette
`ADELE-E2E-E1` abgedeckt.

Eine Testkennung an den Zonen **genügt nicht**: Sie tragen im DOM bereits ein
`data-id`. Das Problem ist der Zeitpunkt, nicht die Benennung.

## Vorschlag

Die Ablagezonen des nächstgelegenen Knotens einblenden, sobald ein Ziehen
beginnt (`dragstart`), statt erst bei Schnittmenge mit dem Startmarker. Also:

- beim `dragstart` die Zonen für den aktuell nächstgelegenen Knoten zeichnen,
- beim Wechsel des nächstgelegenen Knotens umziehen (wie heute),
- beim `dragend`/`drop` entfernen (wie heute).

Die Bedingung „schneidet den Startmarker" entfällt damit; die restliche
Logik in `onDrag()` bleibt.

## Akzeptanzkriterien

1. Beim Aufnehmen eines Kurses sind die möglichen Ablageziele sichtbar,
   bevor der Zeiger sie erreicht.
2. Ein Kurs lässt sich auf jede Zone ablegen; die entstehende Beziehung
   (davor, danach, UND, ODER) entspricht der heutigen.
3. Das Ablegen auf der leeren Leinwand funktioniert unverändert
   (`ADELE-E2E-E1` bleibt grün).
4. Automatisiert belegbar: eine Kette baut den Referenzpfad T01 → T02 → T03
   ausschließlich per Drag&Drop auf und findet ihn nach erneutem Öffnen
   unverändert vor (Testplan §7, E1).

## Abgrenzung

Nicht Teil dieses Issues:

- die logischen Kombinationen aus E2 (A, A UND B, A ODER B, Klammerungen),
- die Tastaturroute aus #575 B5; sie bleibt die Alternative für Personen, die
  nicht ziehen können, und ersetzt diesen Punkt nicht.
