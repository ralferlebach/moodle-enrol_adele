# Issue-Entwurf (local_adele): Knoten mit unbekanntem Status zeigt „Zum Kurs" (Play)

**Gefunden:** Jest-Matrix `CustomNodeEdit.matrix.spec.js` (Jest-Issue,
Bereich A2/L), Session 006, Teil 58.

## Befund

`vue3/components/nodes/CustomNodeEdit.vue` setzt das Symbol in `onMounted`
selbst: Alles, was nicht `closed`, nicht `not_accessible` und nicht
`status_restriction = after` ist, bekommt `fa-play` und den Hinweis
„Go to course"; ein Klick öffnet den Kurs.

Für einen Knoten, dessen Status fehlt (Pfad lädt noch) oder ein
Fehlertext ist (`relation_update::getnodestatus()` liefert z. B. „error:
loop limit exceeded"), heißt das: **Play-Symbol und Kurslink**, während
`data-status` (aus `useNodeStatus`) korrekt `unknown` meldet und der
Accessible Name „unknown" nennt. Der Kommentar in `useNodeStatus.js`
beschreibt eine gemeinsame Ableitung mit dem Symbol – das Symbol nutzt sie
nicht.

## Erwartung

Ein Knoten mit unbekanntem Status zeigt das Schloss und „Not accessible";
Symbol, `data-status` und Accessible Name stammen aus derselben Ableitung
(`useNodeStatus`).

## Reproduktion

```bash
cd vue3
ADELE_RUN_KNOWN_DEFECTS=1 npx jest tests/unit/components/nodes/CustomNodeEdit.matrix.spec.js
```

## Akzeptanz

- Symbol und Hinweis aus `useNodeStatus().status` (plus der bestehenden
  `after`-Regel).
- Die zwei `knownDefect`-Zeilen laufen regulär.
