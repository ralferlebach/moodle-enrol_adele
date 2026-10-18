### Abnahmeprotokoll – Issue #20 (Fortlaufende Einschreibung von neuen Lernpfad-Nutzern in den Host-Kurs) – Zwischenstand P0
- Ziel-Repository / Branch / Commit-SHA: mod_adele, local_adele, enrol_adele – Lieferung `2026100734` (0.7.1); Commit-SHA: **offen** (nach Übernahme)
- Grundlage: Nachprüfungsvermerk vom 2026-10-17 („Noch nicht schließen"); Entscheidung desselben Tages: „Abhängigkeit sichert die Funktionalität, alle manual-Fallbacks rückbauen."
- Original-AKs: 2 / 8 erledigt, 6 offen (Nachweis in Lieferung P1)
  1. ~~Ohne `enrol_adele` bleibt der `enrol_manual`-Sweep als Fallback funktionsfähig.~~ **Entfällt** (harte Abhängigkeit `mod_adele` → `enrol_adele` in `version.php`). Rückbau abgeschlossen:
     - In `local_adele`/`mod_adele` enthält kein Produktionscode mehr `enrol_get_plugin('manual')` – `no_manual_fallback_test::test_no_production_code_enrols_through_enrol_manual` (statischer Scan, rot, sobald ein solcher Aufruf hinzukommt).
     - Die nur dafür bestehende Einstellung `local_adele/enroll_as_setting` wird mit dem Upgrade-Schritt `2026100734` gelöscht; `enrol_adele` übernimmt sie bei der Installation nicht mehr. Auf der Prüfinstanz belegt: Wert gesetzt (`5`) → Upgrade → „No such configuration variable found".
  2. ~~Automatisierter Test: Ohne `enrol_adele` greift der manuelle Fallback.~~ **Ersetzt durch die Gegenprobe:** `no_manual_fallback_test::test_without_enrol_adele_nobody_is_enrolled_by_another_method` – `adele` aus `enrol_plugins_enabled` entfernt, Lernende im Startknoten-Kurs, Quelle 2: keine Einschreibung in den Host-Kurs, über keine Methode; `warn_enrol_adele_missing()` meldet per `debugging()`. Grün auf PostgreSQL 16.
  3. [ ] Node-Kurs-Einschreibung nach dem Speichern löst für Fall 2/3 die Subscription aus – Test geschrieben (`node_enrolment_after_save_test`), Teilfälle grün; Abnahme in P1 (siehe Abweichung).
  4. [ ] Austragung aus dem letzten qualifizierenden Node-Kurs **suspendiert** die Host-Einschreibung; weitere qualifizierende Mitgliedschaften bleiben wirksam – **Abweichung belegt**, siehe unten.
  5. [ ] Host-Einschreibung erscheint als `enrol_adele` (Host-Instanz), nicht manuell – im selben Test grün (nur `adele/host`, keine weitere Methode); Abnahme mit P1.
  6. [ ] Fall 1 unverändert – im selben Test grün; Abnahme mit P1.
  7. [ ] PostgreSQL und MariaDB grün, E2E und Negativprüfungen – offen bis P1.
  8. [ ] CI mit kompatiblen Versionen grün und verlinkt – offen (Commit-SHA nach Übernahme).
- Code (P0): `local/db/upgrade.php` (Schritt 2026100734), `enrol/db/install.php` (Übernahme entfernt), `enrol/db/upgrade.php` (Kommentar), `mod/db/install.xml` (Kommentar), neu `mod/tests/no_manual_fallback_test.php`
- PHPUnit: `mod_adele\no_manual_fallback_test` (2 Tests, 4 Assertions) – grün auf PostgreSQL 16 und MariaDB 10.11 (Gesamtsuiten local 413 / enrol 72 / mod 89 auf beiden)
- JS-Unit/Behat/Playwright: n. a. für P0
- Negativkontrollen: `enrol_adele` deaktiviert → keine Einschreibung, keine andere Methode
- Upgrade/Restore/Privacy: Upgrade-Schritt entfernt eine Einstellung ohne weitere Wirkung (der Pfad, den sie bediente, existiert seit Session 003 nicht mehr)
- Dokumentation/Pflichtenheft: `docs/pflichtenheft.md` 1.3, 1.4 (Bestätigung #20), 1c (überholt); Protokoll Session 006 Teil 68
- Verbleibende Abweichungen:
  - **AK 4 widerspricht #21 (belegt).** #20 verlangt beim Verlassen des letzten qualifizierenden Node-Kurses eine *Suspendierung* der Host-Einschreibung („nicht gelöscht"). #21 verlangt, dass beim Verlassen des Lernpfads alle Host-Einschreibungen *ausgetragen* werden (`unenrol_user()`). Bei Quelle 2/3 ist das Verlassen des letzten Node-Kurses zugleich das Verlassen des Lernpfads (A-4). Der heutige Code folgt #21 und löscht sofort (`purge_all_host_user()` im Observer); `node_enrolment_after_save_test::test_source_3_any_node_last_membership_suspends` ist daran rot (erwartet `suspended`, gefunden: keine Einschreibung).
  - Vom Nutzer bestätigte Auflösung (2026-10-17) für P1, die beide AKs erfüllt: beim Austritt sofort **suspendieren** (Zugang weg, dieselbe Einschreibung bleibt, Rückkehr in der Karenzzeit reaktiviert sie); erst wenn `remove_user_path_adhoc` nach der Karenzzeit den Austritt bestätigt, die Host-Einschreibungen **austragen** (#21). Pflichtenheft 1a/2.5 werden entsprechend angepasst.
- Entscheidung: [x] offen lassen  [ ] schließen – Fallback-Teil erledigt; AK 3–8 mit P1
