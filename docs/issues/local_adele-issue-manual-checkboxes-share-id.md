# Issue-Entwurf (local_adele): „Knoten manuell freischalten" und „Knoten manuell abschließen" teilen eine ID

**Gefunden:** Jest-Matrix `ManualControls.matrix.spec.js` (Jest-Issue,
Bereich C), Session 006, Teil 58.

## Befund

`vue3/components/restriction/conditions_output/manual_output.vue` und
`vue3/components/completion/conditions_output/manual_output.vue` vergeben
beide `id="<node_id>"` an ihr Kontrollkästchen, und beide Labels zeigen mit
`for="<node_id>"` darauf. Hat ein Knoten beide Bedingungen (Lehrendenansicht
eines Lernenden), stehen zwei Elemente mit derselben ID im Dokument:

- Ein Klick auf das Label „Finish node manually" schaltet das **erste**
  Element mit dieser ID um – das Kästchen „Unlock node manually".
- Assistive Technik und `getByLabel()` (Playwright) finden für beide Labels
  dasselbe Kästchen.
- Doppelte IDs sind ungültiges HTML (WCAG 4.1.1 in älteren Fassungen;
  axe-Regel `duplicate-id`).

## Erwartung

Jedes Kästchen hat eine eigene ID (z. B. `<node_id>_manual_restriction` und
`<node_id>_manual_completion`, wie `MasterConditions.vue` es schon macht);
jedes Label zeigt auf sein eigenes Kästchen.

## Reproduktion

```bash
cd vue3
ADELE_RUN_KNOWN_DEFECTS=1 npx jest tests/unit/components/manual/ManualControls.matrix.spec.js
```

## Akzeptanz

- Eindeutige IDs, Labels mit passendem `for`.
- Der `knownDefect`-Test läuft regulär.
- E2E: `setManualCompletion` / `teacherSets` auf einem Knoten mit beiden
  Bedingungen trifft das richtige Kästchen.
