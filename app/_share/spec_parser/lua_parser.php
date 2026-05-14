<?php

declare(strict_types=1);

// @spec
// Lua-Spec-Parser. Liest -- @spec ... -- @end-spec-Bloecke aus Lua-Dateien
// (inkl. Love2D-Quellen) und ordnet sie dem direkt darauffolgenden Symbol
// zu (Top-Level function, function table.method qualifiziert wie
// love.load/love.update/love.draw, local function, local var/local table,
// Tabellen-Felder mit Specs, lokale Spec-Bloecke innerhalb eines
// Funktions-Body). Schema und Beispiele in
// app/_share/spec_parser/README.md.
//
// Implementierung: PHP-seitiger State-Machine-Tokenizer (analog
// nim_parser/js_parser), kein Regex (Decision 0006), keine externen Libs,
// kein Subprozess. Der Tokenizer kennt String-Varianten ('...', "...",
// Long-Strings [[...]] / [=[...]=] mit beliebig vielen "="), Block-
// Kommentare --[[ ... ]] / --[=[ ... ]=] und Zeilen-Kommentare -- ...
// Damit ist garantiert, dass Spec-Marker in Strings, Long-Strings und
// Block-Kommentaren nicht als Marker erkannt werden.
//
// Stub-Phase (M008/0001): Tokenizer/Walker noch nicht implementiert,
// liefert leeres Schema. M008/0002 fuellt die Implementierung.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// Nie `new lua_parser()`, immer `lua_parser::parse(...)`.
// @end-spec
class lua_parser
{

    // @spec
    // Parst eine Lua-Datei und liefert das Schema-Array
    // (file_spec, symbols, warnings).
    // Bei nicht existierender / nicht lesbarer Datei: leeres Schema mit
    // Warning "file not found: $path" — der Dispatcher umhuellt das mit
    // file/language-Feldern.
    // Stub liefert immer leeres Schema fuer existierende Dateien — die
    // echte Implementierung kommt in M008/0002.
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
