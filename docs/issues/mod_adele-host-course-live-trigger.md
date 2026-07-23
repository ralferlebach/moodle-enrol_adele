[IMPROVEMENT] Laufende Einschreibung bei Fall 2/3 (Startnode/irgendeine Node) + Host-Kurs-Einschreibung über enrol_adele

## Problem

Die Optionen 2 ("Startnode-Teilnehmer") und 3 ("Teilnehmer irgendeiner Node")
der Aktivitätseinstellung `participantslist` lösen die Einschreibung in den
Lernpfad und in den Host-Kurs bislang **nur einmalig** aus — und zwar in
`saved_module()`, also ausschließlich beim Anlegen oder Bearbeiten der
mod_adele-Aktivität selbst:

```php
public static function saved_module($data) {
    ...
    if ($participantslist == '2') {
        self::enroll_starting_nodes_participants($adelelp, $data);
    } else if ($participantslist == '3') {
        self::enroll_any_nodes_participants($adelelp, $data);
    }
}
```

Beide Methoden fegen einmalig die zu diesem Zeitpunkt bereits eingeschriebenen
Teilnehmer der jeweiligen Node-Kurse ein. Ein Nutzer, der sich **danach** neu in
einen Startnode- oder Node-Kurs einschreibt, wird nicht erfasst — es gibt dafür
keinen laufenden Beobachter. `user_enrolment_created` ist zwar registriert,
prüft aber ausschließlich, ob der Host-Kurs selbst die Aktivität trägt (Fall 1):

```php
public static function user_enrolment_created($data) {
    $modules = get_course_mods($data->courseid);
    foreach ($modules as $module) {
        if ($module->modname == 'adele' ...) {
            if (in_array('1', $options)) {
                enrollment::subscribe_user_to_learning_path($learningpath, $data);
            }
        }
    }
}
```

