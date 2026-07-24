# [IMPROVEMENT] `enrol_adele` liest `mod_adele`s `adele`-Tabelle direkt, ohne die Abhängigkeit zu deklarieren (G.2)

## Problem

`enrol_adele/classes/observer.php` liest die Tabelle `adele` (Kerntabelle
von `mod_adele`) direkt über die Moodle-DB-API und kennt die interne
Semantik des Feldes `participantslist` (kommaseparierte Optionen `1`/`2`/
`3`):

```
$embeddings = $DB->get_records('adele', ['course' => $courseid], '', 'id, learningpathid');
...
$options = array_map('trim', explode(',', (string) $embedding->participantslist));
```

`enrol_adele/version.php` deklariert aber ausschließlich eine Abhängigkeit
auf `local_adele`:

```
$plugin->dependencies = [
    'local_adele' => 2026072301,
];
```

## Ursache

Die Kopplung an `mod_adele` ist real und notwendig (Host-Kurs-Reconciliation
kennt sonst nicht, welche Lernpfade in welchen Kurs eingebettet sind), wurde
aber nie in `version.php` nachgezogen — vermutlich, weil sie über eine
weiche `$dbman->table_exists('adele')`-Prüfung zur Laufzeit statt über eine
harte Abhängigkeit abgesichert ist.

Zusätzlich verletzt der direkte Tabellenzugriff die vorgesehene
Aufgabenteilung: `enrol_adele` sollte laut Architekturübersicht keine
Kenntnis von `mod_adele`s Schema oder UI-Optionen haben.

## Lösung

Zwei unabhängige Teile:

1. **Deklaration nachziehen** (schnell, risikoarm): `enrol_adele/version.php`
   um `'mod_adele' => <Mindestversion>` ergänzen.
2. **Schichtenverletzung beheben** (größer): `mod_adele` wertet seine
   Host-Kurs-Policy selbst aus und übergibt nur das Ergebnis (welche Kurse
   für welchen Lernpfad host-relevant sind, in welchem Modus) an eine
   öffentliche, dokumentierte Methode von `enrol_adele`. `enrol_adele`
   kennt danach weder die `adele`-Tabelle noch `participantslist`.

Sinngemäß für Teil 2:

```
// enrol_adele, neue öffentliche API:
reconciler::reconcile_host_embeddings(array $embeddings, int $userid): void;

// mod_adele liefert $embeddings bereits ausgewertet:
[
    ['learningpathid' => .., 'hostcourseid' => .., 'entitled' => true, 'mode' => 'visible'],
    ...
]
```

Teil 1 kann unabhängig und sofort umgesetzt werden; Teil 2 ist eine größere
Refaktorisierung und sollte mit Teil 1 des `local_adele`↔`mod_adele`-
Codezirkels (G-Q2, aktuell bewusst zurückgestellt) zusammen betrachtet
werden, falls Letzterer irgendwann doch angegangen wird.

## Manuelles Testverfahren

### Vorbereitung

1. Alle drei Plugins auf einer Testinstanz installiert und aktiv.
2. Einen Lernpfad mit Host-Kurs-Einbettung (Option 2 oder 3) anlegen.

### Testschritte

1. `mod_adele` deaktivieren (nicht deinstallieren).
2. Prüfen, ob `enrol_adele` beim Reconcile eines betroffenen Nutzers einen
   Fehler wirft oder still fehlschlägt (aktuell: stiller No-op über
   `table_exists()`).
3. Nach Umsetzung von Teil 1: Prüfen, dass Moodles Plugin-Verwaltung nun
   `mod_adele` als Abhängigkeit von `enrol_adele` anzeigt.

### Aktuelles Ist-Verhalten

Kein Fehler, keine Warnung — `enrol_adele` funktioniert augenscheinlich
normal weiter, reconciliert aber keine Host-Kurs-Einschreibungen mehr, ohne
dass dies irgendwo sichtbar wird.

### Erwartetes Soll-Verhalten

Die Abhängigkeit ist deklariert und für Administrator/innen sichtbar; nach
Teil 2 hat `enrol_adele` keine Kenntnis mehr von `mod_adele`s internem
Schema.

## Automatisierte Tests

- `version.php`-Abhängigkeit auf `mod_adele` vorhanden (statischer Test
  oder CI-Check).
- Nach Teil 2: PHPUnit-Test, dass `enrol_adele\classes` keine Referenz auf
  die Tabelle `adele` oder das Feld `participantslist` mehr enthält
  (Grep-basierter Architektur-Test, nach Moodle-Konvention z. B. als
  `tests/architecture_test.php`).

## Akzeptanzkriterien

- [ ] `enrol_adele/version.php` deklariert `mod_adele` als Abhängigkeit.
- [ ] (Teil 2) `enrol_adele` liest die Tabelle `adele` nirgends mehr
      direkt.
- [ ] (Teil 2) `enrol_adele` kennt `participantslist` nirgends mehr.
- [ ] Bestehende Host-Kurs-Reconciliation-Tests bleiben grün.
