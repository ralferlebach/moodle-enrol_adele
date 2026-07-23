# Lastenheft `enrol_adele`

**Dokumentstatus:** Fassung 2.0 (ersetzt Entwurf 1.0)
**Stand:** 2026-07-16, nach Klärung der Rückfragen F-1 bis F-10 (Session 001, Teil 2)
**Auftraggeber:** Ralf Erlebach
**Betroffene Komponenten:** `local_adele`, `mod_adele`, `enrol_adele` (neu)

Dieses Dokument beschreibt, **was** gefordert ist. Das *wie* steht im
[Pflichtenheft](pflichtenheft.md), die Umsetzungsreihenfolge im
[Arbeitsplan](arbeitsplan.md).

---

## 1. Ausgangslage

ADELE bildet Lernpfade ab, deren Knoten auf Moodle-Kurse verweisen. Sobald ein
Knoten zugänglich wird, sollen Lernende in die hinterlegten Kurse gelangen; wird
er unzugänglich, sollen sie den Zugang wieder verlieren.

Die Analyse der Codebasis (`local_adele` 0.4.2, `mod_adele` 0.1.4, vier
unveröffentlichte `mod_adele`-Branches sowie vier Referenz-enrol-Plugins) zeigt
folgende strukturelle Defekte:

### 1.1 Einschreibungen laufen über `enrol_manual`

| Fundstelle | Verhalten |
|---|---|
| `local_adele/classes/relation_update.php:1104–1146` | Zielkurs-Einschreibung beim Öffnen eines Knotens über die `manual`-Instanz des Kurses |
| `local_adele/classes/node_completion.php:70–112` | dieselbe Mechanik beim Knotenabschluss |
| `mod_adele/classes/observer.php:144–160` | Hostkurs-Einschreibung (Startknoten-Nutzer) über `enrol_manual` |

Eine von ADELE erzeugte Einschreibung ist damit von einer händisch erzeugten
**nicht unterscheidbar**. ADELE besitzt keine eigene Datenspur.

### 1.2 Es gibt keine Ausschreibung

Eine Volltextsuche über `local_adele` nach `unenrol` liefert (Tests ausgenommen)
**keinen Treffer**. Zugangsverlust an einem Knoten, Löschung eines Lernpfads und
Austragung aus dem einbettenden Kurs haben heute keinerlei Wirkung auf die
Zielkurs-Einschreibungen. Beim Löschen eines Lernpfads
(`learning_paths::delete_learning_path()`, Zeilen 377–396) bleiben alle von ihm
erzeugten Einschreibungen dauerhaft und verwaist bestehen.

### 1.3 Die Nutzer-Lernpfad-Zuordnung ist an einen Hostkurs gebunden

`local_adele_path_user` führt `course_id` als Teil der fachlichen Identität
(`enrollment.php:65–101`, `139–160`). Derselbe Nutzer kann für denselben
Lernpfad mehrere User-Paths erhalten, sobald der Pfad in mehreren Kursen
eingebettet ist. Der unveröffentlichte Branch
`ralferlebach-fix-enrolment-issue` von `mod_adele` hat die Korrektur bereits
vorweggenommen (Aufruf ohne `courseid`), ist aber gegen `local_adele` main
inkompatibel und deshalb nicht integrierbar — die local_adele-seitige Änderung
liefert erst dieses Vorhaben.

### 1.4 Austragungen werden nicht beobachtet

