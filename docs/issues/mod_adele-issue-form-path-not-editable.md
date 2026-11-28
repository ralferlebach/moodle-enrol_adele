# Issue-Entwurf (mod_adele): Lehrende ohne Bearbeitungsrecht am Lernpfad können die Aktivität nicht speichern

> **Stand:** eingereicht als **mod_adele #43**; behoben in Lieferung `2026100736`
> (Re-Audit 0735, TP-01) – Abnahme: `docs/abnahme/mod-43.md`.

**Gefunden:** E2E-Kette `teacher-course-access-enrol.spec.ts` (local_adele
#600, TP4), Session 006, Teil 67.

## Befund

`mod_form.php` füllt die Auswahl „Lernpfad“ (`learningpathid`) aus
`local_adele\learning_paths::get_editable_learning_paths()`. Für alle außer
Website-Administrator/innen sind das nur die Pfade, deren Bearbeiter/in die
Person ist (`local_adele_lp_editors`).

Eine Trainer/in des Gastkurses, die den eingebetteten Pfad nicht bearbeiten
darf, findet den gespeicherten Pfad deshalb nicht in der Auswahl. Speichert
sie das Formular – auch nur, um den Namen oder eine andere Einstellung zu
ändern –, meldet die Prüfung „required“ am Feld Lernpfad
(`id_error_learningpathid`), und nichts wird gespeichert.

Gemessen (2026-10-16): `fx_kz_teacher` (Trainer/in in `E2EKZ`, nicht
Bearbeiter/in von „Verzweigt, gestapelt B1“): `get_editable_learning_paths()`
liefert `[]`; „Speichern und zum Kurs“ bleibt auf `modedit.php` mit
`id_error_learningpathid: required`.

## Erwartung

Der gespeicherte Pfad einer bestehenden Aktivität bleibt in der Auswahl (und
damit speicherbar), auch wenn die Person ihn nicht bearbeiten darf; nur die
Wahl eines **anderen** Pfads setzt das Bearbeitungsrecht an diesem voraus.

## Akzeptanz

- Trainer/in ohne Bearbeitungsrecht am Pfad speichert eine andere Einstellung
  der Aktivität; der Pfad bleibt unverändert.
- Ein anderer, nicht bearbeitbarer Pfad kann weiterhin nicht gewählt werden
  (auch nicht per manipuliertem Formularwert).
- E2E: die KZ-Ketten kommen ohne die Bearbeiterzeile für `fx_kz_teacher` aus
  (zurzeit im Seed gesetzt, damit die Richtlinien-Ketten das Formular
  speichern können).
