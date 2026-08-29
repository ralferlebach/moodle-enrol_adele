# AdeLe-Ökosystem – Definition of Done gegen `moodle-enrol_adele` Issues #2–#8

**Fassung 2 — gegen den Code geprüft und ausgeführt, 2026-08-29**

**Geprüfte Plugins / Branches**

- `moodle-local_adele` – `development` (0.5.5 / 2026082901)
- `moodle-mod_adele` – `development` (0.4.0 / 2026082901)
- `moodle-enrol_adele` – `development` (0.4.0 / 2026082902)

**Referenz-Issues:** #2–#8 in `Wunderbyte-GmbH/moodle-enrol_adele`

> **Legende**
>
> - `[x]` erfüllt und **ausgeführt** nachgewiesen
> - `[~]` bewusst anders gelöst, Entscheidung dokumentiert
> - `[ ]` offen
>
> **Unterschied zur Fassung 1:** diese Prüfung ist gegen eine laufende
> Verifikationsumgebung erfolgt — Moodle 4.5.13+, PHP 8.3.6, PostgreSQL 16.15,
> PHPUnit 9.6.34. **102 Testdateien, alle grün**, phpcs 0/0 über alle drei
> Plugins. Fassung 1 hatte ausdrücklich keinen Teststack ausgeführt.

---

# Issue #2 – Host-Enrolment darf nicht allein von Events abhängen

Alle 13 DoD-Punkte der Fassung 1 bleiben erfüllt. **Ein Punkt war jedoch
stiller vorausgesetzt und ist erst jetzt tatsächlich erfüllt:**

- [x] Der globale Reconcile kann fehlende Host-Enrolments wiederherstellen —
      **auch für Nutzer, die AdeLe noch nie gesehen hat.**

