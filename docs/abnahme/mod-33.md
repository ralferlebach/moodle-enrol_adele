### Abnahmeprotokoll – Issue #33 (Backup-, Restore- und Privacy-Tests in die reguläre Release-Matrix aufnehmen)
- Ziel-Repository / Commit-SHA: alle drei – Lieferung `2026100735`; Commit-SHA: **offen**
- Original-AKs: 2 / 3 lokal belegt, 1 offen bis zum ersten CI-Lauf
  1. Backup/Restore und Privacy auf jeder unterstützten Version grün – lokal Moodle 4.5 × PHP 8.3 × PostgreSQL 16 und MariaDB 10.11 grün; Matrix 4.5 × PHP 8.1/8.2/8.3 × PostgreSQL/MariaDB/MySQL in `.github/workflows/release-gate.yml` (alle drei Plugins). **Offen:** erster grüner Lauf (nach Übernahme; von hier aus nicht auslösbar).
  2. Keine verwaisten Verweise, keine fremden Daten – `local_adele\privacy_provider_test` prüft nach jeder Löschung: keine ADELE-Einschreibung ohne Teilnahme, keine Aktivität ohne Pfad, keine Bearbeiterzeile ohne Pfad, keine fremden Fortschritte gelöscht.
  3. Regression blockiert das Release – Gate läuft bei jedem PR nach `main` und jedem Tag `v*`; Pflichtprüfung von `main` (Branch-Schutz) **vom Repository-Admin zu setzen**.
  - Lösungsumfang: Wiederherstellung in denselben Kurs, einen anderen Kurs, eine andere Website (`restore_policy_test`, 6 Tests); Dateien, Gastkurs-Index, Einschreibungen; Privacy-Export und -Löschung für Ersteller/in, Teilnehmer/in, Bearbeiter/in (local), Delegierende/n (mod), Lehrenden-Zuweisungen (enrol); Moodles Konformitätsprüfung `core_privacy\privacy\provider_test` (Filter `adele`).
- **Befunde (belegt) und Behebung** (Entscheidung des Nutzers 2026-10-17: Anonymisieren, dazu Issue-Entwurf `docs/issues/local_adele-issue-privacy-delete-foreign-data.md`):
  - local_adele: Löschung einer Erstellerin löschte ihre Lernpfade und die von ihr angelegten Teilnahmen **anderer**; Einschreibungen blieben verwaist; „alle Nutzer" löschte alle Lernpfade. Probe mit dem alten Provider: 5 der 8 neuen Tests rot.
  - mod_adele: `null_provider`, obwohl `teachercourseaccessgrantor` eine Nutzer-ID hält; zudem fehlte der Text `privacy:null_reason` (Moodles Konformitätsprüfung: „Invalid get_string() identifier", Test riskant).
  - Jetzt: Pflichtenheft 5.2b (Tabelle der Wirkungen, Nebenwirkungen, Release-Gate).
- Code: `local/classes/privacy/provider.php`, `mod/classes/privacy/provider.php` (+ Texte en/de), PHPUnit-Gruppe `adele_release` an den Sicherungs-, Wiederherstellungs- und Datenschutztests, `.github/workflows/release-gate.yml` (×3)
- PHPUnit: `--group adele_release` 36 Tests und Konformitätsprüfung 12 Tests grün auf PostgreSQL 16 und MariaDB 10.11; Gesamtsuiten siehe Prüfstand in `README.md`
- Verbleibende Abweichungen: AK 1 (CI-Lauf) und AK 3 (Branch-Schutz) nach Übernahme
- Entscheidung: [x] offen lassen  [ ] schließen – bis zum ersten grünen Gate-Lauf und gesetztem Branch-Schutz
- **Re-Audit 0735 (2026-10-18), Lieferung `2026100736`:**
  - Re-Audit TP-06: Negativfälle (fremde Daten, verwaiste Verweise, unberechtigte Rollenzuweisungen) sind Release-Blocker – Gruppe `adele_release` erweitert um `path_choice_test` (gefälschter Pfad), `adele_source_shared_node_test` und `participant_source_test` (ADELE-Einschreibung als fremde Quelle), `cross_plugin_consistency_test`, enrol `roles_and_retention_test` (fremde Rollenzuweisung bleibt); dazu die neuen Privacy- und Restore-Tests. `release-gate.yml` führt `--group adele_release --fail-on-risky --fail-on-warning` aus.
  - Lokal grün auf PostgreSQL 16 und MariaDB 10.11 (Zahlen: Prüfstand `2026100736`). **Offen:** MySQL (hier nicht vorhanden) und der erste Gate-Lauf über die Matrix – CI ist nicht Teil des Re-Audit-Auftrags.
  - Läufe: Prüfstand `2026100736` in `README.md` (PostgreSQL 16 und MariaDB 10.11)
- **Entscheidung (Re-Audit): abnahmefähig**
