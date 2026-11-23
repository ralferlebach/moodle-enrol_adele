# Textentwurf: #12 AK0-L2 und #16 AK0-M4 nach der Entscheidung vom 2026-11-22

> **Stand:** Entwurf (2026-11-23) zum Einfügen in GitHub – **gilt nur, wenn
> die Auftraggeberseite Variante A bestätigt** (Re-Audit 2026-11-22 §3: „Sollmatrix
> `NOGROUPS` × `userlist=1/2` ausdrücklich bestätigen“). Bei Variante B wird
> der Code zurückgebaut und die bisherigen Texte bleiben.

## Entscheidung (2026-11-22, 09:52)

„Die Einstellung ‚Nur die eigenen Ergebnisse anzeigen‘ soll nur im
Nicht-Gruppen-Modus angezeigt und aktiv sein.“

## #12 AK0-L2 – neuer Wortlaut

- [ ] **AK0-L2:** Im Modus „Keine Gruppen“ entscheidet die Einstellung
  „Teilnehmer sehen“ (`userlist`) der Aktivität. Bei „nur eigene Ergebnisse“
  (`userlist=2`) erhalten Lernende ausschließlich ihre eigene Zeile. Bei
  „Ergebnisse aller“ (`userlist=1`) erhalten sie Rangliste und Fortschritt
  der Lernenden **dieser Aktivität**: fremde Zeilen ohne Nutzer-ID (Ersatz-ID
  < 0), ohne Benutzernamen, nicht navigierbar (kein Link, kein Knopf), und
  über die Ersatz-ID ist kein fremder Pfad abrufbar. Jeder andere Kontext –
  Kurskontext, ein anderes Modul, eine ADELE-Aktivität eines anderen Pfads,
  der Pfad in einem fremden Kurs – liefert nur die eigene Zeile oder wird
  abgelehnt. Mit Gruppenmodus hat `userlist` keine Wirkung, es gilt die
  Gruppenmatrix (AK0-L3/L4). Die Lehrendenliste bleibt berechtigt.

## #16 AK0-M4 – neuer Wortlaut

- [ ] **AK0-M4:** „Keine Gruppen“ setzt die Lernenden-Isolation nach
  AK0-L2 (#12) durch. Das Feld „Teilnehmer sehen“ erscheint nur bei „Keine
  Gruppen“: Es ist bei gewähltem Gruppenmodus versteckt und fehlt, wenn der
  Kurs einen Gruppenmodus erzwingt. Der gespeicherte Wert bleibt bei jedem
  Wechsel erhalten und gilt wieder, sobald die Aktivität ohne Gruppen ist.
  Gruppenmodi sind über keinen anderen Host-Kontext umgehbar.

## Nachweise (Stand `2026112300`)

- PHPUnit `local_adele/tests/issue569_userlist_privacy_test.php`
  (`test_all_results_setting_lists_the_learners_without_ids`,
  `…_with_groups_returns_the_own_row`, `…_other_contexts_give_no_more`),
  `group_progress_test.php` (`test_no_groups_all_results_lists_the_activitys_learners`,
  `test_no_groups_only_own_results`, `test_participant_results_apply_without_groups_only`)
- PHPUnit `mod_adele/tests/groups_and_live_settings_test.php::test_participant_results_field_follows_the_group_mode`
- Jest `UserList.spec.js` („"all results" without groups …“)
- Playwright `mod_adele` `learner-data.spec.ts` P2 (keine IDs, keine Links,
  Ersatz-ID öffnet nichts), P3, `withdrawn-settings.spec.ts` (Feld je
  Gruppenmodus, Wert bleibt)
