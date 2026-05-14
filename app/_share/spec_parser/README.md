# spec_parser

Sprach-agnostischer Spec-Parser: liest `// @spec ... // @end-spec`-Bloecke
aus PHP- und JS-Quellcode und gibt sie zusammen mit Signatur/Typ-Infos
aus dem Code als einheitliches JSON-Schema aus.

Skizze und Motivation: `pm/ideas/spec-as-comment.md`.
Decision "kein Regex": `pm/decisions/0006-spec-parser.md`.
Architektur-Ticket (dieses Dokument): `pm/milestones/005-spec-parser/archive/0001-parser-architecture-and-schema.md`.

Aktueller Stand (M005/0001): Architektur, Schema, Sprach-Dispatch und
CLI-Einstieg stehen. Die Sprach-Parser (`php_parser.php`, `js_parser.php`)
sind Stubs und liefern leeres `file_spec`/`symbols`/`warnings`. Tickets
M005/0002 (PHP) und M005/0003 (JS) fuellen sie ohne Schema-Aenderung.

## Eingabe

Ein Dateipfad als String. Akzeptiert wird:

- relativ zum Repo-Root (`app/user/data/User.php`),
- absolut (`/home/mo/Schreibtisch/speckig/app/user/data/User.php`).

Der Pfad wird **nicht** via `realpath()` aufgeloest — Symlinks
wuerden den Vendor-Blacklist-Check umgehen, der textuell auf
`app/_share/vendor/` matcht.

## Aufruf-Form

Es gibt zwei Wege, beide zielen auf die selbe Schema-Ausgabe:

1. **CLI** — `php app/_share/spec_parser/index.php <path>`.
   Schreibt JSON auf STDOUT, Exit-Codes:
   - `0` — Datei wurde erfolgreich dispatcht (auch wenn Stub leeres
     Schema liefert).
   - `1` — Aufruf-Fehler (Pfad fehlt, Datei nicht gefunden).
   - `2` — `error`-Feld im Schema (Vendor-Blacklist, unsupported language).
   Genutzt von M005/0004 (Fixture-Test-Runner) und fuer Ad-hoc-Debugging.

2. **Funktion** — `spec_parser::parse(string $path): array`.
   Gibt das Schema-Array zurueck (nicht JSON-encoded — der CLI macht
   `json_encode`). Genutzt von M005/0005 (UI-Integration in `app/file.php`).

Begruendung fuer beides: CLI ist die einfachste Test-Schnittstelle und
braucht keine Server-Infrastruktur; die Funktion ist die natuerliche
Form fuer den UI-Render-Pfad, weil dort schon ein PHP-Prozess laeuft
und das Array direkt weiterverarbeitet wird (kein `json_decode` noetig).

Klassenname und Funktionsname sind `snake_case` / lowercase nach
Decision 0003 (statisches Funktions-Buendel, nie `new spec_parser()`).

## Sprach-Dispatch

Die Endung des Eingabe-Pfads bestimmt den Sprach-Parser:

| Endung | Parser                | Hinweis                            |
|--------|------------------------|-------------------------------------|
| `.php` | `php_parser::parse()`  | nutzt `token_get_all` (M005/0002)   |
| `.js`  | `js_parser::parse()`   | PHP-seitiger Tokenizer (M005/0003)  |
| `.nim` | `nim_parser::parse()`  | PHP-seitiger Tokenizer (M007/0002)  |
| `.lua` | `lua_parser::parse()`  | PHP-seitiger Tokenizer (M008/0002)  |
| `.ts`  | `ts_parser::parse()`   | PHP-seitiger Tokenizer (M009/0002)  |
| `.groovy` | `groovy_parser::parse()` | PHP-seitiger Tokenizer (M010/0002) |
| sonst  | abgelehnt              | `{"error": "unsupported language"}` |

Pfade unter `app/_share/vendor/` werden **vor** dem Sprach-Dispatch
abgelehnt mit `{"error": "vendor code not parsed"}`. Vendor-Code soll
keine Spec tragen (Decision 0005) und ist fuer den Parser kein Kunde.

## JS-Parser-Strategie (Festlegung in diesem Ticket)

**PHP-seitiger JS-Tokenizer-Walker (State-Machine).** Keine vendored JS-Lib,
kein Node-Subprozess.

Begruendung:

- Das Projekt ist explizit "kein npm, kein Bundler, kein TypeScript"
  (Decision 0004) und PHP-only. Ein Node-Subprozess von PHP aus
  wuerde Node als neue Laufzeit-Abhaengigkeit einfuehren — das ist
  eine groessere Entscheidung als nur "JS parsen".
- Vendored Acorn/Esprima im Browser laufen zu lassen passt zwar zur
  bestehenden `app/_share/js/`-Konvention, hilft aber dem
  Server-Render-Pfad in M005/0005 nicht — die UI-Integration laeuft
  serverseitig, weil die Spec-View vor dem Code-HTML in der Antwort
  liegt.
- Der Subset, den der Spec-Parser braucht (Token-Klassifikation:
  Comment vs. String vs. Regex-Literal vs. Template-Literal,
  Top-Level-`function`/`class`/`const`/`let`/`var`, Klassen-Member),
  ist klein. Ein State-Machine-Tokenizer in 200-400 Zeilen PHP ist
  realistisch und debugbar — kein AST-Walker noetig.
- Die schwierigen Edge-Cases (Regex-Literal `/.../` mit `//` darin,
  Template-Literal `\`${...}\``, ASI) sind alle Tokenizer-Themen,
  keine AST-Themen, und werden in M005/0003 mit Fixtures abgedeckt
  (siehe `regex_literal.js` in M005/0004).

Wenn sich in M005/0003 herausstellt, dass der State-Machine-Tokenizer
zu fragil wird, ist Vendoring von Acorn als Browser-only-Loesung mit
serverseitigem Tokenizer-Fallback eine spaetere Decision. Heute:
PHP-seitig.

PHP-Parser nutzt das Built-in `token_get_all` — entsprechend keine
Diskussion noetig.

## Nim

Nim ist die dritte unterstuetzte Sprache (Milestone M007). Der Parser
ist ein Stub in M007/0001 (dieses Ticket); M007/0002 fuellt ihn.

### Marker-Konvention

Spec-Bloecke in Nim werden mit **`## @spec`** ... **`## @end-spec`**
markiert — als Doc-Comment-Zeilen.

Begruendung:

- Nim hat `#` als normalen Zeilenkommentar, `##` ist der **Doc-Comment**-
  Marker (vom Compiler ausgewertet, in `nim doc`-Output uebernommen).
- "Spec ist Doku" — eine Spec ist die maschinen-lesbare Doku zum Symbol,
  also semantisch ein Doc-Comment, kein Wegwirf-Kommentar.
- `## @spec` faellt damit nicht aus dem normalen Nim-Doku-Workflow heraus,
  sondern setzt darauf auf.

Beispiel:

```nim
## @spec
## Distance between two points in 2D.
## a and b are non-nil
## returns non-negative float
## @end-spec
proc distance(a, b: Point): float = ...
```

### Granularitaet

Spec-Bloecke koennen vor folgenden Symbolen stehen:

- **Datei-Header** — erster Block der Datei, vor jedem Top-Level-Symbol.
- **`type T = object`** / **`type T = enum`** / **`type T = tuple`** —
  Typ-Deklarationen.
- **`proc`**, **`func`**, **`method`**, **`iterator`**, **`template`**,
  **`macro`** — Routinen jeglicher Art.
- **`const`**, **`let`**, **`var`** auf Top-Level.
- **Object-Felder** innerhalb eines `type T = object`-Blocks.
- **Lokale Spec-Bloecke** innerhalb eines `proc`/`func`/`method`-Body —
  als `members[]`-Eintrag mit `kind: "local"`.

### `kind`-Werte fuer Nim-Symbole

| `kind`       | Symbol-Typ                                        |
|--------------|----------------------------------------------------|
| `proc`       | `proc foo(...) = ...`                              |
| `func`       | `func foo(...): T = ...` (side-effect-frei)        |
| `method`     | `method foo(...) = ...` (dynamic dispatch)         |
| `iterator`   | `iterator items(...): T = ...`                     |
| `template`   | `template foo(...) = ...`                          |
| `macro`      | `macro foo(...) = ...`                             |
| `type`       | `type T = ...` allgemein (z.B. Alias `type Id = int`) |
| `object`     | `type T = object` (mit Feldern)                    |
| `enum`       | `type T = enum`                                    |
| `field`      | Feld innerhalb eines `object`-Blocks               |
| `const`      | `const X = ...`                                    |
| `let`        | `let X = ...`                                      |
| `var`        | `var X = ...`                                      |
| `local`      | Spec-Block innerhalb eines Routine-Body            |

