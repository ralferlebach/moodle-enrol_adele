# [BUG] Grant- und Entzugs-Logik für Host-Kurs-Zugang verwenden unterschiedliche Definitionen von „tragend" (G.11)

## Problem

Zwei Funktionen entscheiden über denselben Host-Kurs-Zugang, aber mit
unterschiedlicher Logik:

- **Grant-Seite** (`mod_adele::is_user_entitled_to_host_via_option()`,
  aktiv über `sync_host_access_for_node_enrolment()`): zählt jede
  Einschreibung in einem qualifizierenden Node-Kurs — einschließlich
  Einschreibungen, die `enrol_adele` selbst angelegt hat.
- **Entzugs-Seite** (`enrol_adele::has_foreign_enrolment()`, über
  `is_user_carried()`): schließt für Option 2/3 explizit alle
  `enrol_adele`-eigenen Einschreibungen aus — mit dem dokumentierten Grund
  „otherwise access would keep itself alive circularly".

## Ursache

Die Entzugs-Seite wurde bewusst gegen zirkuläre Selbstversorgung
abgesichert; dieselbe Absicherung fehlt auf der Grant-Seite. Dadurch kann
theoretisch ein Zustand entstehen, in dem ein Nutzer Host-Zugang **erhält**
auf Basis einer `enrol_adele`-eigenen Node-Kurs-Einschreibung, diesen
Zugang aber bei der nächsten Neubewertung nicht mehr **behalten** würde,
weil die Entzugs-Seite dieselbe Einschreibung nicht mitzählt — ein
unklarer, nicht dokumentierter Grenzfall.

## Lösung

Eine einzige, gemeinsam genutzte Definition von „tragender Zugang"
einführen, die von beiden Seiten verwendet wird (z. B. eine neue,
öffentliche Methode `effective_course_membership::user_has_qualifying_enrolment()`),
inklusive der Frage, ob `enrol_adele`-eigene Einschreibungen zählen. Diese
Definition sollte auch die in G.4 beschriebene `timeend`/`e.status`-Prüfung
einschließen.

## Manuelles Testverfahren

### Vorbereitung

Lernpfad mit zwei Knoten, die denselben Node-Kurs referenzieren, Option 2
oder 3 aktiv, Host-Kurs-Einbettung vorhanden.

### Testschritte

1. Nutzer erhält Node-Kurs-Zugang ausschließlich über eine
   `enrol_adele`-eigene Einschreibung (z. B. weil ein anderer Knoten
   bereits denselben Kurs gewährt hat).
2. Prüfen, ob Host-Kurs-Zugang gewährt wird.
3. Die ursprüngliche, fremde Einschreibung entfernen, sodass nur noch die
   `enrol_adele`-eigene Node-Kurs-Einschreibung besteht.
4. Reconcile auslösen, Host-Kurs-Zugang erneut prüfen.

### Aktuelles Ist-Verhalten

Schritt 2: Zugang gewährt. Schritt 4: Zugang möglicherweise entzogen,
obwohl sich am Node-Kurs-Zugang nichts geändert hat — inkonsistent.

### Erwartetes Soll-Verhalten

Dieselbe Einschreibung wird in beiden Bewertungen gleich behandelt.

## Automatisierte Tests

- Grant- und Entzugs-Prüfung liefern für denselben Zustand dasselbe
  Ergebnis (Symmetrietest über mehrere Fallkombinationen).
- Regressionstest für die ursprüngliche Zirkularitäts-Absicherung auf der
  Entzugs-Seite bleibt grün.

## Akzeptanzkriterien

- [ ] Eine gemeinsame Definition von „tragend" für Grant und Entzug.
- [ ] `timeend`/`e.status` (G.4) Teil dieser gemeinsamen Definition.
- [ ] Zirkularitäts-Absicherung bleibt erhalten.
