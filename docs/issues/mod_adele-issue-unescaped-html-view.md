# [IMPROVEMENT] Unescapte HTML-Ausgabe in `mod_adele/view.php` (G.17)

## Problem

`view.php` gibt eine Meldung direkt in einem Heredoc aus, ohne Escaping:

```
echo <<<EOT
    ...
        <strong>{$alisecompatible['msg']}</strong>
    ...
EOT;
```

## Ursache

`$alisecompatible['msg']` (aus `local_adele::get_internalquuiz_id()`) wird
ungeprüft interpoliert. Aktuell ist keine bekannte Quelle bekannt, über die
diese Zeichenkette von einer angreifenden Person beeinflusst werden könnte
— das Muster selbst ist aber strukturell riskant (XSS, sobald sich die
Herkunft der Meldung einmal ändert) und verletzt unabhängig davon die
Trennung von Ausgabe und Logik (kein Renderer, kein Template, direktes
Heredoc-HTML in der Steuerungsdatei).

## Lösung

Über Moodles Standardmechanismen ausgeben, z. B.:

```
echo $OUTPUT->notification(format_string($alisecompatible['msg']), 'notifyproblem');
```

oder, falls die Meldung tatsächlich rohes, vertrauenswürdiges HTML
enthalten soll (zu prüfen), zumindest `s()` für den reinen Textanteil.
Langfristig gehört diese Ausgabe in ein Mustache-Template statt in ein
Heredoc innerhalb von `view.php`.

## Manuelles Testverfahren

### Vorbereitung

Einen Testfall herstellen, der `$alisecompatible['msg']` durchläuft (z. B.
über die aktuellen catquiz-Kompatibilitätsprüfungen).

### Testschritte

1. `get_internalquuiz_id()` testweise eine Zeichenkette mit HTML/Skript-
   Inhalt zurückgeben lassen (nur zu Testzwecken, nicht produktiv).
2. Seite aufrufen, prüfen, ob der Inhalt roh gerendert wird.

### Aktuelles Ist-Verhalten

Roh gerendert (kein Escaping).

### Erwartetes Soll-Verhalten

Escaped ausgegeben, es sei denn, HTML ist ausdrücklich vorgesehen und
kommt ausschließlich aus vertrauenswürdiger Quelle.

## Automatisierte Tests

- Ausgabefunktion escaped einen Testwert mit `<script>`-Inhalt korrekt.

## Akzeptanzkriterien

- [ ] Ausgabe über `$OUTPUT`/Template statt rohem Heredoc.
- [ ] Kein ungeprüft interpolierter Wert im HTML.
