# Abschlusskommentar zu local_adele #580

**Stand:** 2026-10-08, Session 006. Entscheidung des Auftraggebers: #580
schließen, offene Punkte neu erfassen.

Zum Einfügen als Kommentar unter #580, danach das Issue schließen. Alles
unterhalb der Trennlinie ist der Kommentartext.

---

**Erledigt, wird geschlossen.**

1. **Testbarkeit (Begründung des Issues):** trifft nicht zu. Drag & Drop im
   Editor ist mit Playwright automatisiert prüfbar (`locator.hover()` +
   `mouse.down()`, geführt mit `pane.hover({ position, force: true })`); die
   E2E-Ketten E1/E2 und `editor-drop-zones.spec.ts` laufen damit.
2. **Zonen bleiben am zuerst passierten Knoten:** umgesetzt mit `2026100714`.
   Die Zonen gehören dem Knoten, dessen Mitte dem Zeiger am nächsten ist
   (Entscheidung: „immer der topologisch nächstliegende Knoten"). Ausnahmen:
   Eine Zone, auf der der Zeiger steht, bleibt; ein anderer Knoten übernimmt
   erst mit 30 Einheiten Vorsprung. Code: `zoneOwner.ts`,
   `SidebarPath.vue::onDrag`. Tests: Jest `zoneOwner.spec.ts`, E2E
   `editor-drag-and-drop.spec.ts` (2 Tests, beide mit Gegenprobe).
   Ergänzt mit #595 (`2026100717`): Die erreichte Zone bleibt auch über dem
   Startmarker und jenseits der Abstandsgrenzen.
3. **Ansicht nach dem Ablegen nicht neu eingepasst:** neu erfasst als eigenes
   Issue (#…).
4. **Beschriftung der seitlichen Zone:** erledigt durch #584 — die Zone
   „Alternative node" nimmt den parallelen Knoten als weiteren möglichen
   Vorgänger auf (`min_courses` bleibt 1, „einer genügt"); Beschriftung und
   Verhalten stimmen damit überein. Nur der interne Name `dropzone_and` passt
   nicht dazu; er ist für Nutzende nicht sichtbar.
