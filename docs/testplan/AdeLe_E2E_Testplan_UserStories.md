# AdeLe – systematischer E2E-Testplan auf Basis von User Stories

## 1. Ziel

Dieser Testplan beschreibt die browserbasierte End-to-End-Prüfung des AdeLe-Ökosystems aus `local_adele`, `mod_adele` und `enrol_adele`.

Als verbindliche Fixture-Basis dient:

- https://github.com/ralferlebach/adele-test.git

Geprüft werden vollständige Nutzungsketten:

```text
Rolle/Berechtigung
→ Benutzeraktion
→ Editor-/Kurskonfiguration
→ Lernpfadzustand
→ Laufzeitbewertung
→ Rückmeldung
→ Lernerstand/Prozent
→ Einschreibung oder Entzug
→ realer Kurszugriff
```

Der Schwerpunkt liegt auf Abläufen, die nicht bereits vollständig durch Unit-, PHPUnit- oder bestehende Behat-Tests abgesichert sind.

---

# 2. Grundprinzip der E2E-Prüfung

Eine User Story gilt nur dann als bestanden, wenn die Wirkung auf allen relevanten Ebenen konsistent ist.

## A. Benutzeroberfläche

- richtige Seite erreichbar,
- richtige Bedienelemente vorhanden,
- richtiger Lernpfad-/Node-Zustand,
- keine JS-/PHP-Fehler.

## B. Rückmeldung

- i-Symbol,
- Feedback-Buttons,
- Lernerstandsmeldung,
- erwarteter Text,
- richtige Kurs-/Knotennamen,
- richtige Datums-/Zeitwerte,
- keine unverarbeiteten Platzhalter.

## C. Prozentwerte

- konkreter erwarteter Prozentwert,
- mathematisch konsistent mit der tatsächlich erforderlichen Lernleistung,
- richtige Änderung nach Completion/Override,
- alternative Wege korrekt berücksichtigt.

## D. Moodle-Einschreibung

- richtige Person,
- richtiger Kurs,
- richtige Rolle,
- aktiv/suspendiert/entfernt gemäß Fachlogik.

## E. Realer Zugriff

Eine Datenbankzeile oder Teilnehmerlistenanzeige genügt nicht. Die Testperson versucht den Zielkurs tatsächlich zu öffnen.

## F. Persistenz

Nach Reload, Logout/Login und erneutem Öffnen muss der fachliche Zustand fortbestehen.

## G. Negative Kontrolle

Mindestens eine Kontrollperson darf den Effekt nicht erhalten.

---

# 3. Technische Voraussetzungen: Issues #574 und #575

## 3.1 Issue #574 – stabile Test-Hooks

Issue:

https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues/574

### Bewertung

**Für die vollständige, robuste Umsetzung dieses E2E-Testplans praktisch erforderlich.**

Issue #574 verlangt unter anderem:

- stabile `data-testid` für Lernpfade,
- stabile `data-testid` für Nodes,
- maschinenlesbare fachliche Node-Zustände über `data-status`,
- eindeutig adressierbare Editorfelder,
- stabile Accessible Names für Icon-only-Controls.

### Warum notwendig?

Die E2E-Suite soll fachliche Zustände prüfen und darf dafür nicht auf folgende fragile Methoden zurückgreifen:

- `nth()`,
- „zweite Tabellenzeile“,
- CSS-Layoutklassen,
- Vue-generierte IDs,
- reine Farb-/Iconinterpretation,
- Mauskoordinaten zur fachlichen Identifikation.

Für viele Tests muss eindeutig prüfbar sein:

```text
genau dieser Node
→ hat genau diesen fachlichen Zustand
```

Beispiele:

- `accessible`,
- `completed`,
- `locked`.

### Durch #574 besonders betroffene Suites

- `editor-reference-path.spec.ts`
- `access-conditions.spec.ts`
- `timed-access.spec.ts`
- `runtime-feedback.spec.ts`
- `learning-progress.spec.ts`
- `manual-progress-override.spec.ts`
- `alternative-routes.spec.ts`
- `live-path-reconfiguration.spec.ts`
- `path-completion.spec.ts`

### Entscheidung

**#574 sollte vor der vollständigen Implementierung dieser Suites umgesetzt werden.**

Einzelne Smoke-Tests können bereits vorher existieren, die fachliche Kernabdeckung sollte aber nicht auf instabilen Selektoren aufgebaut werden.

