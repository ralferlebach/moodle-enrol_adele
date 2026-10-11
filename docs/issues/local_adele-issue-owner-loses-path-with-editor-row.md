# Issue-Entwurf: Eigentümer verliert den eigenen Lernpfad, wenn er von der Bearbeiterliste genommen wird

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `permissions`
**Stand:** 2026-10-07, Session 006 — gefunden mit Testkette R5 (parallele Berechtigungsgründe)
**GitHub:** noch nicht abgelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Eigentum an einem Lernpfad zählt nur zusammen mit einem Eintrag in der Bearbeiterliste — von der Liste genommen, verliert der Eigentümer den Pfad

## Beobachtung

1. Eine Person mit der Rolle „Adele Assistant" legt einen Lernpfad an. Sie
   ist Eigentümerin (Krone) und steht als erste in der Bearbeiterliste.
2. Sie fügt eine zweite Person als Bearbeiter hinzu (die Oberfläche schützt
   nur den **letzten** Bearbeiter) und nimmt sich selbst von der Liste.
3. Danach wird ihr der eigene, noch verborgene Pfad in der Übersicht **nicht
   mehr angezeigt**. Bearbeiten und Löschen sind damit nicht mehr möglich.

In der Datenbank steht die Person weiter als `createdby` des Pfads; in
`local_adele_lp_editors` steht nur noch die zweite Person.

Der Testplan erwartet für diesen Fall (§6 R5, Zeile „Eigentümer +
Kollaborator, Kollaboration entzogen"): **Eigentümerzugriff bleibt.**

## Ursache (Codelage, zur Beobachtung passend)

- `learning_paths::return_learningpaths_owned()` liefert als „eigene" Pfade
  nur solche, bei denen die Person **Ersteller und zugleich Bearbeiter** ist
  (`WHERE lpe.userid = … AND lp.createdby = …`).
- `learning_paths::require_lp_editor_access()` fragt ausschließlich die
  Bearbeiterliste; Speichern, Löschen, Sichtbarkeit und die Bearbeiterliste
  selbst hängen daran.
- `scope_paths_for_user()` blendet für Assistenten verborgene Pfade aus, die
  nicht als „eigene" gelten.

## Entscheidung (Auftraggeber, 2026-10-08): Regel B

**Der Eigentümer steht immer auf der Bearbeiterliste.** Die Liste bleibt die
einzige Quelle für Bearbeitungsrecht.

## Umsetzung (nach Ablage)

- **Dienst `remove_lp_edit_users`:** lehnt das Entfernen des aktuellen
  Eigentümers ab — gleich, wer es versucht (er selbst, ein anderer
  Bearbeiter, ein AdeLe-Manager). Wer den Pfad abgeben will, überträgt
  vorher das Eigentum.
- **Oberfläche (`userSearch.vue`):** Die Karte des Eigentümers bekommt
  keinen Entfernen-Knopf.
- **Eigentumsübertragung:** `ownership::set_owner()` trägt den neuen
  Eigentümer bereits heute in die Liste ein, falls er fehlt (Codelage) — das
  wird mit einem Test festgehalten, nicht geändert.
- **Upgrade-Schritt:** trägt jeden Eigentümer nach, der heute nicht auf der
  Liste seines Pfads steht (solche Pfade kann es geben; die Testkette hat
  einen erzeugt). Ausgenommen: Pfade, deren Eigentümer gelöscht ist (die
  behandelt die bestehende Nachfolgeregel).
- **Tests:** PHPUnit für Dienst und Upgrade-Schritt; der E2E-Test wird
  umgeschrieben: Der Eigentümer kann sich nicht von der Liste nehmen (kein
  Knopf, Dienst lehnt ab), der Pfad bleibt seiner.

## Frage (vor der Entscheidung)

Welche Regel ist gewollt?

- **A:** Der Eigentümer behält seinen Pfad unabhängig von der
  Bearbeiterliste (Erwartung des Testplans). Dann müssten Auflistung und
  Zugriffsprüfung `createdby` für sich gelten lassen.
- **B:** Der Eigentümer steht immer auf der Bearbeiterliste. Dann dürfte er
  sich nicht entfernen lassen (Oberfläche und Dienst
  `remove_lp_edit_users`), solange er Eigentümer ist.

## Stand der Tests

`roles-parallel-permissions.spec.ts`, Test „an owner who is taken off the
list of editors keeps their path" — als bekannter Fehler markiert (läuft nur
mit `ADELE_RUN_KNOWN_DEFECTS=1` und schlägt dann am Schritt „the path is
still the owner's to edit" fehl). Der Test prüft Regel A; bei Entscheidung
für B wird er umgeschrieben.

## Nicht geprüft

- Dasselbe für eine Eigentümerin, die keine Assistentenrolle (mehr) hat.
- Ob der Manager, der danach einziger Bearbeiter ist, das Eigentum
  übernehmen muss, damit der Pfad wieder jemandem „gehört".
