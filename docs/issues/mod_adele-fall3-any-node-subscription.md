[FEATURE] Einschreibeoption "Fall 3": Teilnahme bei Mitgliedschaft in irgendeinem Node-Kurs des Lernpfads

## Problem

`participantslist` kennt aktuell nur zwei Auslöser für die Lernpfad-
Subscription:

- **Fall 1** — Mitgliedschaft im Host-Kurs selbst.
- **Fall 2** — Mitgliedschaft in einem **Startnode**-Kurs.

Es gibt keine Option, die auf Mitgliedschaft in **irgendeinem** Node-Kurs des
Lernpfads reagiert — auch nicht in einem, der erst mitten im Pfad liegt und
nie über einen Startnode erreicht wird. Konkretes Szenario: Ein Kurs C ist
Node eines Lernpfads, aber kein Startnode (er wird erst freigeschaltet, wenn
ein vorheriger Knoten abgeschlossen ist). Ein Nutzer wird — etwa durch eine
Massenzuteilung, einen Kursbereichs-Zugang oder eine andere, vom Lernpfad
unabhängige Einschreibung — direkt in C eingeschrieben, ohne je den Startnode
durchlaufen zu haben. Weder Fall 1 noch Fall 2 erfasst diesen Nutzer; er bleibt
ohne Lernpfad-Subscription und ohne Host-Kurs-Zugang, obwohl er faktisch schon
mitten im Pfad steht.

## Ursache

`mod_form.php` und `mod_adele_observer` implementieren ausschließlich die
Prüfungen `'1'` und `'2'`; ein dritter Wert existiert im Datenmodell
(`participantslist` ist bereits eine Kommaliste in einem `char(256)`-Feld, kein
Schema-Hindernis), aber keine Programmlogik wertet ihn aus.

Ein Lösungsversuch existiert bereits als unveröffentlichter Branch
(`ralferlebach-fix-enrolment-issue`), konnte aber nicht gemergt werden: Er ruft
`\local_adele\enrollment::subscribe_user_to_learning_path()` **ohne**
`$courseid` auf — zum Zeitpunkt seiner Entstehung ein Pflichtparameter in
`local_adele` main, sodass der Aufruf dort fatal fehlschlägt.

## Lösung

1. **Neue Option 3** in `participantslist`: „Alle, die in irgendeinem
   Knoten-Kurs des Lernpfads eingeschrieben sind."
2. **Neue Sweep-Methode** (Vorbild: `enroll_starting_nodes_participants()`),
   die statt nur der Startnode-Kurse **alle** `course_node_id`-Einträge des
   Lernpfad-Baums durchläuft, die dort eingeschriebenen Nutzer/innen
   einsammelt, über alle Knoten hinweg dedupliziert (derselbe Nutzer kann in
   mehreren Node-Kursen gleichzeitig stehen) und pro Nutzer genau einmal
   subscribed sowie in den Host-Kurs eingeschrieben wird.
3. **Der ursprüngliche Blocker ist inzwischen aufgelöst:** `local_adele` ab
   0.4.5 akzeptiert `$courseid` als optionalen Parameter (reine Provenienz,
   nicht mehr Teil der Identität) — der Aufruf ohne `$courseid` aus dem
   blockierten Branch ist damit kompatibel.
4. **Weiterführend:** Sobald Fall 3 existiert, gilt für ihn — wie für Fall 2 —
   das separat eingereichte Issue zum laufenden, ereignisgesteuerten Trigger
   und zur Host-Kurs-Einschreibung über `enrol_adele` statt `enrol_manual`.
   Dieses Issue hier liefert ausschließlich die Grundlage (Option 3 existiert
   und funktioniert als einmaliger Sweep beim Aktivitäts-Save); das
   Folge-Issue baut darauf auf.

## Manuelles Testverfahren

### Grundfunktion

1. Lernpfad mit Knoten A (Startnode, Kurs KA) → Knoten B (kein Startnode,
   Kurs KB) anlegen.
2. Nutzer/in NUR in KB einschreiben (nicht in KA).
3. mod_adele-Aktivität mit Option 3 im Host-Kurs H anlegen (oder Option 3
   nachträglich einer bestehenden Aktivität hinzufügen und speichern).
4. Prüfen: Nutzer/in erscheint als Lernpfadnutzer/in und ist in H
   eingeschrieben — trotz fehlender Startnode-Mitgliedschaft.

### Deduplizierung

1. Lernpfad mit zwei Knoten C und D, beide mit Kurs KE als `course_node_id`
   verknüpft (geteilter Knoten-Kurs).
2. Nutzer/in in KE einschreiben, Option 3 aktivieren/speichern.
3. Prüfen: genau eine Subscription, genau eine Host-Kurs-Einschreibung — nicht
   zwei.

### Regressionstest Fall 1/2

Bestehende Tests für Fall 1 und Fall 2 laufen unverändert grün; Option 3 darf
deren Verhalten nicht beeinflussen.

## Upgrade-Anforderungen

Keine Datenbankschema-Änderung: `participantslist` ist bereits eine
Kommaliste in einem ausreichend dimensionierten Feld. Bestehende
Aktivitäten ohne Option 3 sind von der Änderung nicht betroffen, bis eine
Lehrkraft sie aktiv hinzufügt.

## Automatisierte Tests

- Option 3 subscribed alle aktuell in einem Node-Kurs eingeschriebenen
  Nutzer/innen beim Aktivitäts-Save, unabhängig davon, ob es sich um einen
  Startnode handelt.
- Ein Nutzer/eine Nutzerin mit Mitgliedschaft in zwei Node-Kursen desselben
  Lernpfads wird nur einmal subscribed (Dedup-Test).
- Ein Nutzer/eine Nutzerin ohne jede Node-Kurs-Mitgliedschaft bleibt
  unberührt.
- Fall 1 und Fall 2 bleiben unverändert (Regressionstest).
- Der frühere Blocker (Aufruf ohne `$courseid`) tritt gegen `local_adele`
  ≥ 0.4.5 nicht mehr auf.

## Akzeptanzkriterien

- [ ] `participantslist` bietet Option 3 ("irgendeine Node") zusätzlich zu
      Fall 1 und Fall 2 an.
- [ ] Beim Speichern der Aktivität werden alle aktuell in irgendeinem
      Node-Kurs des Lernpfads eingeschriebenen Nutzer/innen subscribed und in
      den Host-Kurs eingeschrieben — unabhängig davon, ob der Node-Kurs ein
      Startnode ist.
- [ ] Ein Nutzer/eine Nutzerin mit mehreren qualifizierenden Node-Kurs-
      Mitgliedschaften wird nicht mehrfach subscribed.
- [ ] Fall 1 und Fall 2 verhalten sich unverändert.
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
- [ ] Voraussetzung `local_adele` ≥ 0.4.5 ist in `version.php`
      (`$plugin->dependencies`) dokumentiert.
