# Issue-Entwurf (local_adele): Ein gelöschter Knotenkurs bricht die Auswertung des Lernpfads ab

> **Stand:** eingereicht als local_adele #601 (2026-10-16), behoben mit `2026100730` (Protokoll Teil 65).

**Gefunden:** PHPUnit `mod_adele/tests/node_course_access_test.php`
(Issue „Kurszugriff in der Lehrendenansicht“, TP1), Session 006, Teil 64.

## Befund

Ein Lernpfad, dessen Knoten auf einen inzwischen **gelöschten Kurs** zeigt
(`data.course_node_id` enthält eine Kurs-ID ohne Datensatz in `course`),
lässt sich für niemanden mehr auswerten:

- `classes/course_completion/conditions/course_completed.php`,
  `get_completion_status()` (Zeile 161), ruft für jede Kurs-ID des Knotens
  `learning_path_update::get_course($courseid)` auf.
- `classes/learning_path_update.php`, `get_course()` (Zeile 59), ruft Moodles
  `get_course()` ohne Prüfung auf. Für eine fehlende ID wirft das eine
  `dml_missing_record_exception`.
- Dasselbe Muster steht in `classes/learning_paths.php` (Zeile 957).

Im Test reichte es, eine Lehrperson mit Bearbeitungsrechten in den Gastkurs
einer mod_adele-Einbettung einzuschreiben (Teilnehmerquelle „dieser Kurs“):
Das Abo wird angelegt, der Observer `user_path_updated` wertet den Pfad aus,
und die Ausnahme bricht die Einschreibung mit ab.

Folge: Wird ein Kurs gelöscht, der in einem Lernpfad steckt, schlagen danach
die Einschreibungen in den Gastkurs und jede Neuberechnung des Pfads fehl, die
diesen Knoten berühren.

## Erwartung

Ein gelöschter Knotenkurs zählt wie ein Kurs ohne Abschluss (oder wird
übersprungen); die Auswertung der übrigen Knoten und Kurse läuft weiter. Der
Pfadeditor weist auf den fehlenden Kurs hin.

## Reproduktion

1. Lernpfad mit einem Knoten auf Kurs X; in Kurs Y als mod_adele einbetten
   (Teilnehmerquelle „dieser Kurs“).
2. Kurs X löschen.
3. Eine Person in Kurs Y einschreiben → Ausnahme `dml_missing_record_exception`
   (`get_course`).

Im Test `node_course_access_test` ist der Fall mit `plant(true)` und der
Teilnehmerquelle „dieser Kurs“ nachstellbar (dort zurzeit umgangen:
`participantslist` = Startknoten bzw. `plant(false)`).

## Akzeptanz

- `learning_path_update::get_course()` und die Aufrufer kommen mit fehlenden
  Kursen zurecht (z. B. `get_record(..., IGNORE_MISSING)` und überspringen).
- PHPUnit: Pfad mit gelöschtem Knotenkurs; Einschreibung in den Gastkurs und
  Auswertung laufen ohne Ausnahme, die übrigen Knoten werden richtig bewertet.
- Danach kann `node_course_access_test` die Umgehung entfernen.
