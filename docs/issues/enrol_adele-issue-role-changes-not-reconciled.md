# [IMPROVEMENT] Rollenänderungen an `enrol_adele/roleid` wirken nicht auf Bestandsinstanzen (G.14)

## Problem

`reconciler`s Einschreibe-Aufrufe verwenden die auf der jeweiligen
Enrol-Instanz gespeicherte Rolle, nicht die aktuelle Konfiguration:

```
$plugin->enrol_user(
    $instance,
    $userid,
    $instance->roleid ?: instance_manager::get_role_id(),
    ...
);
```

`$instance->roleid` wird nur beim erstmaligen Anlegen der Instanz
(`ensure_instance()`) gesetzt und danach nie aktualisiert.

## Ursache

Es existiert kein Reconcile-Schritt, der bestehende Instanzen und
Rollenzuweisungen gegen die aktuelle `enrol_adele/roleid`-Einstellung
abgleicht.

## Lösung

`reconcile_all()`/ein dedizierter Wartungsschritt (siehe G.5) ergänzt um:

1. Für jede bestehende ADELE-Enrol-Instanz: `enrol.roleid` auf den
   aktuell konfigurierten Wert aktualisieren, falls abweichend.
2. Für jede betroffene Instanz: vorhandene Rollenzuweisungen der
   *alten* Rolle entfernen und die *neue* Rolle zuweisen — dabei
   ausschließlich Zuweisungen anfassen, die tatsächlich von dieser
   Instanz stammen (nicht fremde, manuell vergebene Rollen in demselben
   Kontext).

```
foreach (instances_with_stale_role() as $instance) {
    role_unassign($instance->old_roleid, $userid, $coursecontext->id, 'enrol_adele', $instance->id);
    role_assign($newroleid, $userid, $coursecontext->id, 'enrol_adele', $instance->id);
    $DB->set_field('enrol', 'roleid', $newroleid, ['id' => $instance->id]);
}
```

(`role_assign()`/`role_unassign()` mit `$component`/`$itemid` sind der
Standard-Moodle-Mechanismus, über den Enrol-Plugins ihre eigenen
Rollenzuweisungen von fremden unterscheiden.)

## Manuelles Testverfahren

### Vorbereitung

Lernpfad mit mindestens einer aktiven Zielkurs-Einschreibung, Rolle
initial auf „Student" konfiguriert.

### Testschritte

1. `enrol_adele/roleid` in den Plugin-Einstellungen auf eine andere Rolle
   ändern (z. B. „Teilnehmer/in").
2. Reconcile auslösen (Task oder „Neu berechnen").
3. Im Zielkurs prüfen, welche Rolle dem Nutzer tatsächlich zugewiesen ist.

### Aktuelles Ist-Verhalten

Rolle bleibt „Student" (die zum Anlagezeitpunkt gültige Rolle).

### Erwartetes Soll-Verhalten

Rolle wird auf die neu konfigurierte Rolle migriert.

## Automatisierte Tests

- Rollenänderung + Reconcile migriert bestehende Zuweisungen korrekt.
- Fremde, nicht von `enrol_adele` vergebene Rollenzuweisungen im selben
  Kontext bleiben unangetastet.
- `enrol.roleid` wird auf den neuen Wert aktualisiert.

## Akzeptanzkriterien

- [ ] Bestehende Instanzen werden bei Rollenänderung migriert.
- [ ] Nur `enrol_adele`-eigene Rollenzuweisungen werden angefasst.
- [ ] Teil des erweiterten `reconcile_all()` (G.5).
