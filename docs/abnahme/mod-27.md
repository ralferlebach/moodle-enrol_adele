### Abnahmeprotokoll – Issue #27 (Rollenabhängiger Auto-Fokus bei Zustandsänderungen)
- Ziel-Repository / Commit-SHA: local_adele (+ mod_adele Tests) – Lieferung `2026100736`; Commit-SHA: **offen**
- Original-AKs: 12 / 12 erfüllt (lokal; Firefox-Lauf offen)
  1. Global abschaltbar – `pathautofocusenabled`; aus: Jest (`autofocusMode` 0; `UserPath.refresh.spec.js` „auto-focus off": Ansicht bleibt), E2E F27 „auto-focus off" (Transformation unverändert).
  2. Getrennte Modi Lehrende/Lernende – `pathautofocusteachermode`, `pathautofocuslearnermode`; Jest `autofocusMode`.
  3. Lehrenden-Standard „zuletzt abgeschlossen/geändert" · 4. Lernenden-Standard „nächste geöffnete, nicht abgeschlossene" – `test_defaults`, Jest `resolveAutofocusTarget`.
  5. Rolle nach Ansicht und wirksamen Rechten – `autofocusRole`: Lehrendenansicht (vom Server aus Rechten gewählt, `view_mode`) oder fremder Pfad → Lehrende; eigener Pfad in der Studierendenansicht → Lernende (Jest, auch über `UserPath`).
  6. Nur bei echter Zustandsänderung – `compareUserPathStates` (Jest: unverändert, nur Texte/Namen geändert, Erstladen, neu sichtbare Knoten → keine Änderung).
  7. Unveränderte Abfragen verändern die Position nicht – Jest (`UserPath`: zweite identische Antwort → kein `fitView`/`setViewport`), E2E F27 (fünf Sekunden Abfragen nach eigenem Verschieben: Transformation gleich).
  8. Parallele nächste Knoten gemeinsam – Jest (`dndnode_2`, `dndnode_3` zusammen; späterer offener Knoten wartet hinter früherem).
  9. Wiederholtes Abfragen überschreibt manuelle Navigation nicht dauerhaft – ein Fokus je Zustand (Signatur), während Verschieben/Zoomen/Dialog/Eingabe kein Fokus (Jest `interactionBusy`), E2E F27.
  10. Nur Anzeige · 11. Keine Neuberechnung oder Speicherung – Jest (Vergleich und Zielwahl ändern die Zustände nicht), `focusNodes` ruft nur `fitView`; `path_refresh_readonly_test`.
  12. Vergleich, Zielwahl, Ansicht getrennt und testbar – `compareUserPathStates`, `resolveAutofocusTarget`, `focusNodes` (`composables/flowHelper/autofocus.js`), je eigene Jest-Tests.
  - E2E F27: T01 in einem zweiten Tab abgeschlossen → der offene Pfad zeigt „abgeschlossen" ohne Neuladen und bewegt die Ansicht zum nächsten offenen Knoten (vorher bewusst weit weg geschoben).
  - Zeitstempel: Der Server speichert heute keine Zeit des Zustandswechsels; die Reihung nutzt sie, sobald vorhanden, sonst „Abschluss vor anderer Änderung, dann Knoten-ID" (Jest).
- Code: `vue3/composables/flowHelper/autofocus.js`, `vue3/components/user_view/UserPath.vue` (Fokus erst, wenn die Knoten nach dem Neuaufbau vermessen sind), Einstellungen wie #26
- JS-Unit: `autofocus.spec.js` (22), `UserPath.refresh.spec.js` (9); PHPUnit `path_view_settings_test`
- Playwright: Story F27 (2 Tests) grün in Chromium; **Firefox offen** (wie #26)
- Verbleibende Abweichungen: Lehrenden-Fokus nur in Unit-Tests (keine E2E-Kette mit Lehrenden-Ansicht eines fremden Pfads); Firefox-Lauf
- Entscheidung: [x] offen lassen  [ ] schließen – bis zum grünen E2E-Lauf mit Firefox
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Alle acht Testfälle aus #27 in Unit **und** E2E: (1) Lehrenden-Modus, neuer Abschluss → `path-refresh.spec.ts` „teacher default (Testfall 1, 2, 6)“; (2) mehrere Änderungen → derselbe Test (T01, dann T02); (3) ein nächster Knoten → F27 „learner default“; (4) **neu** parallele nächste Knoten → „Testfall 4“ (Seed „E2E27 Parallel“: T02 und T03 warten nur auf T01; beide zusammen im Blick; Gegenprobe nur ein Ziel → rot); (5) Auto-Fokus aus → F27 „auto-focus off“; (6) Rollen-Standards → Lehrenden- und Lernenden-Test; (7) unveränderter Zustand mit Verschieben → F27 Schritt „moved alone afterwards“; (8) Interaktion während der Aktualisierung → „Testfall 8“ (Gegenprobe ohne Schutz → rot). Jest: `autofocus.spec.js`, `UserPath.refresh.spec.js`.
  - Tastatur-/Screenreader-Fokus getrennt vom Viewport (TP-03): Auto-Fokus bewegt nur die Ansicht (`focusNodes` → `fitView`), nie den Tastaturfokus; beim Neuaufbau nach einer Änderung merkt `keyboardFocus.js::rememberFocus()` das fokussierte Element (Test-ID, sonst Knoten und Position) und gibt es dem neuen Element zurück, ohne zu scrollen; liegt der Fokus außerhalb des Pfads, wird er nicht angefasst; nach 3 s ohne Element wird aufgegeben – keine Fokusfalle (`UserPath.vue:330–336`; Jest `keyboardFocus.spec.js` (5), `autofocus.spec.js` „keyboard focus stays“).
  - Lehrenden-Fokus jetzt auch E2E (vorher nur Unit).
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
