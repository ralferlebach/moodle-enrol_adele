# Issue-Entwurf: i-Symbol und Sprechblase am Knoten sind nicht per Tastatur bedienbar

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `accessibility`
**Stand:** 2026-10-07, Session 006 — zuerst beobachtet mit Kette L3/O1, bestätigt mit Kette F
**GitHub:** noch nicht angelegt (Umfeld: #575)

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Lernpfad-Ansicht: Informationskarte (i-Symbol) und Feedback (Sprechblase) eines Knotens öffnen nur per Mausklick

## Beobachtung

Beide Elemente nehmen den Tastaturfokus an (`tabindex="0"`), aber keine Taste
öffnet sie. Die Zugangs- und Abschlusskriterien und die Rückmeldung zum
eigenen Stand sind damit ohne Maus nicht erreichbar.

## Reproduktion

Als Lernende die Lernpfad-Aktivität öffnen, mit `Tab` zum i-Symbol bzw. zur
Sprechblase eines Knotens gehen, `Enter` oder Leertaste drücken: nichts
öffnet sich.

Automatisiert: `tests/e2e/tests/feedback-texts.spec.ts`, Test „keyboard: the
information card and the feedback area open without a mouse" (derzeit als
bekannter Fehler übersprungen; `ADELE_RUN_KNOWN_DEFECTS=1` führt ihn aus).

## Ursache

- `nodes_items/NodeInformation.vue`: Der Container ist fokussierbar,
  `@focus` hebt nur die z-Ebene an; geöffnet wird ausschließlich über
  `@click` auf dem inneren `.information`-Element (`toggleCard`).
- `nodes_items/UserInformation.vue`: gleiches Muster, `@click` auf
  `.toggle-button` (`toggleFeedbackarea`).

Beide Auslöser sind `<div>`-Elemente ohne Rolle, Namen und Zustand.

## Vorschlag

Beide Auslöser als `<button>` mit zugänglichem Namen und `aria-expanded`;
`Enter`/Leertaste öffnen und schließen, `Escape` schließt. Die geöffnete
Karte dem Auslöser zuordnen (`aria-controls`).

## Akzeptanzkriterien

1. Der genannte E2E-Test läuft als regulärer Test grün.
2. Screenreader sagen Namen und Zustand (auf-/zugeklappt) beider Auslöser an.
3. Die axe-Prüfung der Lernpfad-Ansicht bleibt ohne Befund.
