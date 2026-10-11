# Issue-Entwurf: Feedbacktexte mit falscher Aussage — Zeitfenster und manueller Abschluss

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `feedback`, `wording`
**Stand:** 2026-10-07, Session 006 — gefunden mit Testkette F (Feedback und Sprache)
**GitHub:** noch nicht angelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Feedback am Knoten: drei Texte sagen etwas Falsches (Zeitfenster vor Beginn, offenes Zeitfenster, manueller Abschluss)

## Beobachtung

Gemessen als Lernende, Sprache Englisch (Moodle 4.5, local_adele 2026100708).
In allen drei Fällen stimmt das Verhalten des Knotens; falsch ist der Text.

**1. Festes Zeitfenster, vor Beginn.** Der Knoten ist gesperrt, die
Sprechblase sagt:

> It will be unlocked when: from 15/01/32, 10:00. **From subscription you
> have access until 15/01/32, 12:00.**

Der zweite Satz gehört zur Bedingung „Zeitraum ab Einschreibung" und
behauptet einen Zugang, den es noch nicht gibt. Derselbe Satz erscheint bei
„Zeitfenster UND Vorgänger", solange der Knoten gesperrt ist — auch innerhalb
des Fensters, wenn nur noch der Vorgänger fehlt.

**2. Zeitfenster mit nur einem Ende.** Das i-Symbol zeigt den Platzhalter für
das fehlende Datum als Text:

> 15/01/32, 10:00 to **Missing date** — bzw. — **Missing date** to 15/01/32, 12:00

**3. Manueller Abschluss.** Das i-Symbol nennt als Abschlusskriterium in
jedem Zustand — bevor etwas eingetragen ist, danach und nach der Rücknahme:

> Completion criteria: • **manual completion recorded**

Das liest sich als Tatsache, nicht als Kriterium. (Die Sprechblase ist
richtig: „a manual completion is recorded by the teacher" vorher, „completed
it successfully" danach.)

## Reproduktion

`tests/e2e/tests/zz-time-feedback.spec.ts`, Tests „fixed window, before it
opens: nobody is told they 'have access until' its end" und „a window open at
one end does not read 'Missing date'"; `tests/e2e/tests/feedback-texts.spec.ts`,
Test „manual completion: the information card does not claim a completion
nobody recorded". Alle drei sind derzeit als bekannte Fehler übersprungen;
`ADELE_RUN_KNOWN_DEFECTS=1` führt sie aus.

## Ursache

1. `relation_update::inbetweenfeedback()` erzeugt für den Zustand „before"
   den String `node_restriction_before_timed` („From subscription you have
   access until {$a}.") für **jede** Zeitbedingung mit Enddatum, auch für das
   feste Fenster (`timed`), nicht nur für `timed_duration`.
2. `course_restriction\conditions\timed::get_restriction_status()` setzt für
   ein fehlendes Datum den String `course_restricition_timed_no_date`
   („Missing date") in den Platzhalter; die Vorlage
   `course_information_condition_timed` ist fest „{start_date} to {end_date}".
3. Der String `course_information_condition_completion_manual` lautet
   „manual completion recorded".

## Vorschlag

1. Vor Beginn eines festen Fensters keinen „until"-Satz; falls das Ende
   genannt werden soll, als „from … to …". Den Satz „From subscription …" nur
   für `timed_duration`.
2. Für offene Fenster eigene Vorlagen: „from {start_date}" / „until
   {end_date}".
3. Den Informationstext als Kriterium formulieren, z. B. „Manual completion
   by the teacher" / „Manueller Abschluss durch die Lehrkraft".

Die deutschen Strings sind entsprechend zu prüfen (nicht gemessen: Kette F
liest die Zeit- und Abschlusstexte nur auf Englisch).

## Akzeptanzkriterien

1. Die drei genannten E2E-Tests laufen als reguläre Tests grün.
2. Die bestehenden Tests der Kette F (Zeitfenster vor/im/nach, UND/ODER)
   bleiben grün.
