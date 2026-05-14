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
| `README.md`        | Dieses Dokument — Schema, Aufruf, Dispatch, JS-Strategie. |

Spaeter (M005/0004) kommt `tests/run.php` mit Fixtures dazu;
M005/0005 verdrahtet `spec_parser::parse()` in `app/file.php`.
