# Issue-Entwurf (local_adele): Verbinden in Gegenrichtung legt eine Rückwärtskante an (Zyklus im Lernpfad)

> **Stand:** Entwurf (2026-10-18, AK/DoD ergänzt 2026-11-19), noch nicht eingereicht.
> Vor dem Einreichen Duplikatabgleich mit den offenen Issues von
> `ralferlebach/moodle-local_adele` (die Issue-Liste war am 2026-11-19 von hier
> aus nicht abrufbar). Gefunden beim
> Umsetzen von local_adele #603 (E2E `editor-duplicate-edges.spec.ts`).
> **Nicht** behoben – außerhalb von #603.

## Befund (belegt, gemessen im Browser 2026-10-18)

Pfad T01 → T02 im Editor (Kante `dndnode_2dndnode_1`, Quelle `dndnode_1`,
Ziel `dndnode_2`). Zieht man die Verbindung **vom Ziel-Griff von T02 zum
Quell-Griff von T01** (also in Gegenrichtung), zeigt der Graph danach
**zwei** Kanten. `LearningPath.vue::handleConnect()` tauscht Quelle und Ziel
anhand des Knotens, an dem der Zug begann (`store.state.startnode`), und
`addCustomEdge(target, source)` vertauscht nochmals: Es entsteht eine Kante
`dndnode_1dndnode_2` von T02 nach T01 – eine andere Verbindung (Gegenrichtung),
also kein Duplikat im Sinne von #603, aber ein Zyklus T01 → T02 → T01.

## Nicht belegt

Wie sich Zugangsregeln und Eltern-/Kind-Listen (`recalculateParentChild`)
bei einem solchen Zyklus verhalten; ob der Server ihn beim Speichern
annimmt.

## Vorschlag

- Beim Verbinden keine Kante anlegen, die zu einem Zyklus führt
  (insbesondere nicht die Gegenrichtung einer bestehenden Kante); Hinweis an
  die Person.
- Serverseitig beim Speichern Zyklen abweisen oder melden.
- Tests: Gegenrichtung, längerer Zyklus, gültige Verzweigung bleibt möglich.

## Abgrenzung zu #603

#603 (eine Kante je Verbindung) betrachtet die Gegenrichtung absichtlich als
*andere* Verbindung: `normalizeEdges.spec.js` und `path_edges_test.php`
bewahren sie. Die Normalisierung behebt den Zyklus deshalb nicht und soll es
auch nicht – ein Zyklus ist eine Frage der Graphstruktur, nicht der
Kantenidentität.

## Akzeptanzkriterien (Codeeigenschaften)

- **AK1 – direkte Rückkante:** Verbinden in Gegenrichtung einer bestehenden
  Kante (Zug vom Ziel- zum Quell-Griff, gleich welcher Knoten den Zug
  beginnt) legt keine Kante an; Graph und `tree.edges` bleiben unverändert;
  die Person erhält einen verständlichen, lokalisierten Hinweis (Live-Region).
- **AK2 – mehrgliedriger Zyklus:** Eine neue Kante, die einen Zyklus über
  mehrere Knoten schließt (A → B → C, dann C → A), wird ebenso abgelehnt;
  dieselbe Prüfung gilt für Ablegen eines Kurses als Kind, UND-/ODER-
  Bedingungen und die Tastatur-Einfügung (`KeyboardInsertDialog`).
- **AK3 – gültige Strukturen bleiben:** Verzweigungen, Zusammenführungen
  (zwei Eltern), gestapelte Knoten und parallele Wege ohne Zyklus bleiben
  uneingeschränkt möglich.
- **AK4 – serverseitige Ablehnung:** `save_learning_path` /
  `update_learning_path` / `duplicate_learning_path` weisen einen Baum mit
  Zyklus ab (Fehlermeldung, nichts gespeichert); Prüfung auf Basis der Kanten
  *und* der Eltern-/Kind-Listen (`parentCourse`/`childCourse`).
- **AK5 – Datenstrategie für Bestand:** Bereits gespeicherte Pfade (auch
  Personenpfade) mit Zyklus werden gezählt und gemeldet (CLI-Vorschau analog
  `repair_path_edges.php`, Upgrade-Bericht), **nicht** automatisch verändert;
  der Editor zeigt den Zyklus an und verlangt die Auflösung vor dem Speichern.
  Fortschritt von Personen bleibt unberührt.
- **AK6 – Laufzeit:** `node_completion`-Routen, `recalculateParentChild` und
  die Fortschrittsberechnung bleiben bei einem Bestands-Zyklus terminierend
  (keine Endlosschleife), was heute für die Routen-Enumeration gilt (#447),
  für die übrigen Stellen zu belegen ist.

## Definition of Done (nur automatisierte CI)

- **DoD1 – Unit:** Jest für die Zyklusprüfung (direkte Rückkante,
  mehrgliedriger Zyklus, Zusammenführung erlaubt, Stapel erlaubt) und PHPUnit
  für die Serverprüfung und die Bestandsmeldung (Vorschau ändert nichts).
- **DoD2 – API:** Web-Service-Tests: Speichern eines Baums mit Zyklus wird
  abgelehnt; ein Baum ohne Zyklus wird unverändert gespeichert.
- **DoD3 – Playwright:** Editor-E2E: Gegenrichtung ziehen → keine zweite
  Kante, Hinweis sichtbar und vorgelesen; Zyklus über drei Knoten → abgelehnt;
  gültige Verzweigung → angelegt; gespeicherter Bestandspfad mit Zyklus →
  Anzeige, Speichern erst nach Auflösung.
- **DoD4 – CI:** Moodle-Plugin-CI (PostgreSQL und MariaDB), Jest und
  Playwright am selben Commit grün, ohne Pflicht-Skips.
