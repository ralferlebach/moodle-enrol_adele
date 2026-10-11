# Issue-Entwurf: Feedbacktexte und Sprache — Mischsprache nach eigener Aktion, Text der Autorin verschwindet

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `i18n`, `feedback`
**Stand:** 2026-10-07, Session 006 — gefunden mit Testkette F (Feedback und Sprache)
**GitHub:** noch nicht angelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Feedback am Knoten: Lernende mit anderer Sprache lesen Mischsprache, und ein von der Autorin geschriebener Text wird durch den Standardtext ersetzt

## Beobachtung

Gemessen mit einem Pfad, der auf Englisch angelegt wurde, und Lernenden mit
Profilsprache Deutsch (Moodle 4.5, local_adele 2026100708).

**1. Mischsprache nach der eigenen Aktion.** Vor der ersten eigenen Aktion ist
alles deutsch. Sobald die Lernende selbst einen Kurs abschließt, mischen sich
die Sprachen — an allen Knoten des Pfads:

| | i-Symbol, Abschlusskriterien | Sprechblase |
|---|---|---|
| vorher | „diesen Kurs bearbeiten." | „… müssen Sie: diesen Kurs erfolgreich bearbeiten." |
| nachher | „**Complete** diesen Kurs." | „… müssen Sie: **successfully complete** diesen Kurs." / „… weil Sie: **successfully completed** diesen Kurs." |

**2. Der Text der Autorin verschwindet.** Schreibt die Autorin an einem Knoten
einen eigenen Text statt des Standards („Standard verwenden" abgewählt), lesen
ihn Lernende mit der Sprache der Autorin. Lernende mit anderer Sprache lesen
stattdessen den **Standardtext** in ihrer Sprache — der eigene Text ist für
sie nicht vorhanden.

## Reproduktion

1. Als Autor:in mit Sprache Englisch einen linearen Pfad anlegen (Knoten 2
   nach Abschluss von Knoten 1), in einen Kurs einbinden.
2. Als Lernende mit Sprache Deutsch den Pfad ansehen: alles deutsch.
3. Den Kurs von Knoten 1 abschließen („Als erledigt kennzeichnen"), Pfad
   erneut ansehen: Mischsprache wie oben.
4. Für Punkt 2: an Knoten 2 beim Zugangs-Feedback „Standard verwenden"
   abwählen und einen eigenen Text eintragen; als deutsche Lernende ansehen.

Automatisiert: `tests/e2e/tests/feedback-texts.spec.ts`, Tests „language:
German stays German after the learner's own action" und „language: the
author's own text reaches a learner with another language" (derzeit als
bekannte Fehler übersprungen; `ADELE_RUN_KNOWN_DEFECTS=1` führt sie aus).

## Ursache

Der Pfad speichert je Feedback-Knoten fertige Texte in der Sprache der
Autorin (`feedback_before`, `feedback_after`, `feedback_inbetween`,
`information`) — auch dann, wenn „Standard verwenden" gesetzt ist.

- **Neuberechnung** (`relation_update::updated_single()` →
  `getfeedback(…, $forcelangdefaults = false)`): nimmt die gespeicherten Texte
  (`pick_feedback_template()`), füllt die Platzhalter aber über `get_string()`
  in der Sprache dessen, der die Neuberechnung auslöst, und vermerkt
  `feedback_lang = current_language()`.
- **Abruf** (`rerender_feedback_language()`, #493): rendert nur neu, wenn
  `feedback_lang` von der Sprache der Betrachterin abweicht — dann aber mit
  `$forcelangdefaults = true`, also **immer** aus den Standardtexten der
  Bedingung, auch wo die Autorin einen eigenen Text geschrieben hat.

Daraus folgen beide Befunde:

1. Löst die Lernende die Neuberechnung selbst aus (ihr Klick, deutsche
   Sitzung), entsteht „englische Vorlage + deutsche Platzhalter" mit dem
   Vermerk `de`; der Abruf hält den Text für deutsch und rendert nicht neu.
   Gemessen: `feedback_lang` wechselt genau bei diesem Klick von `en` auf `de`.
2. Weicht die Sprache ab, ersetzt der Abruf jeden Text durch den Standard.

Abgeleitet aus dem Code, **nicht gemessen:** Die Sätze zum Zeitfenster
(`before_timed`, `inbetween_timed`) werden bei der Neuberechnung in deren
Sprache erzeugt und beim Abruf nicht neu gerendert; sie dürften in der
Sprache der letzten Neuberechnung stehen bleiben.

## Vorschlag

Je Feld unterscheiden, ob es der Standard ist oder ein eigener Text
(`feedback_*_checkmark` liegt dafür bereits vor):

- **Standard:** nie aus dem gespeicherten Text, sondern immer aus dem
  Sprachstring der Bedingung in der Sprache der Lernenden — bei der
  Neuberechnung wie beim Abruf.
- **Eigener Text:** immer der gespeicherte Text, in jeder Sprache.
- Platzhalter und Vorlage in **derselben** Sprache rendern (die der
  Lernenden, der der Pfad gehört), unabhängig davon, wer die Neuberechnung
  auslöst (Lernende, Lehrkraft, Cron).

## Akzeptanzkriterien

1. Die beiden genannten E2E-Tests laufen als reguläre Tests grün.
2. PHPUnit: Neuberechnung in Sprache A, Abruf in Sprache B und umgekehrt —
   Standardtexte vollständig in der Sprache der Lernenden, eigener Text
   unverändert.
3. Die Sätze zum Zeitfenster folgen der Sprache der Lernenden.
