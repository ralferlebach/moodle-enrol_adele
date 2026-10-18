# Issue-Entwurf (local_adele, mod_adele): Datenschutz-Löschung zerstört fremde Daten und lässt Verweise verwaist zurück

> **Stand:** Entwurf (2026-10-17), noch nicht eingereicht. Gefunden beim
> Abarbeiten von mod_adele #33 (Master-Issue #43, P1); die Behebung ist dort
> mit umgesetzt (Entscheidung des Nutzers 2026-10-17: „Anonymisieren", dazu
> dieses Issue).

## Befund (aus dem Code, `local_adele/classes/privacy/provider.php`)

1. `delete_data_for_user()` / `delete_data_for_users()` löschen für die
   betroffene Person
   - **alle Lernpfade, die sie angelegt hat** (`local_adele_learning_paths`
     mit `createdby = X`) – geteilte Inhalte der Website, die andere
     Lehrende einbetten und Lernende bearbeiten;
   - **alle Lernpfad-Datensätze, die sie angelegt hat**
     (`local_adele_path_user` mit `createdby = X`) – also die Fortschritte
     **anderer** Lernender, z. B. wenn X als Lehrkraft die Aktivität
     gespeichert und dadurch Lernende abonniert hat.
2. Zurück bleiben Verweise ins Leere: `{adele}`-Aktivitäten mit
   `learningpathid` eines gelöschten Pfads, ADELE-Einschreibungen
   (Ziel- und Host-Instanzen) ohne Lernpfad-Datensatz – die Invariante
   „Einschreibungen ⇔ aktiver User-Path" (Pflichtenheft 2.5) ist verletzt.
3. `delete_data_for_all_users_in_context()` im Systemkontext löscht
   **sämtliche Lernpfade** der Website.
4. `mod_adele/classes/privacy/provider.php` ist ein `null_provider`
   („speichert keine personenbezogenen Daten"), obwohl
   `adele.teachercourseaccessgrantor` die Nutzer-ID der Person hält, die den
   Lehrenden-Zugangsmodus gewählt hat.
5. Für keinen der drei Provider gibt es PHPUnit-Tests (weder Export noch
   Löschung).

## Erwartung

Eine Löschanfrage entfernt die Daten **der Person**, nicht fremde Daten und
nicht geteilte Inhalte:

- Eigene Teilnahmen (`path_user.user_id = X`) werden gelöscht, die
  ADELE-Einschreibungen von X für diese Pfade mit ausgetragen.
- Bearbeitungsrechte von X (`local_adele_lp_editors`) werden gelöscht.
- Lernpfade von X bleiben; die Eigentümerschaft geht nach der Regel aus #571
  an die dienstälteste verbleibende Bearbeiterin, sonst `createdby = 0`
  (Pfad „ohne Eigentümer/in", Hinweis für Admin/Manager).
- Von X angelegte Teilnahmen anderer bleiben; `createdby` wird 0.
- mod_adele deklariert `teachercourseaccessgrantor`, exportiert es und setzt
  es bei der Löschung auf 0 (der Modus verliert damit seine Befugnis, wie
  heute bei gelöschtem oder gesperrtem Konto – neu delegieren).
- Die Löschung aller Nutzer im Systemkontext löscht Teilnahmen und
  Bearbeitungsrechte, nicht die Lernpfade.

## Akzeptanz

- PHPUnit (`core_privacy`-Hilfen) für alle drei Provider: Metadaten,
  Kontexte, Nutzerliste, Export und Löschung für Ersteller/in, Teilnehmer/in,
  Bearbeiter/in, Delegierende/n.
- Nach jeder Löschung: kein verwaister Verweis (Aktivität ohne Pfad,
  ADELE-Einschreibung ohne User-Path), keine fremden Daten entfernt.
- Grün auf PostgreSQL und MariaDB; Bestandteil der Release-Matrix (#33).
