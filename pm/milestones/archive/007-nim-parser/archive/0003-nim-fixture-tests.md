# 0003 — Nim-Fixture-Tests

Blocked by: 0002

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/nim/` mit Fixture-Paaren `<name>.nim` + `<name>.expected.json`.
- Mindestens diese Fixtures:
  - `proc_with_conditions.nim` — Datei-Header + proc mit Spec inkl. mehreren Conditions.
  - `type_object_with_fields.nim` — `type T = object` mit Felder-Specs.
  - `dangling_spec.nim` — Spec-Block ohne folgendes Symbol -> Warning.
  - `spec_in_string.nim` — Triple-String und Raw-String mit `## @spec` als Text -> NICHT als Spec geparst.
  - `block_comment.nim` — `#[ ## @spec ... ]#` Block-Kommentar mit Spec-Text -> NICHT geparst.
  - `local_spec.nim` — Spec innerhalb eines proc-Body -> `members[]` mit `kind: "local"`.
  - `no_spec.nim` — kein `@spec` irgendwo -> leeres Schema.
- Test-Runner: `app/_share/spec_parser/tests/run.php` (falls schon aus M005/0004 da: erweitern; sonst neu) iteriert ueber alle Sprach-Fixture-Verzeichnisse und vergleicht JSON-Output mit `.expected.json`. Aufruf `php app/_share/spec_parser/tests/run.php` exit 0 bei allen-gruen, exit 1 sonst.
- Output-Zusammenfassung: `nim: X/Y passed`.
- `php -l` sauber.

## Out of scope

- UI-Integration — 0004.
- Andere Sprach-Fixtures (PHP/JS) — die leben in M005/0004.

## Done

- Verzeichnis `app/_share/spec_parser/tests/fixtures/nim/` angelegt mit
  sieben Fixture-Paaren `<name>.nim` + `<name>.expected.json`:
  - `proc_with_conditions.nim` — Datei-Spec + `proc validate_user_input`
    mit drei typed args (string/string/int=64) + Return-Type, vier
    Condition-Zeilen (throws + returned).
  - `type_object_with_fields.nim` — Datei-Spec + `type Reading = object`
    mit drei Feldern (`id: int`, `label: string`, `value: float`),
    je Feld eine Spec-Zeile.
  - `dangling_spec.nim` — Datei-Spec + `proc ok(): int` mit eigener
    Spec, gefolgt von einem trailing Spec-Block ohne folgendes Symbol.
    `warnings` enthaelt `"dangling spec at line 11"`.
  - `spec_in_string.nim` — drei Top-Level `let`-Variablen mit
    Triple-String, Raw-String und Plain-String, die `## @spec`/
    `## @end-spec` als Text enthalten. Erwartet: drei `let`-Symbole,
    `warnings: []` und KEINE fake-Specs.
  - `block_comment.nim` — `#[ ## @spec ... ## @end-spec ]#` Block-
    Kommentar plus `proc real_proc(): int`. Erwartet: nur die proc,
    keine fake-Spec, keine warning.
  - `local_spec.nim` — Datei-Spec + `proc compute(a, b: int): int` mit
    eigener Spec; im Body ein lokaler Spec-Block. `members[]` der
    proc enthaelt einen `kind: "local"`-Eintrag mit dem Spec-Text und
    Namens-Hint (erste 30 Zeichen, analog PHP-local_spec-Fixture).
  - `no_spec.nim` — `type Bare = object` mit zwei Feldern + `proc
    bare_helper(count: int): int`, ohne irgendwelche `## @spec`-Marker.
    Erwartet: `file_spec: []`, `warnings: []`, beide Symbole mit
    `spec: []` und Felder mit `spec: []`.

- `app/_share/spec_parser/tests/run.php` erweitert:
  - Header-Spec aktualisiert (Erwaehnung `.nim`, Pro-Sprache-Summary).
  - Globale `$lang_counts` ergaenzt; `run_fixture()` nimmt jetzt einen
    `$language`-Tag und zaehlt total/passed pro Sprache.
  - `run_all_fixtures()` iteriert ueber drei Gruppen (php/js/nim) statt
    ueber zwei; Gruppen-Tabelle mit Endung + Lang-Tag haelt die Iteration
    DRY und macht das Hinzufuegen weiterer Sprachen trivial.
  - End-Output ergaenzt um Pro-Sprache-Zeilen `php: X/Y`, `js: X/Y`,
    `nim: X/Y` (stabile Reihenfolge).

