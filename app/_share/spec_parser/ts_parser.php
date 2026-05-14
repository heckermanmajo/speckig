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

        $tokens = ts_parser::tokenize($source);

        return ts_parser::walk_tokens($tokens);
    }

    // ----- Tokenizer ------------------------------------------------------

    // @spec
    // State-Machine-Tokenizer fuer TypeScript-Subset (JS-Superset).
    // Ausgabe: array von Tokens, jedes Token = [kind, text, line].
    // kinds wie js_parser plus TS-Spezifika:
    //   "whitespace"        - Whitespace
    //   "comment_line"      - // bis Zeilenende
    //   "comment_block"     - /* ... */ (auch JSDoc /** ... */)
    //   "string_single"     - '...' (mit \-Escape)
    //   "string_double"     - "..." (mit \-Escape)
    //   "template_literal"  - `...` (mit ${...}-Interpolation)
    //   "regex_literal"     - /pattern/flags (kontextabhaengig)
    //   "number"            - Zahlen
    //   "identifier"        - Identifier oder Keyword
    //   "punctuation"       - Alles andere; "@" als Decorator-Praefix,
    //                         "?." Optional Chaining, "!" Non-Null-Assertion,
    //                         "<" / ">" Generics (semantisch erst im Walker
    //                         disambiguiert).
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

            // Single-quoted string
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

            // Double-quoted string
            if ($c === "\"")
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
                    if ($cc === "\"")
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
                $tokens[] = ["string_double", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Template literal
            if ($c === "`")
            {
                $start = $i;
                $start_line = $line;
                $i++;
                $brace_depth = 0;
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

                    // Interpolation start: ${
                    if ($brace_depth === 0
                        && $cc === "$"
                        && $i + 1 < $len
                        && $source[$i + 1] === "{")
                    {
                        $i += 2;
                        $brace_depth = 1;
                        // Scan interpolation content with brace counting and
                        // nested-string/template/comment handling so that
                        // ${"..."}, ${// inline}, ${`x`} and nested {} do not
                        // break the tokenizer.
                        while ($i < $len && $brace_depth > 0)
                        {
                            $cc2 = $source[$i];

                            if ($cc2 === "'" || $cc2 === "\"")
                            {
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
                                    }
                                    $i++;
                                }
                                continue;
                            }

                            if ($cc2 === "`")
                            {
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
                                    if ($cc3 === "`")
                                    {
                                        $i++;
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

                    if ($cc === "`")
                    {
                        $i++;
                        break;
                    }

                    if ($cc === "\n")
                    {
                        $line++;
                    }
                    $i++;
                }
                $tokens[] = ["template_literal", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Slash: regex literal or division operator
            if ($c === "/")
            {
                $regex_context = ts_parser::is_regex_context($tokens);

                if ($regex_context)
                {
                    $start = $i;
                    $start_line = $line;
                    $i++;
                    $in_class = false;
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
                        if ($cc === "[")
                        {
                            $in_class = true;
                            $i++;
                            continue;
                        }
                        if ($cc === "]")
                        {
                            $in_class = false;
                            $i++;
                            continue;
                        }
                        if ($cc === "/" && ! $in_class)
                        {
                            $i++;
                            while ($i < $len)
                            {
                                $fc = $source[$i];
                                $is_flag = ($fc >= "a" && $fc <= "z") || ($fc >= "A" && $fc <= "Z");
                                if ( ! $is_flag)
                                {
                                    break;
                                }
                                $i++;
                            }
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
                    $tokens[] = ["regex_literal", substr($source, $start, $i - $start), $start_line];
                    continue;
                }

                $start = $i;
                $start_line = $line;
                $i++;
                if ($i < $len && $source[$i] === "=")
                {
                    $i++;
                }
                $tokens[] = ["punctuation", substr($source, $start, $i - $start), $start_line];
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
                        || $cc === "n"
                        || ($cc >= "a" && $cc <= "f")
                        || ($cc >= "A" && $cc <= "F")
                        || $cc === "+" && substr($source, $i - 1, 1) === "e"
                        || $cc === "-" && substr($source, $i - 1, 1) === "e";
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
                || $c === "$"
                || $c === "#";

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
                        || $cc === "$";
                    if ( ! $is_ident_char)
                    {
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["identifier", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Punctuation: try multi-char operators first
            $three = $i + 2 < $len ? $c . $source[$i + 1] . $source[$i + 2] : "";

            $three_char_ops = ["===", "!==", "**=", "...", ">>>", "<<=", ">>=", "&&=", "||=", "??="];
            $two_char_ops   = [
                "==", "!=", "<=", ">=", "&&", "||", "??",
                "+=", "-=", "*=", "/=", "%=", "&=", "|=", "^=",
                "++", "--", "**", "<<", ">>", "=>", "?.",
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

            // Single-char punctuation (covers @, !, ?, :, <, > etc.).
            $tokens[] = ["punctuation", $c, $line];
            $i++;
        }

        return $tokens;
    }

    // @spec
    // Liefert true, wenn an der aktuellen Tokenizer-Position ein "/" als
    // Regex-Literal-Start zu interpretieren ist (statt als Division).
    // Heuristik wie js_parser::is_regex_context: das letzte signifikante
    // Token bestimmt es. Value-produzierende Tokens (number, string,
    // template, regex, ")"/"]") -> Division. "}" konservativ Regex-Kontext.
    // Identifier: unterscheidet Werte (this, true, ...) von Expression-
    // startenden Keywords (return, typeof, ...).
    // @end-spec
    private static function is_regex_context(array $tokens): bool
    {
        $i = count($tokens) - 1;
        while ($i >= 0)
        {
            $kind = $tokens[$i][0];
            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $i--;
                continue;
            }
            break;
        }

        if ($i < 0)
        {
            return true;
        }

        $prev_kind = $tokens[$i][0];
        $prev_text = $tokens[$i][1];

        if ($prev_kind === "number"
            || $prev_kind === "string_single"
            || $prev_kind === "string_double"
            || $prev_kind === "template_literal"
            || $prev_kind === "regex_literal")
        {
            return false;
        }

        if ($prev_kind === "punctuation")
        {
            if ($prev_text === "++" || $prev_text === "--")
            {
                return false;
            }
            if ($prev_text === ")" || $prev_text === "]")
            {
                return false;
            }
            if ($prev_text === "}")
            {
                return true;
            }
            return true;
        }

        if ($prev_kind === "identifier")
        {
            $regex_keywords = [
                "return", "typeof", "in", "of", "instanceof",
                "new", "delete", "void", "throw", "yield", "await",
                "case", "do", "else",
            ];
            if (in_array($prev_text, $regex_keywords, true))
            {
                return true;
            }
            return false;
        }

        return true;
    }

    // ----- Walker ---------------------------------------------------------

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // sammelt Decorators, dispatcht Symbol-Erkennung (function, class,
    // interface, enum, type-alias, const, let, var).
    // Datei-Header-Spec: der erste Spec-Block, der vor dem ersten Top-Level-
    // Symbol-Keyword (function, class, interface, enum, type, const, let,
    // var, import, export, @<decorator>) steht.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count = count($tokens);

        $first_decl_index = ts_parser::first_decl_index($tokens);

        $file_spec        = [];
        $file_spec_taken  = false;
        $symbols          = [];
        $warnings         = [];
        $pending_spec     = null;
        $pending_decorators = [];
        $depth            = 0;

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
            if ($depth === 0 && ts_parser::is_spec_start_token($token))
            {
                $spec_block = ts_parser::read_spec_block($tokens, $index, $count);

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

            // Decorator at top level: collect into pending decorator list.
            if ($depth === 0 && $kind === "punctuation" && $text === "@")
            {
                $dec = ts_parser::read_decorator($tokens, $index, $count);
                if ($dec !== null)
                {
                    $pending_decorators[] = $dec["decorator"];
                    $index = $dec["next_index"];
                    continue;
                }
                // Could not parse: treat "@" as plain punctuation, advance.
                $index++;
                continue;
            }

            // Skip whitespace, comments, JSDoc between spec and symbol.
            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            if ($depth === 0 && $kind === "identifier")
            {
                // Modifier-Keywords vor der eigentlichen Deklaration.
                $modifiers = [
                    "export", "default", "async", "static", "readonly",
                    "public", "protected", "private", "abstract", "declare",
                    "override",
                ];
                if (in_array($text, $modifiers, true))
                {
                    $index++;
                    continue;
                }

                if ($text === "function")
                {
                    $symbol_result = ts_parser::read_function(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_decorators,
                        /* inside_class = */ false
                    );
                    if ($symbol_result !== null)
                    {
                        $symbols[]    = $symbol_result["symbol"];
                        $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                        $pending_spec = null;
                        $pending_decorators = [];
                        $index        = $symbol_result["next_index"];
                        continue;
                    }
                }

                if ($text === "class")
                {
                    $symbol_result = ts_parser::read_class(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        $pending_decorators
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $pending_decorators = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "interface")
                {
                    $symbol_result = ts_parser::read_interface(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $pending_spec = null;
                    $pending_decorators = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "enum")
                {
                    $symbol_result = ts_parser::read_enum(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $pending_spec = null;
                    $pending_decorators = [];
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "type")
                {
                    // "type" at top-level is a type-alias keyword if followed
                    // by an identifier and "=". Otherwise it's a regular
                    // identifier (e.g. variable named "type"). We peek.
                    $look_index = $index + 1;
                    $look_index = ts_parser::skip_ws($tokens, $look_index, $count);
                    if ($look_index < $count
                        && $tokens[$look_index][0] === "identifier")
                    {
                        $symbol_result = ts_parser::read_type_alias(
                            $tokens, $index, $count,
                            $pending_spec["lines"] ?? []
                        );
                        if ($symbol_result !== null)
                        {
                            $symbols[]    = $symbol_result["symbol"];
                            $pending_spec = null;
                            $pending_decorators = [];
                            $index        = $symbol_result["next_index"];
                            continue;
                        }
                    }
                }

                if ($text === "const" || $text === "let" || $text === "var")
                {
                    $symbol_result = ts_parser::read_var_decl(
                        $tokens, $index, $count, $text,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $pending_spec = null;
                    $pending_decorators = [];
                    $index        = $symbol_result["next_index"];
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
    // (function, class, interface, enum, type, const, let, var, import,
    // export, oder ein Decorator-"@") oder null wenn keiner existiert.
    // Dient zur Heuristik "Datei-Header-Spec".
    // @end-spec
    private static function first_decl_index(array $tokens): ?int
    {
        $count = count($tokens);
        $keys  = [
            "function", "class", "interface", "enum", "type",
            "const", "let", "var", "import", "export",
        ];
        for ($i = 0; $i < $count; $i++)
        {
            $t = $tokens[$i];
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

            if (ts_parser::is_spec_end_token($token))
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
    // Liest einen Decorator ab Index $index, erwartet $index auf "@".
    // Format: "@" identifier ("." identifier)* ( "(" args ")" )?.
    // Returnt ["decorator" => ["name" => string, "args_source" => string|null],
    //          "next_index" => int] oder null bei Fehlschlag.
    // args_source ist null wenn keine Klammern, "" wenn "()", sonst der Inhalt
    // zwischen den Klammern (ohne die Klammern selbst).
    // @end-spec
    private static function read_decorator(array $tokens, int $index, int $count): ?array
    {
        // Skip "@".
        $index++;

        // Optional whitespace tolerated between @ and name (rare, but cheap).
        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $index++;

        // Qualified name: ("." identifier)*
        while ($index < $count)
        {
            // No skip_ws here — qualified decorator names are tight ("@Foo.Bar").
            if ($tokens[$index][0] === "punctuation" && $tokens[$index][1] === ".")
            {
                $index++;
                if ($index < $count && $tokens[$index][0] === "identifier")
                {
                    $name .= "." . $tokens[$index][1];
                    $index++;
                    continue;
                }
                // Trailing dot without ident — bail out.
                break;
            }
            break;
        }

        // Args? Peek next significant.
        $look_index = ts_parser::skip_ws($tokens, $index, $count);

        $args_source = null;

        if ($look_index < $count
            && $tokens[$look_index][0] === "punctuation"
            && $tokens[$look_index][1] === "(")
        {
            // Read balanced "(...)" and strip outer parens for args_source.
            $index = $look_index;
            $balanced = ts_parser::read_balanced($tokens, $index, $count, "(", ")");
            // Strip outer "(" and ")" (always present in balanced output).
            $inner = substr($balanced, 1, strlen($balanced) - 2);
            $args_source = trim($inner);
        }

        return [
            "decorator"  => [
                "name"        => $name,
                "args_source" => $args_source,
            ],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine function-Deklaration. Erwartet $index auf "function".
    // Sammelt Generics (<...>), Params mit Typ-Annotationen, optional
    // Return-Type ": <type>". Body wird auf lokale Spec-Bloecke gescannt.
    // Decorators kommen aus dem Walker-Kontext und werden ans Symbol gehaengt.
    // Returnt null bei anonymen function-Ausdruecken (kein Name).
    // @end-spec
    private static function read_function(
        array $tokens, int $index, int $count,
        array $spec, array $decorators, bool $inside_class
    ): ?array
    {
        $sig = "function";
        $index++;

        // Optional "*" for generator
        $index = ts_parser::skip_ws($tokens, $index, $count);
        if ($index < $count && $tokens[$index][0] === "punctuation" && $tokens[$index][1] === "*")
        {
            $sig .= "*";
            $index++;
        }

        // Name
        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $sig .= " " . $name;
        $index++;

        // Optional generics "<...>"
        $generics_index = ts_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = ts_parser::read_balanced_angles($tokens, $generics_index, $count);
            if ($generics !== null)
            {
                $sig .= $generics["text"];
                $index = $generics["next_index"];
            }
        }

        // Parameter list "(...)"
        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "(")
        {
            return null;
        }

        $params = ts_parser::read_balanced($tokens, $index, $count, "(", ")");
        $sig .= $params;

        // Optional return-type ":" <type>
        $ret = ts_parser::read_optional_return_type($tokens, $index, $count);
        if ($ret !== null)
        {
            $sig .= $ret["text"];
            $index = $ret["next_index"];
        }

        // Body "{ ... }"
        $index = ts_parser::skip_ws($tokens, $index, $count);
        $body_members = [];

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            $body = ts_parser::read_function_body($tokens, $index, $count);
            $body_members = $body["members"];
            $index        = $body["next_index"];
        }

        $sig = ts_parser::normalise_signature($sig);

        $symbol = [
            "kind"       => $inside_class ? "method" : "function",
            "name"       => $name,
            "signature"  => $sig,
            "decorators" => $decorators,
            "spec"       => $spec,
            "members"    => $body_members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine class-Deklaration "class Name [<generics>] [extends Parent]
    // [implements I, J] { ... }". Sammelt Name, extends, implements
    // (jeweils als String-Array). Body via read_class_body.
    // @end-spec
    private static function read_class(
        array $tokens, int $index, int $count, array $spec, array $decorators
    ): array
    {
        // skip "class"
        $index++;

        $index = ts_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        // Optional generics
        $generics_index = ts_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = ts_parser::read_balanced_angles($tokens, $generics_index, $count);
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
            $index = ts_parser::skip_ws($tokens, $index, $count);

            if ($index >= $count) { break; }

            if ($tokens[$index][0] === "identifier" && $tokens[$index][1] === "extends")
            {
                $index++;
                $list = ts_parser::read_type_ref_list($tokens, $index, $count);
                $extends = array_merge($extends, $list["names"]);
                $index   = $list["next_index"];
                continue;
            }

            if ($tokens[$index][0] === "identifier" && $tokens[$index][1] === "implements")
            {
                $index++;
                $list = ts_parser::read_type_ref_list($tokens, $index, $count);
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
            $body = ts_parser::read_class_body($tokens, $index, $count);
            $members  = $body["members"];
            $warnings = $body["warnings"];
            $index    = $body["next_index"];
        }

        $symbol = [
            "kind"       => "class",
            "name"       => $name,
            "extends"    => $extends,
            "implements" => $implements,
            "decorators" => $decorators,
            "spec"       => $spec,
            "members"    => $members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => $warnings,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest interface Name [<generics>] [extends I, J] { ... }. Body wird
    // V1-pragmatisch geschluckt (read_balanced auf "{...}") — Member-
    // Detail-Extraktion fuer Interfaces ist nicht Teil von V1.
    // @end-spec
    private static function read_interface(
        array $tokens, int $index, int $count, array $spec
    ): array
    {
        // skip "interface"
        $index++;
        $index = ts_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        // Optional generics
        $generics_index = ts_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = ts_parser::read_balanced_angles($tokens, $generics_index, $count);
            if ($generics !== null)
            {
                $index = $generics["next_index"];
            }
        }

        $extends = [];

        $index = ts_parser::skip_ws($tokens, $index, $count);
        if ($index < $count
            && $tokens[$index][0] === "identifier"
            && $tokens[$index][1] === "extends")
        {
            $index++;
            $list = ts_parser::read_type_ref_list($tokens, $index, $count);
            $extends = $list["names"];
            $index   = $list["next_index"];
        }

        // Body: skip balanced "{...}".
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "punctuation" && $t[1] === "{")
            {
                break;
            }
            $index++;
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            ts_parser::read_balanced($tokens, $index, $count, "{", "}");
        }

        $symbol = [
            "kind"    => "interface",
            "name"    => $name,
            "extends" => $extends,
            "spec"    => $spec,
            "members" => [],
        ];

        return [
            "symbol"     => $symbol,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest enum Name { ... }. Body wird geschluckt; Member-Detail-
    // Extraktion ist nicht Teil von V1.
    // @end-spec
    private static function read_enum(
        array $tokens, int $index, int $count, array $spec
    ): array
    {
        // skip "enum"
        $index++;
        $index = ts_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        // Body: skip balanced "{...}".
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "punctuation" && $t[1] === "{")
            {
                break;
            }
            $index++;
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            ts_parser::read_balanced($tokens, $index, $count, "{", "}");
        }

        $symbol = [
            "kind"    => "enum",
            "name"    => $name,
            "spec"    => $spec,
            "members" => [],
        ];

        return [
            "symbol"     => $symbol,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest type Alias [<generics>] = <type>. RHS wird als Source-String
    // bis zum naechsten ";" oder Statement-Newline gesammelt. Returnt null
    // wenn keine "="-Form folgt (dann ist "type" wohl ein Identifier).
    // @end-spec
    private static function read_type_alias(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        // skip "type"
        $index++;
        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $index++;

        // Optional generics
        $generics_index = ts_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = ts_parser::read_balanced_angles($tokens, $generics_index, $count);
            if ($generics !== null)
            {
                $index = $generics["next_index"];
            }
        }

        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "=")
        {
            return null;
        }

        $index++;
        $index = ts_parser::skip_ws($tokens, $index, $count);

        $default = ts_parser::read_expression_until_stop(
            $tokens, $index, $count,
            /* stop_chars = */ [";"],
            /* respect_newline = */ true
        );

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === ";")
        {
            $index++;
        }

        $symbol = [
            "kind"    => "type",
            "name"    => $name,
            "default" => $default,
            "spec"    => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest den Body einer Klasse ab "{". Sammelt Spec-Bloecke, Decorators,
    // dispatcht auf Member-Erkennung (Methoden/Konstruktor/Properties/
    // Getter/Setter). Avanciert hinter das matched "}".
    // @end-spec
    private static function read_class_body(array $tokens, int $index, int $count): array
    {
        // expects $index on "{"
        $depth = 1;
        $index++;

        $members            = [];
        $warnings           = [];
        $pending_spec       = null;
        $pending_decorators = [];

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

            if ($depth === 1 && ts_parser::is_spec_start_token($token))
            {
                $spec_block = ts_parser::read_spec_block($tokens, $index, $count);
                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $spec_block;
                continue;
            }

            // Decorator at member level.
            if ($depth === 1 && $kind === "punctuation" && $text === "@")
            {
                $dec = ts_parser::read_decorator($tokens, $index, $count);
                if ($dec !== null)
                {
                    $pending_decorators[] = $dec["decorator"];
                    $index = $dec["next_index"];
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

            // Optional separator tokens.
            if ($depth === 1 && $kind === "punctuation" && ($text === ";" || $text === ","))
            {
                $index++;
                continue;
            }

            if ($depth === 1)
            {
                $member = ts_parser::try_read_class_member(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? [],
                    $pending_decorators
                );

                if ($member !== null)
                {
                    $members[]          = $member["symbol"];
                    $warnings           = array_merge($warnings, $member["warnings"]);
                    $pending_spec       = null;
                    $pending_decorators = [];
                    $index              = $member["next_index"];
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
    // Versucht, ab $index einen Klassen-Member zu lesen: Methode (inkl.
    // async/static/get/set/generator/async), Konstruktor, Property/Field
    // (mit optionalem Type ": T", optionalem Default "= value"; auch
    // "static name: T = v" und "#privateName = ...").
    // Returnt null wenn nichts geparst werden konnte.
    // @end-spec
    private static function try_read_class_member(
        array $tokens, int $index, int $count, array $spec, array $decorators
    ): ?array
    {
        $modifiers = [];
        $is_getter = false;
        $is_setter = false;
        $is_async  = false;
        $is_generator = false;

        $modifier_idents = [
            "static", "async", "readonly",
            "public", "protected", "private", "abstract", "override", "declare",
        ];

        // Collect modifiers + get/set in any reasonable order.
        while ($index < $count)
        {
            $t = $tokens[$index];
            if ($t[0] === "whitespace" || $t[0] === "comment_line" || $t[0] === "comment_block")
            {
                $index++;
                continue;
            }
            if ($t[0] === "identifier")
            {
                if (in_array($t[1], $modifier_idents, true)
                    && ! in_array($t[1], $modifiers, true))
                {
                    $look = ts_parser::peek_next_significant($tokens, $index + 1, $count);
                    if ($look !== null
                        && ($look[0] === "identifier"
                            || ($look[0] === "punctuation"
                                && ($look[1] === "*" || $look[1] === "#"))))
                    {
                        if ($t[1] === "async") { $is_async = true; }
                        $modifiers[] = $t[1];
                        $index++;
                        continue;
                    }
                    break;
                }
                if (($t[1] === "get" || $t[1] === "set") && ! $is_getter && ! $is_setter)
                {
                    $look = ts_parser::peek_next_significant($tokens, $index + 1, $count);
                    if ($look !== null && $look[0] === "identifier")
                    {
                        if ($t[1] === "get") { $is_getter = true; }
                        else                 { $is_setter = true; }
                        $modifiers[] = $t[1];
                        $index++;
                        continue;
                    }
                    break;
                }
                break;
            }
            break;
        }

        // Generator "*"
        $index = ts_parser::skip_ws($tokens, $index, $count);
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "*")
        {
            $is_generator = true;
            $modifiers[]  = "*";
            $index++;
        }

        // Name (or "constructor" keyword treated as name).
        $index = ts_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $index++;

        $is_constructor = ($name === "constructor");

        // Optional "?" / "!" after property name (TS optional / definite).
        $index = ts_parser::skip_ws($tokens, $index, $count);
        $name_suffix = "";
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && ($tokens[$index][1] === "?" || $tokens[$index][1] === "!"))
        {
            $name_suffix = $tokens[$index][1];
            $index++;
        }

        // Optional generics on method "<T>"
        $generics_text = "";
        $generics_index = ts_parser::skip_ws($tokens, $index, $count);
        if ($generics_index < $count
            && $tokens[$generics_index][0] === "punctuation"
            && $tokens[$generics_index][1] === "<")
        {
            $generics = ts_parser::read_balanced_angles($tokens, $generics_index, $count);
            if ($generics !== null)
            {
                $generics_text = $generics["text"];
                $index = $generics["next_index"];
            }
        }

        // Decide method vs property: peek "(".
        $peek_index = ts_parser::skip_ws($tokens, $index, $count);

        if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === "(")
        {
            $index = $peek_index;
            $params = ts_parser::read_balanced($tokens, $index, $count, "(", ")");

            // Build signature.
            $sig = "";
            foreach ($modifiers as $m)
            {
                if ($m === "*")
                {
                    $sig = rtrim($sig);
                    $sig .= "*";
                    continue;
                }
                $sig .= $m . " ";
            }
            $sig .= $name . $name_suffix . $generics_text . $params;

            // Optional return type ": T"
            $ret = ts_parser::read_optional_return_type($tokens, $index, $count);
            if ($ret !== null)
            {
                $sig .= $ret["text"];
                $index = $ret["next_index"];
            }

            // Body
            $index = ts_parser::skip_ws($tokens, $index, $count);
            $body_members = [];

            if ($index < $count
                && $tokens[$index][0] === "punctuation"
                && $tokens[$index][1] === "{")
            {
                $body = ts_parser::read_function_body($tokens, $index, $count);
                $body_members = $body["members"];
                $index        = $body["next_index"];
            }

            $sig = ts_parser::normalise_signature($sig);

            $member_kind = "method";
            if ($is_constructor) { $member_kind = "constructor"; }
            else if ($is_getter) { $member_kind = "getter"; }
            else if ($is_setter) { $member_kind = "setter"; }

            $symbol = [
                "kind"       => $member_kind,
                "name"       => $name,
                "signature"  => $sig,
                "decorators" => $decorators,
                "spec"       => $spec,
                "members"    => $body_members,
            ];

            return [
                "symbol"     => $symbol,
                "warnings"   => [],
                "next_index" => $index,
            ];
        }

        // Property/Field: optional ": T", optional "= value".
        $type_text = "";
        if ($peek_index < $count
            && $tokens[$peek_index][0] === "punctuation"
            && $tokens[$peek_index][1] === ":")
        {
            // Move to ":" and read type until a property terminator.
            $index = $peek_index + 1;
            $type_text = ts_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ ["=", ";", "}", ","],
                /* respect_newline = */ true
            );
        }
        else
        {
            $index = $peek_index;
        }

        $default = "";
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "=")
        {
            $index++;
            $index = ts_parser::skip_ws($tokens, $index, $count);
            $default = ts_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";", "}", ","],
                /* respect_newline = */ true
            );
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && ($tokens[$index][1] === ";" || $tokens[$index][1] === ","))
        {
            $index++;
        }

        $symbol = [
            "kind"       => "property",
            "name"       => $name,
            "type"       => $type_text,
            "default"    => $default,
            "decorators" => $decorators,
            "spec"       => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Top-Level-Variable-Deklaration: const|let|var NAME [: T]
    // [= value];. Default und Type sind beide optional. Wir behandeln nur
    // den ersten Identifier (kein destructuring). Default wird wie gehabt
    // bis zum ";" oder Top-Level-Newline (ASI-Heuristik) gelesen.
    // @end-spec
    private static function read_var_decl(
        array $tokens, int $index, int $count, string $decl_kind, array $spec
    ): array
    {
        // skip decl keyword
        $index++;
        $index = ts_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        // Optional ": T"
        $type_text = "";
        $look = ts_parser::skip_ws($tokens, $index, $count);
        if ($look < $count
            && $tokens[$look][0] === "punctuation"
            && $tokens[$look][1] === ":")
        {
            $index = $look + 1;
            $type_text = ts_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ ["=", ";"],
                /* respect_newline = */ true
            );
        }
        else
        {
            $index = $look;
        }

        $default = "";

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "=")
        {
            $index++;
            $index = ts_parser::skip_ws($tokens, $index, $count);
            $default = ts_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";"],
                /* respect_newline = */ true
            );
        }

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === ";")
        {
            $index++;
        }

        $symbol = [
            "kind"    => $decl_kind,
            "name"    => $name,
            "type"    => $type_text,
            "default" => $default,
            "spec"    => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Liste von Type-References (Identifier-Pfade A.B mit
    // optionalen <generics>) getrennt durch ",". Stoppt vor "{" oder vor
    // den Keywords "implements"/"extends". Returnt
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
                && ($text === "implements" || $text === "extends"))
            {
                break;
            }

            if ($kind === "punctuation" && $text === "<")
            {
                $generics = ts_parser::read_balanced_angles($tokens, $index, $count);
                if ($generics !== null)
                {
                    $current .= $generics["text"];
                    $index = $generics["next_index"];
                    continue;
                }
                // Fallback: treat as char.
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
    // koennen geschachtelt sein (Map<string, Array<T>>) und enthalten
    // Punctuation, Identifier, Strings, etc. Wir zaehlen "<" und ">" und
    // beachten ">>", ">>>" als doppeltes/dreifaches ">"-Token aus Tokenizer-
    // Sicht (>= ist Vergleichs-Op und kommt im Generic-Kontext nicht vor —
    // in V1 ignorieren wir den theoretischen "<T extends U>"-vs-Vergleich-
    // Ambiguity und vertrauen darauf, dass der Aufrufer nur an einer
    // Position aufruft, wo "<" tatsaechlich Generic ist).
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

        $start_index = $index;
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
                // Decompose multi-char shifts into individual ">".
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
                // ">=" inside a generic arg list is unusual; treat as ">"+"=".
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
                // Stop signals: a top-level (depth==1 means we've only consumed
                // the opening "<") encounter of "(", "{", ";", "=>" before any
                // closing ">" suggests we mis-classified "<" as a generic.
                // V1 just continues and accumulates — caller is responsible
                // for context.
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

        // No matching ">" — bail out, leave caller untouched.
        return null;
    }

    // @spec
    // Liest optional ": <type>" nach einer Param-Liste oder Property-
    // Deklaration. Stop-Zeichen: "{", "=", ";", ",", ")". Whitespace im
    // Type wird komprimiert. Returnt null wenn an $index kein ":" steht.
    // Inkludiert das ":" und einen Leerzeichen-Praefix im Output.
    // @end-spec
    private static function read_optional_return_type(
        array $tokens, int &$outer_index, int $count
    ): ?array
    {
        $look = ts_parser::skip_ws($tokens, $outer_index, $count);

        if ($look >= $count
            || $tokens[$look][0] !== "punctuation"
            || $tokens[$look][1] !== ":")
        {
            return null;
        }

        $index = $look + 1;
        $body = ts_parser::read_expression_until_stop(
            $tokens, $index, $count,
            /* stop_chars = */ ["{", "=", ";", ","],
            /* respect_newline = */ false
        );

        return [
            "text"       => ": " . $body,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest einen Ausdruck-Source-String bis zum naechsten Top-Level-
    // Stop-Zeichen. Respektiert geschachtelte (), [], {} sowie generic
    // angles <...>. Whitespace zu einzelnen Spaces komprimiert.
    // Wenn $respect_newline gesetzt: ein Newline auf Top-Level beendet
    // (grobe ASI-Heuristik fuer Property-Felder ohne ";").
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
                        // Closing brace we don't own — stop.
                        return rtrim($out);
                    }
                    $depth--;
                }
                else if ($text === "<")
                {
                    // Don't treat as generic open here; only used as part of
                    // type-expression syntax. Track so >/>> don't unbalance.
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
    // Liest den Body einer Funktion/Methode ab "{". Sammelt nur lokale
    // Spec-Bloecke und gibt sie als members[] mit kind=local zurueck.
    // Avanciert hinter das matched "}".
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

            if (ts_parser::is_spec_start_token($token))
            {
                $spec_block = ts_parser::read_spec_block($tokens, $index, $count);

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
                && ! ts_parser::is_spec_start_token($t)
                && ! ts_parser::is_spec_end_token($t))
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
    // ",", ":" entfernt; nach "," und ":" wieder genau ein Space.
    // Reine String-Manipulation, kein Regex (uebernommen aus js_parser).
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

            if ($c === " " && ($next === "(" || $next === ")" || $next === "," || $next === ":"))
            {
                continue;
            }

            if ($c === " " && substr($out, -1) === "(")
            {
                continue;
            }

            $out .= $c;

            if (($c === "," || $c === ":") && $next !== "" && $next !== " "
                && $next !== ")" && $next !== ":")
            {
                $out .= " ";
            }
        }

        return trim($out);
    }
}
