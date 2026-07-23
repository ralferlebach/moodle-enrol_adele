[BUG] Fall-2/3-Host-Kurs-Einschreibungen werden nicht ausgetragen, wenn der Lernpfad verlassen wird

## Problem

`enrol_adele\reconciler::purge_user()` — aufgerufen, sobald ein Nutzer den
Lernpfad über das A-4-Regelwerk verliert (Austragung aus dem ursprünglichen
Host-Kurs bei Fall 1, ohne dass eine andere Option ihn weiterträgt) — räumt
ausschließlich **Zielkurs**-Instanzen (`KIND_TARGET`) ab:

```php
public static function purge_user(int $learningpathid, int $userid): void {
    ...
    foreach (instance_manager::get_instances($learningpathid, instance_manager::KIND_TARGET) as $instance) {
        ...
        $plugin->unenrol_user($instance, $userid);
    }
}
```

`reconciler::purge_host_user()` (Host-Kurs-Pendant) existiert bereits als
Baustein, wird aber **an keiner Stelle im Produktivcode aufgerufen** — nur aus
dem PHPUnit-Test heraus. Verlässt ein Nutzer den Lernpfad, bleibt er in jedem
Host-Kurs, in den ihn Fall 2/3 eingeschrieben hat, weiterhin aktiv
eingeschrieben — die Host-Kurs-Einschreibung „überlebt" die Lernpfad-
Mitgliedschaft, die sie ursprünglich begründet hat.

**Konkretes Szenario:** Lernpfad LP ist in Host-Kurs H1 (Fall 1) und Host-Kurs
H2 (Fall 3) eingebettet. Nutzer/in wird aus H1 ausgetragen, keine andere
Option trägt sie/ihn weiter → `local_adele_path_user`-Zeile wird gelöscht,
Zielkurs-Einschreibungen werden entfernt (A-4 funktioniert wie vorgesehen) —
aber die Einschreibung in H2 (durch Fall 3 entstanden) bleibt unangetastet
bestehen, obwohl der Lernpfad für diesen Nutzer nicht mehr existiert.

## Ursache

`purge_user()` wurde für das ursprüngliche A-4-Regelwerk entworfen, bevor es
Host-Kurs-Instanzen überhaupt gab (0.1.1). Mit der Einführung von `KIND_HOST`
(0.1.2) wurde der Host-Kurs-Baustein (`purge_host_user()`) zwar ergänzt, aber
nie an den bestehenden A-4-Aufrufpfad angeschlossen — dokumentierter offener
Punkt E-10 im Pflichtenheft.

Zusätzlich fehlt eine Methode, die **alle** Host-Instanzen eines Nutzers für
einen Lernpfad auf einmal abräumt: `purge_host_user()` nimmt eine konkrete
`$hostcourseid` entgegen, verlangt also bereits zu wissen, in welchem
Host-Kurs geprüft werden soll. Beim Verlassen des Lernpfads ist aber nicht nur
der ursprünglich auslösende Host-Kurs betroffen, sondern potenziell mehrere
gleichzeitig (Mehrfacheinbettung).

## Lösung

1. Neue Methode `reconciler::purge_all_host_user(int $learningpathid, int
   $userid): void` — iteriert über **alle** `KIND_HOST`-Instanzen des
   Lernpfads (nicht nur eine bekannte), analog zu `purge_user()`:

   ```php
   public static function purge_all_host_user(int $learningpathid, int $userid): void {
       global $CFG, $DB;
       require_once($CFG->libdir . '/enrollib.php');
       $plugin = enrol_get_plugin('adele');
       if (!$plugin) {
           return;
       }
       foreach (instance_manager::get_instances($learningpathid, instance_manager::KIND_HOST) as $instance) {
           if ($DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $userid])) {
               $plugin->unenrol_user($instance, $userid);
           }
       }
   }
   ```

2. `enrol_adele\observer::user_enrolment_deleted()` ruft nach dem bestehenden
   `reconciler::purge_user($lpid, $userid)` zusätzlich
   `reconciler::purge_all_host_user($lpid, $userid)` auf — an derselben Stelle,
   im selben A-4-Zweig, nachdem der User-Path-Datensatz gelöscht wurde.
3. `purge_host_user()` (Einzel-Host-Kurs-Variante) bleibt als Baustein für eine
   künftige Verwaltungsseiten-Aktion erhalten, wird aber nicht mehr als der
   einzige verfügbare Weg dokumentiert.

## Manuelles Testverfahren

1. Lernpfad LP mit Fall-1-Einbettung in H1 und Fall-3-Einbettung in H2
   anlegen; Nutzer/in in H1 UND in einem Node-Kurs von LP einschreiben.
2. Prüfen: Nutzer/in ist Lernpfadnutzer/in, in H2 (via `enrol_adele`, Fall 3)
   eingeschrieben, Zielkurs-Einschreibungen aktiv.
3. Nutzer/in aus H1 austragen (letzte tragende Option).
4. Prüfen: `local_adele_path_user`-Zeile gelöscht, Zielkurs-Einschreibungen
   entfernt (bereits bestehendes Verhalten) — **und neu:** Einschreibung in H2
   ebenfalls entfernt.
5. Gegenprobe: Trägt eine andere Option (z. B. eine parallele Fall-2-
   Einbettung) den Nutzer weiterhin, bleibt die Host-Kurs-Einschreibung dort
   unangetastet — nur das tatsächliche Verlassen des Lernpfads löst die
   Austragung aus.

## Upgrade-Anforderungen

Keine Datenbankschema-Änderung. Reine Verhaltensänderung im bestehenden
A-4-Codepfad.

## Automatisierte Tests

- `purge_all_host_user()` entfernt Host-Kurs-Einschreibungen in mehreren
  Host-Kursen gleichzeitig für denselben Nutzer/Lernpfad.
- Ein Nutzer ohne Host-Kurs-Einschreibungen (nur Zielkurse) löst keinen
  Fehler aus (leere Iteration).
- A-4-Regressionstest: bestehendes Verhalten (Zielkurs-Austragung,
  „getragen"-Prüfung) bleibt unverändert.
- Ein Nutzer, der weiterhin von einer anderen Option getragen wird, verliert
  weder Zielkurs- noch Host-Kurs-Einschreibungen.

## Akzeptanzkriterien

- [ ] Verlässt ein Nutzer den Lernpfad über das A-4-Regelwerk, werden alle
      seine Fall-2/3-Host-Kurs-Einschreibungen ausgetragen, nicht nur die
      Zielkurs-Einschreibungen.
- [ ] Mehrfacheinbettung wird korrekt behandelt: alle betroffenen Host-Kurse
      werden bereinigt, nicht nur der auslösende.
- [ ] Ein Nutzer, der weiterhin getragen wird, bleibt vollständig unangetastet.
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
