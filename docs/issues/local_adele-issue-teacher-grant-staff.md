# Befund zu local_adele #13 AK1.5: Person nur mit Lehrenden-Freigabe (kind 3) erscheint als Lernende

> **Stand:** 2026-11-27, gefunden beim Prüffall aus dem Re-Audit vom
> 2026-11-27, Abschnitt 3 („Prüfen, ob ein aktiv eingeschriebener Nutzer
> mit ausschließlich ADELE-Lehrendenzugang (kind 3) in irgendeiner
> Lernendensicht auftaucht“). **In `0.8.0` (2026112700) behoben.** Der
> Befund gehört zu #13. Es ist kein eigenes Issue nötig; der Text dient
> als Kommentarbaustein.

## Befund (belegt)

Ausgangslage: Eine Person ist im Gastgeberkurs **nur** über eine
Lehrenden-Freigabe der Lehrenden-Ansicht eingeschrieben
(`enrol_adele\local\teacher_grants::add`, Modus `enrol_teacher`, Rolle
`teacher`).

- Ohne Snapshot des Pfads steht sie in der Liste „Nicht-Lernende“ mit dem
  Grund `nopath` („ohne Lernpfad“) statt `nonlearner` (Lehrende). In der
  Gruppenübersicht zählt sie als Mitglied der Gruppe.
- Mit einem Snapshot des Pfads aus früherer Zeit, etwa als ehemalige
  Lernende, erscheint sie **in der Teilnehmerliste als Lernende**.

**Ursache:** `enrol_state::get_nonparticipant_userids()` bestimmt, wer
zum Personal des Kurses gehört. Gezählt werden nur Einschreibungen aus
eigenem Recht, also keine von ADELE, und Rollen, die nicht von
`enrol_adele` vergeben wurden. Die Lehrenden-Freigabe ist beides: eine
ADELE-Einschreibung und eine `enrol_adele`-Rolle. Die Person war für die
Lernendensichten deshalb weder Personal noch etwas anderes als eine
Teilnehmerin.

## Behebung (`0.8.0`)

- `get_nonparticipant_userids()` zählt eine aktive Lehrenden-Freigabe
  (kind 3) im Kurs als eigenes Recht:
  - Instanz aktiviert, Einschreibung aktiv und im Zeitfenster;
  - die Rollen dieser Instanz zählen mit.
- Eine Freigabe mit der Lehrendenrolle macht die Person damit zum
  Personal. Eine Freigabe mit einer bewerteten Rolle (Modus `enrol_student`)
  macht sie zur Teilnehmerin.
- `is_participant()`, also Abonnement und Trägerschaft, bleibt
  unverändert: Rollen und Einschreibungen von ADELE tragen dort weiterhin
  kein Abonnement.
- Neue Konstante `enrol_state::TEACHER_GRANT_KIND`; der Roster nutzt sie
  statt der Zahl 3.

## Nachweis

- `local/tests/roster_lifecycle_test.php`, Test
  `test_an_active_teacher_grant_is_no_learner`. Geprüft werden
  Teilnehmerliste, Live-Marker, Live-Zähler, Gruppenübersicht (gemessen
  und Mitglieder) und Nicht-Lernende, jeweils ohne und mit Snapshot.
- Gegenprobe auf `b8bd9b2` (`enrol_state.php` und `learning_paths.php`
  von dort): rot (`nopath` statt `nonlearner`). Mit dem Snapshot: als
  Lernende gelistet.
- Auf `0.8.0` grün auf PostgreSQL 16 und MariaDB 10.11. Die ganze
  PHPUnit-Suite bleibt grün.
