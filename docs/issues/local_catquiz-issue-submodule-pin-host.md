# Issue-Entwurf (local_catquiz, extern): Submodul `catquizcentralhub/host` zeigt auf einen fehlenden Commit

> **Stand:** Entwurf (2026-11-19), für `ralferlebach/moodle-local_catquiz`
> (Zweig `ALiSe-v-1.2.0-legacy`). Nicht in den ADELE-Repositories behebbar.

## Befund (belegt, CI-Logs 2026-11-19 und lokaler Klon)

`ALiSe-v-1.2.0-legacy` (Stand `dea674d`, Version 2026111706) pinnt das
Submodul `catquizcentralhub/host` auf `ec77e605`. Diesen Commit enthält
`moodle-catquizcentralhub_host` nicht (dort nur `main`, `2a23800`).
`git clone --recurse-submodules` bricht deshalb ab; in den ADELE-Workflows
(moodle-plugin-ci, E2E aller drei Plugins, Screenreader-Tunnel) folgten
„phplint: Not enough arguments“ und abgebrochene Jobs.

## Umgehung in ADELE (2026111900)

Die ADELE-Workflows holen Submodule einzeln; fehlt der gepinnte Commit, wird
mit `::warning::` der Standardzweig des Submoduls geklont. Das ist eine
Umgehung – getestet wird dann gegen einen anderen Stand als den gepinnten.

## Vorschlag

Submodul-Zeiger in local_catquiz auf einen vorhandenen Commit von
`moodle-catquizcentralhub_host` setzen (oder den fehlenden Commit dort
veröffentlichen). Danach kann die Umgehung in ADELE entfallen.
