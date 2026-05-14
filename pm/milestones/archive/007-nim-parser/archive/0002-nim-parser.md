# 0002 — Nim-Parser (PHP-seitiger Tokenizer)

Blocked by: 0001

## Done when

- `app/_share/spec_parser/nim_parser.php` implementiert `nim_parser::parse(string $path): array` nach Schema aus M005/0001.
- Parser ist ein PHP-seitiger State-Machine-Tokenizer (kein Regex auf Quelltext, Decision 0006). Strings, Triple-Strings (`"""..."""`), Raw-Strings (`r"..."`), Block-Kommentare (`#[ ... ]#`) duerfen nie als Spec-Marker missverstanden werden.
- Spec-Block-Erkennung: Marker `## @spec` ... `## @end-spec` (wie in 0001 festgelegt), dazwischenliegende `##`-Doc-Kommentare werden als Spec-Zeilen extrahiert (ohne Marker, ohne `## `-Praefix).
- Symbol-Erkennung: `type T = object`, `proc foo(...)`, `func`, `method`, `iterator`, `template`, `macro`, `const`, `let`, `var` Top-Level. Objekt-Felder innerhalb `type T = object` Block. `proc`-Signatur als Source-String inkl. Parameter-Typen, Defaults, Return-Typ, Pragmas.
- Indentation-basierter Block-Scope (Nim hat keine Klammern fuer Bloecke) wird per Spalten-Zaehlung verfolgt — analog Python-Style. Lokale Spec-Bloecke innerhalb eines `proc` gehen in `members[]` der proc mit `kind: "local"`.
- Output-Schema bleibt das aus M005/0001 (`file_spec`, `symbols`, `warnings`). `kind`-Werte: `class` -> nutze `object` als Nim-Aequivalent? Entscheide im README in 0001 mit (oder hier dokumentiere). Vorschlag: `kind: "object"`, `kind: "proc"`, `kind: "func"`, `kind: "type"` etc.
- Existence-Check: Datei nicht da -> `warnings[] = ["file not found: ..."]`, leeres Schema sonst.
- Dangling-Spec-Block -> Warning, Block verworfen.
- `php -l` sauber.

## Smoketest

- Mini-`.nim`-Fixture mit Datei-Header-Spec, Type+Object+Felder mit Spec, proc mit Spec inkl. Conditions. Manuell parsen, Output zeigt erwartete Struktur. Fixture-Datei kann in 0003 als erste echte Test-Fixture wandern.

## Out of scope

- Fixture-Test-Runner — 0003.
- UI-Integration — 0004.
- Nim-spezifische Edge-Cases die aus dem Marker/Indent-Modell rausfallen (z.B. Nim-Macro-erweiterter Code) — V1 parst nur den Source wie geschrieben.

## Done

