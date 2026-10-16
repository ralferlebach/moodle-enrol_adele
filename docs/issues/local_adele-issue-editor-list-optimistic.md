# Issue-Entwurf (local_adele): Bearbeiterliste zeigt Änderungen, die der Server abgelehnt hat

> **Stand:** eingereicht als local_adele #599 (2026-10-13), behoben mit `2026100727` (Protokoll Teil 62).

**Gefunden:** Jest-Matrix `userSearch.matrix.spec.js` (Jest-Issue, Bereich
G2: „UI bleibt nach Fehler konsistent"), Session 006, Teil 58.

## Befund

`vue3/components/charthelper/userSearch.vue`:

- `removeUser()` nimmt die Karte sofort aus der Liste und wartet nicht auf
  die Antwort des Servers.
- `addUser()` fügt die Karte sofort hinzu, ebenfalls ohne Antwort.
- Die Store-Aktionen `createLpEditUsers` und `removeLpEditUsers`
  (`vue3/store.js`) geben den Request nicht zurück; ein Fehler wird nur als
  unbehandelte Promise-Ablehnung sichtbar (Moodle-Fehlerdialog), die Liste
  bleibt falsch.

Folge: Lehnt der Server ab (z. B. `nopermissions`, oder `editorremoveowner`
bei einem veralteten Stand), sieht die Person eine Liste, die nicht gilt –
bis zum Neuladen.

## Erwartung

Die Liste ändert sich erst, wenn der Server zugestimmt hat; bei Ablehnung
bleibt sie, wie sie war (und der Grund wird gemeldet).

## Reproduktion

```bash
cd vue3
ADELE_RUN_KNOWN_DEFECTS=1 npx jest tests/unit/components/charthelper/userSearch.matrix.spec.js
```

## Akzeptanz

- Store-Aktionen geben die Antwort zurück (`return ajax(...)`).
- `addUser`/`removeUser` warten darauf und ändern die Liste nur bei Erfolg.
- Die zwei `knownDefect`-Tests laufen regulär.
