[BUG] Konkurrierende Fall-2/3-Einbettungen desselben Lernpfads im selben Host-Kurs überschreiben sich gegenseitig

## Problem

Die Identität einer Host-Kurs-Instanz ist `(enrol='adele', courseid, customint1
= learningpathid, customint2 = KIND_HOST)` — bewusst **ohne** Bezug auf die
konkrete `mod_adele`-Aktivität. Das ist beabsichtigt (ein Host-Kurs soll für
denselben Lernpfad nicht mehrfach separat verwaltete Einschreibungen
bekommen), hat aber eine Kehrseite: Sind **mehrere** `mod_adele`-Aktivitäten
im selben Host-Kurs eingebettet, die denselben Lernpfad referenzieren (z. B.
eine mit Fall 2, eine zweite mit Fall 3 — oder, sobald das separat gemeldete
Sichtbarkeits-Feature existiert, unterschiedliche `hostenrolmentmode`-Werte),
teilen sich beide dieselbe Instanz — ohne dass eine Prioritätsregel festlegt,
wessen Entscheidung gilt.

`mod_adele_observer::sync_host_access_for_node_enrolment()` iteriert aktuell
**pro Embedding** und ruft `reconcile_host_user()` für jedes einzeln auf:

```php
foreach ($embeddings as $embedding) {
    ...
    \enrol_adele\local\reconciler::reconcile_host_user(
        (int) $embedding->learningpathid,
        (int) $embedding->course,
        $userid,
        $entitled   // pro Embedding einzeln berechnet
    );
}
```

Zielen zwei Embeddings auf **dieselbe** `(learningpathid, hostcourseid)`-
Kombination und liefern unterschiedliche `$entitled`-Werte (z. B. Embedding A
sagt „berechtigt", Embedding B sagt „nicht berechtigt", weil der Nutzer nur in
einem der beiden jeweils relevanten Node-Kurs-Sets sitzt), gewinnt schlicht
der letzte Aufruf in der Schleife — abhängig von der (unspezifizierten)
Rückgabereihenfolge von `$DB->get_records('adele', ...)`. Das Ergebnis ist
nicht deterministisch und nicht nachvollziehbar dokumentiert.

## Ursache

Die Aggregation über mehrere Embeddings fehlt. `reconcile_host_user()` wird
pro Embedding isoliert aufgerufen, obwohl mehrere Embeddings dieselbe
physische Instanz treffen können. Es gibt keine Regel, die festlegt, welche
Entscheidung bei einem Widerspruch Vorrang hat.

## Lösung

1. **Aggregation vor Anwendung statt Anwendung pro Embedding.**
   `sync_host_access_for_node_enrolment()` gruppiert zuerst alle betroffenen
   Embeddings nach `(learningpathid, hostcourseid)` und berechnet **eine**
   effektive Entscheidung pro Gruppe, bevor `reconcile_host_user()`
   überhaupt aufgerufen wird — nicht mehr ein Aufruf pro Embedding.
2. **Prioritätsregel: „großzügigste Option gewinnt".** Konsistent mit der
   bestehenden Philosophie des Projekts (ein Zielkurs bleibt aktiv, solange
   *irgendein* Knoten ihn noch gewährt — Entscheidung F-1/A-6): Berechtigung
   ist die **Vereinigung** aller Embeddings — sobald irgendeine Einbettung
   den Nutzer für berechtigt hält, gilt er als berechtigt, unabhängig davon,
   was andere Embeddings sagen.
3. **Zusammenspiel mit dem separat gemeldeten Sichtbarkeits-Feature
   (`hostenrolmentmode`):** Dieselbe Logik gilt dort analog — die
   großzügigste Sichtbarkeitsstufe gewinnt (`visible` > `hidden` > `none`),
   damit eine Einbettung, die bewusst sichtbaren Zugang möchte, nicht durch
   eine andere, restriktivere Einbettung ausgehebelt wird. Wird jenes Issue
   umgesetzt, sollte die Aggregation aus Punkt 1 direkt beide Dimensionen
   (Berechtigung UND Sichtbarkeitsstufe) gemeinsam auflösen.
4. Die Prioritätsregel wird zentral an einer Stelle dokumentiert (Pflichtenheft),
   nicht nur im Code, damit spätere Erweiterungen sie nicht versehentlich
   unterlaufen.

## Manuelles Testverfahren

### Grundkonflikt

1. Lernpfad LP mit Knoten A (Startnode) und Knoten B (kein Startnode)
   anlegen.
2. Im selben Host-Kurs H zwei `mod_adele`-Aktivitäten einbetten, beide mit
   Lernpfad LP: Aktivität X1 mit Fall 2, Aktivität X2 mit Fall 3.
3. Nutzer/in NUR in Knoten-B-Kurs einschreiben (nicht in A).
4. Erwartung: X1 (Fall 2, Startnode) würde allein „nicht berechtigt" ergeben,
   X2 (Fall 3, irgendeine Node) ergibt „berechtigt". Nach der Fix: Nutzer/in
   ist in H eingeschrieben (Vereinigung, X2 gewinnt), unabhängig von der
   Aufrufreihenfolge.

### Determinismus

1. Denselben Testfall mehrfach hintereinander auslösen (z. B. durch
   wiederholtes Aus- und Wiedereinschreiben in Knoten B).
2. Prüfen: Ergebnis ist bei jedem Lauf identisch — keine Abhängigkeit von der
   DB-Rückgabereihenfolge.

## Upgrade-Anforderungen

Keine Datenbankschema-Änderung. Reine Verhaltensänderung in
`mod_adele_observer::sync_host_access_for_node_enrolment()`.

## Automatisierte Tests

- Zwei Embeddings derselben `(learningpathid, hostcourseid)`-Kombination mit
  widersprüchlichen Berechtigungen: Ergebnis ist immer die großzügigere
  Option, unabhängig von der internen Verarbeitungsreihenfolge (Test mit
  bewusst vertauschter Reihenfolge der Testdaten).
- Zwei Embeddings mit **übereinstimmender** Berechtigung: kein
  überflüssiger doppelter `reconcile_host_user()`-Aufruf.
- Einzelnes Embedding (Regressionstest): Verhalten unverändert.
- Zwei Embeddings in **unterschiedlichen** Host-Kursen (kein echter
  Konflikt): beide werden unabhängig korrekt behandelt.

## Akzeptanzkriterien

- [ ] Mehrere Embeddings desselben Lernpfads im selben Host-Kurs führen zu
      einem deterministischen, nicht reihenfolgeabhängigen Ergebnis.
- [ ] Die Prioritätsregel („großzügigste Option gewinnt") ist sowohl im Code
      als auch im Pflichtenheft dokumentiert.
- [ ] Einzelembeddings verhalten sich unverändert.
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
