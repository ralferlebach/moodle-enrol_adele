# Session-Start-Prompt

Zum Kopieren an den Anfang einer neuen Sitzung. Er stellt den Kontext her, damit
Claude ohne Rückfragen produktiv weiterarbeiten kann. Bei Bedarf den Abschnitt
„Aktueller Stand / Aufgabe" ersetzen.

---

```text
Projekt: ADELE-Lernpfad-Ökosystem für Moodle – drei zusammengehörige Plugins:
enrol_adele (Enrolment-Plugin, Eigentümer aller ADELE-Einschreibungen),
local_adele (Lernpfad-Logik + Vue3-Frontend), mod_adele (Aktivitätsmodul,
bettet einen Lernpfad in einen Host-Kurs ein).
Abhängigkeitsrichtung: mod_adele -> local_adele <- enrol_adele.
enrol_adele liest mod_adele NIEMALS direkt – der Zugriff läuft ausschließlich
über local_adele\enrol_state und die Tabelle local_adele_host_courses.

Sprache: Deutsch. Bitte durchgehend auf Deutsch antworten.
Code, Kommentare, Commit-Texte und technische Dokumentation: Englisch.

Arbeitsweise & Pfade:
- QUELLE (Arbeitskopien, werden ausgeliefert):
    /home/claude/work/moodle-enrol_adele-development
    /home/claude/work/moodle-local_adele-development
    /home/claude/work/moodle-mod_adele-development
- SPIEGEL (Wegwerf-Kopien im Moodle-Baum, gegen die getestet wird):
    /home/claude/moodle/enrol/adele, /home/claude/moodle/local/adele,
    /home/claude/moodle/mod/adele – nie dort entwickeln.
- Arbeitsstände holen (Branch development, ralferlebach-Forks):
    https://github.com/ralferlebach/moodle-enrol_adele/archive/refs/heads/development.zip
    https://github.com/ralferlebach/moodle-mod_adele/archive/refs/heads/development.zip
    https://github.com/ralferlebach/moodle-local_adele/archive/refs/heads/development.zip
- Issues liegen im UPSTREAM, nicht im Fork:
    https://github.com/Wunderbyte-GmbH/moodle-enrol_adele/issues
  Kommentare immer mitlesen – dort stehen mehrfach Richtungsentscheidungen des
  Auftraggebers, die dem Lösungsvorschlag im Issue-Text widersprechen.
- Aufbau der Umgebung: docs/prompt-templates/environment-setup.md.
- phpcs: /tmp/moodlecs/vendor/bin/phpcs --standard=moodle --severity=1
    --extensions=php .
  (Exit 0 = sauber; phpcbf zum Auto-Fix. Keine Sniff-Ausschlüsse – der
  Ausgangsstand ist 0 Fehler / 0 Warnungen und bleibt es.)

Verbindliche Disziplinen:
- Moodle-API nie aus dem Gedächtnis: immer gegen echten Core-Code oder ein
  reales funktionierendes Beispiel prüfen. Vor Berechtigungs- oder
  Benennungsänderungen klären: wer ruft das mit welchem Zugriff auf?
- Bug-Eigentum folgt dem Fehlerursprung: ein Behat-Navigationskonflikt wird in
  dem Test repariert, in dem er auftritt – nicht im anderen Plugin. Bereits
  verworfene Umbenennungen nicht erneut vorschlagen.
- Behat-Diagnose in CI ausschließlich aus Faildump-HTML/Screenshots und dem
  hochgeladenen Quellcode. Browser-Konsolenausgaben sind aus GitHub Actions
  nicht zu bekommen – nie danach fragen.
- amd/build/ muss committet sein, nie in .gitignore. Grunt aus dem
  Moodle-Wurzelverzeichnis laufen lassen, Ergebnis committen.
- Dynamischer Vue-Stringzugriff (store.state.strings.KEY) macht statische
  Analyse ungenutzter Sprachstrings unzuverlässig – Ergebnisse für local_adele
  immer mit Vorbehalt kennzeichnen.
- Gleiche-Tabelle-Unterabfrage: get_fieldset_sql() + delete_records_list()
  statt Subquery (MySQL-Fehler 1093).
- Enrol-Plugins müssen in db/install.php explizit aktiviert werden – sonst
  stiller Ausfall ohne jede Fehlermeldung.
- „Norway-Problem": on: in GitHub-Actions-YAML muss gequotet sein.
- LangFilesOrdering verlangt strikt alphabetische Sprachdatei-Schlüssel.
- GPL-Kommentarsyntax je Dateityp: JS/TS //-Block, Vue <!-- // ... -->.

Fachliche Kernbegriffe:
- KIND_TARGET (customint2=1): Kurs eines Lernpfad-Knotens. Berechtigung aus
  local_adele\enrol_state::get_entitled_courseids() (Node-Feedback-Status
  accessible oder completed).
- KIND_HOST (customint2=2): Kurs mit der mod_adele-Aktivität. Berechtigung
  derzeit nur ereignisgetrieben aus mod_adeles Observer.
- Instanzidentität: enrol='adele', courseid, customint1=Lernpfad-ID,
  customint2=KIND. Die mod_adele-Aktivität gehört NICHT zur Identität.
- participantslist: Kommaliste der Abo-Optionen – 1 = Host-Kurs-Mitgliedschaft
  trägt, 2 = Startknoten-Kurs trägt, 3 = beliebiger Knotenkurs trägt.
- hostenrolmentmode: visible / hidden / none; bei mehreren Einbettungen gilt
  „großzügigste Option gewinnt".
- A-4-Regel: Austragung aus einem tragenden Kurs prüft über
  enrol_adele\observer::is_user_carried(), ob noch eine Option trägt.
- Suspend-not-delete: nicht mehr Berechtigte werden suspendiert, nicht
  ausgetragen. Endgültiges Austragen nur über die purge_*-Methoden.

Auslieferung:
- Ausschließlich Patch-ZIPs, eines pro geändertem Plugin, nur geänderte oder
  neue Dateien, nach /mnt/user-data/outputs kopieren und present_files.
- $plugin->version und $plugin->release NUR bei funktionalen Änderungen
  anheben – nie für reine Test-, Doku- oder Aufräumänderungen. Im Zweifel
  nachfragen.
- Dokumentation ausschließlich eingebettet unter enrol_adele/docs/, nie als
  gesonderter Download. Einzige Ausnahme: GitHub-Issue-Entwürfe zum Kopieren.
- Abschlussbericht ehrlich, inklusive verbleibender Übergangszustände und
  verworfener Ansätze.

Sessions: ein Claude-Chat = eine Session, unabhängig vom Kalendertag. Protokoll
unter enrol_adele/docs/sessions/session-NNN.md mit fortlaufenden „Teil N"-
Abschnitten; mehrere Arbeitstage im selben Chat bleiben dieselbe Session.

Abhängigkeiten/CI: GitHub Actions, Matrix Moodle 4.5/5.0 x PHP 8.1-8.3 x
MariaDB/PostgreSQL. Offen: local_adele-CI installiert enrol_adele als
Part-14-Behelf, obwohl keine formale Abhängigkeit besteht (Alternative:
assertDebuggingCalled() in den betroffenen PHPUnit-Tests).

Dokumentation (enrol_adele/docs/): lastenheft.md (das WAS), pflichtenheft.md
(das WIE), arbeitsplan.md bzw. arbeitsplan-session-NNN.md (Reihenfolge),
sessions/session-NNN.md (Historie), issues/ (Entwürfe für außerhalb des
Auftrags liegende Punkte), prompt-templates/ (dieses Dokument und
environment-setup.md).

Nicht anfassen ohne Entscheidung: G.10 Capability-Redesign (Issue-Dokument
liegt vor).

Aktueller Stand / Aufgabe:
<hier den konkreten Auftrag bzw. den letzten Stand einsetzen; falls unklar,
zuerst version.php aller drei Plugins, die jüngste docs/sessions/session-NNN.md
und die offenen Upstream-Issues sichten>
```

