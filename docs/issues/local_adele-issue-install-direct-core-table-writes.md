# [IMPROVEMENT] `db/install.php` schreibt direkt in Moodle-Core-Tabellen (G.19)

## Problem

`db/install.php` legt die Rollen „Adele Manager"/„Adele Assistant" über
direkte Schreibzugriffe auf `{role}`, `{role_context_levels}` und
`{role_capabilities}` an, statt die dafür vorgesehenen Moodle-APIs zu
verwenden:

```
$role = $DB->get_record('role', ['shortname' => $shortname]);
if (empty($role->id)) {
    $sql = "SELECT MAX(sortorder)+1 AS id FROM {role}";
    ...
    $role->id = $DB->insert_record('role', $role);
}
$DB->insert_record('role_context_levels', ['roleid' => $role->id, 'contextlevel' => CONTEXT_SYSTEM]);
$DB->insert_record('role_capabilities', [
    ...
    'modifierid' => 2,
]);
```

Zusätzlich: `modifierid` fest auf `2` codiert, `$descriptionstr` wird
berechnet, aber nicht verwendet — beide Rollen erhalten dieselbe
Beschreibung „Adele assistant", Rollenbezeichnungen sind fest codiert und
nicht übersetzt (keine Sprachdatei-Anbindung für Rollennamen).

## Ursache

Vermutlich historisch gewachsen, bevor die Standard-APIs im Projekt
etabliert waren.

## Lösung

Auf die vorgesehenen Moodle-Core-Funktionen umstellen:

```
if (!$role = $DB->get_record('role', ['shortname' => $shortname])) {
    $roleid = create_role($namestr, $shortname, $descriptionstr, 'user');
} else {
    $roleid = $role->id;
}
set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
foreach ($capabilities as $capability) {
    assign_capability($capability, CAP_ALLOW, $roleid, context_system::instance()->id);
}
```

`create_role()`/`set_role_contextlevels()`/`assign_capability()` kümmern
sich intern korrekt um `sortorder`, `modifierid` (aktueller Admin-User
statt fest `2`) und Cache-Invalidierung.

Zusätzlich: `$descriptionstr` tatsächlich verwenden (unterschiedliche
Beschreibung je Rolle), Rollennamen über Sprachstrings statt fest codiert.

## Manuelles Testverfahren

### Vorbereitung

Frische Testinstanz ohne `local_adele`.

### Testschritte

1. `local_adele` installieren.
2. Unter „Website-Administration → Nutzer/innen → Rechte → Rollen
   definieren" die beiden neuen Rollen prüfen: Name, Beschreibung,
   `modifierid` (sollte die tatsächlich installierende Person sein, nicht
   grundsätzlich Nutzer-ID 2).

### Aktuelles Ist-Verhalten

Beide Rollen tragen dieselbe Beschreibung „Adele assistant";
`modifierid` ist immer `2`, unabhängig davon, wer installiert hat.

### Erwartetes Soll-Verhalten

Unterschiedliche, zutreffende Beschreibungen je Rolle; `modifierid`
spiegelt die tatsächliche installierende Person wider.

## Automatisierte Tests

- Installation erzeugt beide Rollen mit den über `create_role()` üblichen
  Eigenschaften (PHPUnit-Test gegen `$DB->get_record('role', ...)`).
- Wiederholte Installation (Upgrade-Pfad) erzeugt keine doppelten Rollen.

## Akzeptanzkriterien

- [ ] `create_role()`/`set_role_contextlevels()`/`assign_capability()`
      statt direkter Tabellenschreibzugriffe.
- [ ] `$descriptionstr` tatsächlich verwendet, je Rolle unterschiedlich.
- [ ] Kein fest codiertes `modifierid = 2`.
