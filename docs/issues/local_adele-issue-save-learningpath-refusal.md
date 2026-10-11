# Issue-Entwurf: Lernpfad anlegen ohne Recht — der Dienst lehnt mit einem PHP-Fehler ab statt mit „keine Berechtigung"

**Repository:** `Wunderbyte-GmbH/moodle-local_adele`
**Labels (Vorschlag):** `bug`
**Stand:** 2026-10-07, Session 006 — gefunden mit Testkette R3
**GitHub:** local_adele #594

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

`local_adele_save_learningpath`: Ablehnung beim Anlegen endet in „Class required_capability_exception not found"

## Beobachtung

Ruft eine Person ohne jedes Recht den Dienst `local_adele_save_learningpath`
mit `learningpathid = 0` auf (neuer Pfad), antwortet der Server mit dem
Fehlercode `generalexceptionmessage` statt `nopermissions`.

Direkt in PHP als diese Person aufgerufen:

```
Error: Class "local_adele\external\required_capability_exception" not found
```

**Es wird kein Pfad angelegt** — die Ablehnung selbst greift. Falsch ist nur
ihre Form: ein PHP-Fehler statt der vorgesehenen Ausnahme; im Protokoll des
Servers steht ein Absturz, die aufrufende Seite bekommt keinen Grund.

## Ursache (belegt)

`classes/external/save_learningpath.php`, Zeile 105, wirft
`new required_capability_exception(...)`. Die Datei liegt im Namensraum
`local_adele\external` und importiert die Klasse nicht (`use
required_capability_exception;` fehlt). Alle anderen Dateien unter
`classes/external/`, die diese Ausnahme werfen, importieren sie oder
schreiben `\required_capability_exception` — geprüft mit einer Suche über
`classes/`.

Der zweite Zweig (bestehenden Pfad speichern) ist nicht betroffen: Er wirft
über `learning_paths::require_lp_editor_access()`, wo der Import vorhanden
ist.

## Vorschlag

`use required_capability_exception;` ergänzen.

## Umsetzung (Version `2026100716`)

- `classes/external/save_learningpath.php`: `use required_capability_exception;` ergänzt.
- PHPUnit `tests/security/save_learningpath_refusal_test.php` (2 Tests):
  Person ohne Recht → `required_capability_exception` mit `nopermissions`,
  kein Pfad angelegt; Administrator legt an. Vor der Änderung rot mit
  „Class … not found", danach grün.
- E2E `roles-teacher-becomes-assistant.spec.ts`: Der bisher als bekannter
  Fehler markierte Test läuft regulär; R3 prüft im letzten Schritt jetzt
  ebenfalls `nopermissions`.