---

## Hinweise zur Nutzung

- Der Block ist bewusst kompakt: er verweist auf die Detaildokumente, statt sie
  zu duplizieren. Wird ein konkretes Arbeitspaket bearbeitet, den passenden
  Absatz aus `arbeitsplan-session-NNN.md` zusätzlich anhängen.
- **Keine Versionsnummern hartkodieren.** `$plugin->version` und
  `$plugin->release` ändern sich je Sitzung und weichen zwischen den drei
  Plugins voneinander ab. Zu Sitzungsbeginn aus den drei `version.php` lesen,
  nicht aus dem Prompt oder aus dem Gedächtnis übernehmen. Dasselbe gilt für den
  Stand der Upstream-Issues: Titel, Labels und Kommentare frisch abrufen.
- Ist Verhalten seit dem letzten Stand unklar (neue CI-Logs, Meldung aus einer
  Live-Instanz), zuerst reproduzieren und gegen den echten Code prüfen, bevor
  Änderungen erfolgen.
- GitHub-Archiv-ZIPs sind **unvollständig, und zwar absichtlich**: alle drei
  Repositories tragen ein `.gitattributes` mit `export-ignore` für `.github/`,
  `docs/`, `tools/`, `Makefile`, `CHANGELOG.md` und die Punktdateien selbst.
  `git archive` und damit auch der GitHub-Download lassen diese Pfade weg — im
  Repository sind sie vorhanden. Aus dem Fehlen im entpackten Archiv also **nie**
  auf ein Fehlen im Repo schließen. Wer an CI oder Dokumentation arbeitet,
  braucht einen echten Clone oder einen Upload.
