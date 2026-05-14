<?php

declare(strict_types=1);

// @spec
// Sprach-agnostischer Einstieg fuer den Spec-Parser.
// Nimmt einen Dateipfad, dispatcht an den passenden Sprach-Parser
// anhand der Dateiendung, lehnt vendored Code und unbekannte Sprachen ab.
// Die echten Sprach-Parser (php_parser, js_parser) sind Stubs in M005/0001
// und werden in M005/0002 (PHP) und M005/0003 (JS) gefuellt.
// Schema-Doku lebt in app/_share/spec_parser/README.md.
// @end-spec

namespace _share\spec_parser;

require_once __DIR__ . "/php_parser.php";
require_once __DIR__ . "/js_parser.php";
require_once __DIR__ . "/nim_parser.php";
require_once __DIR__ . "/lua_parser.php";

// @spec
// Statisches Funktions-Buendel — kein Zustand, keine Instanz.
// Lowercase-Klassenname nach Decision 0003 (statische Funktionsbuendel
// wie app, db, document sind lowercase).
// @end-spec
class spec_parser
{

    // @spec
    // Parst die Datei unter $path und gibt das Schema-Array zurueck.
    // Ausgabe-Schema siehe README.md.
    // Fuer .php dispatcht an php_parser::parse, fuer .js an js_parser::parse,
    // fuer .nim an nim_parser::parse, fuer .lua an lua_parser::parse.
    // Andere Endungen liefern {"error": "unsupported language", ...}.
    // Pfade unter app/_share/vendor/ liefern {"error": "vendor code not parsed", ...}.
    // Wirft keine Exceptions — Fehler stehen im Rueckgabe-Array unter "error".
    // @end-spec
    static function parse(string $path): array
    {
        $normalized_path = spec_parser::normalize_path($path);

        $path_is_under_vendor = spec_parser::path_is_under_vendor($normalized_path);

        if ($path_is_under_vendor)
        {
            return [
                "file"  => $normalized_path,
                "error" => "vendor code not parsed",
            ];
        }

        $extension = strtolower(pathinfo($normalized_path, PATHINFO_EXTENSION));

        if ($extension === "php")
        {
            $result = php_parser::parse($normalized_path);
            return spec_parser::wrap_result($normalized_path, "php", $result);
        }

        if ($extension === "js")
        {
            $result = js_parser::parse($normalized_path);
            return spec_parser::wrap_result($normalized_path, "js", $result);
        }

        if ($extension === "nim")
        {
            $result = nim_parser::parse($normalized_path);
            return spec_parser::wrap_result($normalized_path, "nim", $result);
        }

        if ($extension === "lua")
        {
            $result = lua_parser::parse($normalized_path);
            return spec_parser::wrap_result($normalized_path, "lua", $result);
        }

        return [
            "file"      => $normalized_path,
            "error"     => "unsupported language",
            "extension" => $extension,
        ];
    }

    // @spec
    // Pfad-Normalisierung: trim whitespace, behalte vorhandene Trennzeichen.
    // Macht keinen realpath() — das wuerde Symlinks aufloesen und bricht
    // den Vendor-Blacklist-Check, der textuell auf "app/_share/vendor/" matcht.
    // @end-spec
    private static function normalize_path(string $path): string
    {
        return trim($path);
    }

    // @spec
    // True wenn der Pfad-String das Segment "app/_share/vendor/" enthaelt.
    // Kein Regex (Decision 0006) — strpos reicht und ist klar.
    // @end-spec
    private static function path_is_under_vendor(string $path): bool
    {
        return strpos($path, "app/_share/vendor/") !== false;
    }

    // @spec
    // Verpackt das Sprach-Parser-Ergebnis in das gemeinsame Schema.
    // Erwartet vom Sprach-Parser: file_spec, symbols, warnings.
    // Fuellt fehlende Felder mit leeren Arrays auf, damit das Schema
    // auch bei Stub-Parsern syntaktisch valide ist.
    // @end-spec
    private static function wrap_result(string $path, string $language, array $result): array
    {
        return [
            "file"      => $path,
            "language"  => $language,
            "file_spec" => $result["file_spec"] ?? [],
            "symbols"   => $result["symbols"]   ?? [],
            "warnings"  => $result["warnings"]  ?? [],
        ];
    }
}
