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
