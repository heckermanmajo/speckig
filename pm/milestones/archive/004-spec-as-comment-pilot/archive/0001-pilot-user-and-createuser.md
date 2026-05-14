# 0001 — Pilot: User.php und CreateUserAction.php auf Spec-als-Kommentar umstellen

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md

## Done when

- `app/user/data/User.php` traegt:
  - Datei-Header-Spec (Block-Kommentar oben, vor `namespace`).
  - Klassen-Spec direkt ueber `class User`.
  - Pro Feld (`$username`, `$password`, `$email`, `$email_verified`, `$language`, `$is_admin`) je eine `// @spec ... // @end-spec`-Spec.
- `app/user/actions/CreateUserAction.php` traegt:
  - Datei-Header-Spec.
  - Klassen-Spec ueber `class CreateUserAction`.
  - Feld-Spec ueber `$created_user_id`.
  - Funktions-Spec ueber `static function execute(...)` mit Intent-Satz und allen Conditions, die heute in `CreateUserAction.spec` als YAML-`conditions` stehen.
- `app/user/data/User.spec` und `app/user/actions/CreateUserAction.spec` sind via `git rm` geloescht.
- Keine bestehende DocBlock-Notation (`/** ... */`) wurde geaendert; Spec lebt als One-Line-Kommentar-Block (`//` in PHP) ueber dem jeweiligen Symbol.
- Marker-Format ist exakt `// @spec` ... `// @end-spec` wie in `pm/ideas/spec-as-comment.md` beschrieben.
- `php -l app/user/data/User.php` und `php -l app/user/actions/CreateUserAction.php` sind sauber.
- Smoketest gegen `php -S` zeigt: Login + User-Anlage funktionieren weiter (CreateUserAction wird nicht semantisch veraendert, nur kommentiert; User-DataClass-Felder bleiben unveraendert).
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./pm/*"` zeigt nur den kanonischen Pfad.
- Commit-Format: `[004/0001]`, HEREDOC-Body, kein Co-Authored-By, kein `--no-verify`, nicht gepusht.
- Ticket via `git mv open/ -> archive/` im selben Commit, milestone.md-Box gehäkt, `Status: done` gesetzt, Milestone-Folder via `git mv pm/milestones/004-... pm/milestones/archive/004-...` im selben Commit (das ist das einzige Ticket in M004).

## Aus der Idea wichtig

- Spec-Block beschreibt **immer das direkt darauffolgende Symbol**.
- Felder mit kurzer Spec duerfen einzeilig wirken, brauchen aber trotzdem `@end-spec` (Konsistenz fuer den Parser; siehe offene Frage in der Idea — der Pilot waehlt bewusst die konsistente Variante mit `@end-spec`).
- Spec wiederholt **keine Typen**, die im Code stehen. `// @spec public string username` ist falsch — der Typ kommt aus dem Code.
- Spec-Sprache: Englisch oder Deutsch, eine Sprache pro Datei, konsistent. Existierende `.spec`-Dateien sind in Englisch — Pilot bleibt in Englisch.

## Done

- `app/user/data/User.php` migriert: Datei-Header-Spec vor `namespace`,
  Klassen-Spec ueber `class User`, Feld-Spec ueber jedem der 6 Properties
  (`$username`, `$password`, `$email`, `$email_verified`, `$language`,
  `$is_admin`). Marker exakt `// @spec` ... `// @end-spec`. Spec wiederholt
  keine Typen. Bestehender Code (Property-Defaults, Vererbung) unveraendert.
- `app/user/actions/CreateUserAction.php` migriert: Datei-Header-Spec,
  Klassen-Spec, Feld-Spec ueber `$created_user_id`, Funktions-Spec ueber
  `static function execute(...)` mit `does`-Intent + alle 10 Conditions
  in der Reihenfolge der alten YAML. Logik, Signaturen, Validierungen
  unangetastet.
- `git rm app/user/data/User.spec` und `git rm app/user/actions/CreateUserAction.spec`.
- `php -l` sauber fuer beide Files.
- Smoketest gegen `php -S 127.0.0.1:8099 -t app`:
  - `GET /index.php` -> 200
  - `GET /file.php?path=app/user/data/User.php` -> 200, JSON `{"ok":true,...}` mit Spec-Bloecken im html-Feld
  - `GET /file.php?path=app/user/actions/CreateUserAction.php` -> 200, JSON `{"ok":true,...}` mit Spec-Bloecken im html-Feld
  - Server am Ende mit TaskStop beendet, Port 8083 nicht angefasst.
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Spec-Marker-Count: `grep -c "// @spec" app/user/data/User.php` = 8
  (Datei + Klasse + 6 Felder), `grep -c "// @spec" app/user/actions/CreateUserAction.php`
  = 4 (Datei + Klasse + 1 Feld + 1 Funktion). Plausibel.
- Out-of-scope eingehalten: kein Touch an `pm/how-to/spec.md`, Decision 0005
  nicht supersedet, andere `.spec`-Dateien nicht angefasst, kein Refactor
  am bestehenden Code.
- Milestone 004 abgeschlossen: einziges Ticket -> `Status: done`,
  Milestone-Folder via `git mv` nach `pm/milestones/archive/004-spec-as-comment-pilot`.
- Commit-Format `[004/0001]`, Body via HEREDOC, kein Co-Authored-By, kein `--no-verify`, nicht gepusht.
