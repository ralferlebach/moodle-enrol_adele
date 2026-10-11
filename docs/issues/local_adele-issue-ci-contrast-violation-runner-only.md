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

## Nächste Schritte

1. Den Workflow „Playwright smoke" erneut starten (Actions → Run workflow).
2. Tritt der Verstoß wieder auf: Die Fehlermeldung nennt jetzt das Element
   und die Farben — hier eintragen.
3. Danach entscheiden: Farbe korrigieren (echter Verstoß) oder den Test gegen
   den Übergangszustand absichern (Testartefakt).

## Akzeptanzkriterien

1. Die Ursache ist benannt und belegt.
2. „Playwright smoke" ist auf dem Runner dreimal in Folge grün.
