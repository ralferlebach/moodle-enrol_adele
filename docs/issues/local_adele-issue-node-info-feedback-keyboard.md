# Issue-Entwurf: i-Symbol und Sprechblase am Knoten sind nicht per Tastatur bedienbar

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `accessibility`
**Stand:** 2026-10-07, Session 006 — zuerst beobachtet mit Kette L3/O1, bestätigt mit Kette F
**GitHub:** local_adele #591 (Umfeld: #575)

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

## Umsetzung (Stand 2026100711)

- `NodeInformation.vue` (i-Symbol) und `UserInformation.vue` (Sprechblase):
  Der Auslöser ist ein `<button type="button">` mit zugänglichem Namen
  („Access and completion criteria: <Kursname>" / „Feedback on your progress:
  <Kursname>", deutsch „Zugangs- und Abschlusskriterien" / „Rückmeldung zum
  eigenen Stand"), `aria-expanded` und `aria-controls`; das Symbol selbst ist
  `aria-hidden`. `Enter` und Leertaste öffnen und schließen, `Escape`
  schließt. Der umgebende Container ist nicht mehr selbst fokussierbar — ein
  Tabstopp statt zwei.
- Zusätzlich, im Issue nicht genannt: `ExpandNodeInformation.vue`, dasselbe
  i-Symbol an den Kurskarten innerhalb eines Stapels — gleich behoben. **Nur
  mit Jest geprüft, nicht im Browser** (Kette F hat keinen Stapel).
- Neue Sprachstrings `a11y_node_information`, `a11y_node_feedback`.
- Die Tasten erreichen die Zeichenfläche nicht (`keydown.stop` für `Enter`
  und Leertaste), damit die Tastaturbedienung der Knoten nicht dazwischenkommt.

**Tests:** Jest `issue591_keyboard_toggles.spec.js` (15 Tests); E2E
`feedback-texts.spec.ts`, „F2 keyboard: …" — im echten Browser: Schaltfläche
über Rolle und Namen gefunden, `Enter` öffnet, `Escape` schließt, Leertaste
öffnet und schließt, Fokus bleibt auf der Schaltfläche. Derselbe Test war vor
der Änderung rot (als bekannter Fehler geführt). Smoke-Suite mit den
axe-Prüfungen: 14 bestanden.

**Nicht umgesetzt:** Screenreader-Abnahme (Akzeptanzkriterium 2) — der
NVDA-Lauf unter Windows steht weiterhin aus.
