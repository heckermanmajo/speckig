<?php

declare(strict_types=1);

// @spec
// JS-Spec-Parser. Liest // @spec ... // @end-spec-Bloecke aus JS-Dateien
// und ordnet sie dem direkt darauffolgenden Symbol zu (function, class,
// const, let, var, Klassen-Method/Getter/Setter, Klassen-Field, oder
// lokale Stelle in einer Methode). Schema und Beispiele in
// app/_share/spec_parser/README.md.
//
// Implementierung: PHP-seitiger State-Machine-Tokenizer (Decision M005/0001),
// kein Regex (Decision 0006). Der Tokenizer kennt Comments (line/block),
// Strings (single/double/template), Regex-Literals (mit Kontext-Erkennung
// gegenueber Division), Identifiers, Punctuation. Damit ist garantiert,
// dass Spec-Marker in String-, Template-, Regex- und Block-Comment-
// Inhalten nicht als Marker erkannt werden.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// @end-spec
class js_parser
{

    // @spec
    // Parst eine JS-Datei und liefert das Schema-Array
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

        $tokens = js_parser::tokenize($source);

        return js_parser::walk_tokens($tokens);
    }

    // ----- Tokenizer ------------------------------------------------------

    // @spec
    // State-Machine-Tokenizer fuer JavaScript-Subset.
    // Ausgabe: array von Tokens, jedes Token = [kind, text, line].
    // kinds:
    //   "whitespace"        - Whitespace (Space, Tab, Newline)
    //   "comment_line"      - // bis Zeilenende
    //   "comment_block"     - /* ... */
    //   "string_single"     - '...' (mit \-Escape)
    //   "string_double"     - "..." (mit \-Escape)
    //   "template_literal"  - `...` (mit ${...}-Interpolation, V1: nur
    //                         als Block tokenisiert; Inhalt wird nicht
    //                         als Code geparst, ${"..."} und ${// inline}
    //                         werden korrekt durchgereicht, weil ihre
    //                         Strings/Comments innerhalb der Tilde-Tilde-
    //                         Klammer-Verschachtelung behandelt werden)
    //   "regex_literal"     - /pattern/flags (kontextabhaengig)
    //   "number"            - Zahlen (grobe Erkennung)
    //   "identifier"        - Identifier oder Keyword
    //   "punctuation"       - Alles andere (Operatoren, Klammern, ...)
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
                        // skip escape (and possible \n)
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
                        // unterminated single-quote string (line break);
                        // bail out — treat as end of string.
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
                        // Now we are inside ${ ... }.
                        // V1-Strategie: ueberspringe Inhalt zeichenweise und
                        // behandle nested {}, Strings und Comments minimal
                        // korrekt, damit ${"..."}, ${// inline}, ${`x`} und
                        // verschachtelte {} den Tokenizer nicht brechen.
                        while ($i < $len && $brace_depth > 0)
                        {
                            $cc2 = $source[$i];

                            // Nested strings inside interpolation
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

                            // Nested template (we recurse-ish: just count its braces)
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

                            // Comments inside interpolation
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
                $regex_context = js_parser::is_regex_context($tokens);

                if ($regex_context)
                {
                    $start = $i;
                    $start_line = $line;
                    $i++;
                    $in_class = false; // inside [ ... ]
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
                            // flags
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
                            // unterminated regex — bail out
                            $line++;
                            $i++;
                            break;
                        }
                        $i++;
                    }
                    $tokens[] = ["regex_literal", substr($source, $start, $i - $start), $start_line];
                    continue;
                }

                // Division operator: emit as punctuation. Could be `/`, `/=`.
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

            // Number (rough): digit start
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
                        || $cc === "n" // BigInt
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
                || $c === "#"; // private class fields

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

            // Single-char punctuation (any non-alnum left)
            $tokens[] = ["punctuation", $c, $line];
            $i++;
        }

        return $tokens;
    }

    // @spec
    // Liefert true, wenn an der aktuellen Tokenizer-Position ein "/" als
    // Regex-Literal-Start zu interpretieren ist (statt als Division).
    // Heuristik: das letzte signifikante (nicht-Whitespace, nicht-Comment)
    // Token bestimmt es. Identifier-Wert (z.B. "a", "true"), Zahl, String,
    // Template, Regex, ")" und "]" heissen Division. "}" wird konservativ
    // als Regex-Kontext gewertet (Block-Ende ist der haeufigere Fall in
    // realem Code; Object-Literal-Division ist selten). Sonstige
    // Punctuation und expression-startende Keywords (return, typeof, in,
    // of, instanceof, new, delete, void, throw, yield, await, case, do,
    // else) heissen Regex. Am Datei-Anfang: Regex.
    // @end-spec
    private static function is_regex_context(array $tokens): bool
    {
        // Find last significant token.
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
            // start of file
            return true;
        }

        $prev_kind = $tokens[$i][0];
        $prev_text = $tokens[$i][1];

        // Value-producing tokens -> division
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
            // Postfix ++/-- after value -> division
            if ($prev_text === "++" || $prev_text === "--")
            {
                return false;
            }
            // ) and ] -> division
            if ($prev_text === ")" || $prev_text === "]")
            {
                return false;
            }
            // } -> pragmatic: treat as regex context (block-end case)
            if ($prev_text === "}")
            {
                return true;
            }
            // Everything else (=, (, ,, ;, :, ?, +, -, *, %, !, etc.) -> regex
            return true;
        }

        if ($prev_kind === "identifier")
        {
            // Keywords that force regex context next.
            $regex_keywords = [
                "return", "typeof", "in", "of", "instanceof",
                "new", "delete", "void", "throw", "yield", "await",
                "case", "do", "else",
            ];
            if (in_array($prev_text, $regex_keywords, true))
            {
                return true;
            }
            // Any other identifier (variable, this, true, false, null,
            // super) -> division.
            return false;
        }

        return true;
    }

    // ----- Walker ---------------------------------------------------------

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // dispatcht Symbol-Erkennung (function, class, const, let, var).
    // Datei-Header-Spec: der erste Spec-Block, der vor dem ersten
    // Top-Level-Deklarations-Keyword (function, class, const, let, var,
    // import, export) steht.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count = count($tokens);

        $first_decl_index = js_parser::first_decl_index($tokens);

        $file_spec        = [];
        $file_spec_taken  = false;
        $symbols          = [];
        $warnings         = [];
        $pending_spec     = null;
        $depth            = 0; // nesting depth: ( [ { all bump

        $index = 0;

        while ($index < $count)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            // Track depth so we only recognise top-level declarations.
            // (function () { ... })() should not bleed inner function
            // declarations into the top-level symbol list.
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

            // Spec-Start (depth-aware: only top-level spec blocks count
            // as file-spec or pending-symbol-spec).
            if ($depth === 0 && js_parser::is_spec_start_token($token))
            {
                $spec_block = js_parser::read_spec_block($tokens, $index, $count);

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

            // Skip whitespace, comments, JSDoc between spec and symbol.
            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            // Only interpret declaration keywords at top level.
            if ($depth === 0 && $kind === "identifier")
            {
                // Modifier-Keywords vor der eigentlichen Deklaration:
                // export, default, async. Wir schlucken sie weg, damit
                // die folgende decl gefunden wird.
                if ($text === "export" || $text === "async" || $text === "default")
                {
                    $index++;
                    continue;
                }

                if ($text === "function")
                {
                    $symbol_result = js_parser::read_function(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        /* inside_class = */ false
                    );
                    if ($symbol_result !== null)
                    {
                        $symbols[]    = $symbol_result["symbol"];
                        $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                        $pending_spec = null;
                        $index        = $symbol_result["next_index"];
                        continue;
                    }
                }

                if ($text === "class")
                {
                    $symbol_result = js_parser::read_class(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $warnings     = array_merge($warnings, $symbol_result["warnings"]);
                    $pending_spec = null;
                    $index        = $symbol_result["next_index"];
                    continue;
                }

                if ($text === "const" || $text === "let" || $text === "var")
                {
                    $symbol_result = js_parser::read_var_decl(
                        $tokens, $index, $count, $text,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $symbol_result["symbol"];
                    $pending_spec = null;
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
    // Liefert den Token-Index des ersten Top-Level-Deklarations-Keywords
    // (function, class, const, let, var, import, export) oder null wenn
    // keines existiert. Dient zur Heuristik "Datei-Header-Spec".
    // @end-spec
    private static function first_decl_index(array $tokens): ?int
    {
        $count = count($tokens);
        $keys  = ["function", "class", "const", "let", "var", "import", "export"];
        for ($i = 0; $i < $count; $i++)
        {
            $t = $tokens[$i];
            if ($t[0] === "identifier" && in_array($t[1], $keys, true))
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
    // Liest einen kompletten Spec-Block ab Index $index (der auf einem
    // comment_line-Spec-Start-Token steht). Returnt:
    //   ["lines" => [...], "start_line" => N, "start_index" => I]
    // und avanciert $index hinter das @end-spec-Token. Zwischen Start und
    // Ende werden nur comment_line-Token (Inhalt) und whitespace akzeptiert;
    // alles andere schliesst den Block (dann fehlt @end-spec — Walker behandelt
    // das als dangling, weil $pending_spec ggf. liegen bleibt).
    // @end-spec
    private static function read_spec_block(array $tokens, int &$index, int $count): array
    {
        $start_index = $index;
        $start_token = $tokens[$index];
        $start_line  = $start_token[2];

        $lines = [];

        // First token: @spec marker, possibly with inline content.
        $first_text = rtrim($start_token[1]);
        $first_body = ltrim(substr($first_text, 2)); // strip "//"
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

            if (js_parser::is_spec_end_token($token))
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
                // strip exactly one leading space if present
                if (strlen($body) > 0 && $body[0] === " ")
                {
                    $body = substr($body, 1);
                }
                $lines[] = $body;
                $index++;
                continue;
            }

            // Anything else: block did not close with @end-spec. Stop here;
            // caller will pick up the current token next. The block becomes
            // pending; if no symbol follows, walker emits dangling warning.
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
    // Liest eine function-Deklaration. Erwartet $index auf dem
    // "function"-Identifier-Token (kann auch nach "async" / "export"
    // konsumiert werden — die werden vom Walker davor weggeschluckt,
    // siehe walk_tokens). Sammelt Name + Signatur-Source-String + Body.
    // Returnt null bei anonymen function-Ausdruecken (kein Name).
    // @end-spec
    private static function read_function(
        array $tokens, int $index, int $count,
        array $spec, bool $inside_class
    ): ?array
    {
        $sig = "function";
        $index++;

        // Optional "*" for generator
        $index = js_parser::skip_ws($tokens, $index, $count);
        if ($index < $count && $tokens[$index][0] === "punctuation" && $tokens[$index][1] === "*")
        {
            $sig .= "*";
            $index++;
        }

        // Name
        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            // anonymous function expression — not a declared symbol.
            return null;
        }

        $name = $tokens[$index][1];
        $sig .= " " . $name;
        $index++;

        // Parameter list "(...)"
        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "(")
        {
            return null;
        }

        $params = js_parser::read_balanced($tokens, $index, $count, "(", ")");
        $sig .= $params;

        // Body "{ ... }"
        $index = js_parser::skip_ws($tokens, $index, $count);
        $body_members = [];

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            $body = js_parser::read_function_body($tokens, $index, $count);
            $body_members = $body["members"];
            $index        = $body["next_index"];
        }

        $sig = js_parser::normalise_signature($sig);

        $symbol = [
            "kind"      => $inside_class ? "method" : "function",
            "name"      => $name,
            "signature" => $sig,
            "spec"      => $spec,
            "members"   => $body_members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Klassen-Deklaration "class Name [extends Parent] { ... }".
    // Sammelt Name, extends-Liste (max. ein Eltern-Class-Identifier-Pfad),
    // und Member (methods, properties, getters, setters, local-spec in
    // method bodies).
    // @end-spec
    private static function read_class(
        array $tokens, int $index, int $count, array $spec
    ): array
    {
        // skip "class"
        $index++;

        $index = js_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        $extends = [];

        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index < $count
            && $tokens[$index][0] === "identifier"
            && $tokens[$index][1] === "extends")
        {
            $index++;
            $index = js_parser::skip_ws($tokens, $index, $count);
            // Read identifier path (Foo, Foo.Bar)
            $parent = "";
            while ($index < $count)
            {
                $t = $tokens[$index];
                if ($t[0] === "identifier")
                {
                    $parent .= $t[1];
                    $index++;
                    continue;
                }
                if ($t[0] === "punctuation" && $t[1] === ".")
                {
                    $parent .= ".";
                    $index++;
                    continue;
                }
                if ($t[0] === "whitespace")
                {
                    $index++;
                    break;
                }
                if ($t[0] === "punctuation" && $t[1] === "{")
                {
                    break;
                }
                $index++;
                break;
            }
            if ($parent !== "")
            {
                $extends[] = $parent;
            }
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
            $body = js_parser::read_class_body($tokens, $index, $count);
            $members  = $body["members"];
            $warnings = $body["warnings"];
            $index    = $body["next_index"];
        }

        $symbol = [
            "kind"    => "class",
            "name"    => $name,
            "extends" => $extends,
            "spec"    => $spec,
            "members" => $members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => $warnings,
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest den Body einer Klasse ab "{". Sammelt Spec-Bloecke und
    // ordnet sie dem darauffolgenden Member zu (method, getter, setter,
    // property). Avanciert hinter das matched "}".
    // @end-spec
    private static function read_class_body(array $tokens, int $index, int $count): array
    {
        // expects $index on "{"
        $depth = 1;
        $index++;

        $members      = [];
        $warnings     = [];
        $pending_spec = null;

        while ($index < $count && $depth > 0)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            if ($kind === "punctuation" && $text === "{")
            {
                // Shouldn't usually happen at member-list level; track depth.
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

            if (js_parser::is_spec_start_token($token))
            {
                $spec_block = js_parser::read_spec_block($tokens, $index, $count);
                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $spec_block;
                continue;
            }

            if ($kind === "whitespace" || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            // Optional semicolons between members
            if ($kind === "punctuation" && $text === ";")
            {
                $index++;
                continue;
            }

            // Member parse
            $member = js_parser::try_read_class_member(
                $tokens, $index, $count,
                $pending_spec["lines"] ?? []
            );

            if ($member !== null)
            {
                $members[]    = $member["symbol"];
                $warnings     = array_merge($warnings, $member["warnings"]);
                $pending_spec = null;
                $index        = $member["next_index"];
                continue;
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
    // async / static / get / set / generator), Property/Field (name =
    // value;  oder static name = value;  oder #private = value;).
    // Returnt null wenn nichts geparst werden konnte (Walker macht dann
    // einen Schritt weiter).
    // @end-spec
    private static function try_read_class_member(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        $modifiers = [];
        $is_getter = false;
        $is_setter = false;
        $is_static = false;
        $is_async  = false;
        $is_generator = false;

        // Save start position for fallback.
        $start_index = $index;

        // Collect modifiers: static, async, get, set. Loop because order
        // can vary (static async, async static is invalid, get static is
        // invalid, but we accept generously).
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
                if ($t[1] === "static" && ! $is_static)
                {
                    // peek to ensure it's a modifier (not a field named "static")
                    // — if next non-ws token is another identifier or # or get/set,
                    // it's a modifier.
                    $look = js_parser::peek_next_significant($tokens, $index + 1, $count);
                    if ($look !== null
                        && ($look[0] === "identifier"
                            || ($look[0] === "punctuation" && ($look[1] === "*" || $look[1] === "#"))))
                    {
                        $is_static = true;
                        $modifiers[] = "static";
                        $index++;
                        continue;
                    }
                    break;
                }
                if ($t[1] === "async" && ! $is_async)
                {
                    $look = js_parser::peek_next_significant($tokens, $index + 1, $count);
                    if ($look !== null
                        && ($look[0] === "identifier"
                            || ($look[0] === "punctuation" && ($look[1] === "*" || $look[1] === "#"))))
                    {
                        $is_async = true;
                        $modifiers[] = "async";
                        $index++;
                        continue;
                    }
                    break;
                }
                if (($t[1] === "get" || $t[1] === "set") && ! $is_getter && ! $is_setter)
                {
                    // peek: must be followed by an identifier (the property name)
                    $look = js_parser::peek_next_significant($tokens, $index + 1, $count);
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
        $index = js_parser::skip_ws($tokens, $index, $count);
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "*")
        {
            $is_generator = true;
            $modifiers[]  = "*";
            $index++;
        }

        // Name. Either identifier, or #private-identifier-tail. We treat
        // "#" specially because our tokenizer emits "#" as identifier-start
        // (we configured "#" as ident-start). So a private name comes as
        // single identifier token starting with "#".
        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $name_index = $index;
        $index++;

        // What follows decides: "(" -> method, "=" or ";" or "}" or newline -> property.
        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "(")
        {
            // Method (or getter/setter)
            $params = js_parser::read_balanced($tokens, $index, $count, "(", ")");

            // Build signature.
            $sig = "";
            foreach ($modifiers as $m)
            {
                if ($m === "*")
                {
                    // glue without space
                    $sig = rtrim($sig);
                    $sig .= "*";
                    continue;
                }
                $sig .= $m . " ";
            }
            $sig .= $name . $params;

            // Body
            $index = js_parser::skip_ws($tokens, $index, $count);
            $body_members = [];

            if ($index < $count
                && $tokens[$index][0] === "punctuation"
                && $tokens[$index][1] === "{")
            {
                $body = js_parser::read_function_body($tokens, $index, $count);
                $body_members = $body["members"];
                $index        = $body["next_index"];
            }

            $sig = js_parser::normalise_signature($sig);

            $member_kind = "method";
            if ($is_getter) { $member_kind = "getter"; }
            else if ($is_setter) { $member_kind = "setter"; }

            $symbol = [
                "kind"      => $member_kind,
                "name"      => $name,
                "signature" => $sig,
                "spec"      => $spec,
                "members"   => $body_members,
            ];

            return [
                "symbol"     => $symbol,
                "warnings"   => [],
                "next_index" => $index,
            ];
        }

        // Property/Field. Optional "= value;".
        $default = "";

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "=")
        {
            $index++;
            $index = js_parser::skip_ws($tokens, $index, $count);
            $default = js_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";", "}"],
                /* respect_newline = */ true
            );
        }

        // Consume optional trailing ";"
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === ";")
        {
            $index++;
        }

        $symbol = [
            "kind"    => "property",
            "name"    => $name,
            "default" => $default,
            "spec"    => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Top-Level-Variable-Deklaration: const|let|var NAME = ...;
    // Wir behandeln nur den ersten Identifier (kein destructuring,
    // kein multi-decl per Komma — V1-Pragma). Default-Source-String wird
    // wie bei properties bis zum naechsten ";" (oder Statement-Ende per
    // Newline-Heuristik) gelesen.
    // @end-spec
    private static function read_var_decl(
        array $tokens, int $index, int $count, string $decl_kind, array $spec
    ): array
    {
        // skip decl keyword
        $index++;
        $index = js_parser::skip_ws($tokens, $index, $count);

        $name = "";
        if ($index < $count && $tokens[$index][0] === "identifier")
        {
            $name = $tokens[$index][1];
            $index++;
        }

        $default = "";

        $index = js_parser::skip_ws($tokens, $index, $count);

        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "=")
        {
            $index++;
            $index = js_parser::skip_ws($tokens, $index, $count);
            $default = js_parser::read_expression_until_stop(
                $tokens, $index, $count,
                /* stop_chars = */ [";"],
                /* respect_newline = */ true
            );
        }

        // Consume optional trailing ";"
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === ";")
        {
            $index++;
        }

        $symbol = [
            "kind"    => $decl_kind,
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
    // Liest einen Ausdruck-Source-String bis zum naechsten Top-Level-
    // Stop-Zeichen (z.B. ";" oder "}"). Respektiert geschachtelte (), [], {}.
    // Wenn $respect_newline gesetzt: ein Newline auf Top-Level (Tiefe 0),
    // der nach einem nicht-Operator-Token kommt, beendet ebenfalls
    // (grobe ASI-Heuristik fuer Klassen-Felder ohne ";"). Whitespace zu
    // einzelnen Spaces komprimiert.
    // @end-spec
    private static function read_expression_until_stop(
        array $tokens, int &$index, int $count,
        array $stop_chars, bool $respect_newline
    ): string
    {
        $depth = 0;
        $out   = "";

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "punctuation")
            {
                if ($depth === 0 && in_array($text, $stop_chars, true))
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
                        // closing brace we don't own — stop here.
                        return rtrim($out);
                    }
                    $depth--;
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace")
            {
                $contains_newline = strpos($text, "\n") !== false;
                if ($respect_newline && $depth === 0 && $contains_newline && $out !== "")
                {
                    // ASI-ish: stop on top-level newline when we've already
                    // produced something.
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

            if (js_parser::is_spec_start_token($token))
            {
                $spec_block = js_parser::read_spec_block($tokens, $index, $count);

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
    // Avanciert $index ueber whitespace, comment_line (nicht-Spec) und
    // comment_block. Liefert den neuen Index.
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
                && ! js_parser::is_spec_start_token($t)
                && ! js_parser::is_spec_end_token($t))
            {
                $index++;
                continue;
            }
            break;
        }
        return $index;
    }

    // @spec
    // Peekt das naechste signifikante Token (nicht whitespace/comment)
    // ab Position $index. Returns null wenn keines mehr.
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
    // Reine String-Manipulation, kein Regex (uebernommen aus php_parser).
    // @end-spec
    private static function normalise_signature(string $sig): string
    {
        // Pass 1: collapse whitespace runs to single spaces.
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

        // Pass 2: rewrite spacing.
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
