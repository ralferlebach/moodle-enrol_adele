# Issue-Entwurf: Teilnahme am Lernpfad an die „Bewerteten Rollen" der Instanz binden

**Repository:** `Wunderbyte-GmbH/moodle-local_adele` (betrifft auch `enrol_adele`, `mod_adele`)
**Labels (Vorschlag):** `improvement`, `roles`
**Stand:** 2026-10-07, Session 006 — Entscheidung des Auftraggebers vom 2026-10-07, Folge aus #587
**GitHub:** local_adele #593

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Wer am Lernpfad teilnimmt, bestimmen die „Bewerteten Rollen" der Instanz — nicht eine eigene Capability

## Stand heute (local_adele 2026100710, aus #587)

Als Lernende:r eines eingebundenen Lernpfads gilt, wer im Kurs aus eigenem
Recht eine Rolle mit der Capability `local/adele:participate` hat
(`enrol_state::is_participant()`).

Moodle vergibt eine neue Capability von sich aus nur an Rollen des passenden
Archetyps (`student`). Damit eine Instanz, die ihre Lernenden unter einer
eigenen Rolle ohne diesen Archetyp führt, sie beim Upgrade nicht verliert,
kopiert der Upgrade-Schritt `2026100708` die Capability **einmalig** auf die
Rollen aus *Website-Administration → Bewertungen → Allgemeine Einstellungen →
Bewertete Rollen* (`$CFG->gradebookroles`).

Schwächen:

- Die Kopie ist eine Momentaufnahme. Wird später eine Rolle zu den Bewerteten
  Rollen hinzugefügt oder daraus entfernt, folgt die Teilnahme nicht.
- Zwei Stellen beantworten dieselbe Frage („wer lernt hier?"): die
  Bewerteten Rollen für Bewertungen und Abschlussberichte, die Capability für
  den Lernpfad. Sie können auseinanderlaufen.
- Wer eine neue Lernenden-Rolle anlegt, muss an die Capability denken.

## Gewünschtes Verhalten

Am Lernpfad nimmt teil, wer im einbindenden Kurs aus eigenem Recht eine Rolle
hat, die zu den **Bewerteten Rollen** der Instanz gehört — dieselbe
Festlegung, nach der Moodle entscheidet, wer in Bewertungen und
Abschlussberichten erscheint.

1. `enrol_state::is_participant()` prüft die Rollenzuweisungen der Person im
   Kurs (wie bisher einschließlich übergeordneter Kontexte, ohne die von
   `enrol_adele` selbst vergebenen Rollen) gegen `$CFG->gradebookroles`.
2. Die Einstellung wirkt laufend: Eine Änderung der Bewerteten Rollen gilt ab
   der nächsten Auswertung; der nächtliche Abgleich zieht nach (mit der
   bestehenden Schonfrist, bevor ein Lernstand gelöscht wird).
3. Die Capability `local/adele:participate` entfällt (samt der beim Upgrade
   gesetzten Einträge) — eine Quelle der Wahrheit.
4. Unverändert: Lehrende sind keine Lernenden (#587); bekommen sie im Kurs
   zusätzlich eine Bewertete Rolle, nehmen sie teil, solange sie sie haben.

## Zu beachten

- **Entfernen einer Rolle aus den Bewerteten Rollen** nimmt allen, die nur
  diese Rolle haben, die Teilnahme — nach der Schonfrist wird ihr Lernstand
  gelöscht. Vorschlag: Hinweis in der Dokumentation und in der
  ADELE-Verwaltungsseite („Teilnahme folgt den Bewerteten Rollen: …").
- **Vor dem Einspielen** prüfen, welche Rollen unter „Bewertete Rollen"
  stehen. Steht dort eine Lehrenden-Rolle, gelten deren Mitglieder als
  Lernende.
- Steht dort **keine** Rolle, nimmt niemand teil.

## Akzeptanzkriterien

1. PHPUnit: Rolle in den Bewerteten Rollen → Teilnahme; nicht darin → keine;
   Rolle nachträglich aufgenommen bzw. entfernt → Teilnahme folgt ohne
   Upgrade-Schritt; von `enrol_adele` vergebene Rollen zählen nicht.
2. E2E `participants-by-role.spec.ts` bleibt grün (Lehrkraft nicht in der
   Liste; mit zusätzlicher Rolle „Teilnehmer/in" in der Liste und im
   Startkurs; ohne sie sofort wieder draußen).
3. Upgrade: `local/adele:participate` ist entfernt; auf einer Instanz mit den
   Standard-Einstellungen ändert sich für niemanden die Teilnahme.
