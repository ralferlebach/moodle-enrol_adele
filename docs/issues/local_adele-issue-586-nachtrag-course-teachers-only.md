# Nachtrag zu local_adele #586: Lernerdaten nur für Lehrende des einbindenden Kurses

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Bezug:** #586 (offen) — als Kommentar oder Ergänzung des Issue-Texts
**Stand:** 2026-10-07, Session 006 — Entscheidung des Auftraggebers vom 2026-10-07
**GitHub:** local_adele #586, Kommentar vom 2026-10-07

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Text.

---

## Nachtrag: Pfad bearbeiten und Pfad einsetzen sind getrennte Rechte

**Entscheidung (2026-10-07):** Wer einen Lernpfad **erstellt oder bearbeitet**,
bekommt dadurch keine Einsicht in die Daten der Lernenden. Einsicht haben die
**Lehrenden des Kurses, der den Pfad einbindet** — für die Lernenden dieses
Kurses.

| Wer | Lernerdaten lesen | Lernerpfad ändern |
|---|---|---|
| Moodle-Administrator:in | ja, überall | ja, überall |
| Lehrende im einbindenden Kurs (`local/adele:viewparticipants`) | ja, die Lernenden dieses Kurses | nur mit `local/adele:teacheredit` |
| AdeLe-Manager (`local/adele:canmanage`) | **nein** | **nein** |
| Ersteller:in und Mitarbeitende eines Pfads | **nein** | **nein** |
| AdeLe-Assistent | **nein** | **nein** |

Wer in einer der unteren drei Zeilen zugleich Lehrende:r im einbindenden Kurs
ist, hat die Rechte der zweiten Zeile — aus der Kursrolle, nicht aus dem
Pfadrecht.

## Stand heute (local_adele 2026100710)

Der zweite Teil der Behebung von #586 hatte die Einsicht auf den **eigenen**
Pfad begrenzt, sie aber nicht entfernt:

- `learning_paths::has_path_authority()` gibt Administrator:innen,
  AdeLe-Managern sowie Ersteller:in und Mitarbeitenden eines Pfads die
  Lernerdaten dieses Pfads — über den Systemkontext auch die Lernenden
  **aller** Kurse, die ihn einbinden.
  Verwendet in `external/get_lp_user_path_relations` (Teilnehmerliste) und
  `external/get_lp_user_path_relation` (einzelner Lernerpfad).
- `external/save_lp_user_path_relation` (Abschluss/Zugang für einzelne
  Lernende setzen) lässt AdeLe-Manager über `local/adele:canmanage` im
  Systemkontext zu, ohne Kursrolle.

Gemessen in Testkette P (`mod_adele/tests/e2e/tests/learner-data.spec.ts`):
Kollaborator und AdeLe-Manager lesen die Liste, der Manager über den
Systemkontext die Lernenden beider Kurse.

## Gewünschtes Verhalten

1. Die drei genannten Dienste entscheiden allein nach dem **Kurs**: Der Kurs
   muss den Pfad einbinden, und die anfragende Person braucht dort
   `local/adele:viewparticipants` (lesen) bzw. `local/adele:teacheredit`
   (ändern). Ausnahme: Moodle-Administrator:innen.
2. Ohne Kurskontext (Systemkontext) gibt es Lernerdaten nur für
   Moodle-Administrator:innen.
3. `local/adele:canmanage`, die Mitarbeit an einem Pfad und die
   Assistentenrolle bleiben, was sie für den **Editor** sind; für Lernerdaten
   zählen sie nicht.
4. Unverändert: Lernende sehen die eigenen Daten bzw. die Liste gemäß
   „Ergebnisse der Teilnehmenden" der Aktivität; die Liste eines Kurses
   enthält nur dessen Lernende (#588).

## Zu klären

- **Moodles eigene Rolle „Manager"** (Archetyp `manager`) hat
  `local/adele:viewparticipants` und `local/adele:teacheredit` als Vorgabe
  und, systemweit vergeben, damit Einsicht in jedem einbindenden Kurs. Das
  entspricht Moodles Rechtemodell (eine Kurs-Capability, über eine
  höherliegende Rolle geerbt) und bliebe so — sofern nicht anders gewünscht.
- **Editor:** Zeigt der Lernpfad-Editor an einer Stelle Lernende oder
  Fortschritt, entfällt das für alle außer Administrator:innen (wird bei der
  Umsetzung geprüft).

## Akzeptanzkriterien

1. Testkette P, P3: AdeLe-Manager, Kollaborator und Assistent erhalten weder
   Liste noch einzelnen Lernerpfad, in keinem Kontext; als Lehrende im
   einbindenden Kurs erhalten sie beides für diesen Kurs.
2. P4: Kein AdeLe-Manager ändert einen Lernerpfad ohne
   `local/adele:teacheredit` im einbindenden Kurs.
3. PHPUnit (`learner_data_embedding_test`, `roster_course_scope_test`): die
   Matrix oben, je Rolle × Kontext (einbindender Kurs, fremder Kurs, System).
4. Administrator:innen: unverändert.

## Umsetzung (Stand 2026100712)

- `learning_paths::has_path_authority()` entfällt. An seine Stelle tritt
  `has_learner_data_authority()`: Moodle-Administrator:innen, sonst niemand.
- `external/get_lp_user_path_relations`, `get_lp_user_path_relation`,
  `save_lp_user_path_relation`: Außerhalb eines einbindenden Kurses gibt es
  Lernerdaten nur noch für Administrator:innen; im einbindenden Kurs
  entscheidet allein `local/adele:viewparticipants` (lesen) bzw.
  `local/adele:teacheredit` (ändern). `local/adele:canmanage`, die Mitarbeit an
  einem Pfad und die Assistentenrolle zählen dafür nicht mehr.
- **Editor:** zeigt an keiner Stelle Lernende; die drei Dienste werden nur von
  der Lehrenden- und der Teilnehmeransicht der Aktivität aufgerufen (im Code
  nachgesehen). Dort ändert sich für Lehrende und Lernende nichts.
- **Moodles Rolle „Manager":** unverändert (Kurs-Capabilities über die Rolle
  geerbt) — Entscheidung dazu steht aus.

**Tests:** PHPUnit `learner_data_embedding_test` — AdeLe-Manager, Assistent
und Mitarbeitende dieses Pfads: Liste, einzelner Lernerpfad und Ändern
abgelehnt, über die Einbindung wie ohne Kurs; dieselben Personen als Lehrende
des Kurses: lesen dort, ändern nur als Lehrende mit Bearbeitungsrecht.
`user_path_relation_access_test::test_path_editor_cannot_read_user_path`
(vorher das Gegenteil festgeschrieben). Gegenprobe mit dem alten Code: diese
Tests rot. E2E Kette P: Manager und Kollaborator erhalten nichts mehr
(Tabelle P3, Schreibversuche P4, Systemkontext); Administrator unverändert.
