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
