# Issue-Entwurf: Teilnehmerquellen zählen ADELE-eigene Einschreibungen – Abos pflanzen sich über Pfade fort

**Repository:** `Wunderbyte-GmbH/moodle-mod_adele`
**Labels (Vorschlag):** `question`, `semantics`, `enrolment`
**Stand:** 2026-10-06, Session 006 — gefunden beim Aufbau der E2E-Kette H

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Teilnehmerquellen werten auch Einschreibungen aus, die ADELE selbst angelegt hat – ein Abo löst Abos auf weiteren Pfaden aus

## Beobachtung

Testinstanz mit den Fixtures aus `adele-test`. Der Kurs `l01` bettet drei
Lernpfade ein:

| Einbettung in `l01` | Teilnehmerquelle | wirkt auf |
|---|---|---|
| Pfad „Verzweigt, gestapelt B1" | 2 – Startknoten (T01–T04) | jede Einschreibung in T01–T04 |
| Pfad „Linear A2" | 3 – beliebiger Knotenkurs (T01–T03) | jede Einschreibung in T01–T03 |
| Pfad „Äquivalenzumformung (neu)" | 1 – dieser Kurs | jede Mitgliedschaft in `l01` |

Eine Person wird **von Hand nur in T03** eingeschrieben. Danach hat sie:

1. Abos auf „Verzweigt, gestapelt B1" (Quelle 2) und „Linear A2" (Quelle 3),
2. durch beide eine sichtbare Host-Einschreibung in `l01`,
3. durch diese Mitgliedschaft ein Abo auf „Äquivalenzumformung (neu)" (Quelle 1),
4. ADELE-Einschreibungen in T01, T02, T04 — und gilt damit für **jeden
   weiteren** Pfad mit diesen Startkursen als „in einem Startknoten
   eingeschrieben".

## Ursache im Code

`mod_adele_observer::user_enrolment_created()` reagiert auf jede neue
Einschreibung (`\core\event\user_enrolment_created`), gleich welcher Methode.
Die Einschreibungen, die `enrol_adele` selbst für Knoten- und Hostkurse
anlegt, lösen dieselbe Auswertung aus wie eine manuelle.

## Frage

Sollen ADELE-eigene Einschreibungen (`enrol = 'adele'`) für Teilnehmerquellen
zählen?

- **Ja (heute):** Pfade verketten sich über gemeinsame Kurse. Das kann gewollt
  sein, etwa für aufeinander aufbauende Pfade, macht die Teilnehmerschaft aber
  schwer vorhersagbar.
- **Nein:** Nur Einschreibungen anderer Methoden (manuell, Selbsteinschreibung,
  Kohorte, …) zählen. Jede Einbettung wirkt dann nur auf Personen, die der
  Kurs tatsächlich führt.
- **Teilweise:** Etwa Host-Einschreibungen ausnehmen, Knoten-Einschreibungen
  zählen lassen.

Die Entscheidung betrifft auch den Austritt: `enrol_adele\observer::is_user_carried()`
prüft beim Austragen, ob eine Quelle die Person noch trägt; dort müsste
dieselbe Regel gelten.

## Akzeptanzkriterien (nach der Entscheidung)

1. Die beschriebene Testinstanz verhält sich nach der festgelegten Regel;
   PHPUnit-Test mit zwei Pfaden, die sich einen Kurs teilen.
2. Abo und Austritt (A-4-Regel) folgen derselben Regel.
3. Die E2E-Kette H prüft die Negativfälle der Quellen 2 und 3 mit einer
   Person, die nur über einen anderen Pfad in einen Knotenkurs gelangt ist.
