# local_adele: Ungespeicherte Änderungen gehen beim Verlassen der Seite ohne Warnung verloren

> **Stand:** Entwurf 2026-11-27, aus der Frage des Auftraggebers zum FeedbackModal
> (#18 DoD2). Noch nicht gemeldet; die Entscheidung des Auftraggebers steht aus.
> Kein Defekt eines abgenommenen Kriteriums, sondern eine Lücke in der Bedienung.

## Befund (belegt)

- Die Editoren warnen schon heute: Unter den Knöpfen erscheint die Zeile
  `flowchart_cancel_confirmation` („Nicht gespeicherte Änderungen gehen
  verloren.“) mit „Abbrechen“ und „Trotzdem schließen“. Sie steht in:
  - `ControlsPath.vue` (Lernpfad-Editor);
  - `CompletionControls.vue` und `CompletionFlow.vue` (Abschlusskriterien);
  - `RestrictionControls.vue` und `RestrictionFlow.vue` (Zugangskriterien);
  - `UserControls.vue`;
  - `LearningpathsEdit.vue`.
- Die Warnung erscheint **nur** beim Klick auf die eigenen Knöpfe des Editors
  („Abbrechen“, „Zurück zum Lernpfad“), und auch dann nur, wenn sich der
  Inhalt gegenüber dem gespeicherten Stand geändert hat.
- Verlässt man den Editor anders, gehen die Änderungen ohne Warnung verloren:
  über die Moodle-Navigation, mit dem Zurück-Knopf des Browsers, durch Neuladen
  oder durch Schließen des Tabs. Weder `beforeunload` noch Moodles
  `core_form/changechecker` kommen in local_adele vor (Suche in `vue3`,
  `amd/src`, `classes`, `templates`, PHP; 2026-11-27).
- Das entfernte FeedbackModal war **kein** Anfang dieser Warnung, sondern ein
  Rest der Feedback-Bearbeitung von Januar 2024 (`5f858f2`). Seit `bf98dfe`
  (März 2024) wird Feedback direkt im Feedback-Knoten bearbeitet.

## Vorschlag

**AK:**
- [ ] Hat ein Editor (Lernpfad, Zugangs-, Abschlusskriterien) ungespeicherte
  Änderungen, fragt der Browser beim Verlassen der Seite nach: Navigation,
  Zurück, Neuladen, Tab schließen.
- [ ] Es gibt keine Nachfrage, wenn nichts geändert wurde, nach dem Speichern
  und nach „Trotzdem schließen“.
- [ ] Wie heute „ungespeichert“ zählt, bleibt maßgeblich (Vergleich mit dem
  gespeicherten Stand), damit Warnung im Editor und Warnung beim Verlassen
  übereinstimmen.

**DoD:**
- [ ] Jest: Der Zustand „geändert“ setzt den Handler und hebt ihn wieder auf.
- [ ] Playwright: Änderung → Navigation → `beforeunload`-Dialog; ohne Änderung →
  kein Dialog.
- [ ] Barrierefreiheit: Die Nachfrage des Browsers reicht als Hinweis; die
  Warnzeile im Editor bleibt unverändert.
