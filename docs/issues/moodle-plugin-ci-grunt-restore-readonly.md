# Issue-Entwurf (moodlehq/moodle-plugin-ci): `grunt` scheitert seit 4.5.12 an schreibgeschützten Dateien im Plugin (z. B. `.git/objects`)

> **Stand:** Entwurf 2026-11-23 zur Meldung an moodlehq/moodle-plugin-ci
> (betrifft jedes Plugin mit grunt-Schritt und Git-Checkout). In
> mod_adele mit einer Zeile im Workflow umgangen.
>
> **Nachtrag 2026-11-27: upstream behoben, nicht mehr melden.**
> moodle-plugin-ci 4.5.13 (2026-11-23), CHANGELOG: „Fixed `grunt` command
> failing to restore the plugin when it contains read-only files, such as
> `.git` objects.“ `restorePlugin()` entfernt das Plugin-Verzeichnis jetzt
> zuerst und spiegelt dann die Sicherung zurück. Das Gate installiert
> 4.5.13 und ist damit grün. Die `chmod`-Zeile in mod_adele schadet nicht
> und kann bei Gelegenheit entfallen.

## Beobachtung

Moodle Plugin CI, Schritt „Run grunt“ (moodle-plugin-ci 4.5.12, PHP 8.3,
GitHub Actions, `actions/checkout@v6` nach `./plugin`):

```
In Filesystem.php line 59:
  Failed to copy "/tmp/moodle-plugin-ci-grunt-backup-…/.git/objects/pack/pack-….pack"
  to ".../moodle/mod/adele/.git/objects/pack/pack-….pack" because target file could
  not be opened for writing: … Permission denied
```

## Ursache (lokal nachgestellt)

`GruntCommand::restorePlugin()` spiegelt die Sicherung mit `override => true`
zurück. symfony/filesystem **5.4.56** (in 4.5.12) übernimmt beim Kopieren die
Dateirechte der Quelle (`fileperms($originFile) & 0777 & ~umask()`); 5.4.45
(in 4.5.11) setzte nur die Ausführungsbits. Die Git-Objekte des Checkouts
sind `0444`. Die Kette sieht so aus:

1. `install` legt die Kopie im Moodle-Baum an; die Objekte bleiben `0444`.
2. Die Sicherung von grunt übernimmt ebenfalls `0444`.
3. Beim Zurückkopieren lässt sich die schreibgeschützte Zieldatei nicht zum
   Schreiben öffnen.

Nachgestellt mit den Vendor-Verzeichnissen beider Versionen, als
Nicht-root-Benutzer: 4.5.11 → ok, 4.5.12 → „Failed to copy“. Als root tritt
der Fehler nicht auf.

## Vorschlag

`restorePlugin()` / `backupPlugin()` sollten VCS-Verzeichnisse auslassen.
`.git` ist für grunt bedeutungslos, und `validatePluginFiles()` betrachtet
ohnehin nur `*.js`, `*.js.map` und `*.css`. Alternativ vor dem Kopieren
Schreibrechte setzen.

## Umgehung (mod_adele, `2026112301`)

In `extra_plugin_runners`, das vor `install` läuft:

```
chmod -R u+w "$GITHUB_WORKSPACE/plugin/.git"
```
