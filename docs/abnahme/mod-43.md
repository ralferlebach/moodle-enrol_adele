### Abnahmeprotokoll – Issue #43 (Trainer/innen ohne Bearbeitungsrecht am Lernpfad können die Aktivität nicht speichern)
- Ziel-Repository / Commit-SHA: mod_adele (+ local_adele E2E-Seed) – Lieferung `2026100736` (Re-Audit 0735, TP-01); Commit-SHA: **offen**
- Ursache (belegt): `mod_form.php` bot nur `learning_paths::get_editable_learning_paths()` an; der gespeicherte, fremde Pfad fehlte in der Auswahl, die Pflichtauswahl schlug fehl – andere Einstellungen ließen sich nicht speichern. Der E2E-Seed trug Lehrende deshalb künstlich als Pfad-Bearbeiter/innen ein (Workaround).
- AKs des Re-Audits (TP-01): 6 / 6 erfüllt
  1. Bestandswert zusätzlich angeboten, wenn er noch existiert – `mod/classes/local/path_choice.php::options()` (bearbeitbare Pfade + gespeicherter Pfad); `mod_form.php:90–91`.
  2. Beibehaltbar, aber kein frei wechselbares Ziel; Wechsel serverseitig geprüft – `path_choice::check()` (gleich dem gespeicherten → erlaubt; anderer Pfad → nur mit Bearbeitungsrecht, aus der Datenbank, nicht aus den angezeigten Optionen); `mod_form.php::validation()` Zeile 244.
  3. Gelöschter Pfad: verständlicher Reparaturweg ohne zufällige ID – Hinweis `mform_path_missing_stored` im Formular (`mod_form.php:96`), Fehler `mform_path_missing`; eine erfundene ID wird ebenso abgewiesen.
  4. `required` und PHP-Validierung konsistent; gefälschte Werte abgewiesen – `test_another_foreign_path_is_refused` (Wert nicht unter den Optionen, per `mock_submit`).
  5. PHPUnit (a)–(e) – `mod/tests/path_choice_test.php`: (a) `test_a_teacher_keeps_the_path_and_saves` (Name geändert, Pfad behalten, **kein** Bearbeiterrecht danach), (b) `test_another_foreign_path_is_refused`, (c) `test_an_own_path_is_allowed`, (d) `test_a_deleted_path_cannot_be_kept`, (e) `test_an_administrator_chooses_any_path`.
  6. E2E-Workaround entfernt, echte Konstellation – Seed löscht die frühere Bearbeiterzeile (`seed_fixtures.php`, Block KZ); die Kette K16 speichert das Formular als Trainer/in ohne Pfadrecht: grün. Gegenprobe mit altem Formular: K16 rot (Speichern scheitert).
- DoD TP-01: speicherbar ohne Pfadrecht ✓, unberechtigter Wechsel unmöglich ✓, keine Rechteausweitung ✓ (Test a), reproduzierbarer Negativtest ✓ (Test b, d).
- Tests: PHPUnit `path_choice_test` (5) in Gruppe `adele_release` – PostgreSQL und MariaDB grün; Playwright K16 grün (PostgreSQL)
- Verbleibende Abweichungen: keine
- **Entscheidung (Re-Audit): schließbar**
