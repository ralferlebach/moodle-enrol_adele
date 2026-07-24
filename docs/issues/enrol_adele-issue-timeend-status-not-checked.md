# [BUG] `has_foreign_enrolment()` ignoriert `timeend`/`timestart` und deaktivierte Enrol-Instanzen (G.4)

## Problem

`enrol_adele\observer::has_foreign_enrolment()` prüft, ob ein Nutzer eine
nicht-ADELE-Einschreibung in einer gegebenen Kursliste hält, um zu
entscheiden, ob eine Lernpfad-Mitgliedschaft noch „getragen" wird (A-4).
Die Abfrage:

```
SELECT 1
  FROM {user_enrolments} ue
  JOIN {enrol} e ON e.id = ue.enrolid
 WHERE ue.userid = :userid
       AND e.enrol <> 'adele'
       AND e.courseid {$insql}
```

prüft weder `ue.status`, `e.status`, `ue.timestart` noch `ue.timeend`.

## Ursache

Für **Suspendierung** ist das bewusst so gewollt (Entscheidung F-4/A-8:
„Suspendierte gelten weiterhin als subscribed" — siehe Pflichtenheft
Abschnitt 4). Für **Ablauf** (`timeend` in der Vergangenheit) und für eine
**deaktivierte Enrol-Instanz** (`e.status <> ENROL_INSTANCE_ENABLED`)
existiert dagegen keine dokumentierte Entscheidung — das ist eine
unbeabsichtigte Lücke, keine bewusste Erweiterung von F-4/A-8.

Praktisch: Ein Nutzer, dessen Einschreibung im Ausgangskurs regulär
abgelaufen ist (`timeend` überschritten) oder deren Enrol-Methode der
Kursleiter deaktiviert hat, gilt für `has_foreign_enrolment()` weiterhin
als vollwertig eingeschrieben — der Lernpfadzugang bleibt fälschlich
bestehen.

## Lösung

`timeend`/`timestart` und `e.status` zusätzlich in die Abfrage aufnehmen,
`ue.status` (Suspendierung) bewusst weiterhin **nicht** filtern (F-4/A-8
bleibt unangetastet):

```
SELECT 1
  FROM {user_enrolments} ue
  JOIN {enrol} e ON e.id = ue.enrolid
 WHERE ue.userid = :userid
       AND e.enrol <> 'adele'
       AND e.status = :enabled
       AND (ue.timestart = 0 OR ue.timestart <= :now1)
       AND (ue.timeend = 0 OR ue.timeend > :now2)
       AND e.courseid {$insql}
```

`is_user_entitled_to_host_via_option()` in `mod_adele/classes/observer.php`
hat dieselbe Lücke (siehe G.11 — dort zusammen mit der Grant/Entzug-
Asymmetrie behandelt, da beide Funktionen ohnehin vereinheitlicht werden
sollten).

## Manuelles Testverfahren

### Vorbereitung

1. Lernpfad mit Option 1 (Hostkurs-Mitgliedschaft) oder Zielkurs-Setup mit
   Ausgangskurs-Enrolment.
2. Testnutzer im Ausgangskurs eingeschrieben, Lernpfadzugang aktiv.

### Testschritte

1. Im Ausgangskurs die Einschreibung des Testnutzers mit `timeend` auf
   „gestern" setzen (oder die Enrol-Instanz deaktivieren).
2. Ein Ereignis auslösen, das `is_user_carried()`/`has_foreign_enrolment()`
   neu bewertet (z. B. eine andere Einschreibung im selben Kurs
   löschen/anlegen, oder den Reconcile-Task laufen lassen).
3. Prüfen, ob der Lernpfadzugang weiterhin besteht.

### Aktuelles Ist-Verhalten

Lernpfadzugang bleibt bestehen, obwohl die Ausgangseinschreibung
tatsächlich abgelaufen/deaktiviert ist.

### Erwartetes Soll-Verhalten

Lernpfadzugang wird entzogen, sobald keine andere gültige (nicht
abgelaufene, nicht über eine deaktivierte Methode laufende)
Ausgangseinschreibung mehr besteht. Eine reine Suspendierung bleibt
weiterhin folgenlos (F-4/A-8 unverändert).

## Automatisierte Tests

- Abgelaufene Einschreibung (`timeend` in der Vergangenheit) zählt nicht
  als tragend.
- Noch nicht begonnene Einschreibung (`timestart` in der Zukunft) zählt
  nicht als tragend.
- Einschreibung über eine deaktivierte Enrol-Instanz zählt nicht als
  tragend.
- Suspendierte, aber sonst gültige Einschreibung zählt weiterhin als
  tragend (Regressionstest für F-4/A-8).

## Akzeptanzkriterien

- [ ] `timeend`/`timestart` werden geprüft.
- [ ] `e.status` wird geprüft.
- [ ] `ue.status` (Suspendierung) bleibt bewusst ungeprüft.
- [ ] Bestehende F-4/A-8-Tests bleiben grün.
- [ ] Analoge Lücke in `mod_adele::is_user_entitled_to_host_via_option()`
      referenziert (siehe G.11).