---

## 3.2 Issue #575 – Barrierefreiheit

Issue:

https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues/575

### Bewertung

**Für rein funktionale E2E-Tests nicht vollständig blockierend, aber fachlich und technisch sehr empfehlenswert vor #574.**

Issue #575 sieht unter anderem vor:

- Accessible Names für Nodes,
- semantisch korrekte Rollen,
- Labels für Controls,
- Tastaturbedienbarkeit,
- Statusmeldungen,
- axe-core-Prüfungen.

### Bedeutung für Playwright

Die gewünschte Selektorhierarchie lautet:

1. `getByRole()`
2. `getByLabel()`
3. stabiler `data-testid`
4. nur ausnahmsweise stabile Moodle-Core-ID

Wenn #575 zuerst umgesetzt wird, werden viele zusätzliche Test-Hooks aus #574 überflüssig oder auf echte fachliche Zustände beschränkt.

### Entscheidung

Empfohlene Reihenfolge:

```text
#575
→ #574
→ vollständige E2E-Suite
```

#575 ist zwingend erforderlich für zusätzliche Accessibility-Suites wie:

- `accessibility-overview.spec.ts`
- `accessibility-editor.spec.ts`
- `accessibility-runtime.spec.ts`

---

# 4. Fixture-Basis

Verbindliche Basis:

https://github.com/ralferlebach/adele-test.git

Zu verwenden sind:

- vorhandene Testkurse `T01` bis `T20`,
- vorhandene weitere Lern-/Nutzungskurse,
- vorhandene Testpersonen,
- vorhandene Lernpfade,
- vorhandene importierte Pfaddefinitionen.

## Referenzpfad „Linear A1“

Als kanonischer Editor- und Laufzeitpfad wird insbesondere `Linear A1` verwendet.

Mindestens:

```text
T01 → T02 → T03
```

Der Pfad enthält bereits geeignete Elemente für:

- Kursabschluss,
- Vorgängerbedingungen,
- Zeitrestriktionen,
- kombinierte Bedingungen,
- Feedback.

## Zwei Fixture-Modi

### Referenz-Fixture

Importierter Pfad zur Laufzeitprüfung.

### Editor-Sollbild

Derselbe oder ein vergleichbarer Pfad wird in einem E2E-Test vollständig per Drag&Drop nachgebaut.

Damit wird verhindert, dass die zentrale Autorenfunktion durch importierte Testdaten umgangen wird.

---

# 5. Rollenmodell

## Administrator

Darf alles.

## AdeLe-Manager

Darf im Lernpfad-Editor alles und alle Lernpfade bearbeiten.

## Lehrkraft in einem AdeLe-Kurs

Wird eine Lehrkraft in einen AdeLe-Kurs als Lehrkraft eingeschrieben, erhält sie Zugriff auf den Editor als AdeLe-Assistent und kann eigene Lernpfade erstellen.

## Kollaborator

Jede Person kann einem konkreten Lernpfad als Kollaborator hinzugefügt werden.

Sie erhält:

- Editorzugriff,
- Bearbeitungszugriff auf genau den freigegebenen Lernpfad,
- keine automatischen globalen Managerrechte.

## Teilnehmer

Kann den eigenen Lernpfad und die jeweils freigegebenen Lernangebote nutzen.

---

# 6. Testkette R – Rollen, Eigentümerschaft und Kollaboration

**Primär:** `local_adele`

## R1 – Administrator

**User Story:**  
Als Administrator möchte ich alle AdeLe-Funktionen benutzen können.

### Prüfung

- Lernpfadübersicht,
- beliebigen Lernpfad öffnen,
- neuen Pfad anlegen,
- Editor bedienen,
- Kollaboratoren verwalten,
- speichern,
- erneut öffnen.

---

## R2 – AdeLe-Manager

**User Story:**  
Als AdeLe-Manager möchte ich sämtliche Lernpfade vollständig bearbeiten können.

### Varianten

- eigenen Pfad,
- fremden Pfad,
- Kollaborator hinzufügen,
- Kollaborator entfernen.

---

## R3 – Lehrkraft wird AdeLe-Assistent

**User Story:**  
Als Lehrkraft in einem AdeLe-Kurs möchte ich den Editor sehen und eigene Lernpfade anlegen können.

### Zustandskette

