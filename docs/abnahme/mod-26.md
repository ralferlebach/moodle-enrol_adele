### Abnahmeprotokoll – Issue #26 (Aktualisierungsintervall des Nutzerlernpfades global konfigurierbar)
- Ziel-Repository / Commit-SHA: local_adele (+ mod_adele Seiten, Tests) – Lieferung `2026100736`; Commit-SHA: **offen**
- Original-AKs: 14 / 14 erfüllt (lokal; Firefox-Lauf offen, siehe unten)
  1. Global in den Plugin-Einstellungen – local_adele, Abschnitt „Geöffneter Lernpfad" (`settings.php`); `path_view_settings_test::test_defaults` liest die Seite.
  2. Genau Modi 0–3 mit der Semantik – derselbe Test (Auswahl genau 0–3); Verhalten: Jest `useUserPathRefresh.spec.js` (je Modus), E2E R26 (je Modus im Browser).
  3. Standard Modus 2 · 4. Intervall 3000 ms · 5. Debounce 300 ms – `test_defaults`, Jest `parsePathViewSettings`.
  6. Intervall und Debounce konfigurierbar und validiert – `admin_setting_bounded_int` (1000–300000 ms, 0–10000 ms, ganze Zahlen, Grenzen eingeschlossen): `test_bounded_numbers_are_validated`; ungültige gespeicherte Werte gelten als Standard (`test_stored_values_and_invalid_ones`, Jest).
  7. Website-weit, nicht je Aktivität – Einstellungen von local_adele; die Seite übergibt sie einmal als Attribut (`test_the_template_carries_the_settings`); kein Abruf von Einstellungen beim Abfragen.
  8. Fokus-Aktualisierung in 1–3 – Jest (focus, visibilitychange, pageshow aus dem Cache), E2E R26 Modus 1/2.
  9. Modus 2 nur bei aktiver Ansicht – Jest (Pause bei blur/hidden, Fortsetzen nach Fokus), E2E R26 Modus 2 (keine Anfrage, solange verborgen).
  10. Modus 3 im Hintergrund – Jest, E2E R26 Modus 3.
  11. Keine parallelen Anfragen – Jest (langsamer Server, Fokusereignisse währenddessen: höchstens eine offen), E2E R26 Modus 2 mit 2,5 s langsamen Antworten: `maxOpen = 1`.
  12. Listener und Timer beim Unmount entfernt – Jest (`stop()`: 0 Listener, 0 Timer, späte Antwort ohne Wirkung), Jest `UserPath.refresh.spec.js` (Unmount).
  13. Nur gespeicherter Zustand – jede Aktualisierung ruft nur `local_adele_get_user_path_relation` (E2E zählt genau diese Methode); die Teilnehmendenliste nur bei Rückkehr zur Seite.
  14. Keine fachliche Neuberechnung – `path_refresh_readonly_test` (drei Abrufe: keine Änderung an Pfaden und Teilnahmen, keine Einschreibung, kein Ereignis, keine Aufgabe).
  - Fehlerverhalten (Lösung): Debug-Ausgabe, Verdopplung bis ×8/5 min, ein Hinweis nach drei Fehlern, spätere Versuche laufen weiter – Jest; E2E „failing server" (500 → später wieder Anfragen, Seite bleibt bedienbar).
- Code: `local/classes/path_view_settings.php`, `local/classes/admin_setting_bounded_int.php`, `local/settings.php`, `local/templates/initview.mustache`, `local/index.php`, `mod/view.php`, `mod/lib.php`, `vue3/composables/useUserPathRefresh.js`, `vue3/composables/flowHelper/pathViewSettings.js`, `vue3/components/user_view/UserPath.vue`, `vue3/main.js`, `vue3/store.js`, Texte en/de
- PHPUnit: `path_view_settings_test` (4), `path_refresh_readonly_test` (1) – grün auf PostgreSQL und MariaDB
- JS-Unit: `useUserPathRefresh.spec.js` (13), `autofocus.spec.js` (Einstellungen), `UserPath.refresh.spec.js` (9); Gesamt-Jest mit Coverage-Gate grün
- Playwright: Story R26 (5 Tests) grün in Chromium; **Firefox: im Workflow vorgesehen (Projekt `firefox`), lokal nicht installierbar – offen bis zum ersten CI-Lauf**
- Verbleibende Abweichungen: Firefox-Lauf; Behat-Szenarien durch Playwright ersetzt (das Master-Issue lässt „Behat/Playwright" zu)
- Entscheidung: [x] offen lassen  [ ] schließen – bis zum grünen E2E-Lauf mit Firefox
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Überholte Antworten (TP-02): Jede Anfrage trägt eine Generation; eine Antwort, die von einer neueren überholt wurde oder nach `stop()` eintrifft, wird verworfen (`useUserPathRefresh.js:97–139`). Hängende Anfrage: nach `requestTimeout()` = 3 × Intervall, mindestens 10 s, höchstens 60 s, gilt sie als gescheitert; das Abfragen läuft weiter (Zeile 61, 119). Jest: „hung request“, „out-of-order“, „null answer“ (16 Tests).
  - Moduswechsel und Sichtbarkeitswechsel E2E: R26 Modus 0–3 (5 Tests) mit verborgener/sichtbarer Seite; Debounce Modus 1.
  - Die AKs von #26 verlangen keinen bestimmten Browser; Firefox-Projekt für R26 im Workflow vorgesehen (lokal nicht installierbar) – betrifft AK 3 von #32.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
