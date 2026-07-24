# [BUG] `local_adele_pluginfile()` liefert Dateien ohne Login-/Capability-Prüfung aus (G.15)

## Problem

`local_adele_pluginfile()` in `lib.php` prüft ausschließlich, dass der
Kontext ein Systemkontext ist:

```
function local_adele_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (!in_array($context->contextlevel, [CONTEXT_SYSTEM])) {
        return false;
    }
    ...
    $file = $fs->get_file($context->id, 'local_adele', $filearea, $itemid, $filepath, $filename);
    ...
    send_stored_file($file, ...);
}
```

Es fehlen: `require_login()`, jede Capability-Prüfung, eine Allowlist
zulässiger `filearea`-Werte, sowie eine Prüfung, ob der zugehörige
Lernpfad für die anfragende Person überhaupt sichtbar ist.

## Ursache

Der Handler wurde nach dem Moodle-Boilerplate-Muster für
Plugin-Dateizugriffe erstellt, aber die im Kommentar vorgesehene
Zugriffsprüfung („Check the contextlevel is as expected") wurde nie um
tatsächliche Login-/Berechtigungsprüfung ergänzt.

## Lösung

```
function local_adele_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if (!in_array($context->contextlevel, [CONTEXT_SYSTEM])) {
        return false;
    }
    require_login();

    $allowedareas = ['lp_images', 'helpingslider', 'node_background_image'];
    if (!in_array($filearea, $allowedareas, true)) {
        return false;
    }

    // Für lp_images (itemid = learningpathid): Sichtbarkeit/Zugriffsrecht
    // auf DIESEN Lernpfad prüfen, nicht nur "ist eingeloggt".
    if ($filearea === 'lp_images') {
        $itemid = (int) ($args[0] ?? 0);
        if (!\local_adele\learning_paths::user_can_view($itemid)) {
            return false;
        }
    }
    // ... bestehende Logik ...
}
```

(`user_can_view()` exemplarisch — an die tatsächlich vorhandene
Sichtbarkeitslogik von `learning_paths` anzupassen, ggf. wiederverwendbar
mit dem bereits existierenden `check_access()`-Muster.)

Statische Assets (`helpingslider`, `node_background_image`) benötigen
vermutlich nur `require_login()`, keine lernpfadspezifische Prüfung, da
sie nicht an einen einzelnen Lernpfad gebunden sind.

## Manuelles Testverfahren

### Vorbereitung

Einen Lernpfad mit Titelbild, dessen Pluginfile-URL bekannt ist.

### Testschritte

1. Ohne Login (oder als Nutzer/in ohne Zugriff auf den Lernpfad) die
   Pluginfile-URL des Titelbilds direkt aufrufen.
2. Prüfen, ob die Datei ausgeliefert wird.

### Aktuelles Ist-Verhalten

Datei wird ausgeliefert, unabhängig von Login-Status oder
Zugriffsberechtigung auf den Lernpfad.

### Erwartetes Soll-Verhalten

Ohne Login: Zugriff verweigert. Mit Login, aber ohne Sichtbarkeit auf den
Lernpfad: Zugriff verweigert.

## Automatisierte Tests

- Nicht angemeldeter Zugriff wird abgelehnt.
- Angemeldeter Zugriff auf ein Bild eines nicht sichtbaren/fremden
  Lernpfads wird abgelehnt.
- Angemeldeter, berechtigter Zugriff funktioniert weiterhin.
- Zugriff mit unbekanntem `filearea`-Wert wird abgelehnt.

## Akzeptanzkriterien

- [ ] `require_login()` ergänzt.
- [ ] `filearea`-Allowlist ergänzt.
- [ ] Sichtbarkeitsprüfung für `lp_images` ergänzt.
- [ ] Bestehende, legitime Bildauslieferung funktioniert unverändert.
