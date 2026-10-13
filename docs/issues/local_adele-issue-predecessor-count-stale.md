# Issue-Entwurf: Bedingung „Nach Vorgängerknoten" zählt vor dem Speichern die alten Vorgänger

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`
**Stand:** 2026-10-08, Session 006 — gefunden mit Testkette E2 (Klammerung)
**GitHub:** nicht abgelegt — erledigt im Rahmen des E2E-Issues (User-Story-Vervollständigung)

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Zugangsbedingungen eines Knotens: Die Auswahl „n von m Vorgängern" berücksichtigt erst nach Speichern und Neuladen hinzugekommene Vorgänger

## Beobachtung

1. Neuer Pfad: T01 → T02 (Nachfolger), dann T03 auf die seitliche Zone von
   T01 („Alternative node") — T02 hat jetzt zwei Vorgänger.
2. Ohne zu speichern die Zugangsbedingungen von T02 öffnen.

Die Seite nennt oben unter „Parent nodes" **beide** Vorgänger. Die Bedingung
„Nach Vorgängerknoten" darunter zeigt „1 / 1" und bietet nur „1" an — „2 von
2" ist nicht wählbar.

Nach Speichern, Verlassen und erneutem Öffnen des Pfads zeigt dieselbe
Bedingung „/ 2" und bietet 1 und 2 an.

## Ursache (Codelage, zur Beobachtung passend)

`parent_courses.vue` liest die Vorgänger aus `store.state.learningpath`, die
Kopfzeile („Parent nodes") aus dem Pfad, den der Editor gerade bearbeitet.
Der Store-Stand ist der zuletzt geladene.

## Folge

Wer eine UND-Verknüpfung über Knoten baut (paralleler Knoten, dann „2 von
2"), muss zwischendurch speichern und neu öffnen; sonst lässt sich „2 von 2"
nicht einstellen — und wer es nicht merkt, speichert „1 / 1".

## Stand der Tests

`editor-brackets.spec.ts`, bekannter Fehler „the predecessor condition
counts the predecessors drawn, saved or not". Der reguläre Test
„node level: A AND (B OR C)" speichert deshalb vorher und öffnet neu.

## Umsetzung

Umgesetzt mit `2026100721` im Rahmen des E2E-Issues. Die im Entwurf genannte Ursache (Store-Stand) war nur ein Teil: Die eigentliche Ursache war, dass beim Ablegen eines parallelen Knotens die Listen der Nachbarknoten (`parentCourse` des Nachfolgers, `childCourse` des Vorgängers) nicht nachgezogen wurden. Behoben: `linkParallelNode()`; zusätzlich liest `parent_courses.vue` den Pfad, den der Editor gerade hält. Tests: Jest `linkParallelNode.spec`, `parent_courses.spec`; E2E `editor-brackets` regulär.
