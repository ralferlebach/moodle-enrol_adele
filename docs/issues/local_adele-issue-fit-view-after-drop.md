# Issue-Entwurf: Ansicht nach dem Ablegen eines Kurses nicht neu eingepasst

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `enhancement`, `editor`
**Stand:** 2026-10-08, Session 006 — aus local_adele #580 (Punkt 2) ausgegliedert
**GitHub:** noch nicht abgelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Lernpfad-Editor: Nach dem Ablegen eines Kurses liegt der neue Knoten teils außerhalb des sichtbaren Bereichs

## Beobachtung

Wird ein Kurs als Nachfolger abgelegt, entsteht der neue Knoten unterhalb des
bisherigen. Die Ansicht folgt nicht: Der neue Knoten (und erst recht die
Ablagezonen, die beim nächsten Ziehen um ihn herum erscheinen) liegt nach
einigen Schritten teilweise unterhalb der sichtbaren Zeichenfläche. Wer
weiterbauen will, muss erst die Schaltfläche „Ansicht einpassen" drücken oder
herauszoomen.

Beobachtet beim Aufbau der E2E-Kette E1 (T01 → T02 → T03). Die Testhilfe
`fitCanvas()` drückt deshalb vor jedem Ablegen „Ansicht einpassen" und zoomt
zusätzlich heraus.

## Codelage

Eingepasst wird beim Öffnen des Editors (`fitView` nach dem Laden) und bei
Größenänderung der Fläche (#480), nicht nach dem Hinzufügen eines Knotens
(`LearningPath.vue`).

## Vorschlag

Nach jedem Ablegen, das einen Knoten hinzufügt, die Ansicht einpassen — wie
die Schaltfläche, mit derselben Animation. Alternative: nur dann, wenn der
neue Knoten nicht vollständig sichtbar ist (stört weniger, wenn jemand
bewusst hineingezoomt hat).

## Zu entscheiden

Immer einpassen oder nur, wenn der neue Knoten nicht ganz sichtbar ist?

## Akzeptanzkriterien

1. Nach dem Ablegen ist der neue Knoten vollständig sichtbar.
2. E2E: Kette T01 → T02 → T03 → T04 ohne Druck auf „Ansicht einpassen";
   nach jedem Ablegen liegt der neue Knoten ganz in der Zeichenfläche.
