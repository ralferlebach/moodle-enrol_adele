# Issue-Entwurf: Mindestversion Moodle 4.5

**Repositories:** `Wunderbyte-GmbH/moodle_local_adele`, `Wunderbyte-GmbH/moodle-enrol_adele`
**Labels (Vorschlag):** `maintenance`
**Stand:** 2026-10-05, Session 006 — **umgesetzt** in `local_adele` und `enrol_adele` `2026100500`

Zum Kopieren in GitHub, je einmal pro Repository. Alles unterhalb der
Trennlinie ist der Issue-Text.

---

## Titel

`$plugin->requires` auf Moodle 4.5 (2024100700) anheben

## Entscheidung

Moodle 4.4 und älter werden nicht mehr unterstützt. `local_adele` und
`enrol_adele` erklärten bisher `2022112800` (Moodle 4.1) als Mindestversion,
obwohl das Ökosystem darunter gar nicht installierbar war: `mod_adele`
verlangt bereits `2024100700`. In `enrol_adele` war der alte Wert bewusst
stehen geblieben, um bestehende Installationen nicht allein durch eine
Metadatenänderung auszusperren — diese Rücksicht entfällt mit der
Entscheidung.

## Änderung

- `local_adele/version.php`: `$plugin->requires = 2024100700;`
- `enrol_adele/version.php`: `$plugin->requires = 2024100700;`, Kommentar
  zur alten Begründung entfernt
- `enrol_adele/.github/workflows/moodle-plugin-ci.yml`: Kommentar angepasst
- `$plugin->supported` bleibt `[405, 502]`; die CI testet ohnehin nur 4.5 und
  5.x.

## Folgen

- Sites unter Moodle 4.5 können diese Versionen nicht mehr installieren.
- `\core\clock` (ab Moodle 4.4) steht ohne Rückfallebene zur Verfügung — die
  Voraussetzung für `local_adele-issue-core-clock.md` und die gleichartigen
  Entwürfe für `enrol_adele` und `mod_adele`.