Zusätzlich erzeugen beide Sweep-Methoden die Host-Kurs-Einschreibung über
`enrol_manual` (`subscribe_user_course()`), unabhängig vom neuen
Einschreibe-Plugin `enrol_adele`, das genau für diesen Zweck — revidierbare,
nachvollziehbare, vom Lernpfad-Zustand abhängige Einschreibungen — entwickelt
wurde (ticket #486).

## Ursache

Zwei getrennte Lücken:

1. **Fehlender laufender Trigger.** Der einmalige Sweep bei Aktivitäts-Save
   deckt nur den Zustand zum Speicherzeitpunkt ab. Node-Kurse werden aber
   unabhängig vom Lernpfad weiterhin regulär ein- und ausgeschrieben (neue
   Kursteilnehmer, Fristablauf, manuelle Nacheinschreibung usw.), ohne dass
   `mod_adele` davon erfährt.
2. **Falsches Einschreibe-Backend für die Host-Kurs-Konsequenz.** Die
   Host-Kurs-Mitgliedschaft bei Fall 2/3 ist eine *Folge* der Node-Kurs-
   Mitgliedschaft, keine eigenständige Entscheidung — sie muss also genauso
   entzogen werden können, wie sie gewährt wurde. `enrol_manual` kennt diesen
   Zusammenhang nicht und entzieht nichts automatisch.

## Lösung

1. **Laufender, ereignisgesteuerter Trigger für Fall 2/3.** Ein neuer,
   site-weiter Abgleich reagiert auf `user_enrolment_created` **und**
   `user_enrolment_deleted` in *jedem* Kurs (nicht nur Host-Kursen): Für jede
   `{adele}`-Einbettung mit Option 2 oder 3, deren Lernpfad-Baum den
   betroffenen Kurs als (Start-)Node führt, wird der aktuelle Berechtigungs-
   status frisch neu bestimmt (nicht aus dem einzelnen Event abgeleitet, da ein
   Node-Kurs von mehreren Nodes gleichzeitig referenziert werden kann) und
   angewendet.
2. **Host-Kurs-Einschreibung über `enrol_adele` statt `enrol_manual`.** Für
   Fall 2/3 entsteht pro Einbettung × Lernpfad eine eigene, von `enrol_adele`
   verwaltete Instanz im Host-Kurs (`customint2` unterscheidet sie von den
   bestehenden Zielkurs-Instanzen desselben Lernpfads). Verlässt der Nutzer den
   letzten qualifizierenden Node-Kurs, wird die Host-Kurs-Einschreibung
   suspendiert — genau symmetrisch zum bestehenden Verhalten bei Zielkursen.
   Ohne installiertes `enrol_adele` bleibt der bisherige einmalige
   `enrol_manual`-Sweep als Fallback erhalten.
3. Fall 1 (Einschreibung direkt im Host-Kurs) ist von dieser Änderung nicht
   betroffen und bleibt wie bisher `enrol_manual` (Entscheidung F-7/A-10).

## Manuelles Testverfahren

### Laufender Trigger, Fall 2 (Startnode)

1. Lernpfad mit Startnode-Kurs A anlegen, mod_adele-Aktivität mit Option 2 in
   Host-Kurs H einbetten.
2. Nutzer NACH diesem Zeitpunkt in Kurs A einschreiben (nicht vorher).
3. Prüfen: Nutzer erscheint als Lernpfadnutzer und ist in H eingeschrieben.
4. Nutzer aus Kurs A austragen.
5. Prüfen: Host-Kurs-Einschreibung in H wird suspendiert (nicht gelöscht).

### Laufender Trigger, Fall 3 (irgendeine Node)

Wie oben, jedoch mit Option 3 und einem beliebigen Node-Kurs statt
zwingend dem Startnode.

### Host-Kurs-Einschreibung über enrol_adele

1. Nach Schritt 3 oben: in *Teilnehmer/innen* von H die Einschreibemethode der
   neuen Zeile prüfen — muss "ADELE (Lernpfadzugang): <Lernpfadname>" sein,
   nicht "Manuell eingeschriebene Nutzer/innen".
2. `enrol_adele` deinstallieren/deaktivieren, Schritte 1–3 wiederholen: Fallback
   auf `enrol_manual` greift unverändert.

## Upgrade-Anforderungen

Kein Datenbankschema betroffen. Bestehende, über `enrol_manual` erzeugte
Host-Kurs-Einschreibungen aus früheren Aktivitäts-Saves bleiben unangetastet
(keine Migration); erst der nächste Enrolment-Event auf einem Node-Kurs dieses
Nutzers legt bei Bedarf die neue `enrol_adele`-Instanz zusätzlich an.

## Automatisierte Tests

- Node-Kurs-Einschreibung nach Aktivitäts-Save löst Fall-2/3-Subscription aus
  (bisher ungetestet, da bisher nicht implementiert).
- Node-Kurs-Austragung suspendiert die Host-Kurs-Einschreibung, löscht sie
  nicht.
- Ein Nutzer mit Mitgliedschaft in zwei qualifizierenden Node-Kursen bleibt bei
  Austragung aus nur einem davon weiterhin aktiv im Host-Kurs.
- Ohne `enrol_adele`: Fallback auf den bisherigen `enrol_manual`-Sweep bleibt
  funktionsfähig.
- Fall 1 bleibt durch die Änderung unberührt (Regressionstest).

## Akzeptanzkriterien

- [ ] Eine Node-Kurs-Einschreibung, die nach dem Aktivitäts-Save erfolgt, löst
      bei Option 2/3 die Lernpfad-Subscription genauso aus wie eine, die vorher
      bestand.
- [ ] Eine Node-Kurs-Austragung entzieht bei Option 2/3 die Host-Kurs-Zugehörigkeit
      (Suspendierung), sofern kein anderer qualifizierender Node-Kurs mehr greift.
- [ ] Die Host-Kurs-Einschreibung bei Option 2/3 läuft über `enrol_adele`, sichtbar
      als eigene Einschreibemethode in der Teilnehmerliste.
- [ ] Ohne installiertes `enrol_adele` bleibt der bisherige `enrol_manual`-Sweep
      als Fallback funktionsfähig.
- [ ] Fall 1 (Host-Kurs-Einschreibung) verhält sich unverändert.
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
