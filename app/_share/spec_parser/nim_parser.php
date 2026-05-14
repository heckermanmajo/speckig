<?php

declare(strict_types=1);

// @spec
// Nim-Spec-Parser. Liest ## @spec ... ## @end-spec-Bloecke aus Nim-Dateien
// und ordnet sie dem direkt darauffolgenden Symbol zu (proc, func, method,
// iterator, template, macro, type/object/enum, Object-Feld, const, let, var,
// oder lokale Stelle innerhalb eines proc-Body). Schema und Beispiele in
// app/_share/spec_parser/README.md.
//
// Implementierung: PHP-seitiger State-Machine-Tokenizer (analog M005/0001
// JS-Strategie), kein Regex (Decision 0006), keine externen Libs, kein
// Subprozess. Der Tokenizer kennt String-Varianten ("..." / """...""" /
// r"..."), Block-Kommentare (#[ ... ]#), Doc-Kommentare (## ...) und normale
// Zeilen-Kommentare (# ...). Damit ist garantiert, dass Spec-Marker in
// Strings, Block-Kommentaren und Raw-Strings nicht als Marker erkannt
// werden.
//
// Stub-Phase (M007/0001): nim_parser::parse liefert ausschliesslich das
// leere Schema (file_spec, symbols, warnings). Echter Parser kommt in
// M007/0002.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// Nie `new nim_parser()`, immer `nim_parser::parse(...)`.
// @end-spec
class nim_parser
{

    // @spec
    // Parst eine Nim-Datei und liefert das Schema-Array
    // (file_spec, symbols, warnings).
    // Bei nicht existierender / nicht lesbarer Datei: leeres Schema mit
    // Warning "file not found: $path" — der Dispatcher umhuellt das mit
    // file/language-Feldern.
    // Stub-Phase: gibt fuer existierende Dateien ein leeres Schema zurueck.
    // @end-spec
    static function parse(string $path): array
    {
        $file_is_readable = is_file($path) && is_readable($path);

        if ( ! $file_is_readable)
        {
            return [
                "file_spec" => [],
                "symbols"   => [],
                "warnings"  => ["file not found: " . $path],
            ];
        }

        return [
            "file_spec" => [],
            "symbols"   => [],
            "warnings"  => [],
        ];
    }
}