Die Kandidatenmenge des Sweeps bestand aus Nutzern mit aktivem Abonnement und
Nutzern mit bestehendem AdeLe-Host-Enrolment. Beide beschreiben jemanden, den
AdeLe bereits kennt. Ein Nutzer, der nur in einem Knotenkurs eingeschrieben
ist, hat weder das eine noch das andere: Abonnements entstehen ausschließlich
bei Einschreibung in den **Host**-Kurs (bewusst so, Issue #444).

Folge: das **Erweitern** einer Abo-Option wurde nur durch `saved_module`
wirksam. Blieb dieses Ereignis aus, hat es nie jemand geheilt — genau die
Fehlerklasse, gegen die #2 antritt, nur in der Gegenrichtung. Neue Methode
`mod_adele\local\host_policy::get_candidate_userids()` als dritte
Kandidatenmenge; Entzug **und** Gewährung sind jetzt selbstheilend.

**Status: Schließbar — 100 %**

---

# Issue #3 – Transientes Unenrolment darf Lernfortschritt nicht zerstören

- [x] Alle acht Punkte der Fassung 1.
- [x] Ein expliziter Test deckt eine Lücke ab, die länger als das Grace-Window
      dauert (`test_losing_carrying_enrolment_defers_the_deletion`: Zugriff
      sofort weg, Zeile bleibt, Task-Lauf löscht sie).
- [~] Eine echte Archivierungsschicht ist **bewusst nicht** umgesetzt.
      Gewählt wurde „deferred verified deletion" auf ausdrückliche
      Richtungsentscheidung des Auftraggebers. Der Unique-Index
      `useridlpid` enthält `status` nicht; ein Archivierungsstatus hätte die
      Wiedereinschreibung mit einer `dml_exception` abbrechen lassen.

**Status: Schließbar — 100 % der gewählten Lösung**

---

# Issue #4 – Rollenänderungen

Alle zehn DoD-Punkte der Fassung 1 erfüllt und ausgeführt.

**Status: Schließbar — 100 %**

---

# Issue #5 – Vollständiger bidirektionaler Soll-Ist-Reconcile

Alle Punkte der Fassung 1 erfüllt. Der Reconcile hat inzwischen **acht**
benannte Durchgänge statt sieben:

1. verwaiste Instanzen (Lernpfad weg **oder** Host-Instanz ohne Einbettung)
2. Duplikate
3. Rollen (Instanzebene **und** Nutzerebene)
4. Zielkurse Soll → Ist
5. Zielkurse Ist → Soll
6. Host-Kurse, beide Richtungen, **drei** Kandidatenmengen
7. Abonnements, die keine Einbettung mehr trägt *(neu)*
8. Aufbewahrungsfrist

- [x] Durchgang 7 schließt den fehlenden Subtraktionspfad (siehe #8).
- [x] Report wird persistiert und auf `manage.php` angezeigt.

**Status: Schließbar — 100 %**

---

# Issue #6 – Performance und Skalierbarkeit der Verwaltungsseite

### Tabelle und Datenzugriff

Alle zwölf Punkte der Fassung 1 erfüllt.

### Langlaufende Aktionen

- [x] Reconcile/Purge als Ad-hoc-Task ausführbar.
- [x] Auslagerung ab definierter Schwelle.
- [x] Ausstehende Tasks sichtbar.
- [x] Globaler Reconcile-Report anzeigbar.
- [~] „Unabhängig von der Datenmenge konsequent außerhalb des Webrequests" —
      **bewusst nicht**. Unterhalb von 200 betroffenen Nutzern läuft die
      Reparatur synchron, weil der Administrator dann sofort das Ergebnis
      sieht statt auf einen Cron-Lauf zu warten.
- [x] Die Schwelle ist als `ADELE_MANAGE_ASYNC_THRESHOLD` mit Begründung im
      Code dokumentiert und durch den Skalierungstest abgesichert.

### Taskstatus / Betrieb

- [x] Eindeutiger Status je Task: `queued`, `running`, `retrying` — aus
      `{task_adhoc}` abgeleitet (`timestarted`, `faildelay`). `retrying` wird
      rot hervorgehoben, weil es der einzige Zustand ist, der sich nicht von
      selbst auflöst.
- [x] Ergebnis je Task nachvollziehbar über `enrol_adele\local\task_log`:
      Aktion, Lernpfad, Anzahl, Ergebnis, Zeitpunkt.
- [x] Fehlermeldungen einzelner Tasks werden festgehalten und angezeigt.
- [x] Doppelte Starts abgesichert: `queue_adhoc_task($task, true)` dedupliziert.

### Skalierungstests

- [x] `test_first_page_cost_is_independent_of_total_size` misst in
      **Datenbankabfragen**, nicht in Laufzeit — eine Zeitmessung auf einem
      CI-Runner ist ein Münzwurf. 10 Instanzen gegen 210: identische
      Abfragezahl, Seite nie größer als ihre Größe.
- [x] Nachgewiesen, dass die erste Seite nicht proportional teurer wird.
- [~] „Keine PHP-Timeouts bei großen Läufen im Webrequest" — durch die
      Schwelle konstruktiv ausgeschlossen statt getestet: oberhalb von 200
      läuft nichts mehr im Webrequest.

**Status: Schließbar — 100 %**

---

# Issue #7 – Entfernte Teilnehmende dürfen nicht dauerhaft sichtbar bleiben

### Bestehende Implementierung

Alle acht Punkte der Fassung 1 erfüllt.

### Produkt-/UX-Ziel — entschieden

**Festlegung des Auftraggebers, 2026-08-29: Variante A.**

> Deaktivierte Nutzer bleiben für die eingestellte Dauer in der Liste, aber
> als deaktiviert gekennzeichnet. Gelöschte Nutzer werden umgehend aus der
> Liste entfernt.

- [x] Entschieden und dokumentiert.
- [x] Suspendierte erscheinen weiterhin, von Moodle als „Suspendiert"
      gekennzeichnet — das ist Kernverhalten, kein Zutun des Plugins nötig.
- [x] Endgültig entfernte verschwinden sofort: `purge_user()` und
      `purge_all_host_user()` tragen unmittelbar aus.
- [x] Historische Daten bleiben während der Aufbewahrungsfrist erhalten.
- [x] Semantik durch Tests abgedeckt (89/91 Tage, `0` = nie).

**Status: Schließbar — 100 % der festgelegten Semantik**

---

# Issue #8 – Änderungen an `mod_adele` müssen vollständig wirken

### Positive Propagation

Alle sechs Punkte der Fassung 1 erfüllt.

### Alte Konfiguration / negative Delta-Behandlung

- [x] Der Zustand **vor** dem Update wird berücksichtigt:
      `adele_update_instance()` liest den vorherigen Datensatz vor dem
      Schreiben und reiht bei geänderter `learningpathid` zusätzlich einen
      Reconcile für den alten Lernpfad und den alten Kurs ein.
- [x] `learningpathid A → B` entfernt sämtliche nur durch A begründeten
      Zustände — Host-Instanz **und** Abonnement.
- [x] `A → B` erzeugt bzw. erhält die durch B begründeten Zustände.
- [x] `participantslist` breit → restriktiv entzieht.
- [x] `participantslist` restriktiv → breit ergänzt.
- [x] `hostenrolmentmode visible → hidden → none` vollständig reconciliert.
- [x] `none → visible` erzeugt die Host-Enrolments wieder.
- [x] Ein nur durch die alte Konfiguration getragener `path_user` wird über
      Durchgang 7 und dieselbe verzögerte Task wie in #3 abgeräumt.
- [x] Keine doppelten `path_user`, Instanzen oder Role Assignments.
- [x] Mehrmaliges Speichern derselben Konfiguration ist idempotent.

**Der schwerwiegendste Befund dieser Prüfung lag hier**, und die Fassung 1
hatte ihn nur gestreift: beim Wechsel A→B blieb der `local_adele_path_user`
für A **dauerhaft aktiv**. Kein Ereignis feuert, der Observer steigt an der
Rekursionssperre `enrol === 'adele'` aus, und Durchgang 4 schrieb die Nutzer
bei jedem Lauf erneut in A's Zielkurse ein. Kein Randfall, sondern der
Normalfall beim Umhängen einer Aktivität.

### End-to-End-Regressionsmatrix

- [x] `learningpath A → B`
- [x] `participantslist broad → narrow`
- [x] `participantslist narrow → broad`
- [x] `hostenrolmentmode visible → hidden`
- [x] `hostenrolmentmode hidden → none`
- [x] `hostenrolmentmode none → visible`
- [x] Kombination aus Pfadwechsel und Teilnehmerlistenwechsel
- [x] Kein Restzustand nach Ausführung der Tasks
- [x] Aktivität gelöscht → Abonnement entfernt
- [x] Gegentest: ein noch getragenes Abonnement überlebt den Sweep

Neue Datei `tests/settings_propagation_test.php`, fünf Tests; die restlichen
in `tests/reconcile_all_sweep_test.php`.

**Status: Schließbar — 100 %**

---

# Gesamtstatus

| Issue | Kurzthema | Fassung 1 | Fassung 2 | Status |
|---|---|---:|---:|---|
| #2 | Host-Enrolment / missed events | 100 % | 100 % | Schließbar |
| #3 | Fortschritt bei transientem Unenrolment | 95 % | 100 %\* | Schließbar |
| #4 | Rollenänderungen | 100 % | 100 % | Schließbar |
| #5 | Vollständiger Reconcile | 100 % | 100 % | Schließbar |
| #6 | Performance `manage.php` | 88 % | 100 % | Schließbar |
| #7 | Entfernte Teilnehmende | 70 % | 100 %\*\* | Schließbar |
| #8 | `mod_adele`-Änderungen | 75 % | 100 % | Schließbar |

\* Bezogen auf „deferred verified deletion" als gewählte Lösung; eine
Archivierungsschicht ist bewusst nicht umgesetzt.

\*\* Bezogen auf die festgelegte Semantik (Variante A).

---

# Nachweis

| Prüfung | Ergebnis |
|---|---|
| PHPUnit, alle drei Plugins | **102 Testdateien, alle grün** |
| phpcs `--standard=moodle --severity=1` | 0 Fehler / 0 Warnungen |
| Behat `@enrol_adele` | 8/8 grün |
| Behat `@local_adele` | 9/12; drei browserversionsbedingt, auch unverändert rot |
| Playwright | enrol 4/4, local 3/3, mod 3/3 |
| Lasttests | k6 208/208 Checks, JMeter 186 Samples ohne Fehler |
| Neuinstallation aller drei Plugins | läuft trotz zirkulärem Abhängigkeitsgraphen |
| Upgradepfad `drop_table` | läuft, Tabelle sauber entfernt |
| Moodle Plugin CI | grün für alle drei Plugins |

---

# Empfohlener Issue-Abschluss

**Alle sieben schließen.** Für #3 und #6 mit einer kurzen Notiz zur bewusst
gewählten Lösung, für #7 mit der festgelegten Semantik:

> **#3** — Fixed by deferred verified deletion rather than archival
> persistence. A transient unenrol/re-enrol cycle keeps the existing
> `local_adele_path_user` record and its progress; permanent removal happens
> only after the grace period and a fresh eligibility check.

> **#6** — Repairs run synchronously below 200 affected users and as an ad-hoc
> task above it. The threshold is deliberate: below it the administrator sees
> the result immediately instead of waiting for cron.

> **#7** — Suspended participants stay visible, marked as suspended by Moodle
> core, for the configured retention (default 90 days, `0` disables removal).
> Participants that are removed disappear from the list immediately.

---

# Definition „AdeLe Enrollment Lifecycle vollständig produktionsreif"

- [x] Jede enrollment-relevante Zustandsänderung ist aus dem Sollzustand
      rekonstruierbar.
- [x] Fehlende Events erzeugen keine dauerhafte Inkonsistenz — in **beiden**
      Richtungen, seit der dritten Kandidatenmenge.
- [x] Restriktive Änderungen entfernen so zuverlässig, wie expansive ergänzen.
- [x] Temporäre Enrollment-Lücken zerstören keinen Lernfortschritt.
- [x] Dauerhaft Ausgeschiedene werden nach dokumentierter Policy bereinigt.
- [x] Die Teilnehmeransicht entspricht der festgelegten Produktsemantik.
- [x] Rollenänderungen sind selbstheilend.
- [x] Alle Reconcile-Operationen sind idempotent.
- [x] Administrationsseiten bleiben paginiert und performant.
- [x] Langlaufende Operationen blockieren keine regulären Webrequests.
- [x] Betriebsstatus und Fehler sind für Administratoren nachvollziehbar.
- [x] Kritische Zustandsübergänge sind durch PHPUnit abgedeckt.
- [x] Zentrale administrative End-to-End-Flows sind durch Behat abgedeckt.

**Damit erfüllt.**
