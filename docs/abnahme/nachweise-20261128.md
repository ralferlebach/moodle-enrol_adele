# Nachweise zum Nacharbeitsauftrag vom 2026-11-28 (0.8.0, Version `2026112800`)

Grundlage ist „ADELE 0.8.0 – Nacharbeitsauftrag nach Re-Audit“ vom 28.11.2026
(Basis `2026112701`, 87/91, offen #14 DoD2-L4 und #18 DoD2/DoD3/DoD6). Die Release
bleibt `0.8.0`, die Version ist `2026112800`; Abhängigkeiten untereinander stehen auf
`2026112800`. Protokolle liegen in `adele-0.8.0-2026112800-nachweise.zip`, die
Kommentare in `adele-0.8.0-2026112800-kommentare.zip`.

## P0 #18 – Bundle-Gate rot auf `f618e747`: Ursache belegt

**Reproduktion.** Der Commit `f618e747` wurde vollständig exportiert (per `git cat-file`;
`git archive` lässt `vue3/` wegen `export-ignore` aus). Dann lief
`node buildtools/check-bundle.js`. Beide Skripte sind byte-gleich mit der
Arbeitskopie. Ergebnis:

```
::error::amd/src/app-lazy.js was built from other sources (bundle a1083c64e5d2, sources fa4d10625cb2)
```

Das sind genau die Werte des CI-Jobs 140783969665.

**Gegenprobe.** Im selben Export wurde nur `vue3/components/modals/FeedbackModal.vue`
entfernt. Ergebnis:

```
Bundle built from the sources of this commit (a1083c64e5d2); amd/build matches.
```

**Ursache (belegt).** Das Bundle von `2026112701` war richtig gebaut, aus den Quellen
ohne den Dialog. Die Lieferung führte `FeedbackModal.vue` als entfernt. Die ZIP wurde
aber über das Repository gelegt, und ein Entpacken löscht nichts. Die Datei blieb also
getrackt, und `sourcehash.js` hasht jede Datei unter `components`. Das Gate hat damit
richtig angeschlagen.

Laut Auftraggeber (28.11.) ist die Datei inzwischen gelöscht. Auf dem gelesenen
`origin/development` (`f618e747`) war sie zum Prüfzeitpunkt noch vorhanden.

**Damit es nicht wieder passiert:** `package.sh` prüft jetzt die Lieferung so, wie
das Repository sie bekommt (`pruefkette/overlay_check.sh`):
1. `origin/development` wird vollständig exportiert, die neue ZIP darübergelegt.
2. Die Dateien werden gelistet, die im Repository zu löschen sind.
3. Das Bundle-Gate läuft dort einmal ohne und einmal mit diesen Löschungen. Mit den
   Löschungen muss es grün sein, sonst gibt es kein Paket.

Probelauf mit `2026112701` über `f618e747`:
- zu löschen: `vue3/components/modals/FeedbackModal.vue`;
- ohne Löschen rot (`a1083c64e5d2`/`fa4d10625cb2`, wie die CI);
- mit Löschen grün.

Das Gate selbst ist unverändert: keine Abschwächung, kein Hash überschrieben, kein
Ausschluss.

**Stand `2026112800`.** `FeedbackModal.vue` ist nicht in der ZIP. Keine produktive
Referenz auf `FeedbackModal`, `feedbackModal`, `DIALOGS.feedback`, `state.feedback`
oder `modals_edit_feedback`. Bundle-Gate in der Arbeitskopie grün (`a1083c64e5d2`).
Die Überlagerungsprobe gegen den aktuellen `development` steht im Nachweispaket
(`gate/ueberlagerung-2026112800.txt`).

## P1 #14 DoD2-L4 – neuer Test L4 (mod `groups-live.spec.ts`)

Fixture ADELE-E2E-L17 (G16, getrennte Gruppen, Fenster 5 min). Testexklusive
Vorbereitung: Dirk wird zusätzlich im Kurs von Knoten 2 (T02) eingeschrieben.
`resetFixture()` nimmt diese Einschreibung vor jedem Test zurück. Die Ereignisse
sind echt: Dirk öffnet T01, dann T02, jeweils im Browser.

1. **Beide Knoten aktiv:** An Knoten 1 und 2 steht je genau ein aktiver Marker Dirks,
   an Knoten 3 keiner. Es gibt keinen inaktiven Marker Dirks.
   - Name in `.adele-marker-name`;
   - Zustand im `aria-label` („Dirk Blau, active now / gerade aktiv“) und im
     `sr-only`-Text;
   - Avatar `img.adele-marker-avatar` mit `alt=""`, die Quelle ist ein Moodle-Bild und
     tatsächlich geladen (`naturalWidth > 0`);
   - Zähler „1 aktiv“ an beiden Knoten.
2. **Nie beobachtet:** Anna, Bert, Cora und Nora haben keinen Marker. Auf der Seite
   gibt es genau 2 Marker, beide von Dirk.
3. **Kontrollierter Ablauf.** Die Testuhr (`seenAgo()`) setzt die gespeicherten
   Aktivitätszeiten zurück: T01 vor 20 min, T02 vor 10 min, beide außerhalb des
   Fensters, T02 eindeutig zuletzt. Server, Uhr, Fenster und Regeln bleiben
   unverändert. Das Polling (30 s) zeigt ohne Neuladen:
   - kein aktiver Marker mehr;
   - genau ein Marker, inaktiv („zuletzt hier gesehen“), nur an Knoten 2, nicht
     mehrdeutig;
   - Zähler an Knoten 1 und 2 „0 aktiv“;
   - axe ohne schwere Verstöße.

Es gibt kein Sleep über das Fenster und keine gemockte API-Antwort.

Die bestehenden L1 (Polling, Zähler, Namensliste, Zentrierung, Escape), L2
(abgeschaltet) und L3 (Rückweg mit Gruppe, Ausschnitt, Scroll, Fokus) sind
unverändert.

Lokal:
- L4 einzeln grün;
- `groups-live.spec.ts` ganz 11/11 grün;
- die Testeinschreibung bleibt nicht zurück.

## P1 #18 DoD2/DoD3

**DoD2** (unverändert vorhanden, jetzt auf dem Zielstand zu belegen):
- A18 (`dom-ids.spec.ts`), Node- und Tutorial-Dialog, prüft:
  - Name;
  - Fokus im Dialog;
  - Label-Klick setzt den Fokus auf das eigene Feld;
  - Escape und Fokus-Rückgabe;
  - keine doppelte ID und keine gebrochene Referenz.

  Das gilt in Chromium und Firefox; das Firefox-Projekt von local läuft auf
  `@story:A18`.
- E2 (`editor-combinations.spec.ts`), Kursdialog, prüft dasselbe und den Label-Klick
  auf `…-courseModal-fullname`.
- E1 (`editor-reference-path.spec.ts`) ist die Feedback-Regression im Zugangs- und im
  Abschlusseditor, ohne `feedbackModal` im DOM.

**DoD3, neuer Test D1** (mod `groups-live.spec.ts`, `@story:A18`, damit auch im
Firefox-Projekt von mod). Er braucht keine Fixture und keinen künstlichen Öffner: Die
Personenliste an den Zählern eines Knotens ist ein Dialog (`NodeLive.vue`,
`role="dialog"`). Jeder Knoten hat seinen eigenen, und das Öffnen schließt keinen
anderen. D1 öffnet die Listen von Knoten 1 (3 Personen) und Knoten 2 (2 Personen)
gleichzeitig und prüft:
- 2 Dialoge desselben Typs, IDs auf der ganzen Seite eindeutig;
- jeder Dialog durch seinen eigenen Titel benannt und von genau einem Öffner
  (`aria-controls`) genannt;
- alle Referenzen bleiben in der eigenen Instanz;
- jede Instanz zeigt ihren Knoten, Bert nur in Knoten 1;
- axe ohne schwere Verstöße;
- Escape in der zweiten schließt nur sie, der Fokus geht an ihren Zähler, die erste
  bleibt offen mit 3 Einträgen; danach dasselbe für die erste.

Lokal grün in Chromium und Firefox. `stories.json` (mod) führt A18 unter
`alsoCovered`.

Der bestehende A18-Mehrfacheinbettungstest (drei Anwendungen, Boost mit sichtbarer
MiniMap und Classic, kein `dropdownMenuButton`) bleibt.

## Prüfstand

Siehe `README.md`, Zeile „Nacharbeitsauftrag 2026-11-28“.
