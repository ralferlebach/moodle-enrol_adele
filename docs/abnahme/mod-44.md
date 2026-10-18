### Abnahmeprotokoll – Issue #44 (Datenschutz-Löschung zerstört fremde Daten und lässt Verweise verwaist zurück)
- Ziel-Repository / Commit-SHA: local_adele, mod_adele, enrol_adele – Behebung in Lieferung `2026100735` (Paket P1, Entscheidung des Nutzers 2026-10-17: Anonymisieren), Abnahme im Re-Audit 0735 (TP-06) mit `2026100736`; Commit-SHA: **offen**
- Ursprüngliche Defekte (belegt, nicht mehr vorhanden): Löschung der Erstellerin löschte geteilte Lernpfade und Teilnahmen **anderer**; verwaiste Einschreibungen; „alle Nutzer" löschte alle Pfade; mod_adele war `null_provider` trotz `teachercourseaccessgrantor`. Probe mit dem alten Provider: 5 von 8 Tests rot.
- Umsetzung: `local/classes/privacy/provider.php` (`delete_user()`: eigene Teilnahmen mit ihren ADELE-Einschreibungen weg, Bearbeiterrechte weg, erstellte Pfade gehen an die ranghöchste verbleibende Bearbeitung oder bleiben ohne Besitzer, fremde Teilnahmen bleiben mit `createdby = 0`); `mod/classes/privacy/provider.php` (Grantor exportiert und anonymisiert, danach `teacher_enrolment::reconcile_cm()`); `enrol/classes/privacy/provider.php` (Lehrenden-Zuweisungen).
- AKs des Re-Audits (TP-06): 3 / 3 erfüllt
  1. Alle drei Provider mit Kontext-, Export-, Lösch- und Fremddaten-Tests:
     - local: `privacy_provider_test` (9) – Metadaten, Kontexte/Nutzer, Export, Erstellerin, ohne Nachfolge, Lernende/r, Nutzerliste, alle Nutzer, **neu** `test_several_paths_in_bulk` (zwei Pfade, Bulk-Löschung von Erstellerin und Lernender, Rollenzuweisungen von enrol_adele).
     - mod: `teacher_enrolment_test::test_privacy_of_the_grantor` (Kontexte, Nutzerliste, Export, Anonymisierung, erneute Delegation), **neu** `test_privacy_in_bulk_keeps_independent_access` (delegierende Person und Lehrkraft in einem Bulk; Kontext-weite Löschung).
     - enrol: `teacher_enrolment_test::test_privacy` (Kontexte, Löschung der Zuweisung).
     - Moodles Konformitätsprüfung `core_privacy\privacy\provider_test` (Filter `adele`, 12 Tests).
  2. Datenbank-Invarianten nach der Löschung – `assert_nothing_dangles()` (keine ADELE-Einschreibung ohne Teilnahme, keine Aktivität mit fehlendem Pfad, keine Bearbeiterzeile ohne Pfad) in jedem Löschtest; keine Rollenzuweisung `component = enrol_adele` der gelöschten Person; keine Lehrenden-Zuweisung einer Aktivität ohne Befugnis (Grantor 0 → Grant weg).
  3. Fremde Pfade und Teilnahmen bleiben – beide Pfade bleiben, die andere Lernende bleibt auf beiden Pfaden mit ihren Rollen; eine eigene manuelle Einschreibung der gelöschten Person bleibt; die unabhängige Zuweisung einer zweiten Aktivität bleibt, bis auch deren Kontext gelöscht wird.
- Nebenwirkungen (dokumentiert, Pflichtenheft 5.2b): Lehrenden-Modus ohne Grantor wirkt erst wieder nach Speichern durch eine befugte Person.
- Tests: Gruppe `adele_release` – PostgreSQL und MariaDB grün (Zahlen: `README.md`, Prüfstand `2026100736`)
- Verbleibende Abweichungen: keine
- **Entscheidung (Re-Audit): schließbar**
