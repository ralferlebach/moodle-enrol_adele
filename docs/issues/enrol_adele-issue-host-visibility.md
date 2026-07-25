[FEATURE] Host-Kurs-Zugang bei Fall 2/3 konfigurierbar machen (sichtbar / verdeckt / keine Einschreibung)

## Problem

Fall 2 und Fall 3 schreiben Nutzer/innen, die in einem qualifizierenden
Node-Kurs Mitglied sind, immer **aktiv und sichtbar** in den Host-Kurs ein
(`reconciler::reconcile_host_user(..., $entitled = true)` setzt
`ENROL_USER_ACTIVE`). Es gibt keine Möglichkeit für die Lehrkraft, dieses
Verhalten abzuschwächen oder abzuschalten.

Nicht jeder Host-Kurs ist aber dafür gedacht, für Lernende direkt zugänglich
zu sein. Denkbare Fälle:

- Der Host-Kurs ist ein reiner **Verwaltungscontainer** für die
  Lernpfad-Aktivität (z. B. ein zentraler „Lernpfad-Katalog"-Kurs), in den
  Lernende nie selbst hineinschauen sollen.
- Die Host-Kurs-Mitgliedschaft soll zwar **nachvollziehbar** sein (z. B. für
  Berichte, Zertifikate, Rollenbasis), aber **ohne** dass die Lernenden
  tatsächlich Zugriff auf den Kursinhalt erhalten — analog zur bestehenden
  Suspendierungs-Semantik im Zielkurs-Fall (Knoten gesperrt → suspendiert,
  nicht gelöscht).
- Manche Lehrkräfte wollen weiterhin die volle, sichtbare Einschreibung
  (heutiges Verhalten).

## Ursache

Die Host-Kurs-Reconciliation kennt nur einen Zustand für „entitled": aktiv.
`reconcile_host_user()` besitzt keinen Parameter für den gewünschten
Sichtbarkeitsgrad, und `mod_adele` hat keine Einstellung, die einen solchen
Wunsch überhaupt erfassen könnte.

## Lösung

1. **Neues Aktivitäts-Setting** in `mod_adele` (nur relevant, wenn Fall 2
   und/oder 3 ausgewählt sind): `hostenrolmentmode`, dreiwertig:

   | Wert | Bedeutung |
   |---|---|
   | `visible` (Default, heutiges Verhalten) | Host-Kurs-Einschreibung aktiv, Lernende sehen und betreten den Kurs. |
   | `hidden` | Host-Kurs-Einschreibung wird angelegt, aber sofort suspendiert („verdeckt eingeschrieben") — Nutzer/in erscheint in der Teilnehmerliste (z. B. für Berichte/Zertifikate), hat aber keinen Zugriff auf den Kursinhalt. |
   | `none` | Keine Host-Kurs-Einschreibung wird angelegt; nur die Lernpfad-Subscription selbst erfolgt. |

2. `reconciler::reconcile_host_user()` bekommt einen zusätzlichen Parameter
   für den Modus (statt eines reinen Bool für „entitled"), oder — sauberer —
   `mod_adele` übergibt bereits die gewünschte Zielsituation
   (`ENROL_USER_ACTIVE` / `ENROL_USER_SUSPENDED` / „keine Instanz anlegen"),
   und `reconciler` bleibt weiterhin rein mechanisch (Entscheidung bleibt bei
   `mod_adele`, konsistent mit der bestehenden Aufgabenteilung).
3. Bei `none` wird — sofern bereits eine Instanz aus einer früheren
   Konfigurationsänderung besteht — die bestehende Einschreibung
   suspendiert, nicht gelöscht (Datenverlust vermeiden, L-Q-07); ein
   Moduswechsel ist jederzeit reversibel.

## Manuelles Testverfahren

### `hidden`

1. Fall-3-Einbettung mit `hostenrolmentmode = hidden` anlegen.
2. Nutzer/in in einen Node-Kurs einschreiben.
3. Prüfen: Nutzer/in erscheint in der Teilnehmerliste des Host-Kurses, kann
   den Kurs aber nicht betreten (suspendierte Einschreibung).

### `none`

1. Fall-2-Einbettung mit `hostenrolmentmode = none` anlegen.
2. Nutzer/in in den Startnode-Kurs einschreiben.
3. Prüfen: Lernpfad-Subscription erfolgt, **keine** Host-Kurs-Einschreibung
   entsteht.

### Moduswechsel

1. Embedding von `visible` auf `none` umstellen und speichern.
2. Prüfen: bestehende aktive Host-Kurs-Einschreibungen werden suspendiert,
   nicht gelöscht.
3. Zurück auf `visible` umstellen.
4. Prüfen: dieselben Einschreibungen werden reaktiviert (keine neuen
   Datensätze).

## Upgrade-Anforderungen

Neues Feld `hostenrolmentmode` in `{adele}` (z. B. `char`, Default `'visible'`
für Bestandsdaten — kein Verhaltenswechsel für bestehende Aktivitäten).
`db/upgrade.php` ergänzt das Feld und setzt den Default für alle vorhandenen
Zeilen explizit.

## Automatisierte Tests

- `visible` verhält sich wie das bisherige Verhalten (Regressionstest).
- `hidden` erzeugt eine suspendierte statt aktive Einschreibung.
- `none` erzeugt keine Host-Kurs-Instanz, aber weiterhin die
  Lernpfad-Subscription.
- Moduswechsel `visible → none → visible` verändert nie die Instanz-ID
  (dieselbe `user_enrolment`-Zeile wird nur umgeschaltet).
- Fehlender/nicht gesetzter Wert (Altbestand nach Upgrade) verhält sich wie
  `visible`.

## Akzeptanzkriterien

- [ ] Lehrkräfte können pro Einbettung wählen zwischen sichtbarer,
      verdeckter (suspendierter) und keiner Host-Kurs-Einschreibung bei
      Fall 2/3.
- [ ] `hidden` erzeugt eine nachvollziehbare, aber zugriffslose
      Einschreibung.
- [ ] `none` erzeugt gar keine Host-Kurs-Instanz.
- [ ] Ein Moduswechsel verliert keine bestehenden Einschreibungsdaten
      (Suspendieren statt Löschen).
- [ ] Bestandsaktivitäten verhalten sich nach dem Upgrade unverändert
      (`visible`-Default).
- [ ] Der Fix funktioniert unter PostgreSQL und MariaDB.
