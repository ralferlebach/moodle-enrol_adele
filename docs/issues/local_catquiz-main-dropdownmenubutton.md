# Issue-Entwurf (ralferlebach/moodle-local_catquiz, Branch `main`): `id="dropdownMenuButton"` noch vorhanden

> **Stand:** Entwurf 2026-11-26 (Re-Audit 2026-11-26, P2, #18 AK4).
>
> **Entscheidung des Auftraggebers 2026-11-27:** ADELE unterstützt derzeit
> nur `ALiSe-v-1.2.0-legacy`; später `migration-zu-moodle-5.x`, sobald ADELE
> mit Moodle 5.x läuft. `main` wird **nicht** unterstützt, daher kein
> Fixauftrag. Dieser Entwurf bleibt nur zur Information und wird **nicht**
> gemeldet.
>
> Nachgeprüft am 2026-11-27 (nur lesende Klone, kein Code ausgeführt):
> `id="dropdownMenuButton"` steht nur auf `main` (`53c92ee`, `lib.php`
> Zeile 125). Auf `ALiSe-v-1.2.0-legacy` (`53b809e`) und
> `migration-zu-moodle-5.x` (`e7d07d7`) fehlt die ID, in PHP, Mustache und
> JS-Quellen. Wenn ADELE auf Moodle 5.x umstellt, ist A18 gegen
> `migration-zu-moodle-5.x` erneut zu prüfen.

## Befund (belegt, Klon vom 2026-11-26)

- `main` (version `2026090553`, release 1.2.0, `supported = [405, 405]`):
  `lib.php` Zeile 125 setzt noch `id="dropdownMenuButton"` am Navigationslink.
- `ALiSe-v-1.2.0-legacy` hat die ID nicht mehr (Version `2026101701`, Commit
  `8a62ce30`).
- Die CI und die E2E-Suiten von ADELE installieren catquiz
  **ausschließlich** aus `ALiSe-v-1.2.0-legacy` (Entscheidung 2026-10-06,
  `moodle-plugin-ci.yml`, `e2e.yml`). Ein grüner A18-Lauf belegt also nur
  diese Kombination.

## Zu entscheiden

1. Welche catquiz-Branches unterstützt ADELE auf Moodle 4.5? Vorschlag: nur
   `ALiSe-v-1.2.0-legacy`, so festgehalten in der README von local_adele.
2. Soll `main` den Fix bekommen (Cherry-pick von `8a62ce30`)? Beide
   Branches erklären sich für Moodle 4.5 zuständig; wer `main` installiert,
   bekommt die doppelte ID zurück, sobald zwei Navigationslinks auf einer
   Seite stehen.

## AK / DoD (falls 2.)

- AK: Kein `id="dropdownMenuButton"` in `local_catquiz` auf `main`.
- DoD: E2E A18 einmal mit catquiz `main` grün. Dafür bräuchte der
  E2E-Workflow eine Eingabe für den catquiz-Branch; die gibt es noch nicht.
