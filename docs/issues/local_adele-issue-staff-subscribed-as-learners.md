# Issue-Entwurf: Lehrkräfte werden als Lernende des Pfads geführt

**Repository:** `Wunderbyte-GmbH/moodle-local_adele` (Querverweis: `moodle-mod_adele`, `moodle-enrol_adele`)
**Labels (Vorschlag):** `enhancement`, `roles`, `enrolment`
**Stand:** 2026-10-07, Session 006 — beobachtet mit der E2E-Kette P; Entscheidung des Auftraggebers: vorerst akzeptabel, langfristig ausschließen
**GitHub:** local_adele #587

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

## Umsetzung (Stand 2026100708) — alle drei Plugins gehören zusammen

**Kriterium.** Neue Capability `local/adele:participate` (Kurskontext, Archetyp
`student`). „Nimmt teil" heißt: hält sie über eine Rolle im Kurs (oder
darüber) — nicht über eine Rolle, die `enrol_adele` selbst vergeben hat —,
und keine eigene Rolle verbietet sie. Eine Definition für alle:
`local_adele\enrol_state::is_participant()`.

**Wer Lernende(r) wird**

- `local_adele\enrollment::enrolled()` abonniert nur noch Teilnehmende.
- Die Rolle wird in Moodle **nach** dem Einschreibe-Ereignis vergeben
  (`enrol_plugin::enrol_user()`); deshalb abonniert bei einer gewöhnlichen
  Einschreibung der neue Beobachter von `role_assigned`
  (`role_assigned_in_course()`), nicht mehr das Einschreibe-Ereignis.
- `mod_adele\local\host_policy::has_foreign_enrolment()` — die eine Regel der
  Teilnehmerquellen — verlangt zusätzlich die Teilnahme; Quelle 1 auf dem
  Ereignisweg prüft jetzt dieselbe Regel. `mod_adele` beobachtet
  `role_assigned`/`role_unassigned` im Kurs und bewertet wie eine
  Einschreibung neu (Abonnement, Hostzugang der Quellen 2/3).

**Wer es nicht mehr ist**

- `enrol_adele\observer::is_user_carried()` zählt nur noch Teilnahme.
- Neuer Beobachter `role_unassigned`: Wer die Teilnehmerrolle verliert und im
  Kurs nur noch unterrichtet, hat die Lernenden verlassen **wie bei einer
  Ausschreibung** — dieselbe bestehende Regel: ADELE-Einschreibungen sofort
  entfernt, der Lernerpfad nach der Schonfrist von fünf Minuten gelöscht,
  sofern die Person bis dahin nicht wieder teilnimmt.
- **Abweichung vom Entwurf, begründet:** Der Entwurf verlangte „suspendieren".
  Für das Verlassen eines Pfads gibt es bereits eine Regel (enrol_adele #3);
  eine zweite, abweichende nur für Rollenwechsel hätte zwei Bedeutungen von
  „hat den Pfad verlassen" geschaffen.

**Liste.** Die Kursliste lässt weg, wer aus eigenem Recht im Kurs ist und
nicht teilnimmt (`enrol_state::get_nonparticipant_userids()`, zwei Abfragen
für die ganze Liste) — sofort, auch während der Schonfrist und für
Altbestand. Wer nur über ADELE im Kurs ist (Host-Modi), bleibt.

**Bestand — wichtig beim Einspielen**

1. Lehrkräfte, die bereits Lernende sind, verschwinden sofort aus den Listen.
   Ihr Abonnement, ihre ADELE-Einschreibungen und ihr Lernstand werden vom
   nächsten nächtlichen Abgleich von `enrol_adele` entfernt
   (`sweep_uncarried_subscriptions`, mit Schonfrist) — ohne eigenes
   Migrationsskript, durch die bestehende Regel.
2. **Rollen ohne Archetyp.** Moodle gibt eine neue Capability nur den Rollen
   ihres Archetyps. Eine Instanz, die ihre Lernenden unter einer Rolle ohne
   Archetyp `student` führt, verlöre sie alle an denselben Abgleich. Der
   Upgrade-Schritt `2026100708` gibt die Capability deshalb zusätzlich den
   **Bewerterrollen** der Instanz (`$CFG->gradebookroles`). Auf der
   Testinstanz als echtes Upgrade gelaufen und in `role_capabilities`
   nachgesehen. **Vor dem Einspielen prüfen:** Haben alle Rollen, unter denen
   Lernende geführt werden, `local/adele:participate`?
3. Einschreibungen ganz ohne Rolle nehmen nicht teil.

**Tests**

- PHPUnit local `issue587_participants_test` (6): Teilnehmer abonniert,
  Lehrkräfte nicht; Lehrkraft wird Teilnehmer; Rolle ohne Einschreibung;
  Definition (ADELE-eigene Rolle, Verbot); Liste; Upgrade-Schritt.
- PHPUnit enrol `participant_role_test` (4): nur Teilnehmende werden
  getragen; Teilnehmer wird Lehrkraft (sofort nicht getragen, Löschung nach
  Schonfrist); innerhalb der Schonfrist zurück (Lernstand bleibt); Verlust
  einer Lehrrolle als Teilnehmer ändert nichts.
- Vier bestehende Tests (`uc20_enrollment_test`, `subscription_flow_test`)
  riefen `enrolled()` für Personen ohne Rolle auf; sie schreiben jetzt als
  Teilnehmer ein.
- E2E `mod_adele/tests/e2e/tests/participants-by-role.spec.ts`: Lehrkräfte
  nicht in der Liste; Rolle „Student" auf Moodles Teilnehmerseite dazu → in
  der Liste, Startkurs offen; wieder weg → sofort aus der Liste, Startkurs
  zu. Akzeptanzkriterien 1–5 damit geprüft.

**Offen**

- Änderungen an Rollendefinitionen (Capability einer Rolle entzogen) lösen
  kein Ereignis pro Person aus; der nächtliche Abgleich zieht sie nach.
- Knotenkurse (Quellen 2/3): Das Verlassen durch Rollenwechsel zieht den
  Hostzugang sofort nach, das Abonnement erst der nächtliche Abgleich — wie
  schon bei der Ausschreibung aus einem Knotenkurs.