- **Tokenizer** in `app/_share/spec_parser/nim_parser.php` als
  PHP-seitige State-Machine implementiert (kein Regex, Decision 0006).
  Token-Klassen: `whitespace`, `newline`, `comment_line` (`# ...`),
  `doc_comment_line` (`## ...`), `comment_block` (`#[ ... ]#`, **nested**
  per Bracket-Counting), `string_double` (`"..."` mit `\`-Escape),
  `string_triple` (`"""..."""`, mit Mehrzeilen-Inhalt + greedy trailing-
  quote-Logik), `string_raw` (sowohl `r"..."` als auch generalisierte
  `ident"..."`-Strings, plus `ident"""..."""`-Variante; `""` innerhalb
  als escaped quote behandelt), `string_char` (`'x'`), `identifier`,
  `number` (inkl. Nim-Type-Suffix `'i64`/`'f32` etc.), `punctuation`.
  Jedes Token traegt `[kind, text, line, col]` — `col` ist die 0-basierte
  Spalte des ersten Zeichens auf seiner Zeile. Damit kann der Walker
  Indentation tracken, ohne Whitespace-Tokens auszuwerten.

- **Spec-Block-Erkennung**: `is_spec_start_token` / `is_spec_end_token`
  pruefen exklusiv `doc_comment_line`-Tokens; `###...` (3+ Hash-Zeichen)
  zaehlt nicht, weil das ueblicherweise Bonusausgabe ist und Spec-Marker
  nur exakt `## @spec` / `## @end-spec` ist. Inline-Inhalt nach
  `## @spec ` wird zur ersten Spec-Zeile (analog PHP/JS-Parser).

- **Datei-Header-Spec**: erster Spec-Block der Datei, wenn er VOR dem
  ersten Top-Level-Deklarations-Keyword (col == 0) liegt. Sonst zaehlt
  der Block als Symbol-Spec. Das Top-Level-Keyword wird per
  `first_decl_index` ermittelt (Walker macht denselben Trick wie
  `js_parser`).

- **Symbol-Walker**: dispatcht bei col == 0:
  `proc/func/method/iterator/template/macro/converter` ->
  `read_routine`; `type` -> `read_type_section` (single-line oder
  Section-Style); `const/let/var` -> `read_var_section` (single oder
  Section). Andere col == 0-Identifier (z.B. `import`, `from`, `result`)
  werden ignoriert.

- **Routinen** (`read_routine`): Signatur ist Source-String von
  Keyword bis vor `=` (depth 0) oder bis Newline (forward-decl).
  Pragmas `{.inline.}` und Generics `[T]` landen automatisch in der
  Signatur, weil sie in `(`/`[`/`{`-Tiefe stehen oder vor dem `=`.
  Body ist alles vom `=` bis zum naechsten Token mit `col <= 0` (oder
  einem Top-Level-Spec-Marker mit `col <= 0`, damit ein dangling am
  Datei-Ende den Body schliesst). Lokale Spec-Bloecke im Body landen
  als `members[]` mit `kind: "local"`.

- **Type-Section** (`read_type_section`): erkennt single-line
  `type Foo = ...` und multi-line `type \n  Foo = ...`. Spec auf der
  `type`-Zeile wird vom ersten Eintrag uebernommen (via `carry_spec`),
  Folgeeintraege brauchen eigene Spec-Bloecke.

- **Object-Felder** (`collect_object_fields`): nimmt die Indent-Tiefe
  des ersten Feldes als Block-Indent. Felder werden als
  `name[, name2, ...]: Type [= default]` geparst, mit optionalem
  `*`-Export-Marker. Mehrere Namen pro Zeile (`r, g, b: int`)
  expandieren zu mehreren `field`-Members. Spec-Bloecke direkt vor
  einem Feld werden dem Feld zugeordnet.

- **Var/Let/Const**: analoge Section-/Single-Logik. Default-Source-
  String wird vom `=` bis zum Zeilenende gelesen (depth-0 Klammern
  korrekt getrackt).

- **Edge-Cases (alle gruen)**:
  - `## @spec` in `"..."`, `"""..."""`, `r"..."` -> Tokenizer
    klassifiziert als String-Token, nie als doc_comment_line.
  - `## @spec` in `#[ ... ]#` (auch nested) -> Tokenizer klassifiziert
    als comment_block-Token. Bracket-Counting laeuft sauber.
  - Dangling Spec am Datei-Ende: durch col-Vergleich im
    `find_block_end_by_col` wird ein Spec-Marker mit `col <= base_col`
    als Body-Ende gewertet, also nicht in `members[]` der vorigen
    Routine eingesammelt. Walker bekommt den Block dann ans
    Datei-Ende, kein folgendes Symbol -> dangling-Warning.
  - Generics (`[T]`) und Pragmas (`{.inline.}`): bleiben in der
    Signatur, weil sie tokenmaessig in der Signatur-Region liegen.

- **Smoketest-Outputs (kompakt)**:

  `/tmp/nim_smoke.nim`:
  ```
  file_spec: ["File-level: tiny demo for nim parser smoketest."]
  symbols:
    - object Point  -> field x (spec: ["horizontal coordinate"]),
                       field y (spec: ["vertical coordinate"])
    - proc distance -> sig "proc distance(a, b: Point): float",
                       spec ["Returns Euclidean distance between two points.",
                             "throws ValueError if both points are nil"]
  warnings: ["dangling spec at line 23"]
  ```

  `/tmp/nim_strings.nim`:
  ```
  symbols: let s, let t, let r (alle drei mit korrekt erfasstem
           default-string, der das fake-"## @spec"-Material enthaelt)
  warnings: []
  ```

  `/tmp/nim_block_comment.nim`:
  ```
  symbols: proc real_proc, signature "proc real_proc(): int"
  warnings: []
  ```

  `/tmp/nim_dangling.nim`:
  ```
  symbols: proc ok, signature "proc ok()"
  warnings: ["dangling spec at line 2"]
  ```

  `/tmp/nim_extra.nim` (Bonus, nicht im Ticket gefordert):
  - File-Spec, type-section mit zwei objects, Generic-Proc mit Pragma
    `proc identity[T](x: T): T {.inline.}`, getypter let — alles
    korrekt erfasst.

### Verifikations-Belege

- `php -l app/_share/spec_parser/nim_parser.php` -> "No syntax errors detected".
- CLI-Smoketests (alle vier Pflicht-Tests + Bonus) in den Ouput-Snippets
  oben dokumentiert; `index.php` exit 0 fuer alle.
- `php app/_share/spec_parser/tests/run.php` -> `12/12 passed`,
  exit 0 (keine Regression in PHP/JS-Fixtures).
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Temp-Files unter `/tmp/nim_*.nim` nach Verifikation aufgeraeumt.
- Out-of-scope eingehalten: keine Nim-Fixtures im Test-Runner
  hinzugefuegt (das ist 0003), `app/file.php` nicht angefasst (0004),
  README nicht editiert (Schema steht), kein anderer Parser
  veraendert.
