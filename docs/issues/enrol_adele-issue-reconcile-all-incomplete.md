# [IMPROVEMENT] `reconcile_all()` ist kein vollständiges Self-Healing (G.5)

## Problem

`enrol_adele\local\reconciler::reconcile_all()` — der nächtliche
Sicherheitsnetz-Task — lädt ausschließlich aktive
`local_adele_path_user`-Paare und ruft für jedes nur `reconcile_user()`
auf:

```
$pairs = $DB->get_records_sql(
    "SELECT DISTINCT learning_path_id, user_id
       FROM {local_adele_path_user}
      WHERE status = 'active'"
);
foreach ($pairs as $pair) {
    self::reconcile_user((int) $pair->learning_path_id, (int) $pair->user_id);
}
```

Nicht repariert werden: Host-Kurs-Einschreibungen, Einschreibungen
gelöschter Lernpfade, verwaiste Enrol-Instanzen, doppelte Instanzen (siehe
G.6), Rollenabweichungen (siehe G.14), deaktivierte ADELE-Instanzen.

## Ursache

`reconcile_all()` wurde als Sicherheitsnetz für **Zielkurs**-
Reconciliation entworfen (Phase B) und seitdem nicht erweitert, obwohl seit
Phase D/Teil 4 auch Host-Kurs-Reconciliation, Rollenverwaltung und
Instanz-Lifecycle dazugekommen sind.

Zusätzlich lädt `get_records_sql()` das gesamte Ergebnis in den Speicher
(kein Recordset, kein Batching) — bei großen Installationen potenziell ein
Performance-Problem (Review-Abschnitt 4, P1-9).

## Lösung

`reconcile_all()` in zwei Richtungen erweitern:

```
Soll → Ist: fehlende Instanz/Einschreibung anlegen,
            suspendierte berechtigte Einschreibung reaktivieren
Ist → Soll: nicht mehr berechtigte Einschreibung suspendieren/löschen,
            verwaiste Instanz entfernen, Duplikate konsolidieren,
            Rollenabweichungen korrigieren (G.14)
```

Zusätzlich auf `get_recordset_sql()` umstellen und in Batches
verarbeiten, um für große Installationen zu skalieren.

Sinnvoll mit `manage.php` (Arbeitsplan C.2, „Neu berechnen" pro Lernpfad)
zusammen zu planen, da beide denselben vollständigen Reconcile-Kern
brauchen.

## Manuelles Testverfahren

### Vorbereitung

1. Ein Testsystem mit mehreren Lernpfaden, davon mindestens einer mit
   Host-Kurs-Einbettung.
2. Eine verwaiste Enrol-Instanz künstlich erzeugen (z. B. Lernpfad-Eintrag
   löschen, ohne über `purge_learning_path()` zu gehen).

### Testschritte

1. `reconcile_all()` manuell auslösen (Scheduled Task „Jetzt ausführen").
2. Prüfen, ob die verwaiste Instanz weiterhin besteht.
3. Eine Host-Kurs-Einschreibung künstlich in einen falschen Zustand
   versetzen (z. B. suspendiert, obwohl der Nutzer weiterhin berechtigt
   ist) und erneut `reconcile_all()` auslösen.

### Aktuelles Ist-Verhalten

Verwaiste Instanz und falsch suspendierte Host-Kurs-Einschreibung bleiben
unverändert.

### Erwartetes Soll-Verhalten

Beide werden vom nächtlichen Lauf korrigiert.

## Automatisierte Tests

- `reconcile_all()` repariert eine fälschlich suspendierte
  Host-Kurs-Einschreibung.
- `reconcile_all()` entfernt eine verwaiste Instanz (Lernpfad existiert
  nicht mehr).
- `reconcile_all()` konsolidiert doppelte Instanzen (siehe G.6) auf eine.
- Performance-Test mit einer größeren Zahl an Nutzer/Lernpfad-Paaren
  (Recordset statt vollständigem Laden).

## Akzeptanzkriterien

- [ ] Host-Kurs-Reconciliation Teil von `reconcile_all()`.
- [ ] Verwaiste/doppelte Instanzen werden erkannt und bereinigt.
- [ ] Rollenabweichungen werden korrigiert (verweist auf G.14).
- [ ] `get_recordset_sql()` statt `get_records_sql()`, Batch-Verarbeitung.