Weder `\core\event\user_enrolment_deleted` noch `user_enrolment_updated` haben
in einem der beiden Plugins einen Observer. Die Eintragungsseite existiert
(mod_adele-Observer auf `user_enrolment_created`), enthält aber einen Fehler:
Bei Mehrfachauswahl der Einschreibeoptionen (z. B. „1,2") wird die rohe
Kommaliste mit `== '1'` verglichen und die Live-Propagation unterbleibt
(`mod_adele/classes/observer.php:80`).

---

## 2. Ziel

Ein eigenes Einschreibe-Plugin `enrol_adele` wird alleiniger Eigentümer aller
**Zielkurs**-Einschreibungen, die ein ADELE-Lernpfad erzeugt. Der Soll-Zustand
(wer gehört in welche Kurse) bleibt vollständig in `local_adele` definiert;
`enrol_adele` gleicht den Ist-Zustand idempotent dagegen ab (Reconciliation).
Hostkurs-Einschreibungen bleiben ausdrücklich `enrol_manual` (Entscheidung F-7).

---

## 3. Anforderungen

### 3.1 Kernanforderungen (Auftrag vom 2026-07-16)

| ID | Anforderung | Priorität |
|---|---|---|
| **A-1** | Öffnet sich ein Knoten eines Lernpfads, wird der/die Lernende über die Einschreibemethode des Lernpfads in den jeweiligen Kurs eingeschrieben (sofern noch keine solche Einschreibung vorliegt) bzw. reaktiviert (sofern sie vorliegt, aber deaktiviert ist). | Muss |
| **A-2** | Schließt sich ein Knoten wieder, wird die Lernpfad-Einschreibung des Nutzers im zugehörigen Kurs **deaktiviert** (suspendiert) — jedoch nur, wenn kein anderer noch offener Knoten desselben Lernpfads denselben Kurs gewährt (Entscheidung F-1). | Muss |
| **A-3** | Wird ein Lernpfad gelöscht, werden alle Einschreibungen dieses Lernpfads in allen Kursen **gelöscht**. | Muss |
| **A-4** | Wird ein Nutzer aus einem einbettenden Kurs ausgetragen (`user_enrolment_deleted`), in dem ein `mod_adele` mit der Einschreibeoption „Mitgliedschaft in diesem Kurs" (Option 1) liegt, werden die Lernpfad-Einschreibungen des Nutzers in den Zielkursen **gelöscht** — sofern keine andere Einbettung oder Option den Nutzer weiterhin trägt. Bei den Optionen „Startknoten" (2) und „irgendein Knoten" (3, aus Branch `fix-enrolment-issue`) erfolgt **keine** Löschung. | Muss |
| **A-5** | Das Plugin listet alle Lernpfade mit ihren Einschreibungen auf und bietet je Lernpfad zwei Aktionen an: **Neu berechnen** (Reconciliation aller Nutzer gegen den Soll-Zustand) und **Hart löschen** (alle Einschreibungen des Lernpfads in allen Kursen entfernen). | Muss |

### 3.2 Präzisierungen aus den Rückfragen

| ID | Festlegung | Quelle |
|---|---|---|
| **A-6** | Geteilte Zielkurse: Die Einschreibung bleibt aktiv, solange *irgendein* Knoten des Lernpfads den Kurs noch gewährt. | F-1 |
| **A-7** | Asymmetrie ist gewollt: Knoten gesperrt → **deaktivieren**; Lernpfad gelöscht oder Zugang über `mod_adele` verloren → **löschen**. | F-2 |
| **A-8** | Eine Suspendierung im Hostkurs ist **keine** Austragung und löst keine Löschung aus. | F-4 |
| **A-9** | Kein Grant-Datenmodell, keine eigene Log-Tabelle: zustandslose Reconciliation; Nachvollziehbarkeit über das Moodle-Standard-Logging (Events). | F-6 |
| **A-10** | Hostkurs-Einschreibungen (Startknoten-/Any-Node-Nutzer in den einbettenden Kurs) bleiben `enrol_manual`. | F-7 |
| **A-11** | Keine doppelten Einstellungen: Die Rolle für Zielkurs-Einschreibungen wandert zu `enrol_adele` (`enrol_adele/roleid`); `local_adele/enroll_as_setting` wird obsolet, da seine beiden einzigen Verwendungsstellen (`node_completion.php:106`, `relation_update.php:1143`) auf `enrol_adele` umgestellt werden. | F-8 |
| **A-12** | Bestandsdaten (alte, faktisch von ADELE stammende `manual`-Einschreibungen) bleiben unangetastet. Keine Migration. | F-9 |
| **A-13** | Duplizierte oder wiederhergestellte Kurse enthalten keine ADELE-Einschreibungen. Da Moodle nicht wiederherstellbare Einschreibungen je nach Einstellung in `manual` umwandelt, muss das Plugin die Restore-Hooks aktiv als „überspringen" implementieren. | F-10 |
| **A-14** | Der Vergleichsfehler `participantslist == '1'` auf der rohen Kommaliste in `mod_adele/classes/observer.php` wird im Zuge der Arbeiten behoben. | F-3 |
| **A-15** | Die Architektur berücksichtigt die dritte Einschreibeoption „irgendein Knoten" aus dem Branch `ralferlebach-fix-enrolment-issue`, da diese Anforderung wiederkommen wird. | F-3 |

### 3.3 Qualitäts- und Rahmenanforderungen

| ID | Anforderung |
|---|---|
| **L-Q-01** | Moodle-Plugin-Konventionen; Installation über die Standard-Plugin-Installation. |
| **L-Q-02** | Moodle 4.1 LTS bis 5.1, PHP 8.1+. |
| **L-Q-03** | CI über `moodle-plugin-ci`, grün, Code-Checker mit null Warnungen. |
| **L-Q-04** | Privacy-API vollständig bedient (durch A-9 genügt dauerhaft ein `null_provider`, solange keine eigene Tabelle existiert). |
| **L-Q-05** | GPL v3 oder später. |
| **L-Q-06** | Sprachdateien mindestens `en` und `de`. |
| **L-Q-07** | Kein Datenverlust: Deaktivierung nimmt nur den Zugang; auch die harte Löschung entfernt keine Bewertungs- oder Aktivitätsdaten aus der Datenbank (Moodle-Standardverhalten von `unenrol_user()`). |
| **L-Q-08** | `local_adele` und `mod_adele` bleiben ohne installiertes `enrol_adele` voll funktionsfähig (optionale Kopplung über `enrol_get_plugin('adele')` mit Null-Prüfung). |
| **L-Q-09** | Alle Vorgänge sind idempotent; die Reconciliation ist selbstheilend und jederzeit wiederholbar. |

---

## 4. Abgrenzung

Nicht Gegenstand dieses Vorhabens:

* Umbau der Lernpfad-Logik selbst (Restriktionen, Completion, Vue-Editor).
* Migration von Bestandsdaten (A-12).
* Die Gruppenzuordnung (`node_completion::enrol_user_group()`) bleibt vorerst
  unangetastet.
* Rückportierung in die Upstream-Repositories von Wunderbyte.
* Die drei historischen `mod_adele`-Branches `adding-privacy-api`,
  `fix-linking-issues`, `fixing-ux-issue-mod_form` — ihr Inhalt ist im heutigen
  master bereits enthalten oder überholt (siehe Session 001, Teil 2, Abschnitt 2).

---

## 5. Lieferobjekte

| Nr. | Lieferobjekt | Status |
|---|---|---|
| 1 | Lastenheft, Pflichtenheft, Sitzungsprotokolle | fortgeschrieben (Fassung 2.0) |
| 2 | Arbeitsplan | **geliefert (Session 001, Teil 2)** |
| 3 | `enrol_adele` als installierbarer Stub, CI-fähig | geliefert (Teil 1) |
| 4 | `enrol_adele` 0.1.1: Instanzverwaltung, Reconciliation-Engine, A-4-Observer, Task | **geliefert (Teil 3)** |
| 5 | `local_adele` 0.4.3 (Branch `ralferlebach-enrol-plugin`) | **geliefert (Teil 3)** |
| 6 | `mod_adele` 0.1.5 (Branch `ralferlebach-enrol-plugin`), inkl. Option 3 und Bugfix A-14 | **geliefert (Teil 3)** |
| 7 | `enrol_adele` 0.1.2: Verwaltungsseite (A-5), Restore-Hooks (A-13), eigene Events | offen |
| 8 | Testplan und Abnahme | offen |

---

## 6. Risiken

| Risiko | Wirkung | Gegenmaßnahme |
|---|---|---|
| Zirkuläre Plugin-Abhängigkeit (`local_adele` ↔ `mod_adele` besteht bereits) | Installation schlägt fehl | `enrol_adele` deklariert nur `local_adele`; Rückrichtung ungeprüft-optional. |
| Reconciliation liest den Soll-Zustand aus `local_adele_path_user.json`; ändert sich dessen Struktur, bricht der Abgleich | Falsche Ein-/Ausschreibungen | Soll-Zustands-Ermittlung als eine einzige, getestete Funktion in `local_adele` kapseln (dort liegt die JSON-Hoheit), `enrol_adele` konsumiert nur deren Ergebnis. |
| Harte Löschung bei aktivem User-Path: Die nächste Reconciliation würde sofort wieder einschreiben | Löschung wirkungslos | Umgesetzt (R-1): Der Observer löscht zuerst den User-Path-Datensatz, dann die Einschreibungen; beim Lernpfad-Löschen wird zuerst gepurgt, dann archiviert. |
| Rekursion bei Selbsteinbettung (Zielkurs = Hostkurs) | Endlosschleife im Observer | Observer ignoriert Events, deren Einschreibeinstanz `enrol = 'adele'` ist. |
| Kein „Node closed"-Event: Schließung entsteht nur durch Neuberechnung | Suspendierung verzögert sich | Reconciliation hängt an denselben Recompute-Pfaden (Events + zeitgesteuerte Ad-hoc-Tasks) plus Scheduled Task als Sicherheitsnetz. |
| Suspendierte statt gelöschter Einschreibungen füllen Kurslisten | Unübersichtlichkeit | Bewusst akzeptiert (A-7); Bereinigung über Verwaltungsseite (A-5). |