Wir uebernehmen also die Nim-Schluesselwoerter direkt als `kind`-Strings —
keine Uebersetzung in PHP/JS-Vokabular wie `class`/`function`. Nim hat
genug semantische Unterscheidungen, dass eine eigene Achse besser passt
als ein Mapping.

### "Direkt darauffolgend" — praezise

Ein Spec-Block bezieht sich auf das **naechste deklarations-tragende Symbol**
nach `## @end-spec`. Erlaubt zwischen Spec-Block und Symbol:

- Whitespace (Spaces, Tabs, Zeilenumbrueche) — beliebig viel.
- Andere `##`-Doc-Kommentare, die **nicht** mit `## @spec` beginnen
  (regulaere Nim-Doku-Zeilen).
- Normale `#`-Zeilenkommentare.
- Block-Kommentare `#[ ... ]#`.
- Pragmas in der Symbol-Signatur (`{.async.}`, `{.inline.}` etc.) —
  diese kommen typischerweise NACH dem Symbol-Namen, sind also Teil der
  Signatur, nicht etwas zwischen Spec und Symbol. Beispiel:
  `proc foo(): int {.inline.} = ...`.

**Nicht** erlaubt zwischen Spec-Block und Symbol:

- Ein anderes Symbol (Routine, Typ, Variable).
- Ein anderer `## @spec`-Block ohne dazwischenliegendes Symbol —
  der erste Block ist dann "dangling".

Wenn nach einem Spec-Block kein Symbol mehr folgt (Datei-Ende, oder nur
weitere Spec-Bloecke):

- Der Block wird **verworfen**.
- `warnings[]` bekommt einen Eintrag der Form
  `"dangling spec at line N"`.

### Indentation-basierte Bloecke

Nim hat keine geschweiften Klammern fuer Bloecke; Block-Grenzen werden
durch Einrueckung definiert (Off-Side-Regel, wie Python).

V1-Verhalten:

- **Object-Felder**: Nach `type T = object` startet der Felder-Block mit
  einer hoeheren Einrueckung. Felder werden gesammelt, bis die Einrueckung
  wieder unter die `object`-Spalten-Tiefe faellt.
- **Lokale Spec-Bloecke** in einem `proc`-Body werden anhand der
  Einrueckung erkannt: alles, was mit der Routinen-Body-Einrueckung oder
  tiefer kommt, gehoert zur Routine. Wir zaehlen Spalten, kein Regex.

### Parser-Strategie

PHP-seitiger State-Machine-Tokenizer (analog M005/0001-Festlegung fuer JS).
Keine externen Libs, kein Subprozess, **kein Regex** auf Quelltext
(Decision 0006).

Begruendung:

- Konsistenz mit der bestehenden Sprach-Parser-Architektur — alle Parser
  laufen im selben PHP-Prozess, kein neuer Laufzeit-Abhaengigkeit.
- Der noetige Subset (Token-Klassifikation: Comment vs. String vs.
  Triple-String vs. Raw-String vs. Block-Comment, Top-Level-Symbol-
  Erkennung, Indent-Tracking) ist klein genug fuer einen handgeschriebenen
  Tokenizer.
- Nim-Compiler-API als Backend (Subprozess `nim dump` / `nim doc`) ist
  ausdruecklich Out-of-Scope (M007/Out-of-scope) und waere eine eigene
  Decision.

Subset, den V1 NICHT semantisch versteht:

- **Nim-Macros** — Macro-erweiterter Code wird nicht expandiert. V1
  parst, was im Source steht.
- **Generische Procs** (`proc foo[T](x: T): T = ...`) — Generics werden
  tokenmaessig durchgelaufen und als Source-String Teil der Signatur.
  Keine Type-Inference, keine Constraint-Pruefung.

### Edge-Cases die NICHT als Spec erkannt werden duerfen

- `## @spec`-Text in Strings:
  - Einzeilige Strings `"..."`.
  - Triple-Strings `"""..."""`.
  - Raw-Strings `r"..."`.
- `## @spec`-Text in Block-Kommentaren `#[ ... ]#` (Nim erlaubt diese
  zu schachteln; der Tokenizer muss die Tiefe zaehlen).

Der Tokenizer klassifiziert diese Token-Klassen primaer; `## @spec` ist
**nur** dann ein Marker, wenn das Token vom Tokenizer als
"Doc-Comment-Zeile" eingestuft wurde — nicht innerhalb von String- oder
Block-Kommentar-Zustaenden.

### Beispiel-Output (Ziel-Output fuer M007/0002)

Nim-Quelle:

```nim
## @spec
## Geometry helpers for 2D points.
## @end-spec

## @spec
## A point in the plane.
## @end-spec
type
  Point = object
    ## @spec
    ## x coordinate
    ## @end-spec
    x: float
    ## @spec
    ## y coordinate
    ## @end-spec
    y: float

## @spec
## Distance between two points.
## both arguments are required
## returns non-negative float
## @end-spec
proc distance(a, b: Point): float =
  let dx = a.x - b.x
  let dy = a.y - b.y
  result = sqrt(dx * dx + dy * dy)
```

Erwartetes JSON (handgeschrieben):

```json
{
  "file": "geometry.nim",
  "language": "nim",
  "file_spec": [
    "Geometry helpers for 2D points."
  ],
  "symbols": [
    {
      "kind": "object",
      "name": "Point",
      "spec": ["A point in the plane."],
      "members": [
        {
          "kind": "field",
          "name": "x",
          "type": "float",
          "spec": ["x coordinate"]
        },
        {
          "kind": "field",
          "name": "y",
          "type": "float",
          "spec": ["y coordinate"]
        }
      ]
    },
    {
      "kind": "proc",
      "name": "distance",
      "signature": "proc distance(a, b: Point): float",
      "spec": [
        "Distance between two points.",
        "both arguments are required",
        "returns non-negative float"
      ],
      "members": []
    }
  ],
  "warnings": []
}
```

## Lua

Lua ist die vierte unterstuetzte Sprache (Milestone M008). Der Parser ist
ein Stub in M008/0001 (dieses Ticket); M008/0002 fuellt ihn. Zielpublikum
sind handgeschriebene Lua-Quellen, insbesondere Love2D-Spiele
(`love.load`, `love.update`, `love.draw`, ...).

### Marker-Konvention

Spec-Bloecke in Lua werden mit **`-- @spec`** ... **`-- @end-spec`**
markiert — als normale Lua-Zeilen-Kommentare.

Begruendung:

- Lua hat `--` als einzigen Zeilenkommentar-Marker; einen Doc-Comment-
  Marker wie Nims `##` gibt es nicht. Wir bleiben darum bei `-- @spec` /
  `-- @end-spec` und schliessen damit visuell an die PHP/JS-Konvention
  (`// @spec`) an — nur das Praefix wechselt.
- Block-Kommentare `--[[ ... ]]` und Long-Strings `[[ ... ]]` werden
  ausdruecklich **nicht** als Spec-Marker erkannt (siehe Edge-Cases).

Beispiel (Love2D-Update):

```lua
-- @spec
-- Step the simulation forward by dt seconds.
-- dt is the time elapsed since the last frame, always > 0
-- @end-spec
function love.update(dt)
    player.x = player.x + player.vx * dt
end
```

### Granularitaet

Spec-Bloecke koennen vor folgenden Symbolen stehen:

- **Datei-Header** — erster Block der Datei, vor jedem Top-Level-Symbol.
- **Top-Level `function name(...)`** — globale Funktion.
- **Qualifizierte Funktion `function table.method(...)`** — z.B.
  `function love.load()`, `function love.update(dt)`. Das ist die
  Standard-Engine-API in Love2D und wird als ein einzelnes Symbol mit
  qualifiziertem Namen (`name: "love.load"`) erkannt.
- **`local function name(...)`** — modulokale Funktion.
- **`local name = ...`** — modulokale Variable / Tabelle / Konstante auf
  Top-Level (Konstanten, Tabellen-Definitionen).
- **Tabellen-Felder mit Specs** innerhalb eines Tabellen-Literals
  `local foo = { ... }`, deren Eintraege durch Spec-Bloecke vorangestellt
  sind.
- **Lokale Spec-Bloecke** innerhalb eines Funktions-Body — als
  `members[]`-Eintrag mit `kind: "local"`.

### `kind`-Werte fuer Lua-Symbole

