# Issue-Entwurf (mod_adele): Sicherung und Duplikat verlieren den Host-Einschreibemodus

> **Stand:** eingereicht als mod_adele #41 (2026-10-16), behoben mit `2026100731` (Protokoll Teil 66).

**Gefunden:** beim Aufnehmen von `teachercourseaccessmode` in die Sicherung
(local_adele #600, TP3), Session 006, Teil 65.

## Befund

`backup/moodle2/backup_adele_stepslib.php` führt die Felder der Tabelle
`adele` einzeln auf. `hostenrolmentmode` (seit `2026072301`) fehlt in dieser
Liste. Beim Wiederherstellen fehlt der Wert damit, und es gilt der
Spaltenstandard `visible`.

Gemessen (PHPUnit, nicht eingecheckt): Aktivität mit
`hostenrolmentmode = none` → `duplicate_module()` → Original `none`, Kopie
`visible`.

Folge: Ein Duplikat, ein Import oder eine Wiederherstellung schreibt
Personen aktiv in den Gastkurs ein, für die die Lehrperson „keine
Einschreibung“ oder „verborgen“ gewählt hatte.

## Erwartung

Duplikat, Import und Wiederherstellung übernehmen `hostenrolmentmode`
unverändert.

## Akzeptanz

- `hostenrolmentmode` steht in der Feldliste der Sicherung.
- PHPUnit: Duplikat einer Aktivität mit `none` und mit `hidden` behält den
  Wert; eine Sicherung ohne das Feld (ältere Sicherung) ergibt `visible`.
