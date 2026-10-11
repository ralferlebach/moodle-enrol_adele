# Kommentarentwurf zu local_adele #580

Zum Einfügen als Kommentar unter #580. Alles unterhalb der Trennlinie ist der
Kommentartext.

---

**Korrektur der Begründung: Die Testbarkeit ist kein Grund mehr.**

Die Annahme in der Beschreibung — das Anhängen an einen bestehenden Knoten
sei nicht automatisiert prüfbar, weil die Ablagezonen erst während des
Ziehens entstehen — trifft nicht zu. Gemessen:

- Wird das Ziehen mit `locator.hover()` + `mouse.down()` begonnen und mit
  `pane.hover({ position, force: true })` geführt, kommen echte
  HTML5-Drag-Ereignisse mit korrekten Koordinaten an, die vier Zonen
  erscheinen, und die Trefferprüfung des Editors arbeitet korrekt.
- Damit laufen die E2E-Ketten E1 (Referenzpfad T01 → T02 → T03) und E2
  (A ODER B, A UND B) per echtem Drag&Drop grün, ohne Produktänderung
  (`tests/e2e/support/editor.ts`).

Die früheren Fehlschläge lagen in der Testtechnik: Die rohe Maus-API löst
kein HTML5-Ziehen aus; `dragTo` legt sein Ziel fest, bevor die Zonen
existieren; die Seite scrollte mitten im Ziehen.

**Was als Bedienpunkt bleibt** (zu entscheiden, ob dieses Issue dafür
weitergeführt wird):

1. **Zonen bleiben am zuerst passierten Knoten hängen.** Sie werden um den
   Knoten gezeichnet, der dem Zeiger am nächsten ist, und erst wieder
   entfernt, wenn der Zeiger den Startmarker überquert. Wer von der
   Seitenleiste zu einem weiter unten liegenden Knoten zieht, kommt an
   anderen Knoten vorbei und bekommt die Zonen dort angeboten.
2. **Die Ansicht wird nach dem Ablegen nicht neu eingepasst.** Der neue
   Knoten kann teilweise unterhalb der sichtbaren Fläche liegen; Einpassen
   geschieht nur beim Öffnen und bei Größenänderung (#480).
3. **Beschriftung der seitlichen Zonen** — siehe gesondertes Issue zur Zone
   „Alternative node".

Vorschlag: #580 auf Punkt 1 und 2 zuschneiden oder schließen und die Punkte
neu erfassen.

---

## Umsetzung von Punkt 1 (Version `2026100714`)

Entscheidung des Auftraggebers (2026-10-07): „Immer der topologisch
nächstliegende Knoten ist am einfachsten im Handling zu verstehen."

- **Regel:** Die Ablagezonen gehören dem Knoten, dessen Mitte dem Zeiger am
  nächsten ist, und wandern beim Ziehen von Knoten zu Knoten mit. Der Umweg
  über den Startmarker ist nicht mehr nötig.
- **Zwei Ausnahmen, ohne die die Zonen nicht bedienbar wären:**
  1. Solange der Zeiger **auf** einer gezeichneten Zone steht, bleiben die
     Zonen. Eine Zone liegt neben ihrem Knoten und kann einem Nachbarknoten
     näher sein als dem eigenen (Beispiel: die Nachfolger-Zone eines Knotens,
     der schon einen Nachfolger hat, liegt neben diesem Nachfolger).
  2. Ein anderer Knoten übernimmt erst, wenn er um 30 Einheiten der
     Zeichenfläche näher ist — sonst sprängen die Zonen auf der Mittellinie
     zwischen zwei Knoten hin und her.
- **Code:** `vue3/composables/nodesHelper/zoneOwner.ts` (die Entscheidung),
  `vue3/components/flowchart/SidebarPath.vue::onDrag` (ruft sie bei jedem
  Schritt des Ziehens auf).
- **Tests:** Jest `zoneOwner.spec.ts` (5); E2E in
  `editor-drag-and-drop.spec.ts`:
  - „the drop zones follow the pointer from node to node (#580)" — an T01
    vorbei zu T02 ziehen und dort ablegen (T03 hängt an T02); danach hin und
    her über drei Knoten.
  - „a zone that lies nearer to a neighbouring node stays under the pointer
    (#580)" — T01 und T02 nebeneinander; die Zone „parallel zu T01" liegt
    näher an T02 und bleibt trotzdem unter dem Zeiger.
- **Gegenproben:** Der erste Test schlägt mit dem Stand `2026100713` fehl
  (Zonen erscheinen nie an T02). Der zweite schlägt fehl, wenn die Ausnahme 1
  ausgebaut wird (eigens gebaute Variante; „the zones must still be those of
  T01").
- **Erreichbarkeit bei geführtem Zeiger** (14 Schritte vom Knoten zur Zone,
  zwei Topologien, je 3 Knoten × 4 Zonen): vor und nach der Änderung
  dieselben 17 von 24 Zonen erreicht — die Änderung nimmt keine Zone weg.
  Die 7 nicht erreichten sind ein eigener Befund, siehe
  `local_adele-issue-offset-dropzones-unreachable.md`.
- **Nicht Teil dieser Umsetzung:** Punkt 2 (Einpassen nach dem Ablegen) und
  Punkt 3 (Beschriftung der seitlichen Zonen).