```text
keine Lehrkrafteinschreibung
→ kein Assistentenzugriff

Lehrkrafteinschreibung
→ Editorzugriff
→ eigener Lernpfad anlegbar

Lehrkrafteinschreibung entfernen
→ Assistentenzugriff entfällt,
  sofern keine andere Berechtigungsquelle existiert
```

---

## R4 – Kollaborator

**User Story:**  
Als Eigentümer möchte ich einer beliebigen Person Bearbeitungszugriff auf einen einzelnen Lernpfad geben können.

### Ablauf

1. Pfad A anlegen.
2. Kollaborator hinzufügen.
3. Kollaborator neu anmelden.
4. Pfad A bearbeiten.
5. fremden Pfad B öffnen versuchen.
6. Kollaborator entfernen.
7. neu anmelden.
8. Zugriff erneut prüfen.

---

## R5 – mehrere parallele Berechtigungsgründe

| Rechte | entzogen | Erwartung |
|---|---|---|
| Manager + Kollaborator | Kollaboration | globaler Zugriff bleibt |
| Assistent + Kollaborator | Kollaboration | Assistentenzugriff bleibt |
| Assistent + Kollaborator | Assistent | Kollaboratorzugriff bleibt |
| nur Kollaborator | Kollaboration | Zugriff entfällt |
| nur Assistent | Assistent | Zugriff entfällt |
| Eigentümer + Kollaborator | Kollaboration | Eigentümerzugriff bleibt |

---

## R6 – Eigentümerschaft übertragen

Varianten:

- alter Eigentümer ohne weitere Rolle,
- alter Eigentümer als Manager,
- alter Eigentümer zusätzlich Kollaborator.

---

# 7. Testkette E – Drag&Drop-Editor

**Primär:** `local_adele`

## E1 – Referenzpfad nachbauen

**User Story:**  
Als AdeLe-Manager möchte ich einen vollständigen Lernpfad ausschließlich über den grafischen Editor erstellen können.

### Ablauf

1. neuen Pfad anlegen,
2. T01 per Drag&Drop,
3. T02 per Drag&Drop,
4. T03 per Drag&Drop,
5. Knoten verbinden,
6. Abschlussbedingungen setzen,
7. Vorgängerbedingung setzen,
8. Zeitbedingung hinzufügen,
9. UND-Verknüpfung konfigurieren,
10. Feedback konfigurieren,
11. speichern,
12. Editor verlassen,
13. erneut öffnen.

### Persistenzprüfung

- Knoten,
- Kanten,
- Reihenfolge,
- Conditions,
- Feedback,
- Zeitwerte.

---

## E2 – logische Kombinationen

Systematisch prüfen:

- A,
- A UND B,
- A ODER B,
- `(A UND B) ODER C`,
- `A UND (B ODER C)`,
- Condition hinzufügen und entfernen,
- Änderungen ohne Speichern verwerfen.

---

# 8. Testkette C – Zugangsbedingungen

**Primär:** `local_adele`  
**Cross-Plugin:** `enrol_adele`

## C1 – Vorgänger

Varianten:

- 1 von 1,
- 1 von 2,
- 2 von 2,
- Vorgänger + Zeit.

Je Zustand:

- Node-Status,
- i-Symbol,
- Feedback,
- Prozent,
- Enrollment,
- realer Zugriff.

---

## C2 – Kurszugehörigkeit

```text
nicht im Referenzkurs
→ Ziel gesperrt

Referenzkurseinschreibung
→ Ziel zugänglich
→ Zielkurs-Einschreibung aktiv

Referenzkurseinschreibung entfernt
→ Ziel wieder gesperrt
→ Enrollment suspendiert/entfernt
```

---

## C3 – manuelle Freigabe

```text
nicht freigegeben
→ freigegeben
→ Freigabe entfernt
```

Nach jedem Schritt:

- UI,
- Text,
- Prozent,
- Enrollment,
- realer Zugriff.

---

# 9. Testkette T – Zeitabhängige Bedingungen

**Primär:** `local_adele`  
**Cross-Plugin:** `enrol_adele`

Zeit wird im Testbetrieb gezielt manipuliert.

Reales Warten ist nicht zulässig.

## T1 – Startzeit

Für `t0`:

- `t0 - 1 s`,
- `t0`,
- `t0 + 1 s`.

---

## T2 – Zeitfenster

Für `ts` und `te`:

- vor Start,
- exakt Start,
- im Fenster,
- unmittelbar vor Ende,
- exakt Ende,
- nach Ende.

