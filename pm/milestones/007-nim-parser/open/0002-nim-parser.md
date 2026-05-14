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

(append after work)