| `kind`           | Symbol-Typ                                                      |
|------------------|------------------------------------------------------------------|
| `function`       | Top-Level `function name(...) end`                               |
| `method`         | Qualifizierte `function table.method(...) end` (Love2D-Pattern)  |
| `local-function` | `local function name(...) end`                                   |
| `table`          | `local name = { ... }` — Tabellen-Definition auf Top-Level       |
| `field`          | Feld innerhalb einer Tabellen-Literal-Definition mit Spec        |
| `local-var`      | `local name = <skalar>` — Konstanten/Variable                    |
| `local`          | Spec-Block innerhalb eines Funktions-Body (lokale Notiz)         |

Wir uebernehmen Lua-Schluesselwoerter direkt als `kind`-Strings, ergaenzt
um die Lua-spezifischen Unterscheidungen zwischen unqualifiziertem
`function`, qualifiziertem `method` und `local-function`. Felder eines
Tabellen-Literals heissen `field` (gleicher Name wie bei Nim-Object-
Feldern — semantisch dasselbe). Skalar- vs. Tabellen-Locals werden
unterschieden, weil Tabellen Member tragen koennen, Skalare nicht.

### "Direkt darauffolgend" — praezise

Ein Spec-Block bezieht sich auf das **naechste deklarations-tragende
Symbol** nach `-- @end-spec`. Erlaubt zwischen Spec-Block und Symbol:

- Whitespace (Spaces, Tabs, Zeilenumbrueche) — beliebig viel.
- Andere `--`-Zeilen-Kommentare, die **nicht** mit `-- @spec` beginnen
  (regulaere Code-Kommentare).
- Block-Kommentare `--[[ ... ]]` (auch `--[=[ ... ]=]` mit beliebig
  vielen `=`).

**Nicht** erlaubt zwischen Spec-Block und Symbol:

- Ein anderes Symbol (`function`, `local function`, `local`-Deklaration,
  Top-Level-Statement).
- Ein anderer `-- @spec`-Block ohne dazwischenliegendes Symbol — der
  erste Block ist dann "dangling".

Wenn nach einem Spec-Block kein Symbol mehr folgt (Datei-Ende, oder nur
weitere Spec-Bloecke):

- Der Block wird **verworfen**.
- `warnings[]` bekommt einen Eintrag der Form
  `"dangling spec at line N"`.

### Parser-Strategie

PHP-seitiger State-Machine-Tokenizer (analog M005/0001 fuer JS und
M007/0001 fuer Nim). Keine externen Libs, kein Subprozess, **kein Regex**
auf Quelltext (Decision 0006).

Begruendung:

- Konsistenz mit der bestehenden Sprach-Parser-Architektur — alle Parser
  laufen im selben PHP-Prozess, kein neuer Laufzeit-Abhaengigkeit.
- Der noetige Subset (Token-Klassifikation: Comment vs. Block-Comment vs.
  String vs. Long-String; Top-Level-Symbol-Erkennung fuer `function`,
  `local`, qualifizierte Namen `a.b.c`) ist klein genug fuer einen
  handgeschriebenen Tokenizer.
- Eine externe Lua-Toolchain als Backend (z.B. `luac -l`, `luacheck`) ist
  ausdruecklich Out-of-Scope und waere eine eigene Decision.

Subset, den V1 NICHT semantisch versteht:

- **Metatables / `setmetatable`** — keine Vererbungs-Analyse, keine
  OO-Synthese aus `__index`-Ketten.
- **Closures jenseits des Top-Levels** — innere Funktionen werden nicht
  als eigene Symbole gesammelt; sie laufen als Code unter ihrer
  Outer-Funktion.

### Edge-Cases die NICHT als Spec erkannt werden duerfen

Der Tokenizer muss diese Faelle korrekt klassifizieren, sonst werden
String-Inhalte oder Block-Kommentar-Inhalte faelschlich als Spec gelesen:

- `-- @spec`-Text in einzeiligen Strings `'...'` und `"..."`.
- `-- @spec`-Text in Long-Strings `[[ ... ]]` und `[=[ ... ]=]` mit
  beliebig vielen `=` (matched levels — `[==[ ... ]==]` schliesst nur
  bei genau zwei `=`).
- `-- @spec`-Text in Block-Kommentaren `--[[ ... ]]` und
  `--[=[ ... ]=]`. Die Tiefe der `=` muss matchen; das ist Lua-Standard.
- `--[[`-Block-Kommentar darf vom Tokenizer korrekt vom Long-String
  `[[...]]` unterschieden werden — der Praefix `--` macht den
  Unterschied. Konkret: `[[` ohne vorangestelltes `--` ist Long-String,
  `--[[` ist Block-Kommentar.

Der Tokenizer klassifiziert diese Token-Klassen primaer; `-- @spec` ist
**nur** dann ein Marker, wenn das Token vom Tokenizer als
"Zeilen-Kommentar" eingestuft wurde — nicht innerhalb eines String-,
Long-String- oder Block-Kommentar-Zustands.

### Beispiel-Output (Ziel-Output fuer M008/0002)

Lua-Quelle (Love2D-Hauptdatei):

```lua
-- @spec
-- Demo player loop: load assets, advance position, draw the world.
-- @end-spec

local player = { x = 0, y = 0, vx = 50 }

-- @spec
-- Engine entry point. Loads the player sprite once at startup.
-- called by Love2D before the first frame
-- @end-spec
function love.load()
    player.sprite = love.graphics.newImage("player.png")
end

-- @spec
-- Advance simulation by dt seconds.
-- dt is always > 0 (Love2D guarantees this)
-- player.x is clamped to the screen width
-- @end-spec
function love.update(dt)
    player.x = player.x + player.vx * dt
    if player.x > love.graphics.getWidth() then
        player.x = 0
    end
end

-- @spec
-- Draw the player sprite at its current position.
-- @end-spec
function love.draw()
    love.graphics.draw(player.sprite, player.x, player.y)
end
```

Erwartetes JSON (handgeschrieben):

```json
{
  "file": "main.lua",
  "language": "lua",
  "file_spec": [
    "Demo player loop: load assets, advance position, draw the world."
  ],
  "symbols": [
    {
      "kind": "method",
      "name": "love.load",
      "signature": "function love.load()",
      "spec": [
        "Engine entry point. Loads the player sprite once at startup.",
        "called by Love2D before the first frame"
      ],
      "members": []
    },
    {
      "kind": "method",
      "name": "love.update",
      "signature": "function love.update(dt)",
      "spec": [
        "Advance simulation by dt seconds.",
        "dt is always > 0 (Love2D guarantees this)",
        "player.x is clamped to the screen width"
      ],
      "members": []
    },
    {
      "kind": "method",
      "name": "love.draw",
      "signature": "function love.draw()",
      "spec": [
        "Draw the player sprite at its current position."
      ],
      "members": []
    }
  ],
  "warnings": []
}
```

Die `local player = { ... }`-Zeile traegt keine Spec und erscheint
darum nicht in `symbols` — Symbole ohne `@spec` werden nur dann
gesammelt, wenn sie ein Container fuer andere Spec-tragende Member sind.

## TypeScript

TypeScript ist die fuenfte unterstuetzte Sprache (Milestone M009). Der
Parser ist ein Stub in M009/0001 (dieses Ticket); M009/0002 fuellt ihn.
Zielpublikum sind handgeschriebene TS-Quellen, insbesondere Angular-
Komponenten (`@Component`, `@Injectable`, `@Directive`, ...).

### Marker-Konvention

Spec-Bloecke in TypeScript werden mit **`// @spec`** ... **`// @end-spec`**
markiert — exakt wie in JavaScript. TS ist ein Superset von JS, der
Marker bleibt der gleiche.

Begruendung:

- TypeScript erbt JavaScripts Kommentar-Syntax (`//` Zeilen-Kommentar,
  `/* ... */` Block-Kommentar, `/** ... */` JSDoc) eins-zu-eins. Eine
  separate TS-Markervariante waere unnoetige Verdopplung.
- JSDoc-Bloecke `/** ... */` bleiben **unangetastet** und werden NICHT
  als Spec interpretiert. Wer in einer TS-Codebase JSDoc-Doku hat, kann
  Spec-Bloecke und JSDoc parallel fuehren — das eine ersetzt das andere
  nicht.

Beispiel (Angular-Komponente):

```ts
// @spec
// App entry component, mounts at /app-root.
// @end-spec
@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
})
export class AppComponent {
  // @spec current user, null when logged out
  // @end-spec
  user: User | null = null;

  // @spec
  // Logs the user in via the auth service.
  // throws AuthError on bad credentials
  // @end-spec
  @LogCall()
  async login(username: string, password: string): Promise<void> {
    ...
  }
}
```

