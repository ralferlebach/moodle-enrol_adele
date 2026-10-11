# Issue-Entwurf: Teilnehmerliste zeigt Lernende anderer Kurse desselben Pfads

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `privacy`
**Stand:** 2026-10-07, Session 006 — aus dem Code der Kette P gelesen (nicht im Browser gemessen); Entscheidung des Auftraggebers: auf die Schnittmenge Kurseinschreibung × Pfadeinschreibung beschränken
**GitHub:** local_adele #588

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

`get_user_path_relations`: Die Liste ist pfadweit — bei Einbindung in mehreren Kursen sehen Lehrkräfte und Lernende eines Kurses die Lernenden der anderen Kurse

## Beobachtung

`learning_paths::get_learning_user_relations()` liefert alle aktiven
Lernerpfade eines Lernpfads:

```sql
SELECT … FROM {local_adele_path_user} lpu
  LEFT JOIN {user} usr ON lpu.user_id = usr.id
 WHERE lpu.learning_path_id = :learning_path_id AND lpu.status = 'active'
```

Der Kurs der Anfrage spielt keine Rolle. Ist ein Pfad in Kurs X und Kurs Y
eingebunden, zeigt die Aktivität in X auch die Lernenden aus Y — der Lehrkraft
von X, und bei „Results of all other participants" auch den Lernenden von X.
Namen, Fortschritt und Rang verlassen damit den Kurs, in dem sie entstanden
sind.

Hintergrund im Code: Der Filter auf `course_id` wurde entfernt, weil
`local_adele_path_user.course_id` nur festhält, welcher Kurs das Abonnement
zuerst ausgelöst hat (ein Lernerpfad je Person und Pfad, unabhängig vom Kurs).
Die Spalte taugt also nicht als Filter; ein Ersatz fehlt.

## Gewünschtes Verhalten

Die Liste einer Einbindung enthält genau die Personen, die **Lernende des
Pfads** und **im Kurs der Einbindung eingeschrieben** sind (Schnittmenge
Kurseinschreibung × Pfadeinschreibung). Der Rang wird innerhalb dieser Menge
vergeben.

## Vorschlag

1. In `get_lp_user_path_relations::execute()` bzw.
   `get_learning_user_relations()` die Menge auf im Kurs eingeschriebene
   Personen einschränken (`get_enrolled_sql($coursecontext)` als Join), bevor
   sortiert und der Rang vergeben wird.
2. Entscheiden: zählen suspendierte Kurseinschreibungen (`onlyactive`)? Vorschlag:
   nur aktive.
3. Pfadbezogene Rechte ohne Kurskontext (Manager, Pfad-Editor über den
   Systemkontext): pfadweite Liste bleibt, denn dort gibt es keinen Kurs.
4. Dasselbe Kriterium für den Einzelabruf über Kursrechte
   (`get_lp_user_path_relation`): Eine Lehrkraft liest nur Lernende, die in
   ihrem Kurs eingeschrieben sind.
5. Zusammenhang mit „Lehrkräfte als Lernende" (eigener Entwurf): Solange die
   Rolle nicht zählt, gehören eingeschriebene Lehrkräfte zur Schnittmenge.

## Akzeptanzkriterien

1. PHPUnit: Pfad in zwei Kursen eingebunden, je eine Lernende; Lehrkraft und
   Lernende von Kurs X erhalten nur die Lernende von X, Rang 1.
2. PHPUnit: Lernende, die in beiden Kursen eingeschrieben ist, erscheint in
   beiden Listen.
3. PHPUnit: Lehrkraft von X erhält den Lernerpfad der Lernenden von Y nicht.
4. E2E-Kette P: zweiter Hostkurs mit eigener Lernender; P2/P3 prüfen, dass sie
   in E2EP nicht erscheint.

## Umsetzung (Stand 2026100706)

- `learning_paths::get_learning_user_relations()`: optionaler Geltungsbereich
  `scopecourseid` — Join auf `get_enrolled_sql()` des Kurses, **vor** Sortierung
  und Rangvergabe. `get_lp_user_path_relations::execute()` setzt ihn, sobald
  die Anfrage über einen Kurs kommt, der den Pfad einbindet. Ohne solchen Kurs
  (Pfadrecht über den Systemkontext) bleibt die Liste pfadweit.
- **Abweichung vom Vorschlag, begründet:** Es zählen alle Einschreibungen,
  aktive **und suspendierte**. Der Host-Modus `hidden` führt seine Lernenden
  suspendiert im Hostkurs; mit „nur aktive" verschwänden sie aus der Liste
  ihrer Lehrkraft.
- `get_lp_user_path_relation` und `save_lp_user_path_relation`: Das Kursrecht
  einer Lehrkraft deckt nur Lernende, die in ihrem Kurs eingeschrieben sind.
- PHPUnit `tests/security/roster_course_scope_test.php` (7 Tests): Lehrkraft
  und Lernende von X sehen nur X, Rang 1, obwohl die Lernende von Y weiter ist;
  in beiden Kursen eingeschrieben → in beiden Listen; suspendiert bleibt;
  Systemkontext pfadweit; Lehrkraft von X liest und ändert die Lernende von Y
  nicht. Gegenprobe ohne Geltungsbereich: 3 Tests schlagen fehl.
- E2E Kette P: zweiter Hostkurs E2EP2 mit eigener Lernender „Gamma" auf
  demselben Pfad. Gamma erscheint in keiner Liste von E2EP, dort allein mit
  Rang 1; Manager über den Systemkontext sieht alle drei; die Lehrkraft von
  E2EP liest und ändert Gammas Pfad nicht.

## Offen — zur Entscheidung

**Host-Modus `none`.** Lernende, die über die Quellen „Startknoten" oder
„beliebiger Knotenkurs" auf den Pfad kommen, werden im Modus `none` nicht in
den Hostkurs eingeschrieben. Sie gehören damit nicht zur Schnittmenge und
stehen in keiner Kursliste mehr — auch nicht bei der Lehrkraft des Hostkurses.
Pfadweit sieht sie nur, wer das Pfadrecht hat, und dafür gibt es derzeit keine
Oberfläche. Aus dem Code und der Regel abgeleitet, nicht gemessen.
