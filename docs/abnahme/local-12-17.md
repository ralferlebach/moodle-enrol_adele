### Abnahmeprotokolle – ralferlebach/moodle-local_adele #12 – #17 (Gruppen, Nicht-Lernende, Live-Aktivität, Knotenzähler; Cross-Repo mod_adele)
- Ziel-Repositories / Commit-SHA: local_adele, mod_adele – Lieferung `2026111900`; Commit-SHA: **offen**
- Gemeinsame Grundlage (#12 AK0-L7, #14 AK2-L8, #15 AK3.2): **eine** Kohorte für Teilnehmerliste, Ranglisten, Mittelwerte, Nicht-Lernende, Marker und Zähler – `local/classes/cohort.php` (Roster des Gastkurses ∩ aktiver Pfad ∩ Teilnahme per Rolle ∩ Gruppe/Gruppierung), Vertrag aus `mod/classes/local/context_contract.php`.

#### #16 (Cross-Repo mod_adele) – Gruppenmodus aktivieren und serverseitig autorisieren
- AK0-M1 `adele_supports()`: `FEATURE_GROUPS`, `FEATURE_GROUPINGS` (`mod/lib.php`); Gruppenmodus/Gruppierung kommen aus den Moodle-Standardelementen des Formulars.
- AK0-M2/M3 `context_contract::for_cm($cmid, $userid, $group)`: effektiver Gruppenmodus (`groups_get_activity_groupmode`, Kurszwang inklusive), Gruppierung, wählbare Gruppen, gezeigte Gruppe, Sicht (Lehrende/nur lesend/Lernende), Live-Einstellungen; eine nicht sichtbare Gruppe → `invalid_parameter_exception`.
- AK0-M4 ohne Gruppen: Lernende bekommen nur die eigene Zeile – unabhängig von `userlist` und über jeden Kontext (`get_lp_user_path_relations.php`).
- AK0-M5 Gruppierung, Mehrfachmitgliedschaft, `accessallgroups`, Personen ohne Gruppe (`groupid = -1`, leere Kohorte).
- AK0-M6 keine neuen Gruppenfelder in `adele`; Gruppenmodus liegt in `course_modules` (Moodle-Backup); Duplikat/Import geprüft.
- AK0-M7 Moodle-Gruppen-API, Texte en/de.
- DoD0-M1/M2 PHPUnit `mod/tests/groups_and_live_settings_test.php` (8: Matrix, Kurszwang, fremde/abgelehnte Gruppen, Gruppierung, …), `local/tests/group_progress_test.php` (12: Rollen × Modi, manipulierte Gruppe/Kontext). DoD0-M3: Playwright `mod/tests/e2e/tests/groups-live.spec.ts` (G16, zwei Einbettungen desselben Pfads mit getrennten/sichtbaren Gruppen); Gruppierung nur PHPUnit, **Behat nicht gelaufen** (siehe Prüfstand). DoD0-M4/M5: CI **offen**.
- **Entscheidung: testseitig offen** – Gruppierung nur in PHPUnit (nicht im Browser), Behat hier nicht ausführbar, CI-Matrix (MySQL).

#### #12 – Gruppenbezogene Bestenliste und Gruppenmittelwerte
- AK0-L1 jede Anfrage gegen den konkreten `cmid` (Modulkontext → Aktivität) und die effektive Gruppierung; fremde `groupid`/`contextid` → Ablehnung.
- AK0-L2 ohne Gruppen: Lernende nur eigene Daten – **Verhaltensänderung:** „Ergebnisse aller Teilnehmenden“ öffnet Lernenden die Kursrangliste nicht mehr; Lehrende unverändert.
- AK0-L3 getrennte Gruppen: Rangliste je eigener Gruppe, absteigend, feste Reihenfolge bei Gleichstand (Fortschritt, abgeschlossene Knoten, Name, ID; gleicher Rang bei gleichem Wert), ohne IDs, ohne Links (`group_progress::overview()`, `GroupRanking.vue`).
- AK0-L4 sichtbare Gruppen: eigener Mittelwert + Liste; andere Gruppen nur als Mittelwert (keine Namen, IDs, Einzelwerte – PHPUnit prüft die Nutzlast, E2E die Antworten des Servers und das DOM).
- AK0-L5 Mittelwert über der Liste; fremde Werte beschriftet; Gruppe ohne gemessene Lernende „Keine Daten“ (nie 0 % oder NaN); Lernende ohne Fortschritt werden genannt („3 von 4“), nicht still aus dem Nenner genommen; Mehrfachmitgliedschaft je Gruppe eine Rangliste.
- AK0-L6 Lehrende: Gruppenwahl nur aus dem Vertrag (`GroupSelector.vue`), Teilnehmerliste gefiltert, Mittelwerte bei sichtbaren Gruppen.
- AK0-L7 siehe Grundlage. AK0-L8 Texte en/de, Fortschrittsbalken mit `aria-valuetext`, Überschriften als Namen der Abschnitte; axe ohne ernste Verstöße (E2E).
- „Nur eigene Ergebnisse“ schließt auch die Gruppenranglisten (Feld `closed`).
- DoD0-L1/L2 PHPUnit `group_progress_test` (13, davon Abfragebudget: 6 → 36 Lernende höchstens +2 Abfragen); DoD0-L3 Jest `groups_live.spec.js` (Sortierung wie geliefert, Mittelwert/Nenner, leere Zustände, nicht klickbar); DoD0-L4 E2E G1–G3; DoD0-L5 CI **offen**.
- **Entscheidung: abnahmefähig** – CI-Matrix.

#### #13 – Nicht-Lernende auf Wunsch einblenden
- AK1.1 serverseitiger Standardfilter unverändert (`cohort::learners()` ohne Personal). AK1.2 Schalter nur für Lehrende, lädt erst beim Öffnen (`NonLearners.vue`). AK1.3 `local_adele_get_non_learners` – eingeschriebene Personen der Gruppe, die nicht per Rolle teilnehmen („nonlearner“) oder keinen aktiven Pfad haben („nopath“); nichts wird angelegt, kein Fortschritt. AK1.4 eigener Abschnitt außerhalb von Liste, Zählern, Mittelwerten; geschlossen wird die Liste verworfen. AK1.5/AK1.6 Rollen über `enrol_state`, Gruppe über den Vertrag. AK1.7 `aria-expanded`/`aria-controls`, Status-Region, keine E-Mail.
- Tests: PHPUnit `test_non_learners`; Jest (2); E2E G3 (Tutor sieht sich als „nonlearner“, Lernende nicht im Abschnitt, Liste unverändert, axe).
- Hinweis: Eine eingeschriebene Lernperson ohne Pfad ist im Betrieb nur vorübergehend (ADELE abonniert sie selbst); der Fall „nopath“ ist deshalb nur in PHPUnit/Jest belegt.
- **Entscheidung: abnahmefähig** – CI-Matrix.

#### #17 (Cross-Repo mod_adele) – Einstellungen der Live-Marker
- AK2-M1 Abschnitt „Live-Aktivität“: Schalter (Standard aus), Fenster in Minuten (Standard 5, 1–60, serverseitig geprüft: `live_settings::is_valid_window()`, `mod_form.php::validation()`), Hilfe en/de. AK2-M2 Upgrade `2026111900` fügt `livemarkers`/`livewindow` mit Defaults hinzu. AK2-M3 Backup/Restore/Duplikat erhalten die Werte, ein älteres Backup erhält die Defaults, Werte außerhalb werden eingegrenzt. AK2-M4 der Vertrag übergibt die Einstellungen; der Browser schaltet nichts frei. AK2-M5 ausgeschaltet: keine personenbezogenen Live-Daten (nur Zähler „abgeschlossen“). Abfrageintervall: Website-Einstellung `local_adele/livepollinterval` (30 s, 10–300).
- Tests: PHPUnit `groups_and_live_settings_test` (Defaults, Grenzen, Formular, Backup/Restore/Duplikat, Schema); E2E M1 (Standard aus/5, 61 abgelehnt, 7 gespeichert und wieder angezeigt).
- **Entscheidung: abnahmefähig** – CI-Matrix (Behat hier nicht ausführbar; Playwright M1 deckt Speichern, Wiederöffnen, Schalten, Default).

#### #14 – Live-Aktivitätsmarker
- AK2-L1 Ereignisse: `\core\event\course_viewed` und jedes `\core\event\course_module_viewed` (Observer `live_activity::observe`); UTC-Zeitstempel, Uhr `core\clock`; Polling erzeugt keine Aktivität (PHPUnit).
- AK2-L2 eigene Tabelle `local_adele_activity` (eine Zeile je Person und Kurs, Index), kein Scan des Logstores; nur Personen auf einem Lernpfad; Aufbewahrung `liveretention` (30 Tage) mit Task `purge_live_activity`; Privacy: Metadaten, Export, Löschen (auch beim Löschen der Person).
- AK2-L3 ein gebündelter Schnappschuss je Aktivität (`local_adele_get_live_snapshot`): Lehrendensicht, Gruppe, Lernendenrolle, Schalter – serverseitig.
- AK2-L4 aktiv an allen Knoten mit dem Kurs; inaktiv nur am Knoten des zuletzt gesehenen Kurses (Gleichstand: kleinere Kurs-ID; Kurs in mehreren Knoten: erster Knoten); nie gesehen: kein Marker; gestapelte Knoten zählen eine Person einmal.
- AK2-L5 Zustand auch als Text (`aria-label`, `sr-only`, Symbol ●/○, durchgezogener/gestrichelter Rahmen), Bilder `alt=""` neben dem Namen, Überlauf „+N“ (öffnet die Liste).
- AK2-L6 Polling über `useUserPathRefresh` (keine parallelen Anfragen, Pause bei verborgenem Tab, Backoff, Abbau beim Verlassen); Daten im Store, kein Neuaufbau des Graphen.
- AK2-L7 ausgeschaltet: keine Marker, kein aktiver Zähler; Fenstergrenzen 0/5/6 Minuten (PHPUnit mit eingefrorener Uhr).
- DoD2-L1/L2 PHPUnit `live_activity_test` (11); DoD2-L3 Jest `groups_live.spec.js` (Komponente) und `TeacherView.live.spec.js` (3, Fake-Timer: ein Schnappschuss beim Öffnen, dann je Intervall, nie zwei gleichzeitig, Pause bei verborgener Seite, Ende beim Verlassen, Graph nur einmal gemountet, Ablehnung ohne Polling); DoD2-L4 E2E L1 (Marker, Zähler, Polling ohne Neuladen, Tastatur, axe); DoD2-L5 Abfragebudget PHPUnit (5 → 45 Lernende: höchstens +2 Abfragen), Lasttest großer Kohorten **nicht** automatisiert; CI **offen**.
- **Entscheidung: testseitig offen** – Lasttest großer Kohorten, CI-Matrix.

#### #15 – Zwei Knotenzähler, Namenslisten, knotenzentrierte Navigation
- AK3.1 „aktiv“ (Fenster) und „abgeschlossen“ (Knotenstatus wie die Teilnehmerliste), verschiedene Personen, Überschneidung erlaubt. AK3.2 dieselbe Kohorte. AK3.3 `local_adele_get_node_people` – nur auf Anfrage, 20 Namen je Seite, Knoten/Art/Seite geprüft, unbekannter Knoten oder „aktiv“ bei ausgeschaltetem Schalter abgelehnt. AK3.4 Zähler → Liste (Dialog) → Name → Pfad der Person, **zentriert auf diesen Knoten** (`UserPath.vue`, `?node=`). AK3.5 dasselbe vom Marker; Gruppenwahl bleibt im Store. AK3.6 ein Schnappschuss für Zähler und Marker. AK3.7 aktiver Zähler nur bei eingeschaltetem Schalter; Fehler, Null und „niemand“ unterscheidbar. AK3.8 Namen der Knöpfe, `aria-expanded`/`aria-controls`, Escape, Fokus zurück.
- Tests: PHPUnit (Seiten, unbekannter Knoten, Schalter, Budget), Jest (Liste, Seiten, Escape/Fokus, Navigation, Fehler), E2E L1 (Zähler → Namen → zentrierter Knoten; Escape) und L2.
- Offen: Rückkehr mit unverändertem Viewport/Scroll der Übersicht nicht automatisiert geprüft.
- **Entscheidung: testseitig offen** – Rückkehr-Viewport, CI-Matrix.

#### Nachtrag `2026112102` – Re-Audits 2026-11-21 (Vormittag/Abend)
- **#12/#16 Kontext-Umgehung (P0) behoben:** `cohort::teacher_scope()` für Rangliste (`get_lp_user_path_relations`), einzelnen Pfad (`get_lp_user_path_relation`) und Speichern (`save_lp_user_path_relation`): Modulkontext → Gruppenwahl der Aktivität; Kurs-/anderer Modul-/Fremdpfad-Kontext → höchstens die Vereinigung dessen, was die Aktivitäten des Pfads im Kurs geben (Gruppierungen eingeschlossen). PHPUnit `teacher_scope_test` (9; zwei Host-Aktivitäten, verschiedene Gruppierungen; Gegenprobe: 6 rot).
- **#12/#16 „Teilnehmer sehen“ (`userlist`) – Entscheidung 2026-11-22 (Vorgabe der Auftraggeberseite, ersetzt die vorläufige Ausnahme vom Vormittag):** Die Einstellung wird **nur ohne Gruppen angezeigt und wirkt nur dort.** Formular: `hideIf` auf den Gruppenmodus, bei vom Kurs erzwungenem Gruppenmodus entfällt das Feld (gespeicherter Wert bleibt). Ohne Gruppen: `1` – Lernende sehen über den Modulkontext die Rangliste der Lernenden der Aktivität, fremde Zeilen ohne ID/Benutzernamen und ohne Link; `2` – eigene Zeile. Über Kurs- oder anderen Kontext nur die eigene Zeile. Mit Gruppen: Matrix (#12 AK0-L3/L4) unabhängig vom gespeicherten Wert. **Zurückgenommen:** die Lesart von AK0-L2/AK0-M4 „ohne Gruppen immer nur die eigene Zeile, unabhängig von `userlist`“ (oben) für `userlist = 1`. Nachweise: PHPUnit `test_no_groups_all_results_lists_the_activitys_learners`, `test_no_groups_only_own_results`, `test_participant_results_apply_without_groups_only` (`group_progress_test`), `test_all_results_setting_lists_the_learners_without_ids`, `test_all_results_setting_with_groups_returns_the_own_row` (`issue569_userlist_privacy_test`); E2E P2/P3 (`learner-data.spec.ts`: Lernende sehen bei „alle“ beide, Server ohne Betas ID/Benutzernamen), P1/P2-Rücknahme (`withdrawn-settings.spec.ts`: Einbettung ohne Gruppen; zusätzlich Umschalten auf getrennte Gruppen – Feld verborgen, gespeichertes „nur eigene“ ohne Wirkung, zurück ohne Gruppen – Feld wieder da, „nur eigene“ gilt).
- **#12 Mittelwert:** über alle Lernenden der Gruppe, ohne Fortschritt mit 0 % gezählt und genannt, Gruppe ohne Lernende „Keine Daten“ (`test_missing_progress_stays_in_the_denominator`, Gegenprobe rot; Jest; E2E G2).
- **#13 `groupid = 0`:** dieselbe Auswahl wie die Teilnehmerliste – Gruppierung der Aktivität (`test_non_learners_all_participants_respect_the_grouping`, Gegenprobe rot).
- **#14 Gleichstand:** Mehrdeutigkeitsdarstellung statt Auswahl (Marker an jedem Kandidatenknoten, „nicht eindeutig: an N Knoten“, `ambiguous`); PHPUnit `test_stacked_ties_and_reused_course` (Zeitgleichstand, wiederverwendeter Kurs, eindeutiger Fall, Determinismus), Jest.
- **#15/#17 API-Matrix:** `live_groups_api_matrix_test` (33: 4 Endpunkte × manipulierte Rolle/Kontext/Gruppe/Knoten/Seite/Art/Schalter, Kontrollen ohne fremde Daten). **#15 AK3.5** Rückweg mit Gruppe, Ausschnitt, Scroll, Fokus: E2E L3 (Marker und Tastatur; Gegenprobe rot). **#17 DoD2-M1** Altinstallation: `test_upgrade_of_an_old_installation` (PostgreSQL, MariaDB).
- **DB-Matrix:** PostgreSQL in der Plugin-CI fehlte (wiederverwendete Matrix: 4.5/pgsql nur PHP 8.1, entfällt mit `min_php 8.2`) → `moodle-plugin-ci-pgsql.yml` je Plugin; Issue-Entwurf an catalyst-moodle-workflows. MySQL: Lücke.
- **Entscheidungen:**
  - #12 **abnahmefähig** – vorbehaltlich CI am Commit (inkl. neuem PostgreSQL-Workflow).
  - #13 **abnahmefähig** – dito.
  - #14 **testseitig offen** – Lasttest großer Kohorten für den Schnappschuss (k6/JMeter prüfen Seiten, nicht `get_live_snapshot`); sonst abnahmefähig.
  - #15 **abnahmefähig** – vorbehaltlich CI.
  - #16 **testseitig offen** – verschiedene Gruppierungen nur in PHPUnit, nicht im Browser (DoD0-M3); Behat hier nicht ausführbar.
  - #17 **abnahmefähig** – vorbehaltlich CI.

## Nachtrag `2026112300` (Re-Audit 2026-11-22)

Die Zuordnung jedes offenen Punkts zu Code und Tests steht in `nachweise-20261123.md`. Neu in dieser Lieferung:

- **#16 DoD0-M3 / #12 DoD0-L4:** E2E G4 mit zwei Aktivitäten eines Kurses, die verschiedene Gruppierungen haben. Gegenprobe: Ohne Gruppierungsfilter wird G4 rot.
- **#12 DoD0-L2:** PHPUnit zu anderen Kontexten bei „Ergebnisse aller“. Gegenprobe: Ohne Pfadprüfung wird der Test rot.
- **#12 DoD0-L3:** Jest prüft, dass Zeilen nicht navigierbar sind. E2E P2 prüft, dass es keinen Link gibt und die Ersatz-ID keinen Pfad öffnet.
- **#13 AK1.5/DoD1.1:** PHPUnit zu Rollen und Einschreibungsstatus. **#13 AK1.2:** In G3 geht vor dem Umschalter keine Anfrage raus.
- **#14 DoD2-L5 / #15 DoD3.5:** PHPUnit mit 300 Lernenden (gleiche Abfragezahl, jeweils unter 1 s, beide Datenbanken). Dazu der k6-Plan mit Live-Aufrufen und einem Seed mit Aktivität, Gruppen und Live-Daten (Standard 500 Lernende).
- **#15 DoD3.1:** Zwei Gruppen und Doppelrolle werden einmal gezählt.
- **#16 AK0-M6:** Das Feld „Teilnehmer sehen“ folgt dem Gruppenmodus, auch einem erzwungenen. Der gespeicherte Wert bleibt erhalten und gilt wieder. Duplikat und Import übernehmen `userlist` und den Gruppenmodus. Gegenprobe: Ohne die Formularregel wird der Test rot.
- **#12 AK0-L2 / #16 AK0-M4:** Die Bestätigung der Entscheidung vom 2026-11-22 ist angefragt. Der Textentwurf für Variante A liegt in `issues/local_adele-12-16-ak-userlist-entwurf.md`.
- **Entscheidungen:**
  - #12 und #16: **codeseitig offen**, bis die Bestätigung zu `userlist` vorliegt. Sonst testseitig vollständig, lokal grün.
  - #13: **abnahmefähig**.
  - #14: **abnahmefähig** (vorher testseitig offen). Der k6-Lauf steht in der CI aus.
  - #15: **abnahmefähig**.
  - Für alle gilt: DoD = CI am Commit.

## Nachtrag `2026112600` (Re-Audit 2026-11-26)

Die Zuordnung steht in `nachweise-20261126.md`.

- **#13 AK1.5 (neuer Befund, belegt, behoben):** Gesperrte, abgelaufene,
  noch nicht begonnene und deaktivierte ADELE-Einschreibungen zählten als
  Lernende. Jetzt gilt die Moodle-Semantik. Gesperrte zählen nur im
  Host-Modus „verborgen“ des eigenen Pfads. Neuer Test mit 3 Fällen über
  Teilnehmerliste, Live-Daten, Gruppenübersicht und Nicht-Lernende;
  Gegenprobe 3 von 3 rot.
- **#13 DoD1.2–1.4:** E2E G5 (Fehlerfall, Gruppenwechsel bei offener Liste,
  Ablehnung durch den Server).
