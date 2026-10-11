# Issue-Entwurf: Kontrastverstoß (axe `color-contrast`) tritt nur auf dem GitHub-Runner auf

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `accessibility`, `ci`, `needs investigation`
**Stand:** 2026-10-07, Session 006 — beobachtet am 2026-10-06 (Protokoll Teil 10)
**GitHub:** noch nicht angelegt (Umfeld: #575)

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

CI: „Playwright smoke" meldet einen Kontrastverstoß, der lokal nicht auftritt — Ursache unbekannt

## Beobachtung

Im Workflow **Playwright smoke** von `local_adele` (Fork, Branch
`development`, Stand `2026100600`) schlägt die Barrierefreiheitsprüfung
`tests/playwright/tests/issue574-575-accessibility.spec.ts` mit **einem**
Verstoß der axe-Regel `color-contrast` fehl. Die übrigen Workflows des Laufs
(Plugin-CI aller drei Plugins, Smoke von `enrol_adele` und `mod_adele`) sind
grün.

Lokal ist derselbe Test grün — einzeln wie in der vollständigen Suite, auch
in der Reihenfolge der CI.

## Was bekannt ist

- Das Bildschirmfoto des CI-Laufs zeigt nichts Auffälliges; der Trace enthält
  nur die bestandenen axe-Prüfungen.
- Welches Element betroffen ist, ließ sich aus dem damaligen Lauf nicht
  ablesen: Der Test meldete nur die Regel.
- Seit `2026100600` meldet der Test bei einem Verstoß Selektor, Markup,
  gemessene Vorder- und Hintergrundfarbe, Kontrastverhältnis, Anforderung und
  Schrift (gegengeprüft mit einem erzwungenen Verstoß).
- Ein erneuter Lauf zur Diagnose war aus der Arbeitsumgebung nicht möglich
  (kein Recht, Workflows zu starten).

## Was nicht bekannt ist

Die Ursache. Denkbar sind unterschiedliche Schriften oder Schriftglättung auf
dem Runner, ein Element, das nur dort in einem Übergangszustand geprüft wird,
oder ein echter Verstoß, den die lokale Umgebung nicht zeigt. **Keine dieser
Möglichkeiten ist belegt.**

## Stand 2026-10-08 (Version `2026100718`)

Entscheidung des Auftraggebers: erst neu laufen lassen, dann über das Issue
entscheiden. Damit ein roter Lauf diesmal alles Nötige enthält, legt der
Test jetzt bei jedem Lauf Belege in `test-results/<Test>/` ab (Teil des
hochgeladenen Artefakts `playwright-report-local_adele-…`):

- `axe-results.json` — Befunde **und** die von axe nicht entscheidbaren
  Fälle („incomplete", beim Kontrast z. B. ein nicht bestimmbarer
  Hintergrund), mit Browser und Fenstergröße des Laufs;
- je Befund `finding-N-<regel>.json` — Selektor, Markup, von axe gemessene
  Farben und Verhältnis, dazu die vom Browser in diesem Moment berechneten
  Werte (Farbe, Hintergrund, Deckkraft, **Schriftfamilie**, Größe, Gewicht);
- je Befund `finding-N-<regel>-element.png` — nur das Element;
- `findings-in-page.png` — die ganze Seite, Befunde magenta umrandet.

Gegenprobe mit einem erzwungenen Verstoß (Text #bbbbbb auf Weiß, nur auf der
Testinstanz eingespielt): alle vier Belege vorhanden, gemessen 1,91:1.

## Nächste Schritte

1. Den Workflow „Playwright smoke" erneut starten (Actions → Run workflow).
2. Tritt der Verstoß wieder auf: Die Fehlermeldung nennt jetzt das Element
   und die Farben — hier eintragen.
3. Danach entscheiden: Farbe korrigieren (echter Verstoß) oder den Test gegen
   den Übergangszustand absichern (Testartefakt).

## Akzeptanzkriterien

1. Die Ursache ist benannt und belegt.
2. „Playwright smoke" ist auf dem Runner dreimal in Folge grün.
