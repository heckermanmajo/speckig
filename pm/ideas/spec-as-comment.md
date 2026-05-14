# Spec als Kommentar im Code

Spec wandert aus `.spec`-Dateien in den Code. Eine Source of Truth.
Die UI extrahiert die Spec-Bloecke und zeigt sie wie eine .spec-Datei,
ohne dass es eine zweite Datei gibt.

## Warum

- `.spec` neben Code = zwei Sources of Truth. Drift ist garantiert.
- `User.spec` wiederholt heute nur den Klassen-Header in einem Satz —
  aber die einzelnen Felder (`$username`, `$is_admin`) tragen keine Spec,
  obwohl sie genau das sind, was ein Leser zuerst verstehen will.
- `CreateUserAction.spec` listet 10 `conditions`, die **wortwoertlich**
  als `throw new UserInputError(...)` im Code stehen. Wenn jemand eine
  Validierung umstellt, driftet die Spec.
- Entwickler will Specs lesen wie Code — Stub + ein Satz Intent. Genau
  das soll die UI aus Code-Datei + Kommentaren zusammenbauen.

## Format

Block-Kommentar, sprachagnostisch, frei-Inhalt:

```
// @spec
// <ein Satz Intent>
// <weitere Zeilen, frei: conditions, why, invariants>
// @end-spec
<symbol-das-er-beschreibt>
```

Pro Sprache der jeweilige Zeilenkommentar-Praefix:

| Sprache       | Praefix |
|---------------|---------|
| PHP, JS, TS, Java, C, C++, Groovy | `//` |
| Python, Ruby, Shell | `#` |
| SQL, Lua | `--` |

Bestehende DocBlocks (`/** ... */`, `"""..."""`) bleiben unangetastet.
Spec lebt als One-Line-Kommentar-Block **darueber**.

## Beispiele

### Datei + Klasse + Felder (User.php)

```php
<?php
// @spec
// Authenticated platform user.
// admin flag controls privileged endpoints.
// @end-spec

namespace user\data;

use _share\DataClass;

// @spec
// User row: login handle, password hash, email, language, admin flag.
// @end-spec
class User extends DataClass
{
    // @spec login handle, unique, 2..64 chars
    // @end-spec
    public string $username = "";

    // @spec bcrypt hash, never plaintext
    // @end-spec
    public string $password = "";

    // @spec optional, validated via FILTER_VALIDATE_EMAIL when non-empty
    // @end-spec
    public string $email = "";

    // @spec 1 = admin, 0 = normal user
    // @end-spec
    public int $is_admin = 0;
}
```

### Funktion mit Conditions (CreateUserAction.php)

```php
// @spec
// Admin-only: validate inputs and insert a new User row, expose id.
// calls app::enforce_plattform_admin -> NeedsLoginError | NotAllowedError
// throws UserInputError if username empty or not 2..64 chars
// throws UserInputError if password shorter than 6
// throws UserInputError if non-empty email fails FILTER_VALIDATE_EMAIL
// throws UserInputError if username already exists
// throws BadStateError if db::save did not assign an id
// password stored as password_hash, never plaintext
// is_admin = 1 only when input string equals "1"
// @end-spec
static function execute(
    string $username,
    string $password,
    string $email,
    string $is_admin = "0"
): self
{
    ...
}
```

## Granularitaet

Spec darf an:
- Datei-Header (ganz oben)
- Klasse / Interface / Trait
- Methode / Funktion
- Feld / Property / Konstante
- Lokale Variable oder Block — wenn nicht-trivial

Faustregel: wenn der Leser stutzen wuerde, gehoert eine `@spec` dran.

## Parser

- **Pro Sprache ein eigener Parser.** PHP-Parser nutzt `token_get_all`
  oder eine echte AST-Lib, TS-Parser nutzt die TS-Compiler-API, Python
  nutzt `ast`, etc.
- **Kein Regex.** Regex ist beschissen zu debuggen und versagt an
  Strings/Heredocs/verschachtelten Kommentaren. Wird in Decision 0006
  festgeschrieben.
- Parser-Output: AST-light, JSON-foermig:

```json
{
  "file": "app/user/data/User.php",
  "file_spec": ["Authenticated platform user.", "admin flag controls ..."],
  "symbols": [
    {
      "kind": "class",
      "name": "User",
      "extends": "DataClass",
      "spec": ["User row: ..."],
      "members": [
        {
          "kind": "field",
          "name": "username",
          "type": "string",
          "default": "\"\"",
          "spec": ["login handle, unique, 2..64 chars"]
        },
        ...
      ]
    }
  ]
}
```

- Signatur, Typ, Defaultwert kommen aus dem Code.
- Spec-Text kommt aus dem Kommentarblock.
- UI rendert beides zusammen — Spec wiederholt **keine** Typen.

## Migrationsplan (Skizze, wird Milestone)

1. Pilot: `User.php` + `CreateUserAction.php` migrieren, alte `.spec`
   loeschen. Decision 0006 + diese Idea referenzieren.
2. `pm/how-to/spec.md` umschreiben auf Kommentar-Format.
3. `pm/decisions/0005-spec-format.md` durch Decision 0006 superseden.
4. PHP-Parser (Subagent-Ticket) — `token_get_all` basiert, liefert
   obiges JSON-Schema.
5. UI-Integration: aus `.spec`-Anzeige wird Parser-Output-Anzeige.
6. Restliche `.spec`-Dateien ticketweise migrieren, eine pro Subsystem
   (`app/_share/`, `app/user/`, `app/_share/exceptions/`, `app/_share/html/`).
7. `Value.spec` im Repo-Root bleibt — konzeptionell, keiner Code-Datei
   zugeordnet.

## Offene Fragen

- Brauchen wir einen End-Marker pro Einzeiler-Spec (siehe `username`-Feld
  oben), oder reicht der Praefix `// @spec` als Einzeiler ohne `@end-spec`?
  Konsistenz vs. Geschwafel.
- Wie geht der Parser mit Vendor-Code um? Vermutlich: Pfad-Blacklist
  (`app/_share/vendor/`).
- Wie oft soll die UI re-parsen? On-demand oder gecacht?
- Mehrere Sprachen in einem Repo: ein Multi-Parser-Frontend, oder ein
  Parser pro Aufruf mit Sprach-Detection ueber Dateiendung?

## See also

- [[Value]] — Spec-neben-Code-Pain.
- [[decisions/0005-spec-format]] — wird supersedet.
- [[decisions/0006-spec-parser]] — kein Regex.
- [[how-to/spec]] — wird umgeschrieben, sobald Pilot durch ist.