- **#14 DoD2-L5 / #15 DoD3.5:** Der k6-Lauf ohne Live-Endpunkte ist jetzt
  rot. Jeder der drei Endpunkte muss beantwortet werden, das Ergebnis steht
  in der Zusammenfassung des Laufs.
- **#12 AK0-L2 / #16 AK0-M4:** **Entschieden: Variante A** (Auftraggeberseite,
  2026-11-26, 21:56). Code unverändert, AK-Texte einfügefertig in
  `issues/local_adele-12-16-ak-userlist-entwurf.md`.
- **Entscheidungen:**
  - #12 und #16: **abnahmefähig**, sobald die AK-Texte in GitHub
    übernommen sind (Freigabe liegt vor).
  - #13: **abnahmefähig**, nachdem der Roster korrigiert ist.
  - #14 und #15: **abnahmefähig**; offen ist der k6-Lauf mit Live-Endpunkten
    in der CI.
  - Für alle gilt: DoD = CI auf `development` am Commit.

## Nachtrag `2026112800` (Nacharbeitsauftrag 2026-11-28)

- **#14 DoD2-L4:** neuer E2E-Test L4 in mod `groups-live.spec.ts` (Fixture
  ADELE-E2E-L17), aufgebaut wie folgt:
  - testexklusive Einschreibung Dirks im Kurs von Knoten 2, echte Kursaufrufe;
  - aktive Marker an zwei Knoten gleichzeitig mit Avatar, Name und Zustand des
    richtigen Lernenden;
  - nie Beobachtete ohne Marker;
  - kontrollierter Fensterablauf über die Testuhr `seenAgo()` (die Daten werden
    verschoben, nicht die Regeln), mit eindeutigen Zeitpunkten;
  - Polling ohne Neuladen: kein aktiver Marker, der inaktive nur am zuletzt
    beobachteten Knoten.

  Details: `nachweise-20261128.md`.
  **Entscheidung: abnahmefähig**, vorbehaltlich des mod-E2E-Laufs am Zielcommit.