### Granularitaet

Spec-Bloecke koennen vor folgenden Symbolen stehen:

- **Datei-Header** — erster Block der Datei, vor jedem Top-Level-Symbol.
- **`class`** — Klassen-Deklarationen, mit oder ohne Decorators.
- **`interface`** — Interface-Deklarationen.
- **`enum`** — Enum-Deklarationen.
- **`type`-Aliase** — `type Foo = ...`.
- **Klassen-Member** — Properties, Methoden, Konstruktor (`constructor`),
  Getter (`get`), Setter (`set`).
- **Top-Level `function`** — exportierte oder lokale Top-Level-Funktionen.
- **Top-Level `const` / `let` / `var`** — Konstanten / Variablen.
- **Decorator-tragende Symbole** — `@Component`, `@Injectable`,
  `@Directive`, custom (`@LogCall`, ...). Spec-Block + 0..n Decorators
  + Symbol heisst: Spec gehoert zum Symbol, NICHT zum Decorator. Die
  Decorators werden als Strukturinfo am Symbol unter `decorators[]`
  mitgefuehrt (siehe Schema-Erweiterung unten).
- **Lokale Spec-Bloecke** innerhalb eines Funktions-/Methoden-Body —
  als `members[]`-Eintrag mit `kind: "local"`.

### `kind`-Werte fuer TS-Symbole

| `kind`         | Symbol-Typ                                                       |
|----------------|-------------------------------------------------------------------|
| `class`        | `class Name { ... }` (mit oder ohne `@Decorator`)                 |
| `interface`    | `interface Name { ... }`                                          |
| `enum`         | `enum Name { ... }` (auch `const enum Name { ... }`)              |
| `type`         | `type Name = ...` (Aliases, Mapped Types, Union/Intersection)     |
| `function`     | Top-Level `function name(...) { ... }`                            |
| `method`       | Klassen-Methode `name(...) { ... }` (auch `async`/`static`)       |
| `constructor`  | Klassen-Konstruktor `constructor(...) { ... }`                    |
| `property`     | Klassen-Property `name: Type [= default]`                         |
| `getter`       | Klassen-Getter `get name(): Type { ... }`                         |
| `setter`       | Klassen-Setter `set name(v: Type) { ... }`                        |
| `const`        | Top-Level `const NAME = ...`                                      |
| `let`          | Top-Level `let NAME = ...`                                        |
| `var`          | Top-Level `var NAME = ...`                                        |
| `local`        | Spec-Block innerhalb eines Funktions-/Methoden-Body               |

Im Vergleich zur JS-Liste (`class`, `function`, `method`, `getter`,
`setter`, `property`, `const`, `let`, `var`, `local`) kommen
`interface`, `enum`, `type` und `constructor` dazu — die TS-spezifischen
Deklarations-Formen bekommen jeweils ihren eigenen `kind`-String, kein
Mapping in JS-Vokabular. `constructor` wird gegenueber JS ausgegliedert,
damit Renderer Konstruktoren von normalen Methoden unterscheiden koennen
(in TS ist das semantisch wichtiger, weil der Konstruktor Parameter-
Properties via `public`/`private`-Modifier deklarieren kann).

### Decorator-Behandlung (Schema-Erweiterung)

Symbol-Level-Decorators (`@Component(...)`, `@Injectable()`, `@LogCall`,
...) werden als Strukturinfo am Symbol mitgefuehrt:

```json
{
  "kind": "class",
  "name": "AppComponent",
  "decorators": [
    {"name": "Component", "args_source": "{selector: 'app-root', templateUrl: './app.component.html'}"}
  ],
  "spec": [...],
  "members": [...]
}
```

Form: `decorators` ist ein Array von Objekten mit:

- `name` (string) — der Decorator-Identifier (der Identifier nach `@`).
  Bei qualifizierten Namen wie `@Foo.Bar` wird der volle Pfad
  uebernommen: `name: "Foo.Bar"`.
- `args_source` (string|null) — der Source-String der Decorator-Args
  zwischen den Klammern (ohne Klammern selbst). Drei Faelle:
  - **`null`** wenn der Decorator ohne Klammern steht: `@Override` ->
    `{name: "Override", args_source: null}`.
  - **`""`** wenn die Klammern leer sind: `@LogCall()` ->
    `{name: "LogCall", args_source: ""}`.
  - **`"<inhalt>"`** wenn Klammern mit Inhalt: `@Component({selector:'app-root'})`
    -> `{name: "Component", args_source: "{selector:'app-root'}"}`.

Begruendung fuer die `null` vs. `""`-Unterscheidung: ein Renderer kann
zwischen "Decorator ohne Aufruf" (`@Override`) und "Decorator mit leerem
Aufruf" (`@LogCall()`) sinnvoll unterscheiden — das sind in TypeScript
nicht synonyme Formen (die zweite wertet einen Decorator-Factory-Aufruf
aus, die erste verwendet die Funktion direkt). Wir geben dem Konsumenten
beide Informationen, statt sie schon im Parser zu vermischen.

Der Inhalt von `args_source` wird **nicht** semantisch geparst — kein
JSON-Decode, kein TS-Parse. Es ist der rohe Source-String wie er im
Code steht (Whitespace zu einzelnen Spaces komprimiert, analog zu
`signature`/`default` bei den anderen Sprachen). Wer den Selector aus
einem `@Component`-Aufruf braucht, parst `args_source` selbst —
typischerweise reicht eine Mini-Heuristik wie "string nach
`selector:`".

**Schema-Erweiterung am Symbol**: `decorators` ist ein **optionales**
Feld (Default `[]`). Andere Sprachen (PHP, JS, Nim, Lua) setzen das
Feld nicht. Renderer (M009/0004 und ggf. M010 fuer ein
`annotations[]`-Aequivalent) generalisieren ueber das Feld.

V1 erkennt nur **Symbol-Level**-Decorators (vor Klassen, Methoden,
Properties, Konstruktoren, Settern, Gettern). **Parameter-Decorators**
(`@Inject(TOKEN) param: T` innerhalb einer Argumentliste) werden NICHT
extrahiert — sie laufen tokenmaessig durch und landen als Bestandteil
des Signatur-Source-Strings. Falls Bedarf entsteht, ist das eine eigene
spaetere Schema-Erweiterung.

### "Direkt darauffolgend" — praezise

Ein Spec-Block bezieht sich auf das **naechste deklarations-tragende
Symbol** nach `// @end-spec`. Dazwischen stehen darf:

- Whitespace (Spaces, Tabs, Zeilenumbrueche) — beliebig viel.
- Block-Kommentare `/* ... */`.
- JSDoc-Bloecke `/** ... */`.
- Andere `// ...`-Zeilen-Kommentare, die **nicht** mit `// @spec`
  beginnen (regulaere Code-Kommentare).
- **Decorators** (`@Component(...)`, `@Injectable()`, custom). Beliebig
  viele in Folge — Spec-Block + Decorator-Liste + Symbol heisst:
  Spec gehoert zum Symbol, Decorators gehen als `decorators[]`-Array
  ans Symbol.
- Modifier-Keywords vor der Symbol-Deklaration: `export`, `default`,
  `async`, `static`, `readonly`, `public`, `protected`, `private`,
  `abstract`, `declare`, `override`. Sie zaehlen als Teil der
  folgenden Deklaration.

**Nicht** erlaubt zwischen Spec-Block und Symbol:

- Ein anderes Symbol (Klasse, Funktion, Property, Konstante, Type-Alias,
  Interface, Enum).
- Ein anderer `// @spec`-Block ohne dazwischenliegendes Symbol — der
  erste Block ist dann "dangling".

Wenn nach einem Spec-Block kein Symbol mehr folgt (Datei-Ende, oder
nur weitere Spec-Bloecke):

- Der Block wird **verworfen**.
- `warnings[]` bekommt einen Eintrag der Form
  `"dangling spec at line N"`.

### Parser-Strategie

PHP-seitiger State-Machine-Tokenizer (analog M005/0001 fuer JS,
M007/0001 fuer Nim, M008/0001 fuer Lua). Keine externen Libs, kein
Subprozess (insbesondere kein Node + TS-Compiler-API), **kein Regex**
auf Quelltext (Decision 0006).

Begruendung:

