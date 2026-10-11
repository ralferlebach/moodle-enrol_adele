# Issue-Entwurf: Vorgänger „davor" eingefügt — die Vorgänger-Bedingung des Knotens kennt ihn nicht

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`, `editor`
**Stand:** 2026-10-08, Session 006 — gefunden mit Testkette E2 (Klammerung)
**GitHub:** noch nicht abgelegt

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Kurs „vor" einem Knoten eingefügt: Der Knoten bekommt den neuen Vorgänger, seine Bedingung „Nach Vorgängerknoten" aber nicht

## Beobachtung

1. Pfad T01 → T02, gespeichert.
2. T03 über den Tastaturdialog „Kurs einfügen" mit der Beziehung „vor"
   T02 eingefügt (seit #595 der Weg für einen zweiten Vorgänger).
3. Gespeichert, ohne die Zugangsbedingungen von T02 zu öffnen.

Gespeichert ist danach: T02 hat die Vorgänger T01 **und** T03
(`parentCourse`), seine Bedingung „Nach Vorgängerknoten" nennt aber nur T01
(`courses_id: [dndnode_1]`, `min_courses: 1`). T03 zählt für den Zugang zu
T02 nicht — obwohl der Editor die Kante T03 → T02 zeichnet.

Öffnet man die Zugangsbedingungen von T02 und speichert sie, setzt der
Bedingungs-Editor alle Vorgänger ein; danach stimmt es.

## Einordnung

Dieselbe Lücke hat #584 für die seitliche Zone („Alternative node")
geschlossen: Dort nimmt jeder geteilte Nachfolger den neuen Knoten in seine
Bedingung auf. Für „vor" gibt `addAutoRestrictions()` eine vorhandene
Bedingung nach Codelage unverändert zurück (im Entwurf zu #584 schon
notiert).

## Vorschlag

Wie #584: Beim Einfügen „vor" einen Knoten, der bereits eine Bedingung
„Nach Vorgängerknoten" hat, den neuen Knoten dort ergänzen; `min_courses`
bleibt (Voreinstellung 1 = einer genügt).

## Stand der Tests

`editor-brackets.spec.ts`, bekannter Fehler „a predecessor placed before a
node counts in its criterion without opening the criteria" (läuft mit
`ADELE_RUN_KNOWN_DEFECTS=1` und schlägt dann genau hier fehl).
