# [BUG] Race Condition in `instance_manager::ensure_instance()` kann doppelte Enrol-Instanzen erzeugen (G.6)

## Problem

`ensure_instance()` prüft auf eine vorhandene Instanz, legt bei
Nichtvorhandensein eine neue an — ohne Sperre zwischen den beiden
Schritten:

```
$existing = $DB->get_records('enrol', [...], 'id ASC', '*', 0, 1);
if ($existing) {
    return reset($existing);
}
// ... kein Lock hier ...
$instanceid = $plugin->add_instance($course, [...]);
```

Zwei nahezu gleichzeitige Ereignisse (z. B. zwei Enrolment-Events desselben
Nutzers in unterschiedlichen Knotenkursen, die beide zum ersten Mal
denselben Zielkurs berechtigen) können für dieselbe Kombination aus
Lernpfad/Kurs/Kind zwei Enrol-Instanzen anlegen.

## Ursache

Kein Moodle-Lock (`\core\lock\lock_factory`) zwischen Existenzprüfung und
Anlage. `get_instances()` verdeckt den Fehler nachträglich, indem bei
Duplikaten nur die Instanz mit der niedrigsten ID verwendet wird — die
weiteren Instanzen (und ggf. deren eigene `user_enrolments`) bleiben aber
bestehen.

## Lösung

Vor dem Check-and-create einen Lock mit stabilem Schlüssel setzen:

```
$lockfactory = \core\lock\lock_factory::instance();
$lock = $lockfactory->get_lock(
    "enrol_adele_instance_{$learningpathid}_{$courseid}_{$kind}",
    5
);
if (!$lock) {
    throw new \moodle_exception('cannotlockinstance', 'enrol_adele');
}
try {
    // bestehende Existenzprüfung + Anlage hierher verschieben
} finally {
    $lock->release();
}
```

Zusätzlich ein einmaliger Bereinigungslauf für bereits vorhandene
Duplikate (Teil des in G.5 beschriebenen erweiterten `reconcile_all()`).

## Manuelles Testverfahren

### Vorbereitung

1. Lernpfad mit einem Zielkurs, der von zwei unterschiedlichen
   Startknoten aus erreichbar ist.
2. Ein Testnutzer, der beide Startknoten-Kurse gleichzeitig betritt (z. B.
   über zwei parallele Browser-Tabs/Requests).

### Testschritte

1. Beide Node-Kurs-Einschreibungen möglichst gleichzeitig auslösen (z. B.
   über ein Script mit zwei parallelen Webservice-Aufrufen).
2. `mdl_enrol` auf doppelte Zeilen mit identischem
   `(enrol='adele', courseid, customint1, customint2)` prüfen.

### Aktuelles Ist-Verhalten

Unter Last (paralleler Zugriff) können zwei Instanzen entstehen.

### Erwartetes Soll-Verhalten

Es entsteht in jedem Fall genau eine Instanz.

## Automatisierte Tests

- Zwei parallele (simulierte) Aufrufe von `ensure_instance()` mit
  identischen Parametern erzeugen nur eine Instanz (Test über zwei
  Prozesse/Threads oder durch gezieltes Verzögern des zweiten Aufrufs
  zwischen Prüfung und Anlage, falls die Testumgebung das zulässt).
- Lock wird nach der Anlage/dem Fehlschlag zuverlässig freigegeben (kein
  Deadlock bei wiederholten Aufrufen).

## Akzeptanzkriterien

- [ ] Lock schützt Existenzprüfung + Anlage.
- [ ] Kein Deadlock bei Lock-Timeout.
- [ ] Bereinigungslauf für Bestandsduplikate vorhanden (verweist auf G.5).
