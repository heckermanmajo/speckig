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
//
// Slashy-Strings (`/.../`): V1 erkennt sie NICHT als eigenes Token — alle
// "/" werden als Punctuation tokenisiert. Spec-Marker innerhalb eines
// Slashy-Strings koennten daher faelschlich als Marker erkannt werden.
// Slashy-Strings sind in Groovy-Codebases selten (vor allem ausserhalb
// von Regex-Snippets), darum ist die Pragmatik vertretbar. Sollte das
// noetig werden, ist Slashy-String-Tokenisierung eine eigene Erweiterung.
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

        $tokens = groovy_parser::tokenize($source);

        return groovy_parser::walk_tokens($tokens);
    }

    // ----- Tokenizer ------------------------------------------------------

    // @spec
    // State-Machine-Tokenizer fuer Groovy-Subset.
    // Ausgabe: array von Tokens, jedes Token = [kind, text, line].
    // kinds:
    //   "whitespace"           - Whitespace (Space, Tab, Newline, CR)
    //   "comment_line"         - // bis Zeilenende
    //   "comment_block"        - /* ... */ und Groovydoc /** ... */
    //   "string_single"        - '...' (einzeilig, mit \-Escape)
    //   "string_double"        - "..." (einzeilig, mit \-Escape und
    //                            $var/${expr}-Interpolation)
    //   "string_triple_single" - '''...''' (mehrzeilig, KEINE Interpolation)
    //   "string_triple_double" - """...""" (mehrzeilig, mit Interpolation)
    //   "number"               - Zahlen (grobe Erkennung)
    //   "identifier"           - Identifier oder Keyword
    //   "punctuation"          - Alles andere; "@" als Annotation-Praefix.
    //                            "/" wird hier als Punctuation gefuehrt
    //                            (Slashy-Strings nicht erkannt — siehe
    //                            Doku im Datei-Header).
    // @end-spec
    private static function tokenize(string $source): array
    {
        $tokens = [];
        $len    = strlen($source);
        $i      = 0;
        $line   = 1;

        while ($i < $len)
        {
            $c = $source[$i];

            // Whitespace
            $is_whitespace_char = $c === " " || $c === "\t" || $c === "\n" || $c === "\r";

            if ($is_whitespace_char)
            {
                $start = $i;
                $start_line = $line;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    if ($cc === " " || $cc === "\t" || $cc === "\r")
                    {
                        $i++;
                        continue;
                    }
                    if ($cc === "\n")
                    {
                        $line++;
                        $i++;
                        continue;
                    }
                    break;
                }
                $tokens[] = ["whitespace", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Comments
            $two = $i + 1 < $len ? $c . $source[$i + 1] : "";

            if ($two === "//")
            {
                $start = $i;
                $start_line = $line;
                $i += 2;
                while ($i < $len && $source[$i] !== "\n")
                {
                    $i++;
                }
                $tokens[] = ["comment_line", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            if ($two === "/*")
            {
                $start = $i;
                $start_line = $line;
                $i += 2;
                while ($i < $len)
                {
                    if ($source[$i] === "*" && $i + 1 < $len && $source[$i + 1] === "/")
                    {
                        $i += 2;
                        break;
                    }
                    if ($source[$i] === "\n")
                    {
                        $line++;
                    }
                    $i++;
                }
                $tokens[] = ["comment_block", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Triple single-quoted string '''...''' (no interpolation in Groovy)
            $three = $i + 2 < $len ? $c . $source[$i + 1] . $source[$i + 2] : "";

            if ($three === "'''")
            {
                $start = $i;
                $start_line = $line;
                $i += 3;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    if ($cc === "\\")
                    {
                        if ($i + 1 < $len && $source[$i + 1] === "\n")
                        {
                            $line++;
                        }
                        $i += 2;
                        continue;
                    }
                    if ($cc === "'"
                        && $i + 2 < $len
                        && $source[$i + 1] === "'"
                        && $source[$i + 2] === "'")
                    {
                        $i += 3;
                        break;
                    }
                    if ($cc === "\n")
                    {
                        $line++;
                    }
                    $i++;
                }
                $tokens[] = ["string_triple_single", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Triple double-quoted string """...""" (GString interpolation)
            if ($three === "\"\"\"")
            {
                $start = $i;
                $start_line = $line;
                $i += 3;
                $i = groovy_parser::scan_gstring_body(
                    $source, $i, $len, $line,
                    /* is_triple = */ true
                );
                $tokens[] = ["string_triple_double", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Single-quoted string '...' (single line, no interpolation in Groovy)
            if ($c === "'")
            {
                $start = $i;
                $start_line = $line;
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    if ($cc === "\\")
                    {
                        if ($i + 1 < $len && $source[$i + 1] === "\n")
                        {
                            $line++;
                        }
                        $i += 2;
                        continue;
                    }
                    if ($cc === "'")
                    {
                        $i++;
                        break;
                    }
                    if ($cc === "\n")
                    {
                        $line++;
                        $i++;
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["string_single", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Double-quoted string "..." (single line, with GString interpolation)
            if ($c === "\"")
            {
                $start = $i;
                $start_line = $line;
                $i++;
                $i = groovy_parser::scan_gstring_body(
                    $source, $i, $len, $line,
                    /* is_triple = */ false
                );
                $tokens[] = ["string_double", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Number
            $is_digit_start = $c >= "0" && $c <= "9";

            if ($is_digit_start)
            {
                $start = $i;
                $start_line = $line;
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    $is_num_char =
                        ($cc >= "0" && $cc <= "9")
                        || $cc === "."
                        || $cc === "_"
                        || $cc === "e" || $cc === "E"
                        || $cc === "x" || $cc === "X"
                        || $cc === "b" || $cc === "B"
                        || $cc === "o" || $cc === "O"
                        || $cc === "l" || $cc === "L"
                        || $cc === "f" || $cc === "F"
                        || $cc === "d" || $cc === "D"
                        || $cc === "g" || $cc === "G"
                        || ($cc >= "a" && $cc <= "f")
                        || ($cc >= "A" && $cc <= "F")
                        || ($cc === "+" && substr($source, $i - 1, 1) === "e")
                        || ($cc === "-" && substr($source, $i - 1, 1) === "e");
                    if ( ! $is_num_char)
                    {
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["number", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Identifier / keyword
            $is_ident_start =
                ($c >= "a" && $c <= "z")
                || ($c >= "A" && $c <= "Z")
                || $c === "_"
                || $c === "\$";

            if ($is_ident_start)
            {
                $start = $i;
                $start_line = $line;
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    $is_ident_char =
                        ($cc >= "a" && $cc <= "z")
                        || ($cc >= "A" && $cc <= "Z")
                        || ($cc >= "0" && $cc <= "9")
                        || $cc === "_"
                        || $cc === "\$";
                    if ( ! $is_ident_char)
                    {
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["identifier", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Punctuation: try multi-char operators first.
            $three_char_ops = ["===", "!==", "**=", "...", ">>>", "<<=", ">>=", "<=>"];
            $two_char_ops   = [
                "==", "!=", "<=", ">=", "&&", "||", "?:",
                "+=", "-=", "*=", "/=", "%=", "&=", "|=", "^=",
                "++", "--", "**", "<<", ">>", "->", "?.",
                "*.", ".&", ".@", "..", "=~", "==~",
            ];

            if ($three !== "" && in_array($three, $three_char_ops, true))
            {
                $tokens[] = ["punctuation", $three, $line];
                $i += 3;
                continue;
            }

            if ($two !== "" && in_array($two, $two_char_ops, true))
            {
                $tokens[] = ["punctuation", $two, $line];
                $i += 2;
                continue;
            }

            // Single-char punctuation (covers @, !, ?, :, <, >, /, etc.).
            $tokens[] = ["punctuation", $c, $line];
            $i++;
        }

        return $tokens;
    }

    // @spec
    // Liest den Inhalt eines Double-quoted GStrings ab Position $i
    // (direkt nach dem oeffnenden " bzw. """). Beruecksichtigt
    // GString-Interpolation: $simpleVar und ${beliebige Expression}.
    // In ${...} werden geschweifte Klammern korrekt gezaehlt und nested
    // Strings/Comments durchgelaufen, sodass z.B. ${"x"} oder ${foo("bar")}
    // den Tokenizer nicht abreissen.
    // $is_triple steuert, ob als Terminator " (false) oder """ (true)
    // gesucht wird. Liefert den Index NACH dem Terminator.
    // Updates $line per Referenz.
    // @end-spec
    private static function scan_gstring_body(
        string $source, int $i, int $len, int &$line, bool $is_triple
    ): int
    {
        while ($i < $len)
        {
            $cc = $source[$i];

            if ($cc === "\\")
            {
                if ($i + 1 < $len && $source[$i + 1] === "\n")
                {
                    $line++;
                }
                $i += 2;
                continue;
            }

            // Interpolation: ${...}
            $is_dollar_brace =
                $cc === "\$"
                && $i + 1 < $len
                && $source[$i + 1] === "{";

            if ($is_dollar_brace)
            {
                $i += 2;
                $brace_depth = 1;
                while ($i < $len && $brace_depth > 0)
                {
                    $cc2 = $source[$i];

                    if ($cc2 === "'" || $cc2 === "\"")
                    {
                        // Detect nested triple-quoted strings inside interp.
                        $is_triple_nested =
                            $i + 2 < $len
                            && $source[$i + 1] === $cc2
                            && $source[$i + 2] === $cc2;

                        if ($is_triple_nested)
                        {
                            $quote = $cc2;
                            $i += 3;
                            while ($i < $len)
                            {
                                $cc3 = $source[$i];
                                if ($cc3 === "\\")
                                {
                                    if ($i + 1 < $len && $source[$i + 1] === "\n")
                                    {
                                        $line++;
                                    }
                                    $i += 2;
                                    continue;
                                }
                                if ($cc3 === $quote
                                    && $i + 2 < $len
                                    && $source[$i + 1] === $quote
                                    && $source[$i + 2] === $quote)
                                {
                                    $i += 3;
                                    break;
                                }
                                if ($cc3 === "\n")
                                {
                                    $line++;
                                }
                                $i++;
                            }
                            continue;
                        }

                        $quote = $cc2;
                        $i++;
                        while ($i < $len)
                        {
                            $cc3 = $source[$i];
                            if ($cc3 === "\\")
                            {
                                if ($i + 1 < $len && $source[$i + 1] === "\n")
                                {
                                    $line++;
                                }
                                $i += 2;
                                continue;
                            }
                            if ($cc3 === $quote)
                            {
                                $i++;
                                break;
                            }
                            if ($cc3 === "\n")
                            {
                                $line++;
                                $i++;
                                break;
                            }
                            $i++;
                        }
                        continue;
                    }

                    if ($cc2 === "/" && $i + 1 < $len && $source[$i + 1] === "/")
                    {
                        $i += 2;
                        while ($i < $len && $source[$i] !== "\n")
                        {
                            $i++;
                        }
                        continue;
                    }

                    if ($cc2 === "/" && $i + 1 < $len && $source[$i + 1] === "*")
                    {
                        $i += 2;
                        while ($i < $len)
                        {
                            if ($source[$i] === "*" && $i + 1 < $len && $source[$i + 1] === "/")
                            {
                                $i += 2;
                                break;
                            }
                            if ($source[$i] === "\n")
                            {
                                $line++;
                            }
                            $i++;
                        }
                        continue;
                    }

                    if ($cc2 === "{")
                    {
                        $brace_depth++;
                        $i++;
                        continue;
                    }
                    if ($cc2 === "}")
                    {
                        $brace_depth--;
                        $i++;
                        continue;
                    }
                    if ($cc2 === "\n")
                    {
                        $line++;
                    }
                    $i++;
                }
                continue;
            }

            // Simple var interpolation: $ident
            $is_dollar_ident =
                $cc === "\$"
                && $i + 1 < $len
                && (
                    ($source[$i + 1] >= "a" && $source[$i + 1] <= "z")
                    || ($source[$i + 1] >= "A" && $source[$i + 1] <= "Z")
                    || $source[$i + 1] === "_"
                );

            if ($is_dollar_ident)
            {
                $i++; // skip $
                while ($i < $len)
                {
                    $cc3 = $source[$i];
                    $is_ident_char =
                        ($cc3 >= "a" && $cc3 <= "z")
                        || ($cc3 >= "A" && $cc3 <= "Z")
                        || ($cc3 >= "0" && $cc3 <= "9")
                        || $cc3 === "_"
                        || $cc3 === ".";
                    if ( ! $is_ident_char)
                    {
                        break;
                    }
                    $i++;
                }
                continue;
            }

            // Terminator
            if ($is_triple)
            {
                if ($cc === "\""
                    && $i + 2 < $len
                    && $source[$i + 1] === "\""
                    && $source[$i + 2] === "\"")
                {
                    $i += 3;
                    return $i;
                }
                if ($cc === "\n")
                {
                    $line++;
                }
                $i++;
                continue;
            }

            if ($cc === "\"")
            {
                $i++;
                return $i;
            }

            if ($cc === "\n")
            {
                // Single-line string with embedded newline — bail out.
                $line++;
                $i++;
                return $i;
            }

            $i++;
        }

        return $i;
    }

    // ----- Walker ---------------------------------------------------------

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // sammelt Annotations, dispatcht Symbol-Erkennung (class, interface,
    // enum, trait, top-level script-method/script-var, package/import).
    // Datei-Header-Spec: der erste Spec-Block, der vor dem ersten Top-Level-
    // Symbol-Marker (class, interface, enum, trait, def, package, import,
    // ein Annotation-"@", oder ein Type+Name+"("-Methodenkopf) steht.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count = count($tokens);

        $first_decl_index = groovy_parser::first_decl_index($tokens);

        $file_spec          = [];
        $file_spec_taken    = false;
        $symbols            = [];
        $warnings           = [];
        $pending_spec       = null;
        $pending_annotations = [];
        $depth              = 0;

        $index = 0;

        while ($index < $count)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            if ($kind === "punctuation")
            {
                if ($text === "(" || $text === "[" || $text === "{")
                {
                    $depth++;
                    $index++;
                    continue;
                }
                if ($text === ")" || $text === "]" || $text === "}")
                {
                    if ($depth > 0)
                    {
                        $depth--;
                    }
                    $index++;
                    continue;
                }
            }

            // Spec-Start (top-level only).
            if ($depth === 0 && groovy_parser::is_spec_start_token($token))
            {
                $spec_block = groovy_parser::read_spec_block($tokens, $index, $count);

                $spec_starts_before_first_decl =
                    $first_decl_index !== null
                    && $spec_block["start_index"] < $first_decl_index;

                $file_header_unclaimed_yet = ! $file_spec_taken;

                if ($spec_starts_before_first_decl && $file_header_unclaimed_yet)
                {
                    $file_spec       = $spec_block["lines"];
                    $file_spec_taken = true;
                    continue;
                }

                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $spec_block;
                continue;
            }

            // Annotation at top level: collect into pending annotation list.
            if ($depth === 0 && $kind === "punctuation" && $text === "@")
            {
                $ann = groovy_parser::read_annotation($tokens, $index, $count);
                if ($ann !== null)
                {
                    $pending_annotations[] = $ann["annotation"];
                    $index = $ann["next_index"];
                    continue;
                }
                $index++;
                continue;
            }

            // Skip whitespace, comments, Groovydoc between spec and symbol.
            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            if ($depth === 0 && $kind === "identifier")
            {
                // Modifier-Keywords vor der eigentlichen Deklaration.
                $modifiers = [
                    "public", "protected", "private", "static",
                    "final", "abstract", "synchronized", "native",
                    "transient", "volatile", "strictfp",
                ];
                if (in_array($text, $modifiers, true))
                {
                    $index++;
                    continue;
                }

                if ($text === "package" || $text === "import")
                {
                    // Skip the rest of the statement (until newline or ;).
                    $index = groovy_parser::skip_to_statement_end($tokens, $index, $count);
                    // package/import statements consume any pending spec/anns
                    // only if explicitly declared before. Pragmatic: keep
                    // pending_spec/annotations untouched — they belong to the
                    // next real symbol. (TS does the same.)
                    continue;
                }

                if ($text === "class")
                {
                    $symbol_result = groovy_parser::read_class_like(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_annotations,
                        "class"
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $pending_annotations = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "interface")
                {
                    $symbol_result = groovy_parser::read_class_like(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_annotations,
                        "interface"
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $pending_annotations = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "enum")
                {
                    $symbol_result = groovy_parser::read_class_like(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_annotations,
                        "enum"
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $pending_annotations = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "trait")
                {
                    $symbol_result = groovy_parser::read_class_like(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_annotations,
                        "trait"
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $pending_annotations = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                // Top-level script: def name(...) -> script-method, or
                // def name = ... -> script-var. Or Type name(...) ->
                // script-method, Type name = ... -> script-var.
                $script_result = groovy_parser::try_read_script_decl(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? []
                );

                if ($script_result !== null)
                {
                    $symbols[]    = $script_result["symbol"];
                    $pending_spec = null;
                    $pending_annotations = [];
                    $index        = $script_result["next_index"];
                    continue;
                }
            }

            $index++;
        }

        if ($pending_spec !== null)
        {
            $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
        }

        return [
            "file_spec" => $file_spec,
            "symbols"   => $symbols,
            "warnings"  => $warnings,
        ];
    }

    // @spec
    // Liefert den Token-Index des ersten Top-Level-Deklarations-Markers
    // (class, interface, enum, trait, def, package, import, ein
    // Annotation-"@", oder ein Identifier der eine Methoden- oder Var-
    // Deklaration einleitet) oder null wenn keiner existiert.
    // Pragmatik: wir nehmen die offensichtlichen Keywords + "@" + "def"
    // + "package" + "import". Top-level Type+name(... )-Heuristik wird
    // hier nicht versucht — das wuerde die Datei-Header-Heuristik
    // unzuverlaessig machen. Wenn ein Skript nur Type-prefixed top-
    // level decls hat, gehoert Spec halt der ersten.
    // @end-spec
    private static function first_decl_index(array $tokens): ?int
    {
        $count = count($tokens);
        // package/import are explicitly NOT counted as declaration markers:
        // a spec block that follows package/import imports but precedes the
        // first real symbol still qualifies as a file-header spec.
        $keys  = [
            "class", "interface", "enum", "trait", "def",
        ];
        $depth = 0;
        for ($i = 0; $i < $count; $i++)
        {
            $t = $tokens[$i];

            // Skip past package/import statements as a unit so that any "@"
            // inside an import (e.g. `import foo.@Bar`, theoretical) doesn't
            // trip us. In practice plain idents/dots; we just step over.
            if ($t[0] === "identifier"
                && ($t[1] === "package" || $t[1] === "import"))
            {
                $i++;
                while ($i < $count)
                {
                    $tt = $tokens[$i];
                    if ($tt[0] === "punctuation" && $tt[1] === ";")
                    {
                        break;
                    }
                    if ($tt[0] === "whitespace" && strpos($tt[1], "\n") !== false)
                    {
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($t[0] === "punctuation")
            {
                if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                {
                    $depth++;
                    continue;
                }
                if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                {
                    if ($depth > 0) { $depth--; }
                    continue;
                }
            }

            if ($depth !== 0)
            {
                continue;
            }

            if ($t[0] === "identifier" && in_array($t[1], $keys, true))
            {
                return $i;
            }
            if ($t[0] === "punctuation" && $t[1] === "@")
            {
                return $i;
            }
        }
        return null;
    }

    // @spec
    // True, wenn das Token ein comment_line-Token mit Inhalt "// @spec"
    // (optional gefolgt von Space + Inline-Inhalt) ist.
    // @end-spec
    private static function is_spec_start_token($token): bool
    {
        if ($token[0] !== "comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        $is_slash = substr($text, 0, 2) === "//";
        if ( ! $is_slash)
        {
            return false;
        }
        $body = ltrim(substr($text, 2));
        return $body === "@spec"
            || str_starts_with($body, "@spec ")
            || str_starts_with($body, "@spec\t");
    }

    // @spec
    // True, wenn das Token ein comment_line-Token mit Inhalt "// @end-spec"
    // ist.
    // @end-spec
    private static function is_spec_end_token($token): bool
    {
        if ($token[0] !== "comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        $is_slash = substr($text, 0, 2) === "//";
        if ( ! $is_slash)
        {
            return false;
        }
        $body = ltrim(substr($text, 2));
        return $body === "@end-spec"
            || str_starts_with($body, "@end-spec ")
            || str_starts_with($body, "@end-spec\t");
    }

    // @spec
    // Liest einen Spec-Block ab Index $index (auf comment_line-Spec-Start).
    // Returnt ["lines" => [...], "start_line" => N, "start_index" => I]
    // und avanciert $index hinter @end-spec. Whitespace zwischen Spec-Lines
    // wird geschluckt; alles andere zwischen Spec-Lines schliesst den Block
    // (ohne @end-spec) — Walker behandelt das ggf. als dangling.
    // @end-spec
    private static function read_spec_block(array $tokens, int &$index, int $count): array
    {
        $start_index = $index;
        $start_token = $tokens[$index];
        $start_line  = $start_token[2];

        $lines = [];

        $first_text = rtrim($start_token[1]);
        $first_body = ltrim(substr($first_text, 2));
        $after_marker = ltrim(substr($first_body, strlen("@spec")));

        if ($after_marker !== "")
        {
            $lines[] = $after_marker;
        }

        $index++;

        while ($index < $count)
        {
            $token = $tokens[$index];
            $kind  = $token[0];

            if ($kind === "whitespace")
            {
                $index++;
                continue;
            }

            if (groovy_parser::is_spec_end_token($token))
            {
                $index++;
                return [
                    "lines"       => $lines,
                    "start_line"  => $start_line,
                    "start_index" => $start_index,
                ];
            }

            if ($kind === "comment_line")
            {
                $text = rtrim($token[1]);
                $body = substr($text, 2);
                if (strlen($body) > 0 && $body[0] === " ")
                {
                    $body = substr($body, 1);
                }
                $lines[] = $body;
                $index++;
                continue;
            }

            return [
                "lines"       => $lines,
                "start_line"  => $start_line,
                "start_index" => $start_index,
            ];
        }

        return [
            "lines"       => $lines,
            "start_line"  => $start_line,
            "start_index" => $start_index,
        ];
    }

    // @spec
    // Liest eine Annotation ab Index $index, erwartet $index auf "@".
    // Format: "@" identifier ("." identifier)* ( "(" args ")" )?.
    // Returnt ["annotation" => ["name" => string, "args_source" => string|null],
    //          "next_index" => int] oder null bei Fehlschlag.
    // args_source ist null wenn keine Klammern, "" wenn "()", sonst der Inhalt
    // zwischen den Klammern (ohne die Klammern selbst).
    // @end-spec
    private static function read_annotation(array $tokens, int $index, int $count): ?array
    {
        // Skip "@".
        $index++;

        // Optional whitespace tolerated between @ and name (rare, but cheap).
        $index = groovy_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $index++;

        // Qualified name: ("." identifier)*
        while ($index < $count)
        {
            // No skip_ws here — qualified annotation names are tight ("@a.b").
            if ($tokens[$index][0] === "punctuation" && $tokens[$index][1] === ".")
            {
                $index++;
                if ($index < $count && $tokens[$index][0] === "identifier")
                {
                    $name .= "." . $tokens[$index][1];
                    $index++;
                    continue;
                }
                break;
            }
            break;
        }

        // Args? Peek next significant.
        $look_index = groovy_parser::skip_ws($tokens, $index, $count);

        $args_source = null;

        if ($look_index < $count
            && $tokens[$look_index][0] === "punctuation"
            && $tokens[$look_index][1] === "(")
        {
            $index = $look_index;
            $balanced = groovy_parser::read_balanced($tokens, $index, $count, "(", ")");
            $inner = substr($balanced, 1, strlen($balanced) - 2);
            $args_source = trim($inner);
        }

        return [
            "annotation" => [
                "name"        => $name,
                "args_source" => $args_source,
            ],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine class/interface/enum/trait-Deklaration. $kind_label gibt
    // den kind-Wert ans Symbol weiter ("class"/"interface"/"enum"/"trait").
    // Sammelt name, extends-Liste, implements-Liste, body via
    // read_class_body. interface erlaubt "extends I, J", class erlaubt
    // "extends Parent" + "implements I, J", trait wie class, enum hat
    // V1 weder extends noch implements (Body wird trotzdem balanciert
    // gelesen).
    // @end-spec
    private static function read_class_like(
        array $tokens, int $index, int $count,
        array $spec, array $annotations, string $kind_label
    ): array
    {
        // skip the keyword
        $index++;

        $index = groovy_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        // Optional generics "<...>"
        $generics_index = groovy_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = groovy_parser::read_balanced_angles($tokens, $generics_index, $count);
            if ($generics !== null)
            {
                $index = $generics["next_index"];
            }
        }

        $extends    = [];
        $implements = [];

        // Loop: optional "extends" / "implements" before "{".
        while (true)
        {
            $index = groovy_parser::skip_ws($tokens, $index, $count);

            if ($index >= $count) { break; }

            if ($tokens[$index][0] === "identifier" && $tokens[$index][1] === "extends")
            {
                $index++;
                $list = groovy_parser::read_type_ref_list($tokens, $index, $count);
                $extends = array_merge($extends, $list["names"]);
                $index   = $list["next_index"];
                continue;
            }

            if ($tokens[$index][0] === "identifier" && $tokens[$index][1] === "implements")
            {
                $index++;
                $list = groovy_parser::read_type_ref_list($tokens, $index, $count);
                $implements = array_merge($implements, $list["names"]);
                $index      = $list["next_index"];
                continue;
            }

            break;
        }

        // Find class body "{"
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "punctuation" && $t[1] === "{")
            {
                break;
            }
            $index++;
        }

        $members  = [];
        $warnings = [];

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            $body = groovy_parser::read_class_body($tokens, $index, $count, $name);
            $members  = $body["members"];
            $warnings = $body["warnings"];
            $index    = $body["next_index"];
        }

        $symbol = [
            "kind"        => $kind_label,
            "name"        => $name,
            "extends"     => $extends,
            "implements"  => $implements,
            "annotations" => $annotations,
            "spec"        => $spec,
            "members"     => $members,
        ];

        // For enum we don't carry implements (V1) — leave key but empty.
        // For interface, implements stays empty by construction.
        return [
            "symbol"     => $symbol,
            "warnings"   => $warnings,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest den Body einer Klasse/Interface/Enum/Trait ab "{". Sammelt
    // Spec-Bloecke, Annotations, dispatcht auf Member-Erkennung
    // (Methoden/Konstruktor/Felder/Properties). $class_name wird benoetigt,
    // um Konstruktoren zu erkennen (Methode mit Name == Klassen-Name,
    // ohne Return-Type). Avanciert hinter das matched "}".
    // @end-spec
    private static function read_class_body(
        array $tokens, int $index, int $count, string $class_name
    ): array
    {
        // expects $index on "{"
        $depth = 1;
        $index++;

        $members             = [];
        $warnings            = [];
        $pending_spec        = null;
        $pending_annotations = [];

        while ($index < $count && $depth > 0)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            if ($kind === "punctuation" && $text === "{")
            {
                $depth++;
                $index++;
                continue;
            }
            if ($kind === "punctuation" && $text === "}")
            {
                $depth--;
                $index++;
                if ($depth === 0)
                {
                    break;
                }
                continue;
            }

            if ($depth === 1 && groovy_parser::is_spec_start_token($token))
            {
                $spec_block = groovy_parser::read_spec_block($tokens, $index, $count);
                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $spec_block;
                continue;
            }

            // Annotation at member level.
            if ($depth === 1 && $kind === "punctuation" && $text === "@")
            {
                $ann = groovy_parser::read_annotation($tokens, $index, $count);
                if ($ann !== null)
                {
                    $pending_annotations[] = $ann["annotation"];
                    $index = $ann["next_index"];
                    continue;
                }
                $index++;
                continue;
            }

            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            if ($depth === 1 && $kind === "punctuation" && ($text === ";" || $text === ","))
            {
                $index++;
                continue;
            }

            if ($depth === 1)
            {
                $member = groovy_parser::try_read_class_member(
                    $tokens, $index, $count, $class_name,
                    $pending_spec["lines"] ?? [],
                    $pending_annotations
                );

                if ($member !== null)
                {
                    $members[]           = $member["symbol"];
                    $warnings            = array_merge($warnings, $member["warnings"]);
                    $pending_spec        = null;
                    $pending_annotations = [];
                    $index               = $member["next_index"];
                    continue;
                }
            }

            $index++;
        }

        if ($pending_spec !== null)
        {
            $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
        }

        return [
            "members"    => $members,
            "warnings"   => $warnings,
            "next_index" => $index,
        ];
    }

    // @spec
    // Versucht, ab $index einen Klassen-Member zu lesen.
    //   - Sammelt Modifier-Keywords (public/protected/private/static/final/
    //     abstract/synchronized/native/transient/volatile/strictfp/def).
    //   - Liest dann (optional) einen Return-Type (Identifier mit
    //     optionalem Generic + optionalem [] / .Identifier).
    //   - Liest dann den Name-Identifier.
    //   - Wenn nach Name ein "(" steht: Methode oder Konstruktor.
    //   - Sonst: Feld oder Property (= ohne Modifier).
    // Hat_modifier (mind. ein public/protected/private/static/final/abstract)
    // entscheidet field vs property fuer Felder. "def" gilt als
    // expliziter Modifier — Felder mit "def" werden als field gefuehrt.
    // Fuer Konstruktor-Erkennung wird der Klassen-Name mitgegeben.
    // @end-spec
    private static function try_read_class_member(
        array $tokens, int $index, int $count, string $class_name,
        array $spec, array $annotations
    ): ?array
    {
        $modifier_idents = [
            "public", "protected", "private", "static",
            "final", "abstract", "synchronized", "native",
            "transient", "volatile", "strictfp", "def",
        ];

        $access_modifier_idents = [
            "public", "protected", "private",
        ];

        $modifiers       = [];
        $has_def         = false;
        $has_modifier    = false;
        $has_access_mod  = false;

        // Collect modifiers in any reasonable order.
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "whitespace" || $t[0] === "comment_line" || $t[0] === "comment_block")
            {
                $index++;
                continue;
            }
            if ($t[0] === "identifier" && in_array($t[1], $modifier_idents, true)
                && ! in_array($t[1], $modifiers, true))
            {
                $look = groovy_parser::peek_next_significant($tokens, $index + 1, $count);
                if ($look !== null
                    && ($look[0] === "identifier"
                        || ($look[0] === "punctuation" && ($look[1] === "@"))))
                {
                    $modifiers[] = $t[1];
                    $has_modifier = true;
                    if ($t[1] === "def")
                    {
                        $has_def = true;
                    }
                    if (in_array($t[1], $access_modifier_idents, true))
                    {
                        $has_access_mod = true;
                    }
                    $index++;
                    continue;
                }
                break;
            }
            break;
        }

        // Member-level annotations after modifiers are rare but valid; eat
        // them and treat them as additional annotations on the symbol.
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "whitespace" || $t[0] === "comment_line" || $t[0] === "comment_block")
            {
                $index++;
                continue;
            }
            if ($t[0] === "punctuation" && $t[1] === "@")
            {
                $ann = groovy_parser::read_annotation($tokens, $index, $count);
                if ($ann !== null)
                {
                    $annotations[] = $ann["annotation"];
                    $index = $ann["next_index"];
                    continue;
                }
                $index++;
                continue;
            }
            break;
        }

        $index = groovy_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count)
        {
            return null;
        }

        // Scan a "type-or-name token sequence" up to the next significant
        // boundary. We tentatively grab one identifier-with-suffixes and
        // see if it's followed by another identifier (then the first was
        // a type). If not, the single identifier is the member name.
        $first = groovy_parser::read_type_or_name(
            $tokens, $index, $count
        );

        if ($first === null)
        {
            return null;
        }

        $first_text       = $first["text"];
        $first_was_simple = $first["is_simple"];
        $after_first      = $first["next_index"];

        $look_after_first = groovy_parser::skip_ws($tokens, $after_first, $count);

        $type_text = "";
        $name      = "";
        $name_index = -1;

        if ($look_after_first < $count
            && $tokens[$look_after_first][0] === "identifier")
        {
            // first was the type, second is the name
            $type_text  = $first_text;
            $name       = $tokens[$look_after_first][1];
            $name_index = $look_after_first;
            $index      = $look_after_first + 1;
        }
        else
        {
            // first is the name (no explicit type)
            // Only valid if first was a "simple" identifier (no [], no <>).
            if ( ! $first_was_simple)
            {
                return null;
            }
            $name       = $first_text;
            $name_index = $index;
            $index      = $after_first;
        }

        // Decide method vs property: peek "(" after name.
        $peek_index = groovy_parser::skip_ws($tokens, $index, $count);

        $is_method_head =
            $peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === "(";

        if ($is_method_head)
        {
            $index = $peek_index;
            $params = groovy_parser::read_balanced($tokens, $index, $count, "(", ")");

            // Optional throws clause
            $throws_text = "";
            $look = groovy_parser::skip_ws($tokens, $index, $count);
            if ($look < $count
                && $tokens[$look][0] === "identifier"
                && $tokens[$look][1] === "throws")
            {
                $index = $look;
                $throws_text = " throws";
                $index++;
                $list = groovy_parser::read_type_ref_list($tokens, $index, $count);
                if ( ! empty($list["names"]))
                {
                    $throws_text .= " " . implode(", ", $list["names"]);
                }
                $index = $list["next_index"];
            }

            // Build signature
            $sig = "";
            foreach ($modifiers as $m)
            {
                $sig .= $m . " ";
            }
            if ($type_text !== "")
            {
                $sig .= $type_text . " ";
            }
            $sig .= $name . $params . $throws_text;

            // Body: optional ("abstract"/interface methods can omit body).
            $index = groovy_parser::skip_ws($tokens, $index, $count);
            $body_members = [];

            if ($index < $count
                && $tokens[$index][0] === "punctuation"
                && $tokens[$index][1] === "{")
            {
                $body = groovy_parser::read_function_body($tokens, $index, $count);
                $body_members = $body["members"];
                $index        = $body["next_index"];
            }
            else if ($index < $count
                && $tokens[$index][0] === "punctuation"
                && $tokens[$index][1] === ";")
            {
                $index++;
            }

            $sig = groovy_parser::normalise_signature($sig);

            $is_constructor = ($class_name !== "" && $name === $class_name && $type_text === "");

            $symbol = [
                "kind"        => $is_constructor ? "constructor" : "method",
                "name"        => $name,
                "signature"   => $sig,
                "annotations" => $annotations,
                "spec"        => $spec,
                "members"     => $body_members,
            ];

            return [
                "symbol"     => $symbol,
                "warnings"   => [],
                "next_index" => $index,
            ];
        }

        // Field / Property: optional "= value", optional ";".
        $default = "";
        if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === "=")
        {
            $index = $peek_index + 1;
            $index = groovy_parser::skip_ws($tokens, $index, $count);
            $default = groovy_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";", "}", ","],
                /* respect_newline = */ true
            );
        }
        else
        {
            $index = $peek_index;
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && ($tokens[$index][1] === ";" || $tokens[$index][1] === ","))
        {
            $index++;
        }

        // Field vs property heuristic:
        //   - Has access modifier (public/protected/private) -> field
        //   - Has "def" or other modifier (static/final/...) -> field
        //   - No modifier at all                              -> property
        // Background: in Groovy a class member declared without an explicit
        // modifier becomes a property (auto-getter/setter generated). With
        // any modifier (including def) it stays as-is. We use has_modifier
        // as the discriminator.
        $member_kind = $has_modifier ? "field" : "property";

        $symbol = [
            "kind"        => $member_kind,
            "name"        => $name,
            "type"        => $type_text,
            "default"     => $default,
            "annotations" => $annotations,
            "spec"        => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest aus der Token-Liste eine "Type-or-Name"-Sequenz: ein Identifier
    // gefolgt von optionalen ".Identifier"-Pfaden, optionalen Generics
    // "<...>", und optionalen "[]"-Suffixen.
    // is_simple = true wenn nur ein einzelner Identifier ohne Suffixe
    // gelesen wurde (=> Kandidat fuer einen Member-Namen). is_simple = false
    // wenn der gelesene Token-Lauf nur als Type sinnvoll ist.
    // Returnt null wenn an $index kein Identifier steht.
    // @end-spec
    private static function read_type_or_name(array $tokens, int $index, int $count): ?array
    {
        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $text = $tokens[$index][1];
        $index++;

        $is_simple = true;

        // Qualified names "a.b.c"
        while ($index < $count)
        {
            if ($tokens[$index][0] === "punctuation" && $tokens[$index][1] === ".")
            {
                $next = $index + 1;
                if ($next < $count && $tokens[$next][0] === "identifier")
                {
                    $text .= "." . $tokens[$next][1];
                    $index = $next + 1;
                    $is_simple = false;
                    continue;
                }
                break;
            }
            break;
        }

        // Optional generics
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "<")
        {
            $generics = groovy_parser::read_balanced_angles($tokens, $index, $count);
            if ($generics !== null)
            {
                $text .= $generics["text"];
                $index = $generics["next_index"];
                $is_simple = false;
            }
        }

        // Optional array suffixes "[]"
        while ($index + 1 < $count
            && $tokens[$index][0] === "punctuation" && $tokens[$index][1] === "["
            && $tokens[$index + 1][0] === "punctuation" && $tokens[$index + 1][1] === "]")
        {
            $text .= "[]";
            $index += 2;
            $is_simple = false;
        }

        return [
            "text"       => $text,
            "is_simple"  => $is_simple,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Liste von Type-References (Identifier-Pfade A.B mit
    // optionalen <generics>) getrennt durch ",". Stoppt vor "{" oder vor
    // den Keywords "implements"/"extends"/"throws". Returnt
    // ["names" => string[], "next_index" => int].
    // @end-spec
    private static function read_type_ref_list(array $tokens, int $index, int $count): array
    {
        $names = [];
        $current = "";

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            if ($kind === "punctuation" && $text === ",")
            {
                if ($current !== "")
                {
                    $names[] = trim($current);
                    $current = "";
                }
                $index++;
                continue;
            }

            if ($kind === "punctuation" && $text === "{")
            {
                break;
            }

            if ($kind === "identifier"
                && ($text === "implements" || $text === "extends" || $text === "throws"))
            {
                break;
            }

            if ($kind === "punctuation" && $text === "<")
            {
                $generics = groovy_parser::read_balanced_angles($tokens, $index, $count);
                if ($generics !== null)
                {
                    $current .= $generics["text"];
                    $index = $generics["next_index"];
                    continue;
                }
                $current .= $text;
                $index++;
                continue;
            }

            if ($kind === "identifier")
            {
                $current .= $text;
                $index++;
                continue;
            }

            if ($kind === "punctuation" && $text === ".")
            {
                $current .= ".";
                $index++;
                continue;
            }

            // Anything else stops the list.
            break;
        }

        if ($current !== "")
        {
            $names[] = trim($current);
        }

        return ["names" => $names, "next_index" => $index];
    }

    // @spec
    // Liest "<...>" balanciert ab Index $index (auf "<"). Generic-Klammern
    // koennen geschachtelt sein (Map<String, List<T>>). Wir zaehlen "<" und
    // ">" und beachten ">>" als doppeltes ">"-Token (kommt aus Tokenizer-
    // Sicht als ein Token wenn es so im Source steht).
    // Returnt ["text" => "<...>", "next_index" => int] oder null wenn kein
    // matched ">" gefunden wurde.
    // @end-spec
    private static function read_balanced_angles(array $tokens, int $index, int $count): ?array
    {
        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "<")
        {
            return null;
        }

        $depth = 0;
        $out = "";

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "punctuation")
            {
                if ($text === "<")
                {
                    $depth++;
                    $out .= $text;
                    $index++;
                    continue;
                }
                if ($text === ">")
                {
                    $depth--;
                    $out .= $text;
                    $index++;
                    if ($depth === 0)
                    {
                        return ["text" => $out, "next_index" => $index];
                    }
                    continue;
                }
                if ($text === ">>" && $depth >= 2)
                {
                    $depth -= 2;
                    $out .= $text;
                    $index++;
                    if ($depth === 0)
                    {
                        return ["text" => $out, "next_index" => $index];
                    }
                    continue;
                }
                if ($text === ">>>" && $depth >= 3)
                {
                    $depth -= 3;
                    $out .= $text;
                    $index++;
                    if ($depth === 0)
                    {
                        return ["text" => $out, "next_index" => $index];
                    }
                    continue;
                }
                if ($text === ">=")
                {
                    $depth--;
                    $out .= $text;
                    $index++;
                    if ($depth === 0)
                    {
                        return ["text" => $out, "next_index" => $index];
                    }
                    continue;
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace")
            {
                $out .= " ";
                $index++;
                continue;
            }

            if ($kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            $out .= $text;
            $index++;
        }

        return null;
    }

    // @spec
    // Versucht, eine Top-Level-Skript-Deklaration zu lesen:
    //   - "def name(...) {...}" -> script-method
    //   - "def name = expr"     -> script-var
    //   - "Type name(...) {...}" -> script-method
    //   - "Type name = expr"    -> script-var
    //   - "Type name"           -> script-var (ohne Default)
    // Returnt das Symbol-Objekt oder null wenn die Heuristik nicht greift.
    // @end-spec
    private static function try_read_script_decl(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        $start_index = $index;

        $has_def = false;
        if ($tokens[$index][0] === "identifier" && $tokens[$index][1] === "def")
        {
            $has_def = true;
            $index++;
            $index = groovy_parser::skip_ws($tokens, $index, $count);
        }

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $first = groovy_parser::read_type_or_name($tokens, $index, $count);
        if ($first === null)
        {
            return null;
        }

        $first_text       = $first["text"];
        $first_was_simple = $first["is_simple"];
        $after_first      = $first["next_index"];

        $look_after_first = groovy_parser::skip_ws($tokens, $after_first, $count);

        $type_text  = "";
        $name       = "";

        if ($look_after_first < $count
            && $tokens[$look_after_first][0] === "identifier"
            && ! in_array($tokens[$look_after_first][1], [
                "extends", "implements", "throws",
            ], true))
        {
            // first was the type, second is the name
            $type_text = $first_text;
            $name      = $tokens[$look_after_first][1];
            $index     = $look_after_first + 1;
        }
        else if ($has_def && $first_was_simple)
        {
            // def name = ... or def name(...)
            $name  = $first_text;
            $index = $after_first;
        }
        else
        {
            return null;
        }

        $peek_index = groovy_parser::skip_ws($tokens, $index, $count);

        // Method?
        if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === "(")
        {
            $index = $peek_index;
            $params = groovy_parser::read_balanced($tokens, $index, $count, "(", ")");

            $sig = "";
            if ($has_def) { $sig .= "def "; }
            if ($type_text !== "") { $sig .= $type_text . " "; }
            $sig .= $name . $params;

            $index = groovy_parser::skip_ws($tokens, $index, $count);
            $body_members = [];

            if ($index < $count
                && $tokens[$index][0] === "punctuation"
                && $tokens[$index][1] === "{")
            {
                $body = groovy_parser::read_function_body($tokens, $index, $count);
                $body_members = $body["members"];
                $index        = $body["next_index"];
            }

            $sig = groovy_parser::normalise_signature($sig);

            $symbol = [
                "kind"        => "script-method",
                "name"        => $name,
                "signature"   => $sig,
                "annotations" => [],
                "spec"        => $spec,
                "members"     => $body_members,
            ];

            return [
                "symbol"     => $symbol,
                "next_index" => $index,
            ];
        }

        // Var with optional "=" default
        $default = "";
        if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === "=")
        {
            $index = $peek_index + 1;
            $index = groovy_parser::skip_ws($tokens, $index, $count);
            $default = groovy_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";"],
                /* respect_newline = */ true
            );
        }
        else if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === ";")
        {
            $index = $peek_index;
        }
        else if ($peek_index < $count
            && ! ($tokens[$peek_index][0] === "punctuation"
                && ($tokens[$peek_index][1] === "{" || $tokens[$peek_index][1] === ":")))
        {
            // Expect a stand-alone bare declaration (no "=", no body).
            // If we hit something weird, bail out — caller advances by 1.
            // This prevents us from claiming random identifiers as decls.
            $index = $peek_index;
        }
        else
        {
            // Looks like "Type name {...}" — that's a class without keyword,
            // not legal. Bail.
            return null;
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === ";")
        {
            $index++;
        }

        $symbol = [
            "kind"        => "script-var",
            "name"        => $name,
            "type"        => $type_text,
            "default"     => $default,
            "annotations" => [],
            "spec"        => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "next_index" => $index,
        ];
    }

    // @spec
    // Avanciert ueber den Rest eines package/import-Statements bis zum
    // naechsten ";" oder Newline. Whitespace-Token mit "\n" beendet.
    // Pragmatik: wir muessen package/import nicht semantisch verstehen,
    // wir muessen sie nur ueberspringen, damit unser Walker nicht
    // versucht, "org.springframework.web.bind.annotation.RestController"
    // als Type+name(...)-Deklaration zu lesen.
    // @end-spec
    private static function skip_to_statement_end(array $tokens, int $index, int $count): int
    {
        // skip "package" / "import" itself
        $index++;

        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "punctuation" && $t[1] === ";")
            {
                $index++;
                return $index;
            }
            if ($t[0] === "whitespace" && strpos($t[1], "\n") !== false)
            {
                $index++;
                return $index;
            }
            $index++;
        }
        return $index;
    }

    // @spec
    // Liest "(....)" / "{....}" / "[....]" als Source-String inkl. der
    // aeusseren Klammern. Whitespace zu einzelnen Spaces komprimiert.
    // Avanciert $index hinter die schliessende Klammer.
    // @end-spec
    private static function read_balanced(
        array $tokens, int &$index, int $count, string $open, string $close
    ): string
    {
        $out   = "";
        $depth = 0;

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "punctuation")
            {
                if ($text === $open)
                {
                    $depth++;
                    $out .= $text;
                    $index++;
                    continue;
                }
                if ($text === $close)
                {
                    $depth--;
                    $out .= $text;
                    $index++;
                    if ($depth === 0)
                    {
                        return $out;
                    }
                    continue;
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace")
            {
                if ($out !== ""
                    && substr($out, -1) !== " "
                    && substr($out, -1) !== $open)
                {
                    $out .= " ";
                }
                $index++;
                continue;
            }

            if ($kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            $out .= $text;
            $index++;
        }

        return $out;
    }

    // @spec
    // Liest einen Ausdruck-Source-String bis zum naechsten Top-Level-
    // Stop-Zeichen. Respektiert geschachtelte (), [], {} sowie generic
    // angles <...>. Whitespace zu einzelnen Spaces komprimiert.
    // Wenn $respect_newline gesetzt: ein Newline auf Top-Level beendet
    // (grobe ASI-aehnliche Heuristik fuer Properties/Felder ohne ";").
    // @end-spec
    private static function read_expression_until_stop(
        array $tokens, int &$index, int $count,
        array $stop_chars, bool $respect_newline
    ): string
    {
        $depth = 0;
        $angle_depth = 0;
        $out   = "";

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "punctuation")
            {
                if ($depth === 0 && $angle_depth === 0
                    && in_array($text, $stop_chars, true))
                {
                    return rtrim($out);
                }
                if ($text === "(" || $text === "[" || $text === "{")
                {
                    $depth++;
                }
                else if ($text === ")" || $text === "]" || $text === "}")
                {
                    if ($depth === 0)
                    {
                        return rtrim($out);
                    }
                    $depth--;
                }
                else if ($text === "<")
                {
                    $angle_depth++;
                }
                else if ($text === ">")
                {
                    if ($angle_depth > 0) { $angle_depth--; }
                }
                else if ($text === ">>")
                {
                    if ($angle_depth >= 2) { $angle_depth -= 2; }
                    else if ($angle_depth === 1) { $angle_depth = 0; }
                }
                else if ($text === ">>>")
                {
                    if ($angle_depth >= 3) { $angle_depth -= 3; }
                    else { $angle_depth = 0; }
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace")
            {
                $contains_newline = strpos($text, "\n") !== false;
                if ($respect_newline && $depth === 0 && $angle_depth === 0
                    && $contains_newline && $out !== "")
                {
                    return rtrim($out);
                }
                if ($out !== "" && substr($out, -1) !== " ")
                {
                    $out .= " ";
                }
                $index++;
                continue;
            }

            if ($kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            $out .= $text;
            $index++;
        }

        return rtrim($out);
    }

    // @spec
    // Liest den Body einer Funktion/Methode ab "{". Sammelt nur lokale
    // Spec-Bloecke und gibt sie als members[] mit kind=local zurueck.
    // Avanciert hinter das matched "}". Closures `{ -> ... }` werden
    // brace-balanced durchgelaufen — V1 geht NICHT semantisch in
    // Closures rein.
    // @end-spec
    private static function read_function_body(array $tokens, int $index, int $count): array
    {
        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "{")
        {
            return ["members" => [], "next_index" => $index];
        }

        $depth = 1;
        $index++;

        $members = [];

        while ($index < $count && $depth > 0)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            if ($kind === "punctuation" && $text === "{")
            {
                $depth++;
                $index++;
                continue;
            }
            if ($kind === "punctuation" && $text === "}")
            {
                $depth--;
                $index++;
                if ($depth === 0)
                {
                    break;
                }
                continue;
            }

            if (groovy_parser::is_spec_start_token($token))
            {
                $spec_block = groovy_parser::read_spec_block($tokens, $index, $count);

                $first_line = $spec_block["lines"][0] ?? "";
                $name_hint  = mb_substr($first_line, 0, 30);

                $members[] = [
                    "kind" => "local",
                    "name" => $name_hint,
                    "spec" => $spec_block["lines"],
                ];
                continue;
            }

            $index++;
        }

        return ["members" => $members, "next_index" => $index];
    }

    // @spec
    // Avanciert ueber whitespace, comment_line (nicht-Spec) und
    // comment_block. Liefert den neuen Index. NICHT die Aufrufer-Variable
    // veraendern — caller schreibt zurueck.
    // @end-spec
    private static function skip_ws(array $tokens, int $index, int $count): int
    {
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "whitespace" || $t[0] === "comment_block")
            {
                $index++;
                continue;
            }
            if ($t[0] === "comment_line"
                && ! groovy_parser::is_spec_start_token($t)
                && ! groovy_parser::is_spec_end_token($t))
            {
                $index++;
                continue;
            }
            break;
        }
        return $index;
    }

    // @spec
    // Peekt das naechste signifikante Token (nicht whitespace/comment) ab
    // Position $index. Returns null wenn keines mehr.
    // @end-spec
    private static function peek_next_significant(array $tokens, int $index, int $count): ?array
    {
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "whitespace" || $t[0] === "comment_line" || $t[0] === "comment_block")
            {
                $index++;
                continue;
            }
            return $t;
        }
        return null;
    }

    // @spec
    // Komprimiert Whitespace im Signatur-String. Spaces vor "(", ")",
    // "," entfernt; nach "," wieder genau ein Space. Reine String-
    // Manipulation, kein Regex (uebernommen aus js_parser/ts_parser).
    // @end-spec
    private static function normalise_signature(string $sig): string
    {
        $collapsed  = "";
        $len        = strlen($sig);
        $prev_space = false;

        for ($i = 0; $i < $len; $i++)
        {
            $c = $sig[$i];
            if ($c === " " || $c === "\t" || $c === "\n" || $c === "\r")
            {
                if ($prev_space)
                {
                    continue;
                }
                $collapsed .= " ";
                $prev_space = true;
                continue;
            }
            $collapsed .= $c;
            $prev_space = false;
        }

        $out  = "";
        $clen = strlen($collapsed);

        for ($i = 0; $i < $clen; $i++)
        {
            $c    = $collapsed[$i];
            $next = $i + 1 < $clen ? $collapsed[$i + 1] : "";

            if ($c === " " && ($next === "(" || $next === ")" || $next === ","))
            {
                continue;
            }

            if ($c === " " && substr($out, -1) === "(")
            {
                continue;
            }

            $out .= $c;

            if ($c === "," && $next !== "" && $next !== " " && $next !== ")")
            {
                $out .= " ";
            }
        }

        return trim($out);
    }
}