Grenzwertsemantik dokumentieren.

---

## T3 – relative Zeit

Teilnehmer A und B beginnen zu unterschiedlichen Zeiten.

Prüfen:

- individuelle Zeitbasis,
- kein globales Fehlverhalten.

---

## T4 – Zeit UND Vorgänger

| Vorgänger | Zeit | Erwartung |
|---:|---:|---|
| nein | nein | gesperrt |
| ja | nein | gesperrt |
| nein | ja | gesperrt |
| ja | ja | zugänglich |

Feedback muss jeweils genau die noch fehlende Voraussetzung nennen.

---

## T5 – Zeit ODER fachliche Bedingung

| Fachbedingung | Zeit | Erwartung |
|---:|---:|---|
| nein | nein | gesperrt |
| ja | nein | zugänglich |
| nein | ja | zugänglich |
| ja | ja | zugänglich |

---

# 10. Testkette F – Feedback und Sprache

**Primär:** `local_adele`

## F1 – i-Symbol

Je Bedingung prüfen:

- vor Erfüllung,
- teilweise erfüllt,
- erfüllt,
- nach Entzug.

Erwartung:

- korrekter Text,
- richtiger Kurs/Knoten,
- richtige Datums-/Uhrzeit,
- keine Rohplatzhalter.

Nicht sichtbar sein dürfen:

```text
{item}
{node_name}
{start_date}
{end_date}
```

---

## F2 – Feedback-Buttons

Prüfen:

- richtiger Button,
- richtiger Text,
- korrekte Checkmark-/Statusdarstellung,
- unmittelbare Aktualisierung nach Zustandswechsel.

---

## F3 – kombinierte Conditions

- A UND B: beide fehlen,
- A UND B: nur A fehlt,
- A UND B: nur B fehlt,
- A ODER B: beide fehlen,
- A ODER B: eine erfüllt,
- verschachtelte Kombinationen.

---

# 11. Testkette L – Lernerstand und Prozentwerte

**Primär:** `local_adele`

## L1 – linearer Pfad

Für einen Pfad mit vier fachlich erforderlichen Schritten:

- 0/4,
- 1/4,
- 2/4,
- 3/4,
- 4/4.

Je Zustand prüfen:

- Lernerstandsmeldung,
- konkreten Prozentwert,
- sichtbaren Fortschritt.

Nicht nur `%` suchen, sondern den erwarteten Wert assertieren.

---

## L2 – alternative Lernwege

Nicht erforderliche Alternativknoten dürfen den Prozentwert nicht fälschlich reduzieren.

100 % müssen erreichbar sein, wenn der fachlich gültige Weg vollständig abgeschlossen ist.

---

## L3 – manueller Abschluss

```text
regulärer Zustand
→ manueller Abschluss
→ Abschluss zurücknehmen
```

Je Zustand:

- Meldung,
- Prozent,
- Folgeknoten,
- Gesamtfortschritt.

---

## L4 – Master-Abschluss

Master-Abschluss aktivieren und wieder entfernen.

Prüfen:

- Node-Abschluss,
- Prozent,
- Pfadfortschritt,
- Folgefreigaben,
- Gesamtabschluss.

---

# 12. Testkette O – manuelle Abschlüsse und Master-Checkboxen

**Primär:** `local_adele`  
**Cross-Plugin:** `enrol_adele`

## O1 – manueller Abschluss

**User Story:**  
Als berechtigte Lehrkraft möchte ich einen Knoten manuell als abgeschlossen setzen und die Entscheidung zurücknehmen können.

Prüfen:

- Node-Zustand,
- Feedback,
- Prozent,
- Folgezugang,
- Enrollment.

---

## O2 – Master-Zugang

**User Story:**  
Als berechtigte Person möchte ich die Master-Zugangs-Checkbox bedienen können.

Varianten:

1. Bedingung falsch + Master-Zugang aus,
2. Bedingung falsch + Master-Zugang an,
3. Bedingung wahr + Master-Zugang an,
4. Master-Zugang wieder aus,
5. Kombination mit Zeitbedingung.

### Zentrale Assertion

**Master-Zugang darf nicht automatisch Abschluss bedeuten.**

---

## O3 – Master-Abschluss

**User Story:**  
Als berechtigte Person möchte ich die Master-Abschluss-Checkbox bedienen können.

Prüfen:

