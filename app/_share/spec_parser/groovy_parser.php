<?php

declare(strict_types=1);

// @spec
// Groovy-Spec-Parser. Liest // @spec ... // @end-spec-Bloecke aus Groovy-
// Dateien (.groovy, inkl. Spring-Boot-Quellen) und ordnet sie dem direkt
// darauffolgenden Symbol zu (Datei-Header, class/interface/enum/trait,
// Klassen-Member (Felder, Methoden, Konstruktor, statische Methoden,
// Properties), Top-Level Skript-Variablen und -Methoden, Annotation-
// tragende Symbole wie @RestController / @Service / @Repository /
// @Autowired / @RequestMapping / @GetMapping). Bestehende Groovydoc-Bloecke
// /** ... */ bleiben unangetastet und werden NICHT als Spec interpretiert.
// Schema und Beispiele in app/_share/spec_parser/README.md (Sektion
// "## Groovy").
//
// Schema-Erweiterung: Groovy-Symbole tragen ein optionales annotations[]-
// Feld (Default leer). Form: [{name: string, args_source: string|null}] —
// strukturell identisch zum TS-decorators[]-Feld (M009/0001), aber
// semantisch andere Sprach-Familie (Java-Annotations vs. TS-Decorators);
// darum eigenes Feld-Naming. Renderer behandeln beide gleich (M009/0004
// hat bereits decorators || annotations-Fallback). args_source = null
// wenn die Annotation ohne Klammern steht (@Override), args_source = ""
// wenn Klammern leer sind (@Service()), args_source = der Source-String
// zwischen den Klammern (ohne Klammern selbst) sonst.
//
// Implementierung: PHP-seitiger State-Machine-Tokenizer (analog
// js_parser/nim_parser/lua_parser/ts_parser, Decision M005/0001), kein
// Regex (Decision 0006), keine externen Libs, kein Subprozess (insbesondere
// kein groovyc / groovy-AST). Closures `{ -> ... }`, GString-Interpolation
// `"text $var ${expr}"`, Triple-quoted Strings und Operator-Overloading
// werden tokenmaessig durchgelaufen, NICHT semantisch verstanden.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// Nie `new groovy_parser()`, immer `groovy_parser::parse(...)`.
// @end-spec
class groovy_parser
{

    // @spec
    // Parst eine Groovy-Datei und liefert das Schema-Array
    // (file_spec, symbols, warnings).
    // Bei nicht existierender / nicht lesbarer Datei: leeres Schema mit
    // Warning "file not found: $path" — der Dispatcher umhuellt das mit
    // file/language-Feldern.
    // Stub in M010/0001 — der echte Tokenizer/Walker kommt in M010/0002.
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
