# Issue-Entwurf: Zeitbedingungen gegen Zeitstempel prüfen, nicht gegen formatierte Zeichenketten

**Repository:** `Wunderbyte-GmbH/moodle_local_adele`
**Labels (Vorschlag):** `bug`, `time`, `reliability`
**Stand:** 2026-10-05, Session 006
**Hängt zusammen mit:** `local_adele-issue-core-clock.md`

Zum Kopieren in GitHub. Alles unterhalb der Trennlinie ist der Issue-Text.

---

## Titel

Zeitbedingungen: Grenzen als Zeitstempel speichern und vergleichen (Zeitzone, Ein-/Ausschließlichkeit)

## Befund

Die Zugangsbedingung `timed` vergleicht **formatierte Zeichenketten**, die in
`DateTime`-Objekte zurückgeparst werden:

```php
// classes/course_restriction/conditions/timed.php
$currenttimestamp = new DateTime();
$startdate = $this->isvaliddate($restrictionnode['data']['value']['start']);   // "2026-12-01T10:00"
...
if ($startdate <= $currenttimestamp) { ... }
if ($enddate < $currenttimestamp) { $isafterrange = true; }
if ($enddate >= $currenttimestamp && $validstart) { $validtime = true; }

// isvaliddate()
$datetime = DateTime::createFromFormat('Y-m-d\TH:i', $datestring);
```

Die gespeicherten Werte stammen aus `<input type="datetime-local">`
(`vue3/components/restriction/conditions/timed_dates.vue`), sind also eine
Wanduhrzeit **ohne Zeitzone**. Ebenso arbeitet `relation_update.php` mit
`strtotime($restnode['inbetween_info']['endtime'])` (Zeilen 552–553).

Daraus folgen drei Fehlerquellen.

### 1. Die Grenze hängt an der aktuellen Sekunde

`createFromFormat()` füllt alle Felder, die das Format nicht enthält, mit der
**aktuellen** Zeit — hier die Sekunden — und setzt die Mikrosekunden auf 0.
`new DateTime()` dagegen trägt Mikrosekunden. Gemessen mit einer Bedingung
10:00–12:00 und kontrollierter Prozesszeit (`libfaketime`):

| Zeitpunkt | Ergebnis |
|---|---|
| 09:59:59 | vor dem Fenster |
| 10:00:00 | im Fenster |
| 11:59:59 | im Fenster |
| **12:00:00** | **nach dem Fenster** |

Der Code prüft `Ende >= jetzt`, meint also offenbar „Ende einschließlich".
Tatsächlich ist das Ende **ausschließlich**, weil der Vergleich am Ende immer
um Bruchteile einer Sekunde verliert. Das Verhalten folgt aus einem
Nebeneffekt, nicht aus einer Entscheidung.

### 2. Die Zeitzone ist mehrdeutig

Die Autorin gibt eine Uhrzeit in der Zeitzone **ihres Browsers** ein; der
Server parst die Zeichenkette in **seiner** Standardzeitzone
(`date_default_timezone_get()`, in der Testinstanz `Europe/London`). Weichen
beide ab — etwa Autorin in Berlin, Server in UTC —, verschiebt sich das
Fenster um ein bis zwei Stunden, ohne dass es irgendwo sichtbar wird.

### 3. Sommer- und Winterzeit

Eine Wanduhrzeit ohne Zone ist in der Umstellungsnacht nicht eindeutig
(02:30 existiert im Frühjahr nicht und im Herbst zweimal). Ein Zeitstempel
ist es immer.

## Vorschlag

1. **Speichern als Unix-Zeitstempel (int, UTC).** Das Frontend wandelt die
   Eingabe aus `datetime-local` mit der Zeitzone des Browsers in einen
   Zeitstempel um, bevor gespeichert wird; angezeigt wird umgekehrt wieder in
   der Zeitzone der betrachtenden Person (`userdate()` serverseitig bzw.
   `Intl.DateTimeFormat` im Frontend).
2. **Vergleichen als Ganzzahlen** gegen
   `\core\di::get(\core\clock::class)->time()` (siehe
   `local_adele-issue-core-clock.md`). Kein Parsen, kein `createFromFormat`,
   kein `strtotime` im Bewertungspfad.
3. **Grenzwertsemantik ausdrücklich festlegen** und im Code benennen. Mit
   Ganzzahlen ist jede Variante sauber umsetzbar; Vorschlag ist das
   halboffene Intervall, weil es dem heute tatsächlich beobachteten
   Verhalten entspricht und aneinandergrenzende Fenster lückenlos und ohne
   Überlappung erlaubt:

   ```php
   $inwindow = $start <= $now && $now < $end;   // [Beginn, Ende)
   ```

   Die Alternative „Ende einschließlich" (`$now <= $end`) ist ebenso möglich;
   die Entscheidung gehört in dieses Issue, nicht in den Code.
4. **Migration** bestehender Lernpfade: vorhandene Zeichenketten einmalig in
   Zeitstempel umrechnen. Weil die ursprüngliche Zeitzone nicht gespeichert
   wurde, wird dabei die Standardzeitzone der Site angenommen; das ist im
   Upgrade-Schritt zu dokumentieren und in den Release Notes zu nennen.
5. **`relation_update.php`** (Zeilen 552–553, 569) auf dieselben Zeitstempel
   umstellen; die Anzeige (`date('d.m.Y H:i', …)`) auf `userdate()`, damit sie
   die Zeitzone und das Datumsformat der betrachtenden Person verwendet.

## Akzeptanzkriterien

1. Gespeicherte Grenzen sind Zeitstempel (int); im Bewertungspfad wird
   keine Datumszeichenkette mehr geparst.
2. Die Grenzwertsemantik ist festgelegt und im Code benannt.
3. PHPUnit mit `\frozen_clock` prüft für Beginn und Ende jeweils
   `t − 1 s`, `t`, `t + 1 s` und liefert das festgelegte Ergebnis —
   unabhängig von der Sekunde, in der der Test läuft.
4. Ein Test mit abweichender Zeitzone von Autorin und Site belegt, dass das
   Fenster nicht verrutscht.
5. Ein Test über eine Sommer-/Winterzeit-Umstellung belegt eindeutiges
   Verhalten.
6. Bestehende Lernpfade funktionieren nach dem Upgrade weiter; der
   Upgrade-Schritt ist idempotent.

## Hinweis zur Prüfbarkeit

Die oben gezeigte Grenzwerttabelle wurde ohne Codeänderung gemessen: Die
Bewertung lief unter `libfaketime` (verschobene Prozesszeit nur für PHP).
Dieselbe Technik trägt die E2E-Kette T des Testplans; sie ersetzt aber nicht
die Komponententests aus Kriterium 3.
