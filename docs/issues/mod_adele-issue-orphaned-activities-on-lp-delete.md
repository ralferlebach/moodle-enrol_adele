# [IMPROVEMENT] `mod_adele`-Aktivitäten bleiben nach Lernpfad-Löschung ohne Fehlerbehandlung referenziert (G.13)

## Problem

`mod_adele` reagiert nirgends auf das Event `learnpath_deleted`
(Repo-weite Suche ohne Treffer). Wird ein Lernpfad in `local_adele`
gelöscht, bleiben bestehende `mod_adele`-Aktivitäten (`adele.learningpathid`)
auf eine nicht mehr existierende ID verweisen — ohne Verhinderung,
Deaktivierung, Fehlerstatus oder Migration.

## Ursache

`mod_adele.adele.learningpathid` hat keinen abgesicherten Lifecycle
gegenüber Löschungen in `local_adele`. Ein plugin-übergreifender
DB-Fremdschlüssel ist wegen Installations-/Deinstallationsreihenfolge
keine robuste Lösung; es fehlt aber auch ein fachlicher Ersatz (Event-
Observer, Soft-Delete-Status oder Blockade der Löschung).

## Lösung

Eine der folgenden Optionen (Entscheidung mit dem Auftraggeber):

1. **Löschung blockieren**, solange Einbettungen existieren (analog zu
   Moodles eigenem Verhalten bei referenzierten Objekten) — einfachste,
   sicherste Option.
2. **Soft Delete**: Lernpfad erhält einen Status `deleted` statt
   physischer Löschung; `mod_adele` zeigt für referenzierte, gelöschte
   Lernpfade eine klare Fehlermeldung statt eines stillen Defekts.
3. **Explizite Kaskade** mit administrativer Bestätigung (z. B. über die
   in Arbeitsplan C.2 vorgesehene Verwaltungsseite): beim Löschen eines
   Lernpfads werden betroffene Aktivitäten aufgelistet und müssen bestätigt
   werden.

Unabhängig von der gewählten Option: `mod_adele` sollte auf
`learnpath_deleted` observieren und mindestens einen klaren Hinweis in der
Aktivitätsansicht anzeigen, statt eines undefinierten Zustands.

## Manuelles Testverfahren

### Vorbereitung

Lernpfad in mindestens einer `mod_adele`-Aktivität eingebettet.

### Testschritte

1. Lernpfad in `local_adele` löschen.
2. Die einbettende `mod_adele`-Aktivität aufrufen.

### Aktuelles Ist-Verhalten

Undefiniertes/fehlerhaftes Verhalten beim Aufruf (abhängig davon, wie die
Aktivität mit einer fehlenden `learningpathid` umgeht — vermutlich ein
unbehandelter Fehler oder eine leere/kaputte Ansicht).

### Erwartetes Soll-Verhalten

Je nach gewählter Option: entweder war die Löschung von vornherein
blockiert, oder die Aktivität zeigt eine klare, verständliche Meldung.

## Automatisierte Tests

- Löschversuch eines eingebetteten Lernpfads wird blockiert (Option 1)
  bzw. erzeugt einen definierten `deleted`-Status (Option 2).
- `mod_adele`-Ansicht einer Aktivität mit gelöschtem/nicht mehr
  vorhandenem Lernpfad zeigt eine kontrollierte Fehlermeldung, keinen
  unbehandelten Fehler.

## Akzeptanzkriterien

- [ ] Entscheidung zwischen den drei Optionen getroffen und dokumentiert.
- [ ] Kein unbehandelter Fehler beim Aufruf einer betroffenen Aktivität.
- [ ] Verhalten in Pflichtenheft/Lastenheft nachgezogen.
