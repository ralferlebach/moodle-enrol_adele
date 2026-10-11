# Issue-Entwurf: Lehrkraft ohne Bearbeitungsrecht — alles sehen, nichts bearbeiten

**Repository:** `Wunderbyte-GmbH/moodle-mod_adele` (mit Anteil in `moodle-local_adele`)
**Labels (Vorschlag):** `bug`, `roles`, `capabilities`
**Stand:** 2026-10-07, Session 006 — gemessen mit der E2E-Kette P; Entscheidung des Auftraggebers: Sie soll alles sehen können, aber nicht bearbeiten

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Lehrkraft ohne Bearbeitungsrecht („Non-editing teacher") bekommt die Teilnehmeransicht, darf über den Webservice aber Lernerpfade ändern — gewünscht ist das Gegenteil: Lehrkraftansicht nur lesend

## Beobachtung

Gemessen (Moodle 4.5.15, Kurs mit eingebundenem Lernpfad, Rolle `teacher`):

| | heute | gewünscht |
|---|---|---|
| Ansicht der Aktivität | Teilnehmeransicht: bei „Only own results" nur die eigene Zeile, keine ID-Spalte, kein Weg in die Detailansicht | Lehrkraftansicht mit allen Lernenden und Detailansicht |
| Liste vom Server (`get_user_path_relations`) | vollständig | vollständig |
| Einzelner Lernerpfad (`get_user_path_relation`) | lesbar | lesbar |
| Lernerpfad ändern (`save_user_path_relation`: manueller Abschluss, Master-Haken) | **erlaubt** (Antwort `success: true`, Knoten danach abgeschlossen) | **nicht erlaubt** |

Die Oberfläche zeigt also weniger, als der Server herausgibt, und der Server
erlaubt mehr, als die Rolle soll.

## Ursache

- `mod_adele/view.php` und `mod_adele_cm_info_view()` wählen die
  Lehrkraftansicht über `mod/adele:addinstance` (Archetypen `editingteacher`,
  `manager`). Die Fähigkeit, eine Aktivität **anzulegen**, entscheidet damit
  über die Fähigkeit, Lernstände **zu sehen**.
- `local/adele:teacheredit` hat die Archetypen `editingteacher`, `teacher`,
  `manager` und ist die einzige Capability sowohl für das Lesen
  (`get_lp_user_path_relation(s)`) als auch für das Schreiben
  (`save_lp_user_path_relation`).
- Nebenbefund in `db/access.php` von `local_adele`: `local/adele:canmanage`
  und `local/adele:assist` tragen den Archetyp `teacher` (= Lehrkraft **ohne**
  Bearbeitungsrecht), nicht `editingteacher`. `save_lp_user_path_relation`
  prüft `canmanage` im übergebenen Kontext; eine Lehrkraft ohne
  Bearbeitungsrecht besteht diese Prüfung in ihrem Kurs. Siehe
  `local_adele-issue-full-capability-model-redesign.md`.

## Vorschlag

1. Neue Capability zum **Lesen** der Lernstände im Kurs, z. B.
   `local/adele:viewparticipants` (Archetypen `teacher`, `editingteacher`,
   `manager`). `local/adele:teacheredit` bleibt für das **Ändern** und verliert
   den Archetyp `teacher`.
2. `mod_adele`: Lehrkraftansicht, wenn `viewparticipants` vorliegt; die
   Bedienelemente zum Ändern (manueller Abschluss, Master-Haken, Speichern)
   nur mit `teacheredit`. Die SPA braucht dafür ein drittes Ansichtsmerkmal
   (z. B. `view="teacher"` + `canedit="0"`).
3. `local_adele`: Lesedienste prüfen `viewparticipants`, Schreibdienst prüft
   `teacheredit` — jeweils im einbindenden Kurs.
4. Upgrade-Schritt für bestehende Rollen (Capability-Zuweisungen der
   Archetypen nachziehen).

## Akzeptanzkriterien

1. Lehrkraft ohne Bearbeitungsrecht: Lehrkraftansicht in beiden
   Ergebnis-Sichtbarkeiten, alle Lernenden, Detailansicht erreichbar.
2. In der Detailansicht keine Bedienelemente zum Ändern;
   `save_user_path_relation` antwortet mit `nopermissions`.
3. Lehrkraft mit Bearbeitungsrecht: unverändert.
4. E2E-Kette P3: Zeile „teacher without editing rights" wird
   „teacher view … | link", plus Negativschritt für das Speichern.