- `pm/milestones/007-nim-parser/milestone.md`: Box fuer 0003 abgehakt.

### Test-Run-Output

```
spec_parser fixture tests
=========================
PASS app/_share/spec_parser/tests/fixtures/php/class_with_fields.php
PASS app/_share/spec_parser/tests/fixtures/php/dangling_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/function_with_conditions.php
PASS app/_share/spec_parser/tests/fixtures/php/local_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/no_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/spec_in_string.php
PASS app/_share/spec_parser/tests/fixtures/js/class_with_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/function_with_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/no_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/regex_literal.js
PASS app/_share/spec_parser/tests/fixtures/nim/block_comment.nim
PASS app/_share/spec_parser/tests/fixtures/nim/dangling_spec.nim
PASS app/_share/spec_parser/tests/fixtures/nim/local_spec.nim
PASS app/_share/spec_parser/tests/fixtures/nim/no_spec.nim
PASS app/_share/spec_parser/tests/fixtures/nim/proc_with_conditions.nim
PASS app/_share/spec_parser/tests/fixtures/nim/spec_in_string.nim
PASS app/_share/spec_parser/tests/fixtures/nim/type_object_with_fields.nim

synthetic tests
---------------
PASS vendor blacklist rejects app/_share/vendor/* paths
PASS unsupported language rejects .css

19/19 passed
php: 6/6
js: 4/4
nim: 7/7
```

Exit-Code: 0.

### Schema vs. Parser-Output — Diskrepanzen

Keine Abweichungen vom dokumentierten Schema oder Verhalten gefunden.
Der Parser-Output passt bei allen sieben Nim-Fixtures auf die
README-Vorgaben (Datei-Header-Heuristik, `kind`-Werte fuer Nim-Symbole,
String-/Block-Kommentar-Edge-Cases, dangling-Warning, Local-Spec-
Capture in `members[]`).

Feinheiten, die ich beim Schreiben der `expected.json`-Dateien bewusst
ueberprueft habe (kein Bug, aber auffallend):

1. **Datei-Header schluckt den ersten Spec-Block, falls dieser vor dem
   ersten Top-Level-Decl steht.** In `local_spec.nim` und
   `dangling_spec.nim` musste ich daher zwei Spec-Bloecke vor dem
   ersten Symbol setzen, damit sowohl `file_spec` als auch die proc-
   eigene Spec belegt sind. Verhalten ist im README in der "Direkt
   darauffolgend"-Sektion sowie unter "Granularitaet (Datei-Header)"
   dokumentiert.

2. **`local`-Member traegt einen `name`-Hint** (erste 30 Zeichen der
   ersten Spec-Zeile). Das Schema im README sagt: "Bei `local`: leerer
   String oder eine kurze Beschreibung — Parser-Wahl." Nim-Parser folgt
   damit dem PHP-Parser-Stil (siehe `local_spec.expected.json` der
   PHP-Fixtures).

3. **`object`/`field`-Symbole haben kein `default`-Feld**, wenn keiner
   gesetzt ist (statt `"default": ""`). Das ist konsistent mit dem
   PHP-Property-Verhalten in `class_with_fields`-Fixtures und reduziert
   Rauschen in der expected.json.

4. **Triple-String mit Newlines bleibt als Source-String inklusive
   Newlines erhalten** (siehe `spec_in_string.expected.json`,
   `triple_payload.default`). Der `tokens_to_source()`-Helper komprimiert
   Whitespace nicht innerhalb von String-Tokens — der Token-Text wird
   1:1 ausgegeben. Korrekt fuer Nim-Triple-Strings (sie haben keine
   Escapes; Newlines sind Teil des String-Werts).

### Verifikations-Belege

- `php -l app/_share/spec_parser/tests/run.php` -> "No syntax errors detected".
- `php -l app/_share/spec_parser/nim_parser.php` -> sauber.
- `php -l app/_share/spec_parser/spec_parser.php` -> sauber.
- `php app/_share/spec_parser/tests/run.php` -> `19/19 passed`,
  `php: 6/6`, `js: 4/4`, `nim: 7/7`, exit 0.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).
- Out-of-scope eingehalten: `app/file.php` nicht angefasst (das ist
  0004); `nim_parser.php` nicht veraendert (keine Bugs gefunden);
  README nicht editiert; keine andere Sprache angefasst; keine PHP/JS-
  Fixtures aufgeraeumt.
