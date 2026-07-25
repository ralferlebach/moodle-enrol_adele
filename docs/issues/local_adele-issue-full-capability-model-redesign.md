# [IMPROVEMENT] Granulares Capability-Modell statt fünf teils überlappender Capabilities (G.10-Folgearbeit)

## Problem

`local_adele` verwendet aktuell fünf Capabilities
(`local/adele:view`, `local/adele:edit`, `local/adele:canmanage`,
`local/adele:teacheredit`, `local/adele:assist`), die sich in ihrer
praktischen Bedeutung teils überlappen und nicht immer eindeutig sind. Ein
Großteil der tatsächlichen Zugriffssteuerung läuft über eigene,
code-seitige Prüfungen (`learning_paths::check_access()`,
`require_lp_editor_access()`, `require_lp_owner_access()`) statt über
klar benannte, für sich verständliche Capabilities.

## Ursache

Historisch gewachsen; die feingranulare Steuerung, wer einen Lernpfad
erstellen, fremde Lernpfade bearbeiten, Editoren verwalten, Bilder
hochladen oder eine Reconciliation anstoßen darf, wurde nie in eigene
Capabilities gegossen, sondern in Methodenlogik verteilt.

## Lösung (Diskussionsvorschlag, keine fertige Spezifikation)

Ein granulareres Set einführen, orientiert an den tatsächlichen
Aktionsklassen:

```
local/adele:view            (unverändert, lesend)
local/adele:createpath      (neuen Lernpfad anlegen)
local/adele:editownpath     (eigene/als Editor zugewiesene Pfade bearbeiten)
local/adele:editallpaths    (jeden Pfad bearbeiten — ersetzt canmanage teilweise)
local/adele:manageeditors   (Editor/innen eines Pfads verwalten)
local/adele:uploadimages    (Titelbilder hochladen)
local/adele:reconcile       (Verwaltungsseite, Neu-berechnen/Hart-löschen)
```

**Das ist eine Produktentscheidung, keine rein technische** — insbesondere:
Sollen Personen, die nur als Editor/in eines einzelnen Pfads eingetragen
sind (nicht Kursleitung, nicht Manager), über token-basierte externe
Services (Mobile App, REST) dieselben Rechte erhalten wie im
AJAX-basierten UI-Pfad? Das entscheidet, welche Archetypen jede neue
Capability standardmäßig erhält, und sollte vor der Umsetzung mit dem
Auftraggeber geklärt werden (siehe auch Arbeitsplan G.10 zur Begründung,
warum diese Umsetzung in Session 003 bewusst zurückgestellt wurde).

Migration: Bestehende `db/access.php`-Einträge um die neuen Capabilities
ergänzen (additiv, kein Entfernen der alten in derselben Version, um
Bestandsinstallationen nicht abrupt zu brechen); alle 25
`classes/external/*.php`-Klassen sowie `db/services.php` schrittweise auf
die neuen, spezifischeren Capabilities umstellen.

## Manuelles Testverfahren

### Vorbereitung

Nach Umsetzung: Testinstanz mit je einer Person pro neuer Rolle
(Pfaderstellerin ohne Editorrecht an fremden Pfaden, reine Editorin eines
einzelnen Pfads, Kursleitung, Managerin).

### Testschritte

1. Für jede Person die jeweils erwarteten und nicht erwarteten Aktionen
   durchspielen (anlegen, fremden Pfad bearbeiten, Editor/innen verwalten,
   Bild hochladen, Verwaltungsseite aufrufen).
2. Für jede Aktion: erwartetes Ergebnis (erlaubt/verweigert) mit
   tatsächlichem Ergebnis abgleichen.

### Aktuelles Ist-Verhalten

Fünf Capabilities, teils lückenhaft durchgesetzt (siehe G.8/G.10 in
Session 003), viel Logik in Code statt in Capability-Definitionen.

### Erwartetes Soll-Verhalten

Jede Aktionsklasse hat eine eigene, für sich verständliche Capability mit
sinnvollen Standardarchetypen.

## Automatisierte Tests

- Für jede neue Capability: PHPUnit-Test, dass die erwarteten Archetypen
  sie besitzen und andere nicht.
- Für jede External-Function-Klasse: Test, dass die deklarierte
  `services.php`-Capability mit der intern geprüften übereinstimmt.

## Akzeptanzkriterien

- [ ] Produktentscheidung zu Rollenzuschnitten mit dem Auftraggeber
      getroffen (Voraussetzung, siehe oben).
- [ ] Neue Capabilities additiv eingeführt, Bestandsinstallationen bleiben
      funktionsfähig während der Übergangszeit.
- [ ] Alle 25 External-Function-Klassen auf die spezifischste zutreffende
      Capability umgestellt.
- [ ] `services.php`-Deklarationen stimmen mit den internen Prüfungen
      überein.
