# Issue-Entwurf: Abschlussregel „Lernpfad abgeschlossen" wird nie ausgewertet

**Repository:** `Wunderbyte-GmbH/moodle-mod_adele`
**Labels (Vorschlag):** `bug`, `completion`
**Stand:** 2026-10-06, Session 006 — gefunden beim Aufbau der E2E-Ketten L4/O3

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Aktivitätsabschluss „wenn der Lernpfad abgeschlossen ist" (`completionlearningpathfinished`) hat keine Wirkung

## Beobachtung

Das Aktivitätsformular bietet die Abschlussregel `completionlearningpathfinished`
an, und `adele_supports()` meldet `FEATURE_COMPLETION_HAS_RULES`.
`adele_get_coursemodule_info()` trägt die Regel in
`customdata['customcompletionrules']` ein.

Es fehlt aber:

1. **die Klasse `mod_adele\completion\custom_completion`** (Unterklasse von
   `core_completion\activity_custom_completion`). Moodle 4.x wertet eigene
   Regeln nur über diese Klasse aus; ohne sie überspringt
   `completion_info::internal_get_state()` die Regel stillschweigend
   (`activity_custom_completion::get_cm_completion_class()` liefert `null`).
2. **ein Anstoß beim Pfadende.** Nichts ruft `completion_info::update_state()`
   für die Aktivität auf, wenn eine Lernende ihren Pfad abschließt.

Folge: Lehrende, die die Regel wählen, erhalten nie einen Aktivitätsabschluss
über den Lernpfad. Wo der Kursabschluss an die Aktivität gekoppelt ist,
schließt der Kurs nie ab.

## Vorschlag

1. `classes/completion/custom_completion.php` mit
   `get_state('completionlearningpathfinished')`: abgeschlossen, wenn die
   Lernende auf dem eingebetteten Pfad alle Knoten der besten Route
   abgeschlossen hat (dieselbe Logik wie der Fortschritt
   `learning_paths::getnodeprogress()`, 100 %). `get_defined_custom_rules()`,
   `get_custom_rule_descriptions()`, `get_sort_order()`.
2. Beim Aktualisieren eines Lernerpfads (`user_path_updated`) für jede
   Einbettung des Pfads mit dieser Regel `update_state()` aufrufen — auch in
   Gegenrichtung, wenn ein Abschluss zurückgenommen wird (z. B. Master- oder
   manueller Abschluss entfernt).

## Akzeptanzkriterien

1. PHPUnit: Pfad mit zwei Knoten, Einbettung mit der Regel; nach Abschluss
   beider Knoten ist die Aktivität abgeschlossen, nach Rücknahme nicht mehr.
2. E2E-Kette L4/O3 (`master-completion.spec.ts`) prüft zusätzlich den
   Aktivitätsabschluss im Hostkurs; Kette Z nutzt ihn für den Kursabschluss.
