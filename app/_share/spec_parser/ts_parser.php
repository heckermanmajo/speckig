<?php

declare(strict_types=1);

// @spec
// TypeScript-Spec-Parser. Liest // @spec ... // @end-spec-Bloecke aus
// TypeScript-Dateien (.ts) und ordnet sie dem direkt darauffolgenden Symbol
// zu (Datei-Header, class/interface/enum/type-Aliase, Klassen-Member
// (Properties, Methoden, Konstruktor, get/set), Top-Level
// function/const/let/var, Decorator-tragende Symbole wie @Component /
// @Injectable / @Directive). JSDoc-Bloecke /** ... */ bleiben unangetastet
// und werden NICHT als Spec interpretiert. Schema und Beispiele in
// app/_share/spec_parser/README.md (Sektion "## TypeScript").
//
// Schema-Erweiterung: TS-Symbole tragen ein optionales decorators[]-Feld
// (Default leer). Form: [{name: string, args_source: string|null}].
// args_source = null wenn der Decorator ohne Klammern steht (@Foo),
// args_source = "" wenn Klammern leer sind (@Foo()), args_source = der
// Source-String zwischen den Klammern (ohne Klammern selbst) sonst.
//
// Implementierung: PHP-seitiger State-Machine-Tokenizer (analog
// js_parser/nim_parser/lua_parser, Decision M005/0001), kein Regex
// (Decision 0006), keine externen Libs, kein Subprozess. Generics-Syntax
// (<T extends Foo>), Mapped Types und Conditional Types werden
// tokenmaessig durchgelaufen, NICHT semantisch verstanden — sie wandern
// als Source-String in die Signatur.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// Nie `new ts_parser()`, immer `ts_parser::parse(...)`.
// @end-spec
class ts_parser
{

    // @spec
    // Parst eine TypeScript-Datei und liefert das Schema-Array
    // (file_spec, symbols, warnings).
    // Bei nicht existierender / nicht lesbarer Datei: leeres Schema mit
    // Warning "file not found: $path" — der Dispatcher umhuellt das mit
    // file/language-Feldern.
    // Stub in M009/0001 — der echte Tokenizer/Walker kommt in M009/0002.
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

        $source = file_get_contents($path);

        if ($source === false)
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
