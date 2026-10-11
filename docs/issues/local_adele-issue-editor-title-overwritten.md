# Issue-Entwurf: Editor überschreibt eingetippten Pfadnamen beim zweiten Laden

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`, `data loss`
**Stand:** 2026-10-07, Session 006 — gefunden über zufällig scheiternde Rollen-Ketten
**GitHub:** local_adele #585

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Lernpfad bearbeiten: Ein verspätetes zweites Laden überschreibt den eingetippten Namen — gespeichert wird stillschweigend der alte

## Beobachtung

Öffnet man einen bestehenden Lernpfad aus der Übersicht zum Bearbeiten und
tippt sofort einen neuen Namen ein, springt das Feld bei langsamer Verbindung
kurz darauf auf den alten Namen zurück. Wer das nicht bemerkt und speichert,
speichert den alten Namen — ohne Fehlermeldung (der Server antwortet
`error: false`).

## Reproduktion

1. Übersicht `/local/adele/index.php`, bei einem Pfad „Bearbeiten".
2. Sofort einen neuen Titel eingeben.
3. Nach ca. 1–3 s (abhängig von der Verbindung) steht wieder der alte Titel
   im Feld.

Deterministisch nachgestellt, indem die zweite Antwort von
`local_adele_get_learningpath` um 2,5 s verzögert wird
(`tests/e2e/tests/editor-title-overwrite.spec.ts`): erwartet „Neuer Name",
erhalten der alte Name.

## Ursache

Der Pfad wird beim Öffnen **zweimal** geladen:

1. `LearningPathList.vue`, `editLearningpath()`: `store.dispatch('fetchLearningpath')`,
   danach `router.push({ name: 'learningpath-edit' })`.
2. `LearningpathsEdit.vue`, `showForm()`: erneut `store.dispatch('fetchLearningpath')`.

Das Titelfeld (`charthelper/textInputs.vue`) übernimmt jede neue Ladung aus
`store.state.learningpath.name` in die Eingabe. Es ist nach der ersten Ladung
bereits bedienbar; kommt die zweite später, überschreibt sie die Eingabe.

## Vorschlag

- Den Ladeaufruf in `editLearningpath()` (und analog `viewLearningpath()`)
  entfernen — das Ziel lädt ohnehin selbst; **oder**
- `showForm()` lädt nicht erneut, wenn `store.state.learningpath.id` bereits
  der angeforderte Pfad ist; **zusätzlich**
- das Formular erst nach abgeschlossener Ladung bedienbar machen.

## Akzeptanzkriterien

1. Der E2E-Test `editor-title-overwrite.spec.ts` läuft als regulärer Test
   (statt `test.fixme`) grün.
2. Beim Öffnen des Editors wird `local_adele_get_learningpath` genau einmal
   aufgerufen.

## Umsetzung (Stand 2026100705)

- `LearningPathList.vue`: `editLearningpath()` und `viewLearningpath()` laden
  den Pfad nicht mehr selbst; das Ziel der Route lädt ihn.
- `LearningpathsEdit.vue`: `showForm()` setzt `pathLoaded` erst, wenn **diese**
  Anfrage beantwortet ist (eine laufende Nummer verwirft überholte Antworten);
  Titel, Beschreibung und Zeichenfläche — im Editor wie in der Nur-Lese-Ansicht
  — erscheinen erst dann. Vorher zeigte das Formular, was noch vom zuletzt
  geöffneten Pfad im Store lag.
- `amd/build` neu gebaut. Der Build ist reproduzierbar: Ein Bau des
  unveränderten Stands ergab die ausgelieferte Datei Byte für Byte.
- Jest: `LearningPathList.spec.js` — „Bearbeiten" setzt die Pfad-ID, wechselt
  die Route und fordert den Pfad nicht an.
- E2E `editor-title-overwrite.spec.ts`, jetzt regulär, zwei Tests:
  1. Beim Öffnen des Editors geht genau eine Anfrage
     `local_adele_get_learningpath` hinaus (Akzeptanzkriterium 2).
  2. Die eine Antwort wird zurückgehalten: Solange gibt es kein Titelfeld;
     danach bleibt der eingetippte Titel stehen, und es folgt keine zweite
     Anfrage.
  Akzeptanzkriterium 1 wörtlich („der Test läuft unverändert grün") ist nicht
  erfüllbar: Der alte Test verzögerte die **zweite** Antwort, die es nicht
  mehr gibt. Der neue prüft dieselbe Zusage am verbliebenen Ladevorgang.
