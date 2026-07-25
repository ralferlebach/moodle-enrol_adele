# [IMPROVEMENT] Release-Pakete enthalten Git-Historie und Windows-Metadaten (G.7)

## Problem

Ein zur Analyse hochgeladenes `local_adele`-Paket enthielt ein
vollständiges `.git`-Verzeichnis (54 MB) und 444
`Zone.Identifier`-Dateien (Windows-Download-Metadaten). Entpackte Größe
rund 75 MB statt der tatsächlichen Plugin-Größe.

## Ursache

Das Paket war offensichtlich ein roher Ordner-Export (z. B. „Ordner als
ZIP komprimieren" unter Windows) eines lokalen Git-Checkouts, kein
`git archive`-Build. `.gitattributes` enthält bereits korrekte
`export-ignore`-Regeln für `.git/`, `.github/`, `docs/`, `tools/` u. a. —
diese greifen aber nur bei `git archive`, nicht bei einem manuellen
Ordner-Export.

## Lösung

Für alle drei Plugins sicherstellen, dass das tatsächlich ausgelieferte
Release-Artefakt über `git archive` oder ein äquivalentes Build-Skript
erzeugt wird, nicht über einen rohen Ordner-Export. `enrol_adele` hat
dafür bereits `make zip` (nutzt eine explizite Dateiliste, kein
`git archive`, aber ebenfalls ohne `.git`/Zone.Identifier). Für
`local_adele`/`mod_adele` fehlt ein vergleichbares Makefile-Target bislang
— siehe Arbeitsplan-Referenz auf das `enrol_adele`-Makefile als Vorlage.

Kurzfristig (falls kein Build-Skript vorhanden ist): vor jedem Hand-Export
`.git` und `*Zone.Identifier*` explizit ausschließen.

## Manuelles Testverfahren

### Vorbereitung

Aktuelles `local_adele`- bzw. `mod_adele`-Release-Paket zur Hand.

### Testschritte

1. Paket entpacken.
2. Prüfen: `find . -iname "*Zone.Identifier*" | wc -l` → sollte 0 sein.
3. Prüfen: `.git`-Verzeichnis sollte nicht vorhanden sein.
4. Entpackte Größe mit der erwarteten reinen Plugin-Größe vergleichen.

### Aktuelles Ist-Verhalten

444 Zone.Identifier-Dateien, volles `.git`, ~75 MB statt der reinen
Plugin-Größe (~11–20 MB, siehe Größenaufschlüsselung im Review).

### Erwartetes Soll-Verhalten

Nur Laufzeitdateien (plus ggf. `docs/` je nach Lieferkonvention), keine
Entwicklungsartefakte.

## Automatisierte Tests

- CI-Schritt, der das gebaute Release-ZIP auf Abwesenheit von `.git/` und
  `*Zone.Identifier*` prüft, bevor es als Artefakt veröffentlicht wird.

## Akzeptanzkriterien

- [ ] `local_adele` und `mod_adele` haben ein Build-Target analog zu
      `enrol_adele`s `make zip`.
- [ ] CI prüft das Release-Artefakt auf Entwicklungsartefakte.