- Konsistenz mit der bestehenden Sprach-Parser-Architektur — alle
  Parser laufen im selben PHP-Prozess, kein neuer Laufzeit-
  Abhaengigkeit. Decision 0004 ("kein npm, kein Bundler, kein
  TypeScript") gilt fuer den Speckig-Server-Code; sie schliesst
  ausdruecklich auch einen Node-Subprozess fuer den Parser aus.
- Der noetige Subset fuer Spec-Extraktion ist klein: Token-
  Klassifikation (Comment vs. String vs. Regex vs. Template-Literal,
  identisch zu JS), Decorator-Praefix `@<ident>(...)`, Top-Level-
  Symbol-Erkennung mit `class`/`interface`/`enum`/`type`/`function`/
  `const`/`let`/`var`. Type-Annotationen (nach `:` bis zum
  Statement-Ende) werden tokenmaessig geschluckt, nicht semantisch
  verstanden.
- Generics `<T extends Foo>` werden tokenmaessig durchgelaufen und
  landen als Source-String in der Signatur. Mapped Types
  (`{[K in keyof T]: U}`), Conditional Types (`T extends U ? X : Y`),
  Type-Inference und der gesamte Type-Checker sind
  **out of scope** — Speckig braucht das fuer Spec-Extraktion nicht.

Subset, den V1 NICHT semantisch versteht (gleicher Boden wie JS plus
TS-Spezifika):

- **Generics-Constraints** — werden im Source-String mitgefuehrt, kein
  Constraint-Check.
- **Mapped / Conditional / Inferred Types** — laufen als Tokens durch.
- **Decorator-Args** — bleiben als Source-String in `args_source`
  liegen, kein Parse des Args-Inhalts.
- **Parameter-Decorators** — gehen tokenmaessig in die Signatur, werden
  nicht als eigene `decorators[]`-Liste pro Parameter gesammelt.
- **Namespace-Bloecke** (`namespace X { ... }`) — V1 sammelt nur
  Top-Level-Symbole; Symbole im Namespace landen nicht in `symbols[]`.
  Falls noetig: eigene spaetere Erweiterung.

### Edge-Cases die NICHT als Spec erkannt werden duerfen

Der Tokenizer muss diese Faelle korrekt klassifizieren, sonst werden
String- oder Kommentar-Inhalte faelschlich als Spec gelesen:

- `// @spec`-Text in einzeiligen Strings `'...'` und `"..."`.
- `// @spec`-Text in Template-Literals `` `...` `` mit
  `${...}`-Interpolation. Der Tokenizer muss die Tilde-Klammern
  korrekt matchen, auch bei verschachtelten Strings im Interpolations-
  Body (`${"x"}`, `` ${`y`} ``).
- `// @spec`-Text in Regex-Literals `/.../flags`. Kontextabhaengige
  Erkennung wie in JS — nach Wert-produzierenden Tokens (`identifier`,
  `number`, `)`, `]`) ist `/` Division; nach Operatoren / Statement-
  Anfaengen ist `/` Regex-Start.
- `// @spec`-Text in Block-Kommentaren `/* ... */`.
- `// @spec`-Text in JSDoc-Bloecken `/** ... */`. JSDoc-Inhalt wird
  vom Parser ignoriert; nur One-Line-Kommentare `//` werden als
  Spec-Marker akzeptiert.

Der Tokenizer klassifiziert diese Token-Klassen primaer; `// @spec` ist
**nur** dann ein Marker, wenn das Token vom Tokenizer als
"Zeilen-Kommentar" (`comment_line`) eingestuft wurde — nicht innerhalb
eines String-, Template-, Regex- oder Block-Kommentar-Zustands.

### Beispiel-Output (Ziel-Output fuer M009/0002)

TypeScript-Quelle (Angular-Komponente):

```ts
// @spec
// App entry component, mounts at /app-root.
// @end-spec
@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
})
export class AppComponent {
  // @spec current user, null when logged out
  // @end-spec
  user: User | null = null;

  // @spec
  // Logs the user in via the auth service.
  // throws AuthError on bad credentials
  // @end-spec
  @LogCall()
  async login(username: string, password: string): Promise<void> {
    await this.auth.sign_in(username, password);
  }
}
```

Erwartetes JSON (handgeschrieben):

```json
{
  "file": "app.component.ts",
  "language": "ts",
  "file_spec": [],
  "symbols": [
    {
      "kind": "class",
      "name": "AppComponent",
      "extends": [],
      "implements": [],
      "decorators": [
        {
          "name": "Component",
          "args_source": "{ selector: 'app-root', templateUrl: './app.component.html', }"
        }
      ],
      "spec": [
        "App entry component, mounts at /app-root."
      ],
      "members": [
        {
          "kind": "property",
          "name": "user",
          "type": "User | null",
          "default": "null",
          "decorators": [],
          "spec": ["current user, null when logged out"]
        },
        {
          "kind": "method",
          "name": "login",
          "signature": "async login(username: string, password: string): Promise<void>",
          "decorators": [
            {"name": "LogCall", "args_source": ""}
          ],
          "spec": [
            "Logs the user in via the auth service.",
            "throws AuthError on bad credentials"
          ],
          "members": []
        }
      ]
    }
  ],
  "warnings": []
}
```

Anmerkungen zum Beispiel:

- Die Datei traegt keinen Datei-Header-Spec-Block — der erste
  `// @spec`-Block steht direkt vor `@Component`/`class AppComponent`
  und gehoert dadurch zur Klasse.
- `@Component(...)` und `@LogCall()` zeigen die drei Decorator-Args-
  Faelle: Args mit Inhalt (`args_source: "{ ... }"`) und leere Klammern
  (`args_source: ""`). Ein hypothetischer `@Override` ohne Klammern
  haette `args_source: null`.
- Beim `user`-Property zeigt das Beispiel das `decorators: []`-Default
  (kein Decorator vorhanden). Renderer behandeln fehlendes Feld und
  leeres Array gleich.

## Groovy

Groovy ist die sechste unterstuetzte Sprache (Milestone M010). Der Parser
ist ein Stub in M010/0001 (dieses Ticket); M010/0002 fuellt ihn.
Zielpublikum sind handgeschriebene Groovy-Quellen, insbesondere
Spring-Boot-Komponenten (`@RestController`, `@Service`, `@Repository`,
`@Autowired`, `@RequestMapping`, `@GetMapping`, ...).

### Marker-Konvention

Spec-Bloecke in Groovy werden mit **`// @spec`** ... **`// @end-spec`**
markiert — exakt wie in JS/TS. Groovy erbt JavaScripts/Javas Kommentar-
Syntax (`//` Zeilen-Kommentar, `/* ... */` Block-Kommentar,
`/** ... */` Groovydoc) eins-zu-eins.

Begruendung:

- Eine separate Groovy-Markervariante waere unnoetige Verdopplung gegenueber
  JS/TS. Wer in einer Groovy-Codebase `// @spec` schreibt, liest dasselbe
  wie in einer TS-Komponente.
- Groovydoc-Bloecke `/** ... */` bleiben **unangetastet** und werden NICHT
  als Spec interpretiert. Wer in einer Groovy-Codebase Groovydoc fuehrt,
  kann Spec-Bloecke und Groovydoc parallel verwenden — das eine ersetzt
  das andere nicht.

Beispiel (Spring-Boot-RestController):

```groovy
// @spec
// REST controller for the foo resource. Mounts at /api.
// @end-spec
@RestController
@RequestMapping("/api")
class FooController {

    // @spec
    // Repository handle, injected by Spring at construction time.
    // never null after Spring DI is complete
    // @end-spec
    @Autowired
    FooRepository repo

    // @spec
    // Returns the foo with the given id.
    // throws NotFoundException when no row matches
    // @end-spec
    @GetMapping("/foo/{id}")
    Foo getFoo(@PathVariable Long id) {
        return repo.findById(id).orElseThrow{ new NotFoundException() }
    }
}
```

### Granularitaet

Spec-Bloecke koennen vor folgenden Symbolen stehen:

- **Datei-Header** — erster Block der Datei, vor jedem Top-Level-Symbol.
- **`class`** — Klassen-Deklarationen, mit oder ohne Annotations.
- **`interface`** — Interface-Deklarationen.
- **`enum`** — Enum-Deklarationen.
- **`trait`** — Groovy-Traits (vergleichbar PHP-Traits).
- **Klassen-Member** — Felder, Methoden, Konstruktoren, statische Methoden,
  Properties (Felder ohne expliziten Modifier sind Groovy-Properties mit
  auto-generiertem Getter/Setter — wir erkennen sie als `kind: "property"`).
- **Top-Level Skript-Variablen** — Groovy erlaubt Skripte ohne Klassen-
  Wrapper; `def x = ...` / `Type x = ...` auf Top-Level.
- **Top-Level Skript-Methoden** — Methoden in Skripten ohne Klassen-Wrapper.
- **Annotation-tragende Symbole** — `@RestController`, `@Service`,
  `@Repository`, `@Autowired`, `@RequestMapping(...)`, `@GetMapping(...)`,
  custom (`@Transactional`, ...). Spec-Block + 0..n Annotations + Symbol
  heisst: Spec gehoert zum Symbol, NICHT zur Annotation. Die Annotations
  werden als Strukturinfo am Symbol unter `annotations[]` mitgefuehrt
  (siehe Schema-Erweiterung unten).
- **Lokale Spec-Bloecke** innerhalb eines Methoden-/Skript-Body — als
  `members[]`-Eintrag mit `kind: "local"`.

### `kind`-Werte fuer Groovy-Symbole

| `kind`           | Symbol-Typ                                                       |
|------------------|-------------------------------------------------------------------|
| `class`          | `class Name { ... }` (mit oder ohne Annotation)                   |
| `interface`      | `interface Name { ... }`                                          |
| `enum`           | `enum Name { ... }`                                               |
| `trait`          | `trait Name { ... }` (Groovy-Trait)                               |
| `method`         | Klassen-Methode `Type name(...) { ... }` (auch `static`)          |
| `constructor`    | Klassen-Konstruktor `ClassName(...) { ... }`                      |
| `field`          | Klassen-Feld mit explizitem Modifier (`private`/`protected`/`public`) |
| `property`       | Klassen-Feld ohne expliziten Modifier (Groovy-Property mit auto-Getter/Setter) |
| `script-var`     | Top-Level `def name = ...` / `Type name = ...` in einem Groovy-Skript |
| `script-method`  | Top-Level Methode in einem Groovy-Skript ohne Klassen-Wrapper     |
| `local`          | Spec-Block innerhalb eines Methoden-/Skript-Body                  |

Die Unterscheidung `field` (mit Modifier) vs. `property` (ohne Modifier)
ist semantisch wichtig in Groovy: `String name` auf Klassen-Ebene ist
eine Property — der Compiler generiert `getName()` und `setName(String)`.
`private String name` ist ein Feld ohne Auto-Accessoren. Ein Renderer kann
diese Information nutzen, um Properties als zugaengliche API zu zeigen,
Felder als interne Implementierung.

`script-var` und `script-method` decken das Groovy-Skript-Modell ab
(Quelldateien ohne `class`-Wrapper, die als Skript-Body laufen).
Konstruktoren bekommen ihren eigenen `kind`-String, damit Renderer sie
optisch von normalen Methoden trennen koennen.

### Annotation-Behandlung (Schema-Erweiterung)

Symbol-Level-Annotations (`@RestController`, `@RequestMapping("/api")`,
`@Autowired`, `@GetMapping("/foo")`, ...) werden als Strukturinfo am
Symbol mitgefuehrt:

```json
{
  "kind": "class",
  "name": "FooController",
  "annotations": [
    {"name": "RestController", "args_source": null},
    {"name": "RequestMapping", "args_source": "\"/api\""}
  ],
  "spec": [...],
  "members": [...]
}
```

Form: `annotations` ist ein Array von Objekten mit:

- `name` (string) — der Annotation-Identifier (der Identifier nach `@`).
  Bei qualifizierten Namen wie `@org.springframework.stereotype.Service`
  wird der volle Pfad uebernommen: `name: "org.springframework.stereotype.Service"`.
- `args_source` (string|null) — der Source-String der Annotation-Args
  zwischen den Klammern (ohne Klammern selbst). Drei Faelle:
  - **`null`** wenn die Annotation ohne Klammern steht: `@RestController` ->
    `{name: "RestController", args_source: null}`.
  - **`""`** wenn die Klammern leer sind: `@Service()` ->
    `{name: "Service", args_source: ""}`.
  - **`"<inhalt>"`** wenn Klammern mit Inhalt: `@RequestMapping("/api")`
    -> `{name: "RequestMapping", args_source: "\"/api\""}`.

`args_source`-Semantik ist absichtlich identisch zur TS-`decorators[]`-
Form (M009/0001). Begruendung fuer die `null` vs. `""`-Unterscheidung:
in Java/Groovy sind `@Foo` und `@Foo()` syntaktisch beide gueltig (Java
lockert die Klammer-Pflicht, wenn alle Annotation-Members Defaults
haben), und ein Renderer/Konsument kann die zwei Formen unterscheiden,
falls noetig — etwa um Source-Treue beim Re-Rendern zu wahren. Wir
mischen das nicht schon im Parser.

Der Inhalt von `args_source` wird **nicht** semantisch geparst — kein
Java-Parse, kein String-Decode. Es ist der rohe Source-String wie er im
Code steht (Whitespace zu einzelnen Spaces komprimiert, analog zu
`signature`/`default` bei den anderen Sprachen). Wer den Pfad aus einem
`@RequestMapping("/api")` braucht, parst `args_source` selbst — eine
Mini-Heuristik wie "string nach erstem Komma" oder "trim Quotes" reicht
typischerweise.

**Warum `annotations[]` und nicht `decorators[]`?** Beide tragen
strukturell dieselbe Form. Wir benennen das Feld trotzdem
sprachgetreu: in Java/Groovy heisst das Sprach-Konstrukt "Annotation",
in TypeScript "Decorator". Eine sprachfremde Benennung wuerde Renderer
und Konsumenten zwingen, die Sprache umzudenken. Auf der Renderer-Seite
ist die Generalisierung trivial (M009/0004 hat bereits den Fallback
`symbol.decorators || symbol.annotations`), aber semantisch bleibt der
Schema-Konsument bei der Sprache.

**Schema-Erweiterung am Symbol**: `annotations` ist ein **optionales**
Feld (Default `[]`). Andere Sprachen (PHP, JS, Nim, Lua, TS) setzen das
Feld nicht. Renderer generalisieren ueber `decorators[] || annotations[]`.

V1 erkennt nur **Symbol-Level**-Annotations (vor Klassen, Methoden,
Feldern, Konstruktoren, Properties). **Parameter-Annotations**
(`@PathVariable Long id`, `@RequestBody Foo body` innerhalb einer
Argumentliste) werden NICHT extrahiert — sie laufen tokenmaessig durch
und landen als Bestandteil des Signatur-Source-Strings. Falls Bedarf
entsteht, ist das eine eigene spaetere Schema-Erweiterung.

### "Direkt darauffolgend" — praezise

Ein Spec-Block bezieht sich auf das **naechste deklarations-tragende
Symbol** nach `// @end-spec`. Dazwischen stehen darf:

- Whitespace (Spaces, Tabs, Zeilenumbrueche) — beliebig viel.
- Block-Kommentare `/* ... */`.
- Groovydoc-Bloecke `/** ... */`.
- Andere `// ...`-Zeilen-Kommentare, die **nicht** mit `// @spec`
  beginnen (regulaere Code-Kommentare).
- **Annotations** (`@RestController`, `@RequestMapping(...)`, custom).
  Beliebig viele in Folge — Spec-Block + Annotation-Liste + Symbol heisst:
  Spec gehoert zum Symbol, Annotations gehen als `annotations[]`-Array
  ans Symbol.
- Modifier-Keywords vor der Symbol-Deklaration: `public`, `protected`,
  `private`, `static`, `final`, `abstract`, `def`. Sie zaehlen als Teil
  der folgenden Deklaration.

**Nicht** erlaubt zwischen Spec-Block und Symbol:

- Ein anderes Symbol (Klasse, Methode, Feld, Konstruktor, Skript-Var,
  Skript-Methode).
- Ein anderer `// @spec`-Block ohne dazwischenliegendes Symbol — der
  erste Block ist dann "dangling".

Wenn nach einem Spec-Block kein Symbol mehr folgt (Datei-Ende, oder
nur weitere Spec-Bloecke):

- Der Block wird **verworfen**.
- `warnings[]` bekommt einen Eintrag der Form
  `"dangling spec at line N"`.

### Parser-Strategie

PHP-seitiger State-Machine-Tokenizer (analog M005/0001 fuer JS,
M007/0001 fuer Nim, M008/0001 fuer Lua, M009/0001 fuer TS). Keine
externen Libs, kein Subprozess (insbesondere kein `groovyc` /
Groovy-AST), **kein Regex** auf Quelltext (Decision 0006).

Begruendung:

- Konsistenz mit der bestehenden Sprach-Parser-Architektur — alle
  Parser laufen im selben PHP-Prozess, kein neuer Laufzeit-
  Abhaengigkeit. Decision 0004 ("kein npm, kein Bundler, kein
  TypeScript") gilt symmetrisch fuer Java/Groovy: keine JVM-
  Abhaengigkeit fuer den Speckig-Server.
- Der noetige Subset fuer Spec-Extraktion ist klein: Token-
  Klassifikation (Comment vs. String vs. GString vs. Triple-String),
  Annotation-Praefix `@<ident>(...)`, Top-Level- und Klassen-Member-
  Erkennung mit `class`/`interface`/`enum`/`trait`/`def`/Typ-Praefix.
  Type-Annotationen werden tokenmaessig geschluckt, nicht semantisch
  verstanden.

Subset, den V1 NICHT semantisch versteht:

- **Closures** `{ -> ... }` — werden als Tokens durchgelaufen; der
  Closure-Body wird nicht analysiert. Closures als Methoden-Argument-
  Defaults (`def foo(Closure cb = { -> }) {...}`) wandern als Source-
  String in die Signatur.
- **GString-Interpolation** `"text $var ${expr}"` — der Tokenizer muss
  die `$var`- und `${...}`-Stellen korrekt durchlaufen, ohne dass
  `// @spec` im Interpolations-Body als Spec gewertet wird.
- **Operator-Overloading** — Groovy erlaubt das, der Parser interessiert
  sich aber nur fuer Symbol-Erkennung, nicht fuer Operator-Semantik.
- **Annotation-Args** — bleiben als Source-String in `args_source`
  liegen, kein Parse des Args-Inhalts.
- **Parameter-Annotations** — gehen tokenmaessig in die Signatur, werden
  nicht als eigene `annotations[]`-Liste pro Parameter gesammelt.

### Edge-Cases die NICHT als Spec erkannt werden duerfen

Der Tokenizer muss diese Faelle korrekt klassifizieren, sonst werden
String- oder Kommentar-Inhalte faelschlich als Spec gelesen:

- `// @spec`-Text in einzeiligen Strings `'...'` und `"..."`.
- `// @spec`-Text in Triple-Strings `'''...'''` und `"""..."""`.
- `// @spec`-Text in GStrings `"text $var ${expr}"` mit
  `${...}`-Interpolation. Der Tokenizer muss die geschweiften
  Interpolations-Klammern korrekt matchen, auch bei verschachtelten
  Strings im Interpolations-Body (`${"x"}`, `${foo("bar")}`).
- `// @spec`-Text in Block-Kommentaren `/* ... */`.
- `// @spec`-Text in Groovydoc-Bloecken `/** ... */`. Groovydoc-Inhalt
  wird vom Parser ignoriert; nur One-Line-Kommentare `//` werden als
  Spec-Marker akzeptiert.

Der Tokenizer klassifiziert diese Token-Klassen primaer; `// @spec` ist
**nur** dann ein Marker, wenn das Token vom Tokenizer als
"Zeilen-Kommentar" (`comment_line`) eingestuft wurde — nicht innerhalb
eines String-, GString-, Triple-String- oder Block-Kommentar-Zustands.

### Beispiel-Output (Ziel-Output fuer M010/0002)

Groovy-Quelle (Spring-Boot-RestController):

```groovy
// @spec
// REST controller for the foo resource. Mounts at /api.
// @end-spec
@RestController
@RequestMapping("/api")
class FooController {

    // @spec
    // Repository handle, injected by Spring at construction time.
    // never null after Spring DI is complete
    // @end-spec
    @Autowired
    FooRepository repo

    // @spec
    // Returns the foo with the given id.
    // throws NotFoundException when no row matches
    // @end-spec
    @GetMapping("/foo/{id}")
    Foo getFoo(@PathVariable Long id) {
        return repo.findById(id).orElseThrow{ new NotFoundException() }
    }
}
```

Erwartetes JSON (handgeschrieben):

```json
{
  "file": "FooController.groovy",
  "language": "groovy",
  "file_spec": [],
  "symbols": [
    {
      "kind": "class",
      "name": "FooController",
      "extends": [],
      "implements": [],
      "annotations": [
        {"name": "RestController", "args_source": null},
        {"name": "RequestMapping", "args_source": "\"/api\""}
      ],
      "spec": [
        "REST controller for the foo resource. Mounts at /api."
      ],
      "members": [
        {
          "kind": "property",
          "name": "repo",
          "type": "FooRepository",
          "annotations": [
            {"name": "Autowired", "args_source": null}
          ],
          "spec": [
            "Repository handle, injected by Spring at construction time.",
            "never null after Spring DI is complete"
          ]
        },
        {
          "kind": "method",
          "name": "getFoo",
          "signature": "Foo getFoo(@PathVariable Long id)",
          "annotations": [
            {"name": "GetMapping", "args_source": "\"/foo/{id}\""}
          ],
          "spec": [
            "Returns the foo with the given id.",
            "throws NotFoundException when no row matches"
          ],
          "members": []
        }
      ]
    }
  ],
  "warnings": []
}
```

Anmerkungen zum Beispiel:

- Die Datei traegt keinen Datei-Header-Spec-Block — der erste
  `// @spec`-Block steht direkt vor `@RestController`/`class FooController`
  und gehoert dadurch zur Klasse.
- `@RestController` ohne Klammern -> `args_source: null`.
  `@RequestMapping("/api")` -> `args_source: "\"/api\""` (die Quotes
  sind Teil des Source-Strings, weil sie im Source stehen). Ein
  hypothetisches `@Service()` ohne Inhalt haette `args_source: ""`.
- `@PathVariable` an einem Methoden-Parameter wird NICHT als Symbol-
  Annotation erkannt — Parameter-Annotations laufen tokenmaessig in
  die Signatur (`signature: "Foo getFoo(@PathVariable Long id)"`).
- `repo` ohne expliziten Modifier ist eine Groovy-Property
  (`kind: "property"`), nicht ein Feld — der Compiler generiert
  Getter/Setter automatisch. Mit `private FooRepository repo` waere
  es `kind: "field"`.

## Ausgabe-Schema

JSON-Objekt mit fester Form. Erfolgs-Pfad:

```json
{
  "file":      "app/user/data/User.php",
  "language":  "php",
  "file_spec": ["Authenticated platform user; admin flag controls privileged endpoints."],
  "symbols":   [ ... ],
  "warnings":  []
}
```

Fehler-Pfad (Vendor-Blacklist, unsupported language, file not found):

```json
{
  "file":      "<input-pfad>",
  "error":     "unsupported language",
  "extension": "css"
}
```

### Top-Level-Felder

| Feld        | Typ          | Pflicht | Beschreibung |
|-------------|--------------|---------|--------------|
| `file`      | string       | ja      | Eingabepfad, wie reingereicht (nur `trim`). |
| `language`  | string       | ja*     | `"php"` oder `"js"`. *Fehlt im Fehler-Pfad. |
| `file_spec` | string[]     | ja*     | Spec-Zeilen des Datei-Headers, ohne `// `-Praefix, ohne `@spec`/`@end-spec`-Marker. |
| `symbols`   | object[]     | ja*     | Top-Level-Symbole der Datei (siehe unten). *Fehlt im Fehler-Pfad. |
| `warnings`  | string[]     | ja*     | Nicht-fatale Probleme (z.B. dangling Spec). *Fehlt im Fehler-Pfad. |
| `error`     | string       | nur Fehler | Kurz-String, was schiefging. |
| `extension` | string       | optional | Bei `error: "unsupported language"`. |

### Symbol-Objekt

| Feld         | Typ      | Wann                  | Beschreibung |
|--------------|----------|------------------------|--------------|
| `kind`       | string   | immer                  | `"class"`, `"interface"`, `"trait"`, `"function"`, `"method"`, `"property"`, `"const"`, `"local"`. |
| `name`       | string   | immer (ausser `local`) | Bezeichner. Bei `local`: leerer String oder eine kurze Beschreibung — Parser-Wahl. |
| `signature`  | string   | `function`, `method`   | Volle Signatur als Source-String, inkl. Argument-Typen, Defaults, Returntyp. |
| `type`       | string   | `property`, `const`    | Deklarierter Typ als Source-String (z.B. `"string"`, `"int"`, `"?User"`). |
| `default`    | string   | `property`, `const`    | Default als Source-String, **nicht** eval'd (z.B. `"\"\""`, `"0"`). |
| `extends`    | string[] | `class`, `interface`, `trait` | Eltern-Klassen/Interfaces; leer wenn keine. |
| `implements` | string[] | `class`                | Implementierte Interfaces; leer wenn keine. |
| `spec`       | string[] | immer                  | Spec-Zeilen des Symbols, ohne `// `-Praefix, ohne `@spec`/`@end-spec`. Leer wenn das Symbol keine Spec hat. |
| `members`    | object[] | `class`, `interface`, `trait`, `method` | Properties / Konstanten / Methoden eines Containers; bei `method` lokale Spec-Bloecke (`kind: "local"`). Leer wenn keine. |
| `decorators` | object[] | optional, TS-only      | Decorator-Liste eines Symbols. Form `[{name, args_source}]`. Default `[]`. Wird in V1 nur vom TS-Parser gesetzt; PHP/JS/Nim/Lua/Groovy emittieren das Feld nicht. Renderer ignoriert das Feld bei Sprachen, die es nicht setzen. Details siehe Sektion `## TypeScript`. |
| `annotations` | object[] | optional, Groovy-only | Annotation-Liste eines Symbols. Form `[{name, args_source}]` — strukturell identisch zu `decorators[]`, aber semantisch andere Sprach-Familie (Java-Annotations vs. TS-Decorators). Default `[]`. Wird in V1 nur vom Groovy-Parser gesetzt; PHP/JS/Nim/Lua/TS emittieren das Feld nicht. Renderer behandeln `decorators` und `annotations` gleich (M009/0004 hat `decorators \|\| annotations`-Fallback). Details siehe Sektion `## Groovy`. |

### "Direkt darauffolgend" — praezise

Ein Spec-Block bezieht sich auf das **naechste deklarations-tragende Token**
nach `// @end-spec`. Deklarations-tragend sind:

- `class`, `interface`, `trait`
- `function` (Top-Level oder Methode)
- Property-Deklaration (`public`, `protected`, `private`, `static`, `readonly`,
  optional gefolgt von Typ + `$name`)
- `const NAME = ...`

Erlaubt zwischen Spec-Block und Symbol:

- Whitespace (Leerzeichen, Tabs, Zeilenumbrueche).
- Andere Kommentare, die **nicht** mit `// @spec` beginnen
  (Doc-Blocks `/** ... */`, normale `// ...`-Kommentare,
  Block-Kommentare `/* ... */`).
- Modifier-Tokens (`public`, `static`, `final`, `abstract`,
  `readonly` etc.) zaehlen als Teil der folgenden Deklaration.

**Nicht** erlaubt zwischen Spec-Block und Symbol:

- Ein anderes Symbol (Klasse, Funktion, Property, Konstante).
- Ein anderer `// @spec`-Block ohne dazwischenliegendes Symbol —
  der erste Block ist dann "dangling".

Wenn nach einem Spec-Block kein Symbol mehr folgt (Datei-Ende, oder
nur weitere Spec-Bloecke):

- Der Block wird **verworfen**.
- `warnings[]` bekommt einen Eintrag der Form
  `"dangling spec at line N"`.

Spec-Block-Marker sind exakt `// @spec` (Start) und `// @end-spec` (Ende)
als One-Line-Kommentare. `T_COMMENT` in `token_get_all`-Termen, **nicht**
`T_DOC_COMMENT` (`/** ... */`). Spec-Inhalt sind die dazwischen liegenden
One-Line-Kommentare; ihr Inhalt wird ohne fuehrendes `// ` und ohne
trailing whitespace ins `spec[]`-Array uebernommen.

## Beispiel-Output (manuell erzeugt — Ziel-Output fuer M005/0002)

Diese JSON-Beispiele zeigen, was die Sprach-Parser **liefern sollen**.
Heute (Stub-Phase) liefert der Parser nur die Huelle (`file`, `language`,
leere Felder). Die Bloecke unten sind die Vorlage fuer M005/0002.

### `app/user/data/User.php`

```json
{
  "file": "app/user/data/User.php",
  "language": "php",
  "file_spec": [
    "Authenticated platform user; admin flag controls privileged endpoints."
  ],
  "symbols": [
    {
      "kind": "class",
      "name": "User",
      "extends": ["DataClass"],
      "implements": [],
      "spec": [
        "User row: login handle, password hash, email + verification flag, language, admin flag."
      ],
      "members": [
        {
          "kind": "property",
          "name": "username",
          "type": "string",
          "default": "\"\"",
          "spec": ["login handle, unique, 2..64 chars"]
        },
        {
          "kind": "property",
          "name": "password",
          "type": "string",
          "default": "\"\"",
          "spec": ["password_hash(PASSWORD_DEFAULT) bcrypt output, never plaintext"]
        },
        {
          "kind": "property",
          "name": "email",
          "type": "string",
          "default": "\"\"",
          "spec": ["optional contact address; validated via FILTER_VALIDATE_EMAIL when non-empty"]
        },
        {
          "kind": "property",
          "name": "email_verified",
          "type": "int",
          "default": "0",
          "spec": ["1 if email ownership has been confirmed, 0 otherwise"]
        },
        {
          "kind": "property",
          "name": "language",
          "type": "string",
          "default": "\"\"",
          "spec": ["BCP-47-ish locale tag, empty string means \"use default\""]
        },
        {
          "kind": "property",
          "name": "is_admin",
          "type": "int",
          "default": "0",
          "spec": ["1 = platform admin (privileged endpoints), 0 = normal user"]
        }
      ]
    }
  ],
  "warnings": []
}
```

### `app/user/actions/CreateUserAction.php`

```json
{
  "file": "app/user/actions/CreateUserAction.php",
  "language": "php",
  "file_spec": [
    "Admin-only action that validates inputs and inserts a new User row, exposing the created id."
  ],
  "symbols": [
    {
      "kind": "class",
      "name": "CreateUserAction",
      "extends": ["Action"],
      "implements": [],
      "spec": [
        "Action: create a new User row after admin gate and input validation; result carries the new id."
      ],
      "members": [
        {
          "kind": "property",
          "name": "created_user_id",
          "type": "int",
          "default": "0",
          "spec": ["id of the user this action created; 0 before execute() ran"]
        },
        {
          "kind": "method",
          "name": "__construct",
          "signature": "function __construct()",
          "spec": [],
          "members": []
        },
        {
          "kind": "method",
          "name": "execute",
          "signature": "static function execute(string $username, string $password, string $email, string $is_admin = \"0\"): self",
          "spec": [
            "Enforce admin, validate username/password/email, ensure username is unique, then save the new user.",
            "calls app::enforce_plattform_admin, which throws NeedsLoginError or NotAllowedError if the caller is not a platform admin",
            "throws UserInputError if username is empty after trim",
            "throws UserInputError if password is empty",
            "throws UserInputError if username length is not in 2..64",
            "throws UserInputError if password is shorter than 6 characters",
            "throws UserInputError if a non-empty email fails FILTER_VALIDATE_EMAIL",
            "throws UserInputError if a User row already exists with the same username",
            "throws BadStateError if db::save returns without assigning an id",
            "password is stored as a password_hash, never plaintext",
            "is_admin is set to 1 only when the input string equals \"1\""
          ],
          "members": []
        }
      ]
    }
  ],
  "warnings": []
}
```

## Files in diesem Verzeichnis

| Datei              | Rolle                                                   |
|--------------------|----------------------------------------------------------|
| `index.php`        | CLI-Einstieg. Nimmt Pfad-Arg, gibt JSON, setzt Exit-Code. |
| `spec_parser.php`  | Sprach-agnostischer Dispatcher. Funktion `spec_parser::parse()`. |
| `php_parser.php`   | Sprach-Parser fuer `.php`. Stub in M005/0001, gefuellt in M005/0002. |
| `js_parser.php`    | Sprach-Parser fuer `.js`. Stub in M005/0001, gefuellt in M005/0003. |
| `nim_parser.php`   | Sprach-Parser fuer `.nim`. Stub in M007/0001, gefuellt in M007/0002. |
| `lua_parser.php`   | Sprach-Parser fuer `.lua` (inkl. Love2D). Stub in M008/0001, gefuellt in M008/0002. |
| `ts_parser.php`    | Sprach-Parser fuer `.ts` (inkl. Angular). Stub in M009/0001, gefuellt in M009/0002. |
| `groovy_parser.php` | Sprach-Parser fuer `.groovy` (inkl. Spring Boot). Stub in M010/0001, gefuellt in M010/0002. |
| `README.md`        | Dieses Dokument — Schema, Aufruf, Dispatch, JS-Strategie, Nim-Strategie, Lua-Strategie, TS-Strategie, Groovy-Strategie. |

Spaeter (M005/0004) kommt `tests/run.php` mit Fixtures dazu;
M005/0005 verdrahtet `spec_parser::parse()` in `app/file.php`.
