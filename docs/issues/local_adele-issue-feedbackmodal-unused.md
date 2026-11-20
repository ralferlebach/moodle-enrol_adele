# Issue-Entwurf (local_adele): Der Feedback-Dialog des Abschluss-Editors wird nie geöffnet

> **Stand:** Entwurf (2026-11-19), noch nicht eingereicht. Gefunden beim
> Umsetzen von ralferlebach/moodle-local_adele #18 (eindeutige DOM-IDs).

## Befund (belegt im Quelltext, 2026-11-19)

`vue3/components/modals/FeedbackModal.vue` wird in `CompletionFlow.vue`
eingebunden und ist damit im Abschluss-Editor gemountet. Kein Element öffnet
ihn: Es gibt weder ein `data-target` auf seine ID noch einen Aufruf von
`.modal('show')` (Suche über `components/` und `composables/`). Der Dialog
war außerdem nicht lauffähig: `@input="handleInput"` verwies auf eine nicht
existierende Funktion, die Template-Ref `feedbackContent` trug denselben Namen
wie ein `computed` (Speichern las `innerHTML` eines Strings), und
`aria-labelledby` zeigte auf eine ID, die es nicht gab. Feedback wird heute
direkt in `feedbackNode.vue` bearbeitet.

Im Rahmen von #18 behoben, weil es die Dialog-IDs betrifft (AK2/AK3):
eindeutige IDs, korrekte Beschriftung, Ref umbenannt, nicht vorhandener
Handler entfernt. **Nicht** entschieden: ob der Dialog gebraucht wird.

## Vorschlag

- Entscheiden, ob der Dialog einen Einstieg erhalten oder entfernt werden soll.
- Wenn er bleibt: Öffnen-Knopf mit `aria-haspopup="dialog"`/`aria-controls`,
  Jest-Test für Speichern (bereinigtes HTML landet am richtigen Feedback-Knoten),
  Playwright-Test für Öffnen, Fokus, Escape.
- Wenn er entfällt: Komponente und Strings entfernen, `CompletionFlow.vue`
  ohne den Dialog; Jest-Test „kein Dialog ohne Einstieg“.
