<?php

declare(strict_types=1);

// @spec
// JS-Spec-Parser-Stub. Das Schema ist in M005/0001 festgelegt
// (siehe app/_share/spec_parser/README.md), die Implementierung kommt
// in M005/0003. Stub gibt heute leeres Schema zurueck.
// Implementierungsstrategie ist in M005/0001 festgelegt:
// PHP-seitiger JS-Tokenizer-Walker (State-Machine), kein Regex
// (Decision 0006). Begruendung im README.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// @end-spec
class js_parser
{

    // @spec
    // Parst eine JS-Datei. Stub-Rueckgabe heute: file_spec, symbols, warnings
    // jeweils leer. Wird in M005/0003 gefuellt.
    // Die Schnittstelle (Eingabe: Pfad-String, Ausgabe: Array mit den drei
    // Feldern) ist hier festgeschrieben und darf in 0003 nicht geaendert werden.
    // @end-spec
    static function parse(string $path): array
    {
        return [
            "file_spec" => [],
            "symbols"   => [],
            "warnings"  => [],
        ];
    }
}