- fachlicher Abschluss,
- Prozent,
- Folgeconditions,
- Gesamtfortschritt,
- `mod_adele`-Completion, falls gekoppelt.

---

## O4 – Kombination beider Master-Schalter

| Master-Zugang | Master-Abschluss | Erwartung |
|---:|---:|---|
| 0 | 0 | normale Logik |
| 1 | 0 | Zugang überschrieben, kein automatischer Abschluss |
| 0 | 1 | Abschlusswirkung gemäß Implementierung |
| 1 | 1 | beide Wirkungen konsistent |

Zugang und Abschluss dürfen nicht semantisch vermischt werden.

---

# 13. Testkette D – Diagnose/CAT

**Primär:** `local_adele`

Zwei Teilnehmer mit unterschiedlichen Ergebnissen.

Grenzwert:

- `x - ε`,
- `x`,
- `x + ε`.

Prüfen:

- korrekte Route,
- Feedback,
- Prozent,
- Einschreibung.

---

# 14. Testkette A – alternative Routen

**Primär:** `local_adele`

Beispiel:

```text
          ┌─ T02 ─┐
T01 ──────┤       ├──── T04
          └─ T03 ─┘
```

Varianten:

- nur T02,
- nur T03,
- beide,
- keiner.

Prüfen:

- T04 korrekt,
- nicht gewählte Alternative nicht fälschlich Pflicht,
- Prozent korrekt,
- Feedback korrekt.

---

# 15. Testkette M – laufenden Lernpfad ändern

**Primär:** `local_adele`  
**Cross-Plugin:** `enrol_adele`

## M1 – Restriktion hinzufügen

Teilnehmer hat bereits Zugang.

Autor fügt zusätzliche Restriktion hinzu.

Prüfen:

- Recompute,
- UI,
- Feedback,
- Prozent,
- Enrollment-Suspendierung,
- realer Zugriffsverlust.

## M2 – Restriktion entfernen

Vorher gesperrter Teilnehmer erhält anschließend:

- Zugang,
- richtige Meldung,
- aktive Einschreibung,
- realen Kurszugriff.

---

# 16. Testkette H – Kurseinbindung

**Primär:** `mod_adele`  
**Cross-Plugin:** `local_adele`, `enrol_adele`

## H1 – Host-Kursteilnehmer in Lernpfad übernehmen

**User Story:**  
Als Lehrkraft möchte ich einen Lernpfad in einen Moodle-Kurs einbinden und dessen Teilnehmer in die vorgesehenen Eingangskurse übernehmen.

Prüfen:

- Aktivität erstellt,
- Lernpfad ausgewählt,
- Teilnehmerquelle gesetzt,
- Teilnehmer A wird tatsächlich in Eingangskurs eingeschrieben,
- Teilnehmer A kann Eingangskurs öffnen,
- negative Kontrollperson nicht.

---

## H2 – Teilnehmerquellen

Jede angebotene Quelle:

- einzeln,
- fachlich relevante Kombinationen.

Jeweils:

- positive Person,
- negative Person.

---

## H3 – Host-Enrolment-Modi

Varianten:

- sichtbar,
- verborgen,
- keine automatische Host-Einschreibung.

Prüfen:

- Teilnehmerliste,
- Enrollmentstatus,
- realer Zugriff.

---

# 17. Testkette P – Lernerdateneinsicht in Kurseinbindungen

**Primär:** `mod_adele`

Mindestens Teilnehmer A und B mit unterschiedlichen Lernständen.

## P1 – eigene Daten

Teilnehmer A sieht:

- eigenen Status,
- eigenen Fortschritt,
- eigenen Prozentwert.

Er sieht nicht:

- Namen,
- Fortschritt,
- Prozent,
- Detaildaten von B.

---

## P2 – Übersicht aller zugeordneten Teilnehmer

Bei entsprechend konfigurierter Result Visibility sieht eine berechtigte Person:

- A und B,
- deren korrekte Statuswerte,
- deren korrekte Prozentwerte.

---

## P3 – rollenabhängige Einsicht

Prüfen:

- Administrator,
- AdeLe-Manager,
- Lehrkraft,
- Assistent,
- Kollaborator,
- Teilnehmer.

Die erwartete Sichtbarkeit folgt den implementierten Capabilities.

---

## P4 – Datenschutz-Negativtest

Teilnehmer versucht:

- fremde Zeile direkt anzusprechen,
- fremde Detailansicht aufzurufen,
- direkte URL.

