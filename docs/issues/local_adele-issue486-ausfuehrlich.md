[IMPROVEMENT] Eigene Einschreibemethode für ADELE (enrol_adele) — ausführliche Fassung

> Ausführliche Neufassung von #486. Ersetzt dessen knappe Stichpunktliste
> durch eine vollständige Spezifikation dessen, was inzwischen gebaut wurde
> und warum. Verwandte, aus der Umsetzung entstandene Einzeltickets: #501
> (Duplikat-Absicherung, als Nebenwirkung gelöst), #477 (verwaiste
> Lernpfadnutzer nach Kursaustragung, durch dieses Vorhaben behoben), sowie
> die separat eingereichten Tickets zu Fall 2/3 (laufender Trigger,
> Host-Kurs-Sichtbarkeit, Priorisierung bei Mehrfacheinbettung).

## Problem

Zielkurs-Einschreibungen, die ein ADELE-Lernpfad erzeugt, liefen bislang über
`enrol_manual` — dieselbe Einschreibemethode, die auch Lehrkräfte für
händische Einschreibungen benutzen. Drei konkrete Folgeprobleme:

1. **Keine Eigentümerschaft.** Eine von ADELE erzeugte Einschreibung ist von
   einer händisch erzeugten nicht unterscheidbar. Es gibt keine Datenspur,
   die zeigt, *warum* ein Nutzer in einem Kurs eingeschrieben ist.
2. **Keine Deaktivierung bei Zugangsverlust.** Schließt sich ein Knoten
   wieder (z. B. weil eine Zugangsbedingung nicht mehr erfüllt ist), bleibt
   die `enrol_manual`-Einschreibung im Zielkurs unverändert aktiv — es gibt
   keinen Mechanismus, der sie deaktiviert.
3. **Keine Löschung bei Austragung aus dem Lernpfad oder bei
   Lernpfad-Löschung.** Verlässt ein Nutzer den Lernpfad (Austragung aus dem
   Hostkurs, keine tragende Alternative mehr) oder wird der Lernpfad selbst
   gelöscht, bleiben sämtliche Zielkurs-Einschreibungen unverändert bestehen
   — dauerhaft verwaist. Symptom davon: #477 (manuell aus dem Kurs entfernte
   Nutzer erscheinen weiterhin als Lernpfadnutzer, weil nichts die
   Konsequenz zieht).

