### Abnahmeprotokoll – Issue #11 („Message was not sent" beim ersten Anlegen eines Lernpfads in einem Kurs)
- Ziel-Repository / Commit-SHA: mod_adele – Lieferung `2026100736`; Commit-SHA: **offen**
- Ursache (belegt, Teil 17; Pflichtenheft 1b): Moodles Willkommensnachricht einer `enrol_manual`-Einschreibung, die der frühere manual-Rückfallweg von ADELE auslöste; die Meldung stammt aus `core\message\manager::send_message_to_processors()` („Message was not sent."), wenn ein Nachrichtenprozessor scheitert. Der Rückfallweg existiert nicht mehr (#20, `no_manual_fallback_test`).
- Master-Issue P2.4:
  1. Erste Aktivität in einem frischen Kurs, zweite als Gegenprobe – E2E S11 (`first-embedding.spec.ts`): pro Lauf ein **neuer** Kurs mit einem Mitglied, manuelle Einschreibung sendet die Willkommensnachricht (Absender „noreply"); erste Aktivität (Quellen „dieser Kurs" und „irgendein Kurs") und zweite über das Formular.
  2. Konsole, PHP-Ausgabe, AJAX, Ereignisweg geprüft – S11 beobachtet Konsolenfehler, Seitenfehler, HTTP ≥ 500, AJAX-Antworten mit Ausnahme, Seitentext („Message was not sent", Notice/Warning/Deprecated/Debug) und Fehlerhinweise: keine. Nachrichten und Benachrichtigungen der Website vorher/nachher: gleich.
  3. Behoben, ohne legitime Nachrichten global zu unterdrücken – keine Unterdrückung; ADELE schreibt nur noch über enrol_adele ein (keine Willkommensnachricht).
  4. Regressionstest erster und weiterer Lernpfad – PHPUnit `first_embedding_test`: Nachrichtensenke bleibt bei beiden Aktivitäten leer, Mitglied und Node-Kurs-Teilnehmerin sind auf beiden Pfaden, Gastkurs nur über `adele`. Gegenprobe des Instruments: eine manuelle Einschreibung im selben Aufbau erzeugt eine Nachricht (`test_the_sink_sees_a_manual_welcome`; im Browser-Aufbau ebenso geprüft: +1).
- PHPUnit: `first_embedding_test` (2) – grün auf PostgreSQL und MariaDB; Playwright S11 grün
- Verbleibende Abweichungen: keine
- Entscheidung: [ ] offen lassen  [x] schließen – nach grünem CI-Lauf des finalen Commits
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Erstes und zweites Anlegen reproduziert und als Regression belegt (`first_embedding_test`, E2E S11); kein Fix ohne reproduzierten Fehler nötig, die Ursache (manual-Rückfallweg) ist mit #20 entfallen.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): schließbar**