Erwartung:

kein Zugriff auf fremde Lerndaten.

---

# 18. Testkette Z – Gesamtabschluss

**Primär:** `local_adele`, `mod_adele`

## Z1 – regulärer Abschluss

Vor letztem erforderlichem Schritt:

- < 100 %,
- Pfad offen,
- Aktivität nicht abgeschlossen.

Nach letztem Schritt:

- 100 %,
- Pfad abgeschlossen,
- `mod_adele` abgeschlossen, sofern Completion-Kopplung aktiv.

---

## Z2 – alternative Route

100 % und Gesamtabschluss müssen erreichbar sein, obwohl ein fachlich nicht mehr erforderlicher Alternativzweig unbesucht bleibt.

---

## Z3 – manueller Abschluss

Prüfen:

- Prozent,
- Pfadabschluss,
- Aktivitätsabschluss.

---

## Z4 – Master-Abschluss

Analog zu Z3.

---

# 19. Kombinationsstrategie

Eine vollständige kartesische Kombination aller Einstellungen wäre unverhältnismäßig.

Deshalb:

## Regel 1 – jeder Einzelwert

Jede auswählbare Option mindestens einmal.

## Regel 2 – risikoreiche Paare

Immer gemeinsam testen:

- Zeit + Vorgänger,
- Zeit + ODER,
- Diagnose + ODER,
- manueller Abschluss + Prozent,
- Master-Zugang + Zeit,
- Master-Abschluss + Prozent,
- Teilnehmerquelle + Host-Enrolment,
- Kollaboration + zweite Rollenquelle,
- Pfadänderung + aktive Einschreibung.

## Regel 3 – reversible Bedingungen

Wenn möglich:

```text
false → true → false
```

## Regel 4 – Grenzwerte

```text
unterhalb → exakt → oberhalb
```

---

# 20. Zeitsteuerung

Zeitabhängige Tests müssen die Testzeit kontrolliert verändern können.

Bevorzugt:

- Mock-Time,
- definierte Test-Clock,
- gezielte Testzeitsteuerung.

Nicht zulässig:

- reales langes Warten,
- CI-abhängige unscharfe Zeitfenster.

---

# 21. Selektorvertrag

Reihenfolge:

1. `getByRole(..., {name, exact: true})`
2. `getByLabel(..., {exact: true})`
3. stabiler fachlicher `data-testid`
4. nur falls zwingend: stabile Moodle-Core-ID

Nicht zulässig:

- `nth()`,
- CSS-Layoutklassen,
- Vue-Zufalls-IDs,
- Farbauswertung,
- Iconinterpretation,
- Textsuche ohne fachlichen Scope.

---

# 22. Vorgeschlagene Playwright-Suites

```text
roles-and-collaboration.spec.ts
editor-reference-path.spec.ts
access-conditions.spec.ts
timed-access.spec.ts
runtime-feedback.spec.ts
learning-progress.spec.ts
manual-progress-override.spec.ts
diagnostic-routing.spec.ts
alternative-routes.spec.ts
live-path-reconfiguration.spec.ts
host-course-participants.spec.ts
host-enrolment-modes.spec.ts
result-visibility.spec.ts
path-completion.spec.ts
```

Nach #575 zusätzlich:

```text
accessibility-overview.spec.ts
accessibility-editor.spec.ts
accessibility-runtime.spec.ts
```

---

# 23. Plugin-Zuordnung

| Bereich | Primäres Plugin | Cross-Plugin |
|---|---|---|
| Rollen/Kollaboration | `local_adele` | Moodle Core |
| Drag&Drop | `local_adele` | – |
| Conditions | `local_adele` | `enrol_adele` |
| Zeit | `local_adele` | `enrol_adele` |
| Feedback | `local_adele` | – |
| Lernerstand/Prozent | `local_adele` | `mod_adele` |
| manuelle Overrides | `local_adele` | `enrol_adele` |
| CAT | `local_adele` | CAT, `enrol_adele` |
| Alternativrouten | `local_adele` | `enrol_adele` |
| Live-Rekonfiguration | `local_adele` | `enrol_adele` |
| Kurseinbindung | `mod_adele` | `local_adele`, `enrol_adele` |
| Lernerdateneinsicht | `mod_adele` | `local_adele` |
| Gesamtabschluss | `mod_adele` | `local_adele` |

---

