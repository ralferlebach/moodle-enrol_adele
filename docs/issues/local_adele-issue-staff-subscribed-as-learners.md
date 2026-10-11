# Issue-Entwurf: Lehrkräfte werden als Lernende des Pfads geführt

**Repository:** `Wunderbyte-GmbH/moodle-local_adele` (Querverweis: `moodle-mod_adele`, `moodle-enrol_adele`)
**Labels (Vorschlag):** `enhancement`, `roles`, `enrolment`
**Stand:** 2026-10-07, Session 006 — beobachtet mit der E2E-Kette P; Entscheidung des Auftraggebers: vorerst akzeptabel, langfristig ausschließen

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Einschreibung in einen einbindenden Kurs abonniert den Lernpfad unabhängig von der Rolle — Lehrkräfte stehen als Lernende in der Teilnehmerliste

## Beobachtung

Wer in einen Kurs eingeschrieben wird, der einen Lernpfad über `mod_adele`
einbindet, wird Lernende(r) dieses Pfads — auch als Lehrkraft mit oder ohne
Bearbeitungsrecht. Gemessen (Moodle 4.5.15, Kurs mit zwei Lernenden, einer
Lehrkraft, einer Lehrkraft ohne Bearbeitungsrecht):

- Die Teilnehmerliste zeigt die Lehrkräfte mit 0 %, 0/3 und einem Rang
  (hier Rang 3 hinter den beiden Lernenden).
- Für die Lehrkräfte entsteht ein Lernerpfad (`local_adele_path_user`), sie
  werden über `enrol_adele` in die Startknotenkurse eingeschrieben.
- Mit „Results of all other participants" sehen Lernende die Lehrkräfte als
  Mitlernende.

## Ursache

- `local_adele\enrollment::enrolled()` (Beobachter von
  `user_enrolment_created`) abonniert für jeden Pfad, der im Kurs eingebunden
  ist — ohne Blick auf die Rolle und ohne Blick auf die Teilnehmerquelle der
  Aktivität.
- `mod_adele_observer::user_enrolment_created()` und `enroll_all_participants()`
  (Quelle „dieser Kurs") arbeiten ebenfalls auf Einschreibungen, nicht auf
  Rollen.

## Gewünschtes Verhalten

Lernende eines Pfads ist, wer im einbindenden Kurs **als Teilnehmer** geführt
wird — nicht, wer dort unterrichtet.

## Warum das kein kleiner Eingriff ist

Die Rolle ist kein Merkmal der Einschreibung, sondern eine eigene, jederzeit
änderbare Zuweisung. Eine Lösung braucht deshalb:

1. **Ein Kriterium:** z. B. eine Capability „nimmt am Lernpfad teil"
   (Vorschlag `local/adele:participate`, Archetyp `student`), geprüft im
   Kurskontext — statt fester Rollennamen.
2. **Nachverfolgung von Rollenwechseln:** `role_assigned` / `role_unassigned`
   im Kurskontext müssen dieselbe Bewertung auslösen wie eine Einschreibung
   (Teilnehmer wird Lehrkraft und umgekehrt; mehrere Rollen gleichzeitig).
3. **Rücknahme:** Verliert jemand das Kriterium, muss das Abonnement
   zurückgenommen werden — mit den Folgen für `enrol_adele` (Zielkurs-
   Einschreibungen suspendieren, nicht löschen) und für Hostkurs-Zugänge der
   Quellen 2/3. Das ist Reconciliation, nicht ein einzelnes Ereignis.
4. **Bestand:** Migration bzw. Abgleichslauf für bereits abonnierte
   Lehrkräfte; Entscheidung, was mit ihrem bisherigen Lernstand geschieht.
5. **Zusammenspiel:** `enrol_adele`-Issue „role changes not reconciled"
   (`enrol_adele-issue-role-changes-not-reconciled.md`) und der Entwurf zum
   Capability-Modell (`local_adele-issue-full-capability-model-redesign.md`).

## Akzeptanzkriterien

1. Eine Lehrkraft, die in einen einbindenden Kurs eingeschrieben wird,
   erscheint nicht in der Teilnehmerliste und erhält keinen Lernerpfad.
2. Wird aus einem Teilnehmer eine Lehrkraft (Rollenwechsel ohne neue
   Einschreibung), verschwindet er aus der Liste; die von ADELE erzeugten
   Einschreibungen werden suspendiert.
3. Wird aus einer Lehrkraft ein Teilnehmer, wird der Pfad abonniert.
4. Wer beide Rollen hat, gilt als Teilnehmer.
5. E2E-Kette P: Die Zeilen des Personals entfallen (heute bewusst ungeprüft).