Zusätzlich verschärfte die bestehende Datenmodellierung das Problem: Die
User-Path-Identität war ursprünglich an den *Hostkurs* gebunden
(`user_id + course_id + learning_path_id`), sodass derselbe Lernpfad in
mehreren Kursen eingebettet zu **mehreren, unabhängig verwalteten**
User-Path-Datensätzen für denselben Nutzer führte (#433). Ein späterer
Reparaturversuch für eine verwandte Race Condition beim Anlegen dieser
Datensätze (#501) verstärkte dieses Modell zunächst sogar (Unique-Index auf
dem Dreier-Tripel), bevor die Kursbindung im Zuge dieses Vorhabens
konsequent entfernt wurde (`user_id + learning_path_id`, kursunabhängig).

## Ursache

`local_adele` und `mod_adele` hatten keine eigene Instanz zur Verwaltung von
Einschreibungen und griffen deshalb direkt auf `enrol_manual` zurück
(`relation_update::enrol_user_into_node()`, `node_completion.php`,
`mod_adele/classes/observer.php`). Ohne dedizierte Instanz gibt es keinen
Ort, an dem sich Herkunft, Gültigkeit und Lebenszyklus einer Einschreibung
festhalten ließen — jede der drei oben genannten Lücken ist eine direkte
Folge davon.

## Lösung

### Architektur: zustandslose Reconciliation, keine eigene Tabelle

`enrol_adele` besitzt bewusst **kein eigenes Datenmodell** (keine Grant-
Tabelle, kein Log). Stattdessen vergleicht eine Reconciliation-Funktion bei
jedem Lauf den aktuellen lokal_adele-Zustand (welche Kurse ein Nutzer laut
Knotenstatus gerade betreten darf) gegen die tatsächlichen
`user_enrolments` und gleicht ab — einschreiben, reaktivieren oder
suspendieren, jeweils idempotent. Begründung: keines der vier untersuchten
Referenz-enrol-Plugins (`coursecompleted`, `autoenrol`, `oss`,
`campusonline`) materialisiert Gründe in einer eigenen Tabelle, und der
Vue3-Editor vergibt Knoten-IDs nach Löschung neu — persistierte
Knoten-Referenzen wären also fragil gewesen.

### Instanz-Identität

Eine Einschreibeinstanz gehört zu genau einem Paar aus Lernpfad und Kurs:
`enrol = 'adele'`, `courseid`, `customint1 = learningpathid`. Ein zweites
Unterscheidungsmerkmal (`customint2`) trennt zwei unabhängige Arten:

- **Zielkurs-Instanzen** (`KIND_TARGET`): Kurse, die ein Lernpfad-Knoten
  gewährt. Herkunft der Berechtigung: `local_adele`s Knoten-Feedback-Status.
- **Host-Kurs-Instanzen** (`KIND_HOST`): der Kurs, der die
  `mod_adele`-Aktivität einbettet, für die Einschreibeoptionen 2/3
  („Startnode"/„irgendeine Node"). Herkunft der Berechtigung:
  Node-Kurs-Mitgliedschaft, von `mod_adele` ermittelt.

### Die drei ursprünglichen Probleme, gelöst

1. **Eintragen in Kurse** — `reconciler::reconcile_user()` schreibt einen
   Nutzer in jeden Kurs ein, den ein offener Knoten aktuell gewährt.
2. **Deaktivieren bei gesperrter Node** — derselbe Abgleich suspendiert die
   Einschreibung, sobald kein Knoten den Kurs mehr gewährt (Datenverlust
   vermieden: Suspendieren, nicht Löschen).
3. **Löschen bei Austragen aus dem LP oder LP-Löschung** —
   `reconciler::purge_user()` (Einzelnutzer, ausgelöst durch das
   A-4-Regelwerk) und `reconciler::purge_learning_path()` (gesamter
   Lernpfad, ausgelöst bei dessen Löschung) entfernen die Einschreibungen
   vollständig über `delete_instance()`/`unenrol_user()` — nie durch
   direktes Löschen von `{enrol}`-Zeilen (dokumentiertes Anti-Pattern,
   hinterlässt sonst verwaiste `user_enrolments`).

### Regelwerk für Austragung aus dem Hostkurs (A-4)

Verliert ein Nutzer die Mitgliedschaft im ursprünglichen Hostkurs, prüft ein
Observer, ob eine andere Einbettung (Fall 1/2/3) ihn weiterhin trägt.
Suspendierung zählt als „noch Mitglied" (Fall 4/A-8) — nur eine echte
Austragung löst die Prüfung aus. Trägt keine Option mehr, wird die
Lernpfad-Mitgliedschaft entfernt und alle Einschreibungen (Ziel- **und**
Host-Kurs) abgeräumt.

### Ohne anderweitige Einschreibungen zu beeinflussen

`enrol_adele` fasst ausschließlich seine eigenen Instanzen an. Bestehende
`enrol_manual`-, Self- oder Cohort-Einschreibungen bleiben in jedem Szenario
unangetastet — Voraussetzung dafür ist gerade die eigene Instanz-Identität
(Punkt „Instanz-Identität" oben), die eine klare Trennung erst ermöglicht.

## Manuelles Testverfahren

### Lebenszyklus Zielkurs

1. Lernpfad mit Knoten A → Kurs KA anlegen, Nutzer/in einschreiben, sodass
   Knoten A zugänglich wird.
2. Prüfen: Nutzer/in ist in KA eingeschrieben, Einschreibemethode ist
   „Lernpfad-Einschreibung", nicht „Manuell".
3. Zugangsbedingung so ändern, dass Knoten A wieder gesperrt ist.
4. Prüfen: Einschreibung in KA ist suspendiert, nicht gelöscht.
5. Bedingung zurücksetzen: dieselbe Einschreibung wird reaktiviert (keine
   neue `user_enrolment`-Zeile).

### Austragung aus dem Hostkurs (A-4)

1. Nutzer/in wird über Fall 1 in den Lernpfad subscribed.
2. Aus dem Hostkurs austragen, keine andere Option trägt ihn/sie.
3. Prüfen: Lernpfad-Mitgliedschaft und alle zugehörigen Zielkurs-
   Einschreibungen sind entfernt.

### Lernpfad-Löschung

1. Lernpfad mit mehreren aktiven Nutzer/innen löschen.
2. Prüfen: alle zugehörigen `enrol_adele`-Instanzen (Ziel- und Host-Kurs)
   sind entfernt; parallele `enrol_manual`-Einschreibungen in denselben
   Kursen bleiben unberührt.

### Koexistenz

1. Kurs mit sowohl einer `enrol_adele`- als auch einer
   `enrol_manual`-Einschreibung für denselben Nutzer (unterschiedliche
   Quelle).
2. `enrol_adele`-Einschreibung entfernen (z. B. via Lernpfad-Löschung).
3. Prüfen: die `enrol_manual`-Einschreibung bleibt vollständig unberührt.

## Upgrade-Anforderungen

`enrol_adele` selbst installiert keine eigene Tabelle (Privacy-Provider
bleibt dauerhaft `null_provider`). Die Identitätsmigration in `local_adele`
(Entfernen von `course_id` aus der User-Path-Identität, neuer Unique-Index
`user_id + learning_path_id`) ist Voraussetzung und separat migriert (siehe
lokal_adele-`db/upgrade.php`).

## Automatisierte Tests

- Zielkurs-Lebenszyklus: einschreiben, suspendieren, reaktivieren,
  Idempotenz bei wiederholtem Lauf.
- Geteilter Zielkurs bleibt aktiv, solange irgendein Knoten ihn noch
  gewährt.
- Harte Löschung (Lernpfad, Einzelnutzer) räumt vollständig ab, ohne andere
  Lernpfade oder Einschreibemethoden zu berühren.
- A-4-Regelwerk: Optionen 1/2/3, Rekursionsschutz, Suspendierung zählt als
  tragend.
- Host-Kurs-Lebenszyklus (Fall 2/3): einschreiben, suspendieren,
  reaktivieren, Nicht-Kollision mit einer Zielkurs-Instanz im selben Kurs.

## Akzeptanzkriterien

- [ ] Zielkurs-Einschreibungen laufen über `enrol_adele`, erkennbar als
      eigene Einschreibemethode.
- [ ] Ein gesperrter Knoten führt zur Suspendierung, nicht zur Löschung, der
      zugehörigen Einschreibung.
- [ ] Verlassen des Lernpfads oder dessen Löschung entfernt alle
      zugehörigen Einschreibungen vollständig.
- [ ] Andere Einschreibemethoden (manuell, selbst, Cohort) bleiben in jedem
      Szenario unberührt.
- [ ] Ein geteilter Zielkurs bleibt aktiv, solange irgendein Knoten ihn noch
      gewährt.
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
