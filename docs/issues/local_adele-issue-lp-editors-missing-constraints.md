# [IMPROVEMENT] `local_adele_lp_editors` ohne Unique-Index und Fremdschlüssel (G.18)

## Problem

```xml
<TABLE NAME="local_adele_lp_editors" COMMENT="Users that are allowed to edit certain learningpaths">
  <FIELDS>
    <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false"/>
    <FIELD NAME="learningpathid" TYPE="int" LENGTH="10" NOTNULL="false" SEQUENCE="false" COMMENT="Learningpath id"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
  </KEYS>
</TABLE>
```

Kein Unique-Index auf `(userid, learningpathid)`, beide Felder
`NOTNULL="false"`, keine Fremdschlüssel auf `{user}` bzw.
`local_adele_learning_paths`.

## Ursache

Das Schema schützt die fachliche Invariante „eine Person ist höchstens
einmal Editor/in eines Lernpfads" nicht selbst — der Code enthält bereits
Workarounds für doppelte Editor-Zeilen (Hinweis darauf, dass Duplikate in
der Praxis vorkommen).

## Lösung

Upgrade-Schritt in `db/upgrade.php`:

1. Bestehende Duplikate bereinigen (niedrigste `id` je `(userid,
   learningpathid)`-Paar behalten, Rest löschen).
2. `userid`/`learningpathid` auf `NOTNULL` setzen.
3. Unique-Index auf `(userid, learningpathid)` ergänzen.
4. Optional: Fremdschlüssel auf `user.id` und
   `local_adele_learning_paths.id` (rein deklarativ, MySQL/MariaDB/
   PostgreSQL prüfen das je nach Engine unterschiedlich streng).

```xml
<INDEXES>
  <INDEX NAME="userid_learningpathid" UNIQUE="true" FIELDS="userid, learningpathid"/>
</INDEXES>
```

## Manuelles Testverfahren

### Vorbereitung

Testinstanz mit einem Lernpfad und mindestens einer/einem Editor/in.

### Testschritte

1. Vor dem Upgrade: per Script/DB-Zugriff eine doppelte Editor-Zeile für
   denselben `(userid, learningpathid)` einfügen.
2. Upgrade ausführen.
3. Prüfen, ob die Duplikate bereinigt wurden und der Unique-Index
   existiert.
4. Versuchen, programmatisch eine doppelte Zeile einzufügen — sollte an
   der DB-Ebene scheitern.

### Aktuelles Ist-Verhalten

Duplikate sind möglich und bleiben bestehen.

### Erwartetes Soll-Verhalten

Duplikate sind nach dem Upgrade bereinigt; neue Duplikate werden von der
Datenbank verhindert.

## Automatisierte Tests

- Upgrade-Test mit vorab eingefügten Duplikaten: nach dem Upgrade genau
  eine Zeile je `(userid, learningpathid)`.
- Versuch, eine doppelte Zeile einzufügen, wirft eine
  `dml_write_exception`.

## Akzeptanzkriterien

- [ ] Upgrade-Schritt bereinigt Bestandsduplikate ohne Datenverlust
      (höchste/aktuellste Zeile bleibt erhalten).
- [ ] Unique-Index vorhanden.
- [ ] Felder `NOTNULL`.
