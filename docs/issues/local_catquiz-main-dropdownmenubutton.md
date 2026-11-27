# Issue-Entwurf (ralferlebach/moodle-local_catquiz, Branch `main`): `id="dropdownMenuButton"` noch vorhanden

> **Stand:** Entwurf 2026-11-26 (Re-Audit 2026-11-26, P2, #18 AK4), zur
> Entscheidung.

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
