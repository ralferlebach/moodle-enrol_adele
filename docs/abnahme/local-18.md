### Abnahmeprotokoll – ralferlebach/moodle-local_adele #18 (doppelte DOM-IDs in Dialogen und Navigation)
- Ziel-Repositories / Commit-SHA: local_adele (+ mod_adele E2E) – Lieferung `2026111900`; Commit-SHA: **offen**
- **Ursache (belegt):** feste IDs in Komponenten, die gleichzeitig gemountet sind – `ModalNode.vue` und `ModalCourse.vue` (`exampleModalLabel`, `fullname`, `description`), `FeedbackModal.vue` (`exampleModalLabel`, dazu `aria-labelledby` auf die nicht vorhandene ID `feedbackModalLabel`), `HelpingSlider.vue` (`aria-labelledby` auf die nicht vorhandene ID `helpingSliderModal`), `LearningModule.vue` (`color`/`presetColors` doppelt in Hinzufügen- und Bearbeiten-Formular), Steuerleisten (`cancel-learning-path` bis zu dreifach). Auf einer Kursseite mit mehreren ADELE-Aktivitäten zusätzlich: Mount-ID `local-adele-app-<Pfad>`, alle knotenbezogenen IDs, Kanten-`path`-IDs und VueFlow-Marker `arrowclosed` (gemessen: drei Einbettungen → `arrowclosed` ×3, Kanten-IDs ×2/×3).
- **AK4 `dropdownMenuButton` – zweite Quelle ermittelt (belegt):** die Navbar-Schaltfläche von **local_catquiz** setzte dieselbe ID (`lib.php`, entfernt in local_catquiz `2026101701`, Commit `8a62ce30` „Issue #30: no generic id“). local_adele setzt die ID jetzt ebenfalls nicht mehr (und kein `aria-haspopup`, es ist ein Link): `lib.php::local_adele_render_navbar_output()`; PHPUnit `navbar_output_test` (2).
- **AK1/AK2/AK7 – Umsetzung:** Jede Anwendung erhält beim Mounten ein eigenes Präfix (`adele1`, `adele2`, …; `main.js`, `store.state.domPrefix`, `app.config.idPrefix`), `composables/domIds.js::appDomId()`/`appDomSelector()`. Dialoge `nodeModal`, `courseModal`, `feedbackModal`, `helpingSlider` und ihre Felder/Titel, alle knotenbezogenen IDs (Infokarten, Stapel, Kurszugriff, Bestätigungs-/Anfragedialoge, Bedingungs-Editoren, Feedback-Knoten), Teilnehmerliste, Seitenleisten-Suche, Tastatur-Einfügedialog, Formularfelder des Pfades: präfixiert. Mount-ID mit `{{uniqid}}` (`templates/initview.mustache`). Kanten: `edges/ScopedEdge.vue` (Bezier/Smoothstep, nur DOM-ID präfixiert, Kanten-ID im Graphen unverändert), `TransitionEdge.vue`, `ConditionLine.vue`; Marker als Objekt → VueFlow benennt sie je Instanz (`scopeMarkers()` in `loadFlowChart.ts`, `UserPath.vue`, `PathView.vue`; neue Kanten in `addCustomEdge.ts`, `outerGraphDisplay.ts`). Die doppelten Steuer-IDs (`save-learning-path`, `cancel-learning-path`, `confim-cancel-learning-path`) entfallen; Tests finden die Knöpfe über `data-testid` (`learningpath-save`, `learningpath-cancel-confirm`).
- **AK3 – Referenzen:** `data-target`, `aria-controls`, `aria-haspopup="dialog"` an allen Öffnern (`CustomNode.vue`, `OrCourses.vue`, `LearningPathList.vue`), `getElementById('helpingSlider')` durch Template-Ref ersetzt, `for`/`aria-labelledby`/`aria-describedby`/`list` nachgezogen. Tests mit `#adele-userlist-*` nutzen `data-testid` (E2E, Behat), `[id^=…]`-Selektoren auf `[id*=…]`.
- **AK5 – Tastatur/Screenreader/Fokus:** Dialoge `role="dialog"`, benannt durch ihren eigenen Titel, Schließen-Knopf lokalisiert benannt; Feedback-Editor `role="textbox"` mit Namen; Fokus wandert in den Dialog, Escape schließt, Fokus zurück zum Öffner (E2E).
- **AK6 – keine Regression:** Jest 868/868 (alte Spezifikationen nur auf neue IDs umgestellt), E2E-Gesamtläufe siehe Prüfstand. Nebenbefund FeedbackModal (kein Einstieg, nicht vorhandener Handler, Ref-Namenskonflikt) behoben, Entscheidung über den Dialog als Issue-Entwurf `local_adele-issue-feedbackmodal-unused.md`.
- **DoD1** E2E `local/tests/e2e/tests/dom-ids.spec.ts` (Story A18): ganze Seite (Moodle, VueFlow, ADELE) ohne doppelte ID in Übersicht, Editor mit allen Dialogen und Kursseite mit drei Anwendungen; jede Referenz zeigt auf genau ein Element derselben Anwendung. `editor-duplicate-edges.spec.ts` prüft jetzt ebenfalls die ganze Seite.
- **DoD2** dieselbe Spezifikation: Knoten- und Tutorial-Dialog per Tastatur geöffnet – Name, Fokus, Labels, Escape, Fokus-Rückgabe; axe (WCAG 2.1 A/AA) am offenen Dialog ohne ernste Verstöße.
- **DoD3** Mehrfachinstanz: Fixture `E2EM18` (drei Aktivitäten, derselbe Pfad zweimal); Teilnehmerliste einer Anwendung klappt nur ihre eigene Tabelle.
- **DoD4** Jest `issue18_dom_ids.spec.js` (15): zwei Anwendungen nebeneinander ohne doppelte ID, Referenzen je Anwendung, Dialognamen, Öffner, Feldtrennung, Lernmodul-Formulare, ScopedEdge, Marker.
- **DoD5** Chromium und Firefox lokal grün (A18 3/3 je Browser, zweimal); Firefox-Projekt in `local/tests/e2e/playwright.config.ts`, Workflow installiert Firefox bei Bedarf (local und enrol wie mod).
- **DoD6** Build, Jest, Playwright lokal grün; Moodle-CI am Commit **offen**.
- **Nachtrag `2026112000` (CI 2026-11-20, belegt):** Behat `adele_basic_usage.feature` fand `goalsubjectplaceholder` nicht mehr – Behat sucht über id, name oder Label, die id trägt jetzt das Präfix, `name` war der übersetzte Platzhaltertext. Jetzt `name="goalsubjectplaceholder"`. Wächter: Jest `behat_locators.spec.js` (jeder Feld-Locator der Features als `name` oder Label erreichbar), E2E A18 prüft beide Namen im gerenderten Editor. Behat selbst hier nicht ausführbar. **Nachtrag `2026112100` (CI 2026-11-21, belegt):** `adele_basic_usage.feature:168` prüfte per CSS `#restriction-condition_1-duration` (präfixierte id) → jetzt `[name=…]`; der Wächter prüft zusätzlich alle CSS-/XPath-id-Selektoren und `the field …`-Schritte.
- **Entscheidung: abnahmefähig** – codeseitig und lokal vollständig; DoD = CI am Commit, steht aus.
- **Nachtrag `2026112102` (Re-Audit 2026-11-21):** AK2 – derselbe Dialog zweimal in **einer** Anwendung bekommt je Instanz eine eigene Nummer (`dialogInstanceId()`; erste Instanz = ID der Öffner), Jest „the same dialog twice in one application“ (3, Gegenprobe rot). AK4/DoD3 – E2E A18: kein `[id="dropdownMenuButton"]` auf der Seite, Navigationslink ohne ID (in CI mit local_catquiz als zweiter Quelle). DoD2 – Kursdialog eines Stapels im Editor per Tastatur, Titel/Labels/Escape/Fokus zurück (E2E E2); der Feedback-Dialog ist in der Oberfläche nicht erreichbar (Issue-Entwurf `local_adele-issue-feedbackmodal-unused.md`), nur Jest. DoD6 – Build-Gate im Jest-Workflow (`buildtools/check-bundle.js`: Bundle aus den Quellen dieses Commits, `amd/build` = Bundle; Gegenprobe Exit 1); Lint-Gate im Jest-Workflow (`buildtools/lint-gate.js`, ESLint `eslint:recommended` + `plugin:vue/essential`, TS-Parser für `<script lang="ts">`) als Sperrklinke: die 92 Befunde, die der Code beim Einführen hatte (79 `no-unused-vars`, 6 `vue/no-mutating-props`, …), stehen je Datei und Regel in `lint-baseline.json`; jeder Befund darüber hinaus lässt den Lauf scheitern (Gegenprobe: eine neue ungenutzte Variable → Exit 1). Die Altbefunde selbst sind **nicht** behoben. **Entscheidung: abnahmefähig** – vorbehaltlich CI am Commit; Abbau der 92 Altbefunde ist eigene Arbeit.
- **Nachtrag `2026112201` (Jest-Lauf 45034960566, belegt):** DoD6 – das Bundle-Gate war rot, weil es nach `npm install --save-dev @babel/plugin-syntax-dynamic-import` lief, das die gehashte `package.json` ändert; Bundle und Quellen des Commits waren richtig (nachgestellt: vorher `e93b6e039159`, nachher `e5973012f684`). Das Gate läuft jetzt vor der Installation, Jest/Lint/Build auch bei rotem Gate. Lokal in der Schrittfolge von `jest.yml` grün, Gegenprobe rot. Entscheidung unverändert **abnahmefähig**; DoD6 = grüner Jest-Lauf am Commit, steht aus.
- **Nachtrag `2026112300` (Re-Audit 2026-11-22):**
  - **Neuer Befund AK7 (belegt), behoben:** Die MiniMap setzte die Knoten-IDs ein zweites Mal. `dndnode_1` bis `dndnode_3` standen je dreimal auf der Seite, sobald die MiniMap erscheint (Fluss breiter als 768 px, z. B. im Classic-Theme). Behoben mit `MiniMapNodeShape.vue`; Jest und E2E-Schritt im Classic-Theme. Siehe `issues/local_adele-issue-minimap-node-ids.md`.
  - **AK4:** E2E A18 im Classic-Theme: keine doppelte ID, kein `dropdownMenuButton`, der Navigationslink funktioniert. Die übrigen Fundstellen im Moodle-Baum sind eingeordnet (`nachweise-20261123.md`).
  - **AK5:** Ein Klick auf das Label fokussiert das Feld, geprüft im Knoten- und im Kursdialog.
  - **Lint:** Vue-3-Regelsatz statt Vue 2. Baseline 92 → 88, Einordnung in `nachweise-20261123.md`, Issue-Entwurf `issues/local_adele-issue-eslint-vue2-rules.md`.
  - **DoD2 Feedback-Dialog:** weiter ohne Einstieg (Issue-Entwurf).
  - **Entscheidung: abnahmefähig**, außer DoD2 zum Feedback-Dialog: Dort ist zu entscheiden, ob der Dialog bleibt.
- **Nachtrag `2026112600` (Re-Audit 2026-11-26):**
  - Neu in E2E A18: Boost mit sichtbarer MiniMap. Die Inhaltsbreite wird
    angehoben; gemessen zeigt Boost die MiniMap sonst nicht. Im Classic-Schritt
    wird die MiniMap jetzt ausdrücklich gefordert.
  - local_catquiz `main` hat `dropdownMenuButton` noch, legacy nicht
    (belegt). Entscheidungsvorlage: `issues/local_catquiz-main-dropdownmenubutton.md`.
  - Lint-Baseline 88 → 85; die 6 Prop-Mutationen sind einzeln bewertet,
    das Laufzeitrisiko ist gering.
  - **Entscheidung: abnahmefähig, außer** DoD2 (Feedback-Dialog) und der
    catquiz-Branchfrage – beides bei der Auftraggeberseite.

- **Nachtrag `2026112701` (Re-Audit 2026-11-27 abends, P1 FeedbackModal, Option A):**
  - **Entscheidung des Auftraggebers (2026-11-27, 21:28):** „Ja, bitte entfernen.“
    Vorher hatte er Bildschirmfotos und die Herkunft erhalten: ein Rest der
    Feedback-Bearbeitung von 01/2024 (`5f858f2`), seit 03/2024 (`bf98dfe`) ohne
    Öffner. Der Dialog war kein Anfang einer Warnung vor ungespeicherten
    Änderungen.
  - **Entfernt:**
    - `FeedbackModal.vue` und seine Einbindung in `CompletionFlow.vue`;
    - `DIALOGS.feedback` (`domIds.js`, `.d.ts`);
    - `store.state.feedback`;
    - Sprachstring `modals_edit_feedback` (de/en).
  - **DoD2, transparent angepasst** (Vorschlag für den Issue-Text): „Node-,
    Kurs- und Tutorial-Dialog per Playwright: Name, Fokus, Labels, Escape,
    Fokus-Rückgabe. Der Feedback-Dialog entfällt; er wurde mit Freigabe des
    Auftraggebers vom 27.11.2026 entfernt (0.8.0, `2026112701`). Die
    Feedback-Bearbeitung im Feedback-Knoten ist regressionsgeprüft.“ Belege:
    - Node- und Tutorial-Dialog: E2E A18 (`dom-ids.spec.ts`);
    - Kursdialog: E2E E2 (`editor-combinations.spec.ts`);
    - Feedback-Knoten im Zugangs- und im Abschlusseditor: E2E E1
      (`editor-reference-path.spec.ts`, neu: Abschlusseditor, gespeicherter
      Wert, neue Sitzung, kein `feedbackModal` auf der Seite).
  - **DoD3** (gleichzeitige Dialoginstanzen, mehrere Einbettungen):
    - Jest „the same dialog twice in one application“;
    - E2E A18 „three ADELE applications on one course page“ (keine ID doppelt,
      jede Referenz in ihrer Anwendung) in Boost mit sichtbarer MiniMap und in
      Classic.
  - Neuer Issue-Entwurf zur fehlenden Warnung beim Verlassen der Seite:
    `issues/local_adele-issue-unsaved-changes-on-leave.md`.
