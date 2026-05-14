# 0002 — PHP-Parser via token_get_all

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md
Blocked by: 0001

## Done when

- `app/_share/spec_parser/php_parser.php` implementiert die in 0001 festgelegte Schnittstelle.
- Parser nutzt ausschliesslich `token_get_all` (PHP-Built-in) — keine Regex, keine externen Libs.
- Parser erkennt:
  - Datei-Header-Spec (Block-Kommentar oben, vor `namespace` oder `class`).
  - `class`/`interface`/`trait` mit Spec-Block direkt davor.
  - Properties (`public string $foo = "..."`) mit Spec-Block direkt davor.
  - Methoden / `function`-Deklarationen mit Spec-Block direkt davor.
  - Konstanten (`const FOO = ...`) mit Spec-Block direkt davor.
  - Lokale Spec-Bloecke innerhalb einer Methode (gehen in `members[]` der Methode mit `kind: local`).
- Parser ignoriert Spec-Bloecke, denen kein Symbol folgt (Edge-Case: dangling Spec) — gibt aber eine Warnung im JSON-Output (`warnings: []`).
- Parser ignoriert vendored Code: Pfade unter `app/_share/vendor/` werden vor dem Parsen abgelehnt.
- Output enthaelt fuer jedes Symbol:
  - `kind`, `name`
  - bei Funktion/Methode: `signature` als String (Argumente mit Typen + Defaults, Returntyp).
  - bei Property/Konstante: `type`, `default` als Strings.
  - `spec`: Array von Strings, eine Zeile pro Spec-Inhalt-Zeile (ohne `// ` Praefix, ohne `@spec`/`@end-spec`).
- Aufruf gegen `app/user/data/User.php` (aus M004-Pilot) liefert sechs Felder mit jeweils einer Spec-Zeile.
- Aufruf gegen `app/user/actions/CreateUserAction.php` liefert die Klasse, das Feld `created_user_id`, und die Methode `execute` mit ihrem Spec-Block (Intent + Conditions als Zeilen).
- `php -l` sauber.
- **Tests fuer dieses Ticket:** mindestens die zwei Pilot-Dateien manuell parsen, Output zeigen. Echte Fixture-Tests landen in 0004.

## Aus der Idea wichtig

- Heredoc-/Nowdoc-/String-Inhalte duerfen nie als Spec-Marker missverstanden werden — `token_get_all` macht das per Default richtig, aber explizit testen.
- Spec-Block-Marker ist exakt `// @spec` ... `// @end-spec`. Token-Typ ist `T_COMMENT` (One-Line), nicht `T_DOC_COMMENT`. Bestehende `/** ... */` werden nicht als Spec interpretiert.

## Done

(append after work)
