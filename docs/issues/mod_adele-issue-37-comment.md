# Kommentarentwurf zu mod_adele #37

Zum Einfügen als Kommentar unter #37. Alles unterhalb der Trennlinie ist der
Kommentartext.

---

**Umgesetzt — mit einer Korrektur am Befund.**

**Regel:** Für Teilnehmerquellen zählen nur Einschreibungen, die ADELE nicht
selbst angelegt hat (`enrol <> 'adele'`).

**Korrektur der Beschreibung:** Quellen 2 und 3 (`host_policy`) und die
A-4-Prüfung (`enrol_adele\observer::is_user_carried()`) zählten **schon
vorher** nur fremde Einschreibungen. Punkt 4 der Beobachtung stimmt daher
nicht: Eine ADELE-Einschreibung in T01 macht niemanden zum
Startknoten-Teilnehmer eines weiteren Pfads. Die Abos auf „Verzweigt,
gestapelt B1" und „Linear A2" waren korrekt — die manuelle Einschreibung in
T03 trägt beide Quellen.

**Die tatsächliche Lücke war Quelle 1 („dieser Kurs"), an zwei Stellen:**

1. `mod_adele`: `user_enrolment_created()` und `enroll_all_participants()`
   (Speichern der Aktivität) werteten jede Einschreibung aus, also auch die
   Host-Einschreibung eines anderen Pfads. Jetzt über
   `host_policy::has_foreign_enrolment()`, dieselbe Regel wie für die Quellen
   2 und 3.
2. `local_adele`: `enrollment::enrolled()` abonniert bei jeder Einschreibung
   in einen Kurs mit ADELE-Aktivität für alle dort eingebetteten Pfade. Jetzt
   ohne ADELE-eigene Einschreibungen.

Beide Stellen sind nötig (Gegenprobe: nur eine Korrektur → Test rot).

**Nachweis:** `mod_adele/tests/participant_source_test.php` — zwei Pfade, ein
gemeinsamer Hostkurs; Ereignisweg, Speicherweg und Gegenkontrolle mit echter
Mitgliedschaft. Alter Code rot, neuer grün. In der Testinstanz hat die Person
aus der Beobachtung jetzt zwei statt drei Abos.

**Eine weitere Beobachtung, nicht geändert:** `local_adele\enrollment::enrolled()`
fragt die gewählte Teilnehmerquelle nicht ab. Wer manuell in einen Hostkurs
eingeschrieben wird, wird für **jeden** dort eingebetteten Pfad abonniert —
auch für Einbettungen, die nur Quelle 2 oder 3 gewählt haben. Ob das gewollt
ist (Mitglieder des Hostkurses sind immer dabei) oder Quelle 1 dort Bedingung
sein soll, wäre eine eigene Entscheidung.
