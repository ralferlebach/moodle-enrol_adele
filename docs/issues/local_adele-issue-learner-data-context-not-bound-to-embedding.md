# Issue-Entwurf: Lernerdaten über den Kontext eines beliebigen Kurses abrufbar

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `security`, `privacy`
**Stand:** 2026-10-07, Session 006 — gefunden mit der E2E-Kette P (Lernerdaten), in der Arbeitskopie behoben
**GitHub:** local_adele #586 (zweiter Teil berührt #516 und #517)

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Lernerdaten-Webservices: Kursrechte gelten in jedem Kurs, Pfadrechte für jeden Pfad — Teilnehmerliste und Lernstand fremder Pfade lesbar, „Only own results" umgehbar, Lernerpfade fremder Pfade änderbar

## Beobachtung

Beide Webservices nehmen die `contextid` vom Client. Rechte, die aus einem Kurs
stammen (im Kurs eingeschrieben sein, den Kurs unterrichten), wurden gegen
**diesen übergebenen** Kurs geprüft — ohne zu prüfen, ob der Kurs den
angefragten Lernpfad überhaupt einbindet. Damit öffnet der Kontext irgendeines
Kurses, dem man angehört, die Lernerdaten jedes Pfads.

Auf einer laufenden Instanz gemessen (Moodle 4.5.15, Pfad mit zwei Lernenden
Alpha 1/3 und Beta 2/3, eingebunden im Kurs E2EP mit „Only own results"):

| Wer | übergebener Kontext | Aufruf | Antwort vorher |
|---|---|---|---|
| Teilnehmer eines **anderen** Kurses ohne Lernpfad, auf keinem Pfad | eigener Kurs | `local_adele_get_user_path_relations` | vollständige Liste: Namen, Fortschritt, Rang aller Lernenden |
| Lernende Alpha, Einbindung „Only own results" | Knotenkurs T01 (dort von ADELE selbst eingeschrieben) | `local_adele_get_user_path_relations` | vollständige Liste statt nur der eigenen Zeile — #569 umgangen |
| Lehrkraft (ohne Bearbeitungsrecht) eines **anderen** Kurses | eigener Kurs | `local_adele_get_user_path_relations` | vollständige Liste |
| dieselbe Lehrkraft | eigener Kurs | `local_adele_get_user_path_relation` (Beta) | Betas Snapshot samt E-Mail-Adresse |

Schreibend, ebenfalls gemessen (`local_adele_save_user_path_relation`, Master-
Abschluss auf Betas Knoten 3):

| Wer | übergebener Kontext | Antwort vorher |
|---|---|---|
| Lehrkraft (ohne Bearbeitungsrecht) eines **anderen** Kurses | eigener Kurs | `success: true` — Betas Knoten 3 ist abgeschlossen |
| Teilnehmer; Teilnehmer eines anderen Kurses; Assistent; Kollaborator des Pfads | — | abgelehnt |

Pfadrechte, gemessen: Ein AdeLe-Assistent, der nur einen **anderen** Pfad
bearbeiten darf und in keinem Kurs ist, erhielt Liste und Betas Snapshot —
`check_access()` fragt nur, ob jemand irgendeinen Pfad bearbeiten darf.

Zusätzlich:

- Ein Modulkontext einer **anderen** ADELE-Aktivität desselben Kurses (anderer
  Pfad, „Results of all other participants") hob „Only own results" für den
  angefragten Pfad auf: `only_own_results()` las die Einstellung der Aktivität
  des Kontexts, ohne deren `learningpathid` mit der angefragten zu vergleichen.
- Mit dem Systemkontext antworteten beide Dienste mit einem `codingerror`
  („Context does not belong to any course") statt mit einer Ablehnung bzw. —
  für Manager — mit der Liste.

## Ursache

`classes/external/get_lp_user_path_relations.php`:

```php
$isprivileged = has_capability('local/adele:teacheredit', $coursecontext)
    || learning_paths::check_access();
if (!$isprivileged && !is_enrolled($coursecontext, $USER->id)) { throw … }
```

`$coursecontext` stammt aus der übergebenen `contextid`; `learningpathid` wird
unabhängig davon übergeben. Der Kommentar an der Stelle nennt das Ziel
(„this only blocks enumeration of unrelated courses/paths"), die Prüfung
erreicht es nicht. `get_lp_user_path_relation.php` prüft `teacheredit` ebenso
gegen den übergebenen Kontext.

`tests/issue569_userlist_privacy_test.php::test_unembedded_path_keeps_current_behaviour`
hielt das Verhalten „Pfad im Kurs nicht eingebunden → vollständige Liste" als
gewollt fest; genau dieser Fall ist die Umgehung.

## Behebung (in der Arbeitskopie umgesetzt)

- Neu `learning_paths::is_embedded_in_course($learningpathid, $courseid)`:
  Es gibt eine `mod_adele`-Aktivität dieses Kurses mit diesem Pfad.
- Liste: Kursrechte (`teacheredit`, eingeschrieben) zählen nur, wenn der Kurs
  des Kontexts den Pfad einbindet. Pfadbezogene Rechte bleiben unabhängig vom
  Kurs (ihr Umfang: zweiter Teil unten).
- Einzelabruf: `teacheredit` zählt nur in einem einbindenden Kurs; der eigene
  Snapshot bleibt in jedem Kontext lesbar.
- `only_own_results()`: Die Einstellung der Aktivität entscheidet nur, wenn
  diese Aktivität den angefragten Pfad zeigt; sonst gilt die strengste
  Einbindung des Pfads im Kurs.
- Kontext außerhalb eines Kurses: kein `codingerror`; pfadbezogene Rechte
  werden bedient, alle anderen abgelehnt.

## Tests

- PHPUnit neu: `tests/security/learner_data_embedding_test.php` (8 Tests; vier
  davon schlagen ohne die Prüfung fehl).
- PHPUnit angepasst: `user_path_relation_access_test.php` bindet den Pfad
  jetzt im Kurs ein; `issue569_userlist_privacy_test.php` erwartet für den
  nicht eingebundenen Pfad eine Ablehnung.
- E2E: `mod_adele/tests/e2e/tests/learner-data.spec.ts`, P3 und P4.

## Behebung, zweiter Teil (Stand 2026100704, nach Entscheidung des Auftraggebers)

- Neu `learning_paths::has_path_authority($learningpathid)`: Site-Administrator
  oder `local/adele:canmanage` im System — jeder Pfad; sonst nur Pfade, deren
  Editor man ist (`local_adele_lp_editors`). Ersetzt `check_access()` in
  `get_lp_user_path_relations` und `get_lp_user_path_relation`. Assistenten
  und Kollaboratoren lesen damit nur noch die Lernenden ihrer Pfade; Lehrkräfte
  eines einbindenden Kurses weiterhin über das Kursrecht.
- `save_lp_user_path_relation`: Kursrechte (`teacheredit`, `canmanage` im
  Kurs) zählen nur in einem Kurs, der den Pfad einbindet; `canmanage` im
  System weiterhin überall. Der Pfad, der tatsächlich geschrieben wird, steht
  **im Payload** (`route.learningpathId`); er muss dem Parameter
  `learningpathid` entsprechen, sonst `invalid_parameter_exception` — sonst
  ließe sich die Prüfung mit dem eigenen Pfad im Parameter und dem fremden im
  Payload umgehen.
- PHPUnit `learner_data_embedding_test`: jetzt 14 Tests (Editor eines anderen
  Pfads liest nichts; Editor dieses Pfads liest; fremde Lehrkraft schreibt
  nicht; Payload-Pfad muss passen; Teilnehmer schreibt nicht; Lehrkraft des
  einbindenden Kurses und Manager schreiben). Gegenproben mit der alten Logik
  schlagen fehl.
- E2E: P3 Zeile „ADELE assistant" jetzt abgelehnt; P4 neuer Schritt
  „niemand ohne Recht ändert Betas Pfad".

## Nicht in diesem Issue — eigene Entwürfe

1. Lehrkräfte stehen als Lernende in der Liste:
   `local_adele-issue-staff-subscribed-as-learners.md`.
2. Lehrkraft ohne Bearbeitungsrecht soll alles sehen, aber nicht bearbeiten:
   `mod_adele-issue-read-only-teacher-view.md`.
3. Liste auf Kurseinschreibung × Pfadeinschreibung beschränken:
   `local_adele-issue-roster-not-limited-to-course.md`.
