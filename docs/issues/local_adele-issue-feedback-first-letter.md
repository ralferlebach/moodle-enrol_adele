# Issue-Entwurf (local_adele): Erster Feedbacktext beginnt nicht mit Großbuchstaben

**Gefunden:** Jest-Matrix `UserInformation.matrix.spec.js` (Jest-Issue
„Systematische Jest-Testschicht", Bereich E), Session 006, Teil 57.

## Befund

`vue3/components/nodes_items/UserFeedbackBlock.vue`, Funktion `capitalize()`:

```js
else if (index == 0) text.charAt(0).toUpperCase() + text.slice(1);
```

Der großgeschriebene Text wird berechnet und verworfen (weder `return` noch
Zuweisung). Der erste Eintrag einer Feedbackliste erscheint deshalb so, wie
er vom Server kommt – z. B. „complete this course." statt „Complete this
course.". Der letzte Eintrag erhält wie vorgesehen den Schlusspunkt.

## Erwartung

Der erste Eintrag beginnt mit einem Großbuchstaben (Absicht des Codes); der
Schlusspunkt am letzten Eintrag bleibt.

## Reproduktion

```bash
cd vue3
ADELE_RUN_KNOWN_DEFECTS=1 npx jest tests/unit/components/nodes_items/UserInformation.matrix.spec.js
```

Der Test „[known defect, draft local_adele-issue-feedback-first-letter] the
first text starts with a capital letter" ist im normalen Lauf übersprungen
(sichtbar als „skipped") und schlägt mit der Variable fehl.

## Akzeptanz

- `capitalize()` gibt für Index 0 den großgeschriebenen Text zurück (bzw. mit
  Punkt, wenn er zugleich der letzte ist).
- Der Test läuft regulär (`it` statt `knownDefect`).
- E2E-Kette F: die gemessenen Feedbacktexte prüfen, ob sie dadurch anders
  beginnen; erwartete Texte ggf. anpassen.
