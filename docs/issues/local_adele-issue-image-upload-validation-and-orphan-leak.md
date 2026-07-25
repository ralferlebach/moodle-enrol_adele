# [BUG] `asset_handler::set_new_image()`: keine Bild-/Größenvalidierung, alte Dateien werden nie gelöscht (G.16)

## Problem

`asset_handler::set_new_image()` verarbeitet einen Base64-kodierten
Bild-Upload für das Titelbild eines Lernpfads. Mehrere Probleme:

1. Keine Größen-, MIME-Typ- oder Bildinhaltsprüfung; die Dateiendung
   `.jpg` wird unabhängig vom tatsächlichen Inhalt erzwungen.
2. `base64_decode($image)` ohne striktes `true`-Flag (dekodiert auch
   ungültige Base64-Eingaben stillschweigend teilweise).
3. **Konkreter, aktiver Bug:** Die Existenzprüfung für ein bereits
   vorhandenes Bild sucht nach einem Dateinamen ohne Zeitstempel:

   ```
   $filename = 'uploaded_file_lp_' . $learningpathid . '.jpg';
   ...
   if ($existingfile = $fs->get_file($contextid, 'local_adele', 'lp_images', $learningpathid, $filepath, $filename)) {
       $existingfile->delete();
   }
   $filerecord = [
       ...
       'filename'  => $filename . (string)time(),  // <- mit Zeitstempel gespeichert
       ...
   ];
   ```

   Gespeichert wird der Dateiname **mit** angehängtem Zeitstempel, gesucht
   wird aber **ohne**. Die Existenzprüfung findet die zuvor gespeicherte
   Datei dadurch nie — `$existingfile` ist immer `false`, die Löschung
   greift nie. Jeder erneute Bild-Upload für denselben Lernpfad hinterlässt
   eine neue, nie mehr gelöschte Datei in der Moodle-Dateiablage.
4. Kein `try`/`finally` um die temporäre Datei (`tempnam()`) — bei einer
   Exception aus `create_file_from_pathname()` bleibt die Temp-Datei liegen.

## Ursache

Die Zeitstempel-Ergänzung am Dateinamen wurde offenbar eingeführt, um
Browser-Caching zu umgehen, ohne die zugehörige Existenzprüfung
anzupassen.

## Lösung

**Für Punkt 3 (aktiver Bug, hohe Priorität):** Existenzprüfung über einen
Präfix-Vergleich statt exakter Namensgleichheit, oder — robuster — eine
feste, nicht zeitgestempelte Datei-ID verwenden und stattdessen einen
Cache-Buster als URL-Parameter statt im Dateinamen anhängen:

```
$files = $fs->get_area_files($contextid, 'local_adele', 'lp_images', $learningpathid, 'filename', false);
foreach ($files as $oldfile) {
    $oldfile->delete();
}
```

**Für Punkte 1/2/4:**

```
$decodedfile = base64_decode($image, true);
if ($decodedfile === false) {
    throw new \invalid_parameter_exception('Invalid base64 data');
}
if (strlen($decodedfile) > MAX_IMAGE_BYTES) {
    throw new \invalid_parameter_exception('Image too large');
}
$imageinfo = @getimagesizefromstring($decodedfile);
if ($imageinfo === false || !in_array($imageinfo['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    throw new \invalid_parameter_exception('Not a valid image');
}
$tempfile = tempnam(sys_get_temp_dir(), 'upload_');
try {
    file_put_contents($tempfile, $decodedfile);
    // ... bestehende Logik ...
} finally {
    @unlink($tempfile);
}
```

Alternativ: vollständig auf Moodles Draft File API umstellen
(`file_save_draft_area_files()`), die Größenlimits und Aufräumen bereits
eingebaut hat.

## Manuelles Testverfahren

### Vorbereitung

Lernpfad mit Bearbeitungsrecht, Zugriff auf die Bild-Upload-Funktion.

### Testschritte

1. Für denselben Lernpfad dreimal nacheinander ein neues Titelbild
   hochladen.
2. In der Moodle-Dateiablage (`mdl_files`, `filearea = 'lp_images'`,
   `itemid = <learningpathid>`) nachsehen, wie viele Dateien vorhanden
   sind.
3. Eine offensichtlich ungültige Datei (z. B. eine `.php`-Datei,
   Base64-kodiert) als „Bild" hochladen.

### Aktuelles Ist-Verhalten

Schritt 2: drei Dateien statt einer. Schritt 3: Upload wird akzeptiert,
unabhängig vom tatsächlichen Inhalt.

### Erwartetes Soll-Verhalten

Schritt 2: genau eine Datei (die älteren wurden ersetzt). Schritt 3: Upload
wird mit einer klaren Fehlermeldung abgelehnt.

## Automatisierte Tests

- Wiederholter Upload für denselben Lernpfad hinterlässt genau eine Datei.
- Upload eines Nicht-Bildes wird abgelehnt.
- Upload über der Größengrenze wird abgelehnt.
- Ungültige Base64-Eingabe wird abgelehnt, kein stiller Teilerfolg.
- Temp-Datei wird auch bei einer Exception in `create_file_from_pathname()`
  aufgeräumt.

## Akzeptanzkriterien

- [ ] Alte Bilddateien werden bei jedem neuen Upload zuverlässig entfernt.
- [ ] Größen-, MIME- und Bildinhaltsprüfung vorhanden.
- [ ] `base64_decode(..., true)`.
- [ ] Temp-Datei-Aufräumen über `try`/`finally`.
