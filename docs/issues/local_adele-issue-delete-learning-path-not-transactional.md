# [BUG] `delete_learning_path()` ist nicht transaktional (G.12)

## Problem

`learning_paths::delete_learning_path()` führt mehrere unabhängige
Datenänderungen und einen externen Seiteneffekt ohne Transaktionsklammer
aus:

```
enrol_state::request_purge((int) $params['learningpathid']);
$result = $DB->delete_records('local_adele_learning_paths', [...]);
if ($result) {
    $DB->set_field('local_adele_path_user', 'status', 'archived', [...]);
    $DB->delete_records('local_adele_lp_editors', [...]);
    $event->trigger();
}
```

## Ursache

Kein `$DB->start_delegated_transaction()`. Ein Fehlschlag zwischen den
Schritten kann zu inkonsistenten Zwischenständen führen (z. B.
Einschreibungen bereits entfernt, Lernpfad-Datensatz aber noch vorhanden,
oder Lernpfad gelöscht, aber Nutzerpfade weiterhin als „active" markiert).

## Lösung

Die rein lokalen Datenbankänderungen (`delete_records`, `set_field`,
`delete_records`) in eine delegierte Transaktion fassen. Der externe
Seiteneffekt (`enrol_state::request_purge()`, der seinerseits in
`enrol_adele` eigene DB-Änderungen vornimmt) entweder:

- nach erfolgreichem Commit der lokalen Transaktion ausführen (Reihenfolge
  umkehren: erst lokale Daten committen, dann Purge anstoßen), oder
- über einen dauerhaften Ad-hoc-Task/Outbox-Eintrag entkoppeln, der bei
  einem Fehlschlag automatisch wiederholt wird.

Die erste Variante ist einfacher und ausreichend, solange
`request_purge()`/`purge_learning_path()` bereits idempotent sind (laut
F-6/L-Q-09 der Fall).

## Manuelles Testverfahren

### Vorbereitung

Lernpfad mit aktiven Nutzerpfaden und Editoren anlegen.

### Testschritte

1. Einen künstlichen Fehler zwischen zwei der Teilschritte auslösen (z. B.
   testweise eine Exception nach `delete_records('local_adele_learning_paths', ...)`
   werfen).
2. Prüfen, welcher Zwischenstand in der Datenbank verbleibt.

### Aktuelles Ist-Verhalten

Teilweise durchgeführte Änderungen bleiben bestehen (z. B. Lernpfad
gelöscht, Nutzerpfade weiterhin „active").

### Erwartetes Soll-Verhalten

Entweder alle lokalen Änderungen sind durchgeführt, oder keine.

## Automatisierte Tests

- Simulierter Fehler nach dem ersten Teilschritt: keine lokale Änderung
  bleibt bestehen (Rollback).
- Erfolgreicher Durchlauf: alle vier Teilschritte greifen wie bisher.
- Idempotenz von `request_purge()` bleibt durch einen wiederholten Aufruf
  bestätigt.

## Akzeptanzkriterien

- [ ] Lokale Datenbankänderungen laufen in einer delegierten Transaktion.
- [ ] Kein inkonsistenter Zwischenstand bei simuliertem Fehler.
- [ ] Bestehende Lösch-Tests bleiben grün.
