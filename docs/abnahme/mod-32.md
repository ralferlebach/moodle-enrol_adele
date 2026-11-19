### Abnahmeprotokoll – Issue #32 (Frontend-Unit-Tests für Polling, Fokus, Viewport und Datenfluss)
- Ziel-Repository / Commit-SHA: local_adele (+ mod_adele E2E) – Lieferung `2026100736` (von P1 nach P2 verschoben, weil die Tests der Aktualisierungslogik aus #26 gelten); Commit-SHA: **offen**
- Original-AKs: 3 / 4 erfüllt, 1 offen bis zum Firefox-Lauf
  1. Genau ein Initialabruf – **Befund (belegt), behoben:** `StudentView` reichte den geladenen Pfad als `userlearningpathparent` weiter, `UserPath` las `user_learningpath_parent`; jede Lernendenansicht lud doppelt. Gegenprobe mit dem alten Namen: E2E R26 Modus 0 „exactly one load" rot (2 statt 1). Jest: `UserPath` mit Elterndaten → 0 eigene Abrufe; ohne → genau 1; der Composable lädt beim Start nicht, `pageshow` beim ersten Laden zählt nicht.
  2. Keine parallelen Abfragen – Jest (langsamer Server mit Fokusereignissen, Fehler, Abbruch durch `stop()`), E2E R26 (2,5 s langsame Antworten, `maxOpen = 1`).
  3. Fokus-Aktualisierung in allen unterstützten Browsern – Chromium grün (E2E R26 Modus 1/2); **Firefox: Projekt im Workflow vorgesehen, lokal nicht installierbar – offen**.
  4. Deterministisches Viewport-Verhalten – Jest (`UserPath`: Fokus nur bei Änderung, sonst exakte Wiederherstellung; Interaktion verhindert Fokus), E2E F27.
  - Master-Issue P1.8: Datenfluss, Statusvergleich ohne Seiteneffekte (Jest), CI mit Coverage (Jest-Workflow mit Coverage-Gate; Gate bleibt eingehalten), Playwright als E2E-Schicht (R26, F27, S17 auch für Firefox vorgesehen).
- Tests: Jest gesamt 800 (103 Suiten), davon neu `useUserPathRefresh.spec.js` (13), `autofocus.spec.js` (22), `UserPath.refresh.spec.js` (9), `expandedCourse.spec.js` (4), `ExpandedCourses.spec.js` (2), `ExpandNodeEdit.spec.js` (+1)
- Verbleibende Abweichungen: Firefox (AK 3); Behat-Smoke in Firefox/Chromium durch Playwright in beiden Browsern ersetzt
- Entscheidung: [x] offen lassen  [ ] schließen – bis zum grünen E2E-Lauf mit Firefox
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Isolierte Frontend-Tests (TP-04): Zustandsvergleich, Zielwahl, Viewport getrennt (`autofocus.js`), Abfrage-Logik (`useUserPathRefresh.js`), Tastaturfokus (`keyboardFocus.js`); Fake-Timer und kontrollierte Promises statt Wartezeiten; ein Initialabruf, kein paralleler Request, Debounce, Unmount, Visibility/Focus, überholte Antworten, Null-/Leerzustand, mehrere Knoten, manuelle Navigation – je eigener Testfall.
  - Lokal ausführbar: `cd local/vue3 && npm test` (Jest gesamt mit Coverage-Gate, Zahl: Prüfstand in `README.md`).
  - Offen: AK 3 „alle unterstützten Browser“ – Firefox-Lauf der Ketten R26/F27/S17 (Projekt `firefox`, `ADELE_E2E_FIREFOX`) nur im Workflow; hier nicht installierbar.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): abnahmefähig**
- **Re-Audit 2026100736 (2026-10-18), Lieferung `2026101800`:** Neue isolierte Tests: späte Rückrufe nach Unmount, zwei Aktualisierungen kurz hintereinander, nicht mehr gewollte Fokus-Rückgabe. Firefox bleibt offen (AK 3).
