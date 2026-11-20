# Issue-Entwurf (local_adele): Dialoge des Editors wiederholen DOM-IDs

> **Stand:** eingereicht als ralferlebach/moodle-local_adele #18; umgesetzt in 2026111900
> (Protokoll `docs/abnahme/local-18.md`). Ursprünglicher Entwurf (2026-10-18), gefunden beim
> Umsetzen von local_adele #603 (DOM-Prüfung im Editor). Damals nicht behoben –
> außerhalb von #603 (das die Kanten des Graphen betrifft).

## Befund (belegt, gemessen im Browser 2026-10-18)

Im Lernpfad-Editor (`/local/adele/index.php`, Pfad geöffnet) kommen diese
IDs mehrfach vor: `dropdownMenuButton`, `exampleModalLabel`, `fullname`,
`description`. Herkunft im Quelltext: `components/modals/ModalNode.vue` und
`components/modals/ModalCourse.vue` setzen beide `id="exampleModalLabel"`,
`id="fullname"`, `id="description"`; `FeedbackModal.vue` ebenfalls
`id="exampleModalLabel"`. `dropdownMenuButton` setzt `lib.php` (Zeile 66, Navigationsmenü von
local_adele); dass es zweimal auf der Seite steht, ist gemessen – ob die
zweite Stelle aus dem Theme oder einem zweiten Aufruf kommt, ist nicht
belegt.

## Folgen

`<label for>`/`aria-labelledby` können auf das Feld des anderen Dialogs
zeigen; ein Screenreader liest dann den falschen Namen, ein Klick auf das
Label setzt den Fokus in den falschen Dialog.

## Vorschlag

Eindeutige IDs je Dialog (Präfix je Komponente), Labels und
`aria-labelledby` nachziehen; E2E: im Editor keine DOM-ID doppelt (heute
prüft `editor-duplicate-edges.spec.ts` nur den Graphen).