# 24. Umsetzungsreihenfolge

## Phase 0 – Testbarkeit

1. #575 umsetzen bzw. semantische UI festlegen.
2. #574 anschließend ergänzen.
3. `adele-test` um `enrol_adele` ergänzen.
4. Testpersonen/Rollen standardisieren.
5. kontrollierte Testzeit bereitstellen.

## Phase 1 – Kern

- Rollen/Kollaboration,
- Drag&Drop,
- Zugang,
- Enrollment,
- Entzug.

## Phase 2 – Logik und Zeit

- Condition-Kombinationen,
- zeitabhängige Bedingungen,
- Alternativrouten,
- CAT.

## Phase 3 – Rückmeldung und Fortschritt

- i-Symbol,
- Feedback-Buttons,
- Lernerstand,
- Prozentwerte,
- manuelle Abschlüsse,
- Master-Zugang,
- Master-Abschluss.

## Phase 4 – Kurseinbindung

- Teilnehmerquellen,
- Host-Enrolment,
- Result Visibility,
- Datenschutz,
- Aktivitätsabschluss.

---

# 25. Definition of Done

- [ ] Fixtures reproduzierbar.
- [ ] alle drei AdeLe-Plugins installiert.
- [ ] stabile Selektoren.
- [ ] keine positionsabhängigen Selektoren.
- [ ] realer Drag&Drop-Pfad wird aufgebaut.
- [ ] Rollenwechsel geprüft.
- [ ] Kollaborator hinzufügen/entfernen geprüft.
- [ ] kombinierte Berechtigungsquellen geprüft.
- [ ] Conditions inklusive Entzug geprüft.
- [ ] Zeitgrenzen geprüft.
- [ ] i-Symbol-Texte geprüft.
- [ ] Feedback-Button-Texte geprüft.
- [ ] Prozentwerte konkret geprüft.
- [ ] manueller Abschluss geprüft.
- [ ] Master-Zugang geprüft.
- [ ] Master-Abschluss geprüft.
- [ ] Master-Zugang × Master-Abschluss geprüft.
- [ ] reale Einschreibungen geprüft.
- [ ] Suspendierung/Entzug geprüft.
- [ ] realer Kurszugriff geprüft.
- [ ] Lernerdateneinsicht geprüft.
- [ ] Datenschutz-Negativtests geprüft.
- [ ] Gesamtabschluss geprüft.
- [ ] `mod_adele` Completion geprüft.
- [ ] negative Kontrollpersonen vorhanden.
- [ ] reversible Zustände `false → true → false`.
- [ ] CI reproduzierbar grün.

---

# 26. Gesamtentscheidung zu #574 und #575

## #574

**Ja – für die vollständige Umsetzung des hier beschriebenen funktionalen E2E-Testplans praktisch notwendig.**

Ohne #574 bleiben zentrale Assertions zu Node-Zuständen und Editorobjekten fragil.

## #575

**Für die rein funktionale Suite nicht zwingend vollständig notwendig.**

Für eine robuste, semantisch saubere und barrierefreie Gesamtimplementierung jedoch sehr empfehlenswert und idealerweise zuerst umzusetzen.

## Empfohlene Reihenfolge

```text
#575
→ #574
→ E2E-Suite
```

#574 soll danach nur noch diejenigen fachlichen Test-Hooks ergänzen, die sich nicht bereits sauber aus einer barrierefreien Oberfläche über Rollen und Labels adressieren lassen.

---

# 27. Zentraler E2E-Vertrag

Freigabe:

```text
Bedingung erfüllt
→ Node fachlich zugänglich
→ Rückmeldung korrekt
→ Prozentwert korrekt
→ Enrollment aktiv
→ Teilnehmer kann Kurs öffnen
```

Entzug:

```text
Bedingung entfällt
→ Node gesperrt
→ Rückmeldung nennt den Grund
→ Prozent wird fachlich neu berechnet
→ Enrollment suspendiert/entfernt
→ aktiver Kurszugriff entfällt
```

Manuelle Zustände:

```text
Master-Zugang ≠ Master-Abschluss
```

Beide Funktionen werden unabhängig voneinander und in Kombination getestet.

Damit stellt die E2E-Suite sicher, dass Benutzeroberfläche, Rückmeldung, Lernfortschritt, Lernpfadlogik und reale Moodle-Berechtigungen jederzeit denselben fachlichen Zustand repräsentieren.
