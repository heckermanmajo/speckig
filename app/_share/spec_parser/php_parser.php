<?php

declare(strict_types=1);

// @spec
// PHP-Spec-Parser-Stub. Das Schema ist in M005/0001 festgelegt
// (siehe app/_share/spec_parser/README.md), die Implementierung kommt
// in M005/0002. Stub gibt heute leeres Schema zurueck, damit die
// Sprach-Dispatch-Kette und der CLI-Einstieg sauber funktionieren.
// Implementierung MUSS token_get_all nutzen, kein Regex (Decision 0006).
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// @end-spec
class php_parser
{

    // @spec
    // Parst eine PHP-Datei. Stub-Rueckgabe heute: file_spec, symbols, warnings
    // jeweils leer. Wird in M005/0002 mit token_get_all-Logik gefuellt.
    // Die Schnittstelle (Eingabe: Pfad-String, Ausgabe: Array mit den drei
    // Feldern) ist hier festgeschrieben und darf in 0002 nicht geaendert werden.
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
