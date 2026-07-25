# [SECURITY/BUG] Automatische Assistentenrolle wird nie entzogen (fehlendes `role_unassigned`-Gegenstück)

> **Hinweis:** Der Auftraggeber hat angegeben, dass dieser Punkt bereits als
> Issue im Wunderbyte-`local_adele`-Repository hinterlegt ist. Dieses
> Dokument ist als **Ergänzung** gedacht — es hält den konkreten
> technischen Befund und einen Umsetzungsvorschlag fest, falls das
> bestehende Issue den Revocation-Aspekt noch nicht abdeckt.

## Problem

`local_adele` gewährt über die Einstellung `enrollassistant` eine
automatische, **systemweite** Rolle: Wird einer Person irgendwo die
konfigurierte Auslöser-Rolle zugewiesen, erhält sie zusätzlich die
System-Rolle `adeleassistant`. Umgesetzt in
`enrollment::assign_assistant_to_role()`, verdrahtet über den Observer auf
`\core\event\role_assigned` in `db/events.php`.

Es gibt **kein Gegenstück auf `\core\event\role_unassigned`**. Wird die
Auslöser-Rolle der Person später wieder entzogen, bleibt die systemweite
`adeleassistant`-Rolle bestehen. Die erhöhten Rechte akkumulieren und
werden nie automatisch zurückgenommen — dieselbe Klasse von
Lebenszyklus-Verwaisung („gewährt, aber nie entzogen"), die das
ADELE-Einschreibe-Ökosystem für Kurs-Einschreibungen gerade behebt.

## Ursache

Der Assistenten-Mechanismus wurde nur für den Gewähren-Pfad implementiert.
`role_assigned` wird beobachtet, `role_unassigned` nicht. Die
`assign_assistant_to_role()`-Methode ruft `role_assign()` auf; ein
korrespondierender `role_unassign()`-Pfad existiert nicht.

## Betroffener Code

- `classes/enrollment.php`, `assign_assistant_to_role($event)` — nur
  Gewähren via `role_assign($systemrole->id, $event->relateduserid, ...)`.
- `db/events.php` — nur ein Observer:
  `\core\event\role_assigned → local_adele_observer::assign_assistant_to_role`.

## Lösung (Vorschlag)

1. Zweiten Observer auf `\core\event\role_unassigned` in `db/events.php`
   ergänzen, der auf eine neue Methode
   `enrollment::unassign_assistant_from_role($event)` zeigt.
2. Diese Methode spiegelt die Gewähren-Logik:
   - dieselben Frühausstiege (leere/`'0'`-Konfiguration, Rolle ≠
     Auslöser-Rolle),
   - dann `role_unassign()` der `adeleassistant`-System-Rolle.
3. **Wichtige Fachfrage** (Produktentscheidung, vor Umsetzung klären):
   Eine Person kann die Auslöser-Rolle in **mehreren** Kontexten gleichzeitig
   halten. Der Entzug in *einem* Kontext darf die systemweite
   Assistentenrolle nur dann entfernen, wenn **keine** verbleibende
   Zuweisung der Auslöser-Rolle mehr existiert (analog zur „großzügigste
   Option gewinnt"-Regel bei geteilten Kursen). Vor dem `role_unassign()`
   also prüfen:
   `user_has_role_assignment()` bzw. eine Abfrage auf `{role_assignments}`,
   ob der Person die Auslöser-Rolle noch irgendwo zugewiesen ist; nur wenn
   nicht, die Assistentenrolle entziehen.
4. Nur von `local_adele` selbst gesetzte Zuweisungen entziehen (kein
   Entzug einer fremd/manuell vergebenen `adeleassistant`-Rolle) — sofern
   sich das über den Zuweisungskontext/`component` unterscheiden lässt.

## Manuelles Testverfahren

### Vorbereitung

`enrollassistant` auf eine Auslöser-Rolle (z. B. `editingteacher`) setzen.
Eine Testperson bereitstellen. Sicherstellen, dass die System-Rolle
`adeleassistant` existiert (wird von `db/install.php` angelegt).

### Testschritte

1. Der Testperson die Auslöser-Rolle in einem Kurskontext zuweisen.
   → Erwartung: Person erhält systemweit `adeleassistant`
   (unter *Website-Administration → Nutzer/innen → Berechtigungen →
   Rollen zuweisen → System*).
2. Der Person die Auslöser-Rolle in **einem zweiten** Kontext zuweisen,
   dann in **einem** der beiden Kontexte wieder entziehen.
   → Erwartung (nach Umsetzung): `adeleassistant` bleibt erhalten, weil
   noch eine Zuweisung der Auslöser-Rolle besteht.
3. Die letzte verbliebene Zuweisung der Auslöser-Rolle entziehen.
   → Erwartung (nach Umsetzung): `adeleassistant` wird systemweit
   automatisch entzogen.
   → Ist-Zustand (heute): `adeleassistant` bleibt dauerhaft bestehen.

## Referenz

Muster analog zu
https://github.com/Wunderbyte-GmbH/moodle_local_adele/issues/502.
Priorität: mittel (Rechte-Akkumulation; Verhaltensänderung, daher vor
Umsetzung mit dem Auftraggeber abzustimmen).
