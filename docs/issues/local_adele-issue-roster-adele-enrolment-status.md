# Issue-Entwurf (local_adele): Gesperrte, abgelaufene und deaktivierte ADELE-Einschreibungen zählten als Lernende

> **Stand:** gefunden im Re-Audit vom 2026-11-26 (P1, #13 AK1.5), belegt
> und behoben in `2026112600`. Ein eigenes Issue ist nicht nötig, wenn #13
> den Befund aufnimmt.

## Befund (belegt)

`learning_paths::roster_members_sql()` nahm über einen zweiten UNION-Zweig
**jede** `enrol_adele`-Einschreibung des Kurses in die Teilnehmerliste auf.
Der Zweig prüfte weder `user_enrolments.status` noch `timestart`/`timeend`
noch `enrol.status`. Daraus folgt die Teilnehmerliste für Rangliste,
Gruppenübersicht, Live-Marker und -Zähler sowie, als Rest, die Liste der
„anderen eingeschriebenen Personen“.

Der neue Test `roster_adele_enrolment_status_test` lief auf dem alten Code
**3 von 3 rot**. Als Lernende einer Aktivität mit Host-Modus „sichtbar“
erschienen:
- eine gesperrte ADELE-Einschreibung (so hält enrol_adele eine Person, die
  den Pfad verlassen hat, bis zum Ende der Karenzzeit);
- eine abgelaufene (`timeend` vorbei);
- eine noch nicht begonnene (`timestart` in der Zukunft);
- eine aus einer deaktivierten ADELE-Instanz.

## Festgelegte Semantik (ab `2026112600`)

Eine ADELE-Einschreibung zählt so, wie Moodle eine Einschreibung zählt:

- **aktiv:** Die Instanz ist aktiviert, die Einschreibung aktiv und im
  Zeitfenster. Das prüft der erste Zweig über `get_enrolled_sql(..., true)`,
  wie für jede Methode.
- **gesperrt:** zählt **nur** als „eingeschrieben ohne Zugriff“ des
  Host-Modus „verborgen“ (#588). Dafür muss die Instanz aktiviert und das
  Zeitfenster offen sein, und der Pfad der Instanz muss in **diesem** Kurs
  eine Einbettung mit `hostenrolmentmode = 'hidden'` haben.
- **Lehrenden-Zugang** (`customint2 = 3`): nie Einschreibung von Lernenden.
- Hat jemand zusätzlich eine andere aktive Einschreibung, trägt diese wie
  bisher.

**Bekannte Grenze:** In einem Kurs mit einer „verborgenen“ Einbettung bleibt
eine Person, die den Pfad verlassen hat, bis zum Ende der Karenzzeit
sichtbar. Dann entfernt enrol_adele die gesperrte Einschreibung. Am
Datensatz lassen sich die beiden Sperrungen nicht unterscheiden.

## Tests

- `local_adele/tests/roster_adele_enrolment_status_test.php` (3 Fälle).
  Geprüft werden dieselben Personen in der Teilnehmerliste der Lehrenden,
  in den Live-Markern und -Zählern, in der Gruppenübersicht, in der Liste
  der anderen Personen und in `is_roster_member()`.
- `security/roster_course_scope_test::test_a_learner_adele_keeps_suspended_stays_on_the_roster`
  bildet den Modus „verborgen“ jetzt ausdrücklich nach: Instanz des Pfads,
  Einbettung „hidden“.
