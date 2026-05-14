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
// r"..." / '...'), Block-Kommentare (#[ ... ]#, nestable), Doc-Kommentare
// (## ...) und normale Zeilen-Kommentare (# ...). Damit ist garantiert,
// dass Spec-Marker in Strings, Block-Kommentaren und Raw-Strings nicht als
// Marker erkannt werden.
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

        $tokens = nim_parser::tokenize($source);

        return nim_parser::walk_tokens($tokens);
    }

    // ----- Tokenizer ------------------------------------------------------

    // @spec
    // State-Machine-Tokenizer fuer Nim-Subset.
    // Ausgabe: array von Tokens, jedes Token = [kind, text, line, col].
    // - "col" ist die 0-basierte Spalte des ersten Zeichens des Tokens auf
    //   seiner Start-Zeile. Wir brauchen das fuer Indent-Tracking
    //   (Object-Felder, Proc-Body).
    // kinds:
    //   "whitespace"        - Spaces/Tabs (kein Newline)
    //   "newline"           - "\n"
    //   "comment_line"      - "# ..." bis Zeilenende (nicht doc)
    //   "doc_comment_line"  - "## ..." bis Zeilenende (Spec-Traeger)
    //   "comment_block"     - "#[ ... ]#" (nestable)
    //   "string_double"     - "..." mit \-Escape
    //   "string_triple"     - """..."""
    //   "string_raw"        - r"..." (oder generalized-string ident"...")
    //   "string_char"       - 'x' / '\n' / '\xHH'
    //   "number"            - Zahlen (grobe Erkennung)
    //   "identifier"        - Identifier oder Keyword
    //   "punctuation"       - alles andere
    // @end-spec
    private static function tokenize(string $source): array
    {
        $tokens = [];
        $len    = strlen($source);
        $i      = 0;
        $line   = 1;
        $line_start = 0; // index of start of current line

        // Helper: column of byte $i on its current line (0-based).
        $col_at = function (int $pos) use (&$line_start): int
        {
            return $pos - $line_start;
        };

        while ($i < $len)
        {
            $c = $source[$i];

            // Newline
            if ($c === "\n")
            {
                $tokens[] = ["newline", "\n", $line, $col_at($i)];
                $i++;
                $line++;
                $line_start = $i;
                continue;
            }

            // CR (treat as whitespace; Nim source files normally use LF)
            if ($c === "\r")
            {
                $start = $i;
                $start_col = $col_at($i);
                $i++;
                $tokens[] = ["whitespace", substr($source, $start, $i - $start), $line, $start_col];
                continue;
            }

            // Whitespace (spaces, tabs)
            if ($c === " " || $c === "\t")
            {
                $start = $i;
                $start_col = $col_at($i);
                while ($i < $len && ($source[$i] === " " || $source[$i] === "\t"))
                {
                    $i++;
                }
                $tokens[] = ["whitespace", substr($source, $start, $i - $start), $line, $start_col];
                continue;
            }

            // Comments. Nim:
            //   #[ ... ]#  -- block comment, nestable
            //   ## ...     -- doc comment line
            //   # ...      -- normal comment line
            if ($c === "#")
            {
                $start_col = $col_at($i);

                // Block comment "#[ ... ]#" with nesting.
                if ($i + 1 < $len && $source[$i + 1] === "[")
                {
                    $start      = $i;
                    $start_line = $line;
                    $i += 2;
                    $depth = 1;
                    while ($i < $len && $depth > 0)
                    {
                        // open: "#["
                        if ($source[$i] === "#" && $i + 1 < $len && $source[$i + 1] === "[")
                        {
                            $depth++;
                            $i += 2;
                            continue;
                        }
                        // close: "]#"
                        if ($source[$i] === "]" && $i + 1 < $len && $source[$i + 1] === "#")
                        {
                            $depth--;
                            $i += 2;
                            continue;
                        }
                        if ($source[$i] === "\n")
                        {
                            $line++;
                            $i++;
                            $line_start = $i;
                            continue;
                        }
                        $i++;
                    }
                    $tokens[] = ["comment_block", substr($source, $start, $i - $start), $start_line, $start_col];
                    continue;
                }

                // Doc-comment line "## ..." vs normal "# ..."
                $is_doc = $i + 1 < $len && $source[$i + 1] === "#";

                if ($is_doc)
                {
                    $start = $i;
                    while ($i < $len && $source[$i] !== "\n")
                    {
                        $i++;
                    }
                    $tokens[] = ["doc_comment_line", substr($source, $start, $i - $start), $line, $start_col];
                    continue;
                }

                // Normal comment "# ..."
                $start = $i;
                while ($i < $len && $source[$i] !== "\n")
                {
                    $i++;
                }
                $tokens[] = ["comment_line", substr($source, $start, $i - $start), $line, $start_col];
                continue;
            }

            // Triple-string """..."""
            if ($c === "\""
                && $i + 2 < $len
                && $source[$i + 1] === "\""
                && $source[$i + 2] === "\"")
            {
                $start      = $i;
                $start_line = $line;
                $start_col  = $col_at($i);
                $i += 3;
                while ($i < $len)
                {
                    // Closing """ — but Nim allows up to 2 trailing quotes
                    // INSIDE a triple string, and the close is the first
                    // run of >= 3 quotes. Pragmatic V1: close on first
                    // occurrence of three consecutive quotes.
                    if ($source[$i] === "\""
                        && $i + 2 < $len
                        && $source[$i + 1] === "\""
                        && $source[$i + 2] === "\"")
                    {
                        $i += 3;
                        // Greedy: consume any extra trailing quotes (Nim
                        // accepts """abc"""" — last quote is content).
                        while ($i < $len && $source[$i] === "\"")
                        {
                            $i++;
                        }
                        break;
                    }
                    if ($source[$i] === "\n")
                    {
                        $line++;
                        $i++;
                        $line_start = $i;
                        continue;
                    }
                    $i++;
                }
                $tokens[] = ["string_triple", substr($source, $start, $i - $start), $start_line, $start_col];
                continue;
            }

            // Double-quoted string "..."
            if ($c === "\"")
            {
                $start      = $i;
                $start_line = $line;
                $start_col  = $col_at($i);
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    if ($cc === "\\")
                    {
                        // Skip escape (and possible \n inside escape).
                        if ($i + 1 < $len && $source[$i + 1] === "\n")
                        {
                            $line++;
                            $i += 2;
                            $line_start = $i;
                            continue;
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
                        // Unterminated single-line string — bail.
                        $line++;
                        $i++;
                        $line_start = $i;
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["string_double", substr($source, $start, $i - $start), $start_line, $start_col];
                continue;
            }

            // Char literal 'x' / '\n' / '\xHH'
            if ($c === "'")
            {
                $start      = $i;
                $start_line = $line;
                $start_col  = $col_at($i);
                $i++;
                // pragmatic: read until matching ' on the same line.
                while ($i < $len)
                {
                    $cc = $source[$i];
                    if ($cc === "\\")
                    {
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
                        // Not a char literal after all — we already
                        // consumed the opening '. Treat what we read as
                        // string_char anyway and stop.
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["string_char", substr($source, $start, $i - $start), $start_line, $start_col];
                continue;
            }

            // Identifier / keyword. Nim identifiers: letter or _, then
            // letter/digit/_. We allow ASCII only — sufficient for our
            // V1 source-symbol detection. Also handle generalized raw
            // strings: ident immediately followed by " becomes a raw
            // string token (we emit ident + " ... " as a single
            // string_raw token).
            $is_ident_start =
                ($c >= "a" && $c <= "z")
                || ($c >= "A" && $c <= "Z")
                || $c === "_";

            if ($is_ident_start)
            {
                $start      = $i;
                $start_line = $line;
                $start_col  = $col_at($i);
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    $is_ident_char =
                        ($cc >= "a" && $cc <= "z")
                        || ($cc >= "A" && $cc <= "Z")
                        || ($cc >= "0" && $cc <= "9")
                        || $cc === "_";
                    if ( ! $is_ident_char)
                    {
                        break;
                    }
                    $i++;
                }

                $ident_text = substr($source, $start, $i - $start);

                // Generalized raw string literal: ident immediately
                // followed by ". The triple-string variant ident""""""""
                // also exists; we handle both.
                if ($i < $len && $source[$i] === "\"")
                {
                    // Triple-raw ident"""..."""
                    if ($i + 2 < $len
                        && $source[$i + 1] === "\""
                        && $source[$i + 2] === "\"")
                    {
                        $i += 3;
                        while ($i < $len)
                        {
                            if ($source[$i] === "\""
                                && $i + 2 < $len
                                && $source[$i + 1] === "\""
                                && $source[$i + 2] === "\"")
                            {
                                $i += 3;
                                while ($i < $len && $source[$i] === "\"")
                                {
                                    $i++;
                                }
                                break;
                            }
                            if ($source[$i] === "\n")
                            {
                                $line++;
                                $i++;
                                $line_start = $i;
                                continue;
                            }
                            $i++;
                        }
                        $tokens[] = ["string_raw", substr($source, $start, $i - $start), $start_line, $start_col];
                        continue;
                    }

                    // Single-line raw string ident"..." — inside, "" is
                    // an escaped quote. End is a single " not followed by
                    // another ".
                    $i++; // past opening "
                    while ($i < $len)
                    {
                        $cc = $source[$i];
                        if ($cc === "\"")
                        {
                            // doubled "" -> literal quote in raw string
                            if ($i + 1 < $len && $source[$i + 1] === "\"")
                            {
                                $i += 2;
                                continue;
                            }
                            $i++;
                            break;
                        }
                        if ($cc === "\n")
                        {
                            $line++;
                            $i++;
                            $line_start = $i;
                            break;
                        }
                        $i++;
                    }
                    $tokens[] = ["string_raw", substr($source, $start, $i - $start), $start_line, $start_col];
                    continue;
                }

                $tokens[] = ["identifier", $ident_text, $start_line, $start_col];
                continue;
            }

            // Number (rough): digit start
            $is_digit_start = $c >= "0" && $c <= "9";

            if ($is_digit_start)
            {
                $start      = $i;
                $start_line = $line;
                $start_col  = $col_at($i);
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
                        || ($cc >= "a" && $cc <= "f")
                        || ($cc >= "A" && $cc <= "F");
                    if ( ! $is_num_char)
                    {
                        break;
                    }
                    $i++;
                }
                // Optional Nim type suffix like 'i64, 'u8, 'f32 — read
                // until non-ident-char.
                if ($i < $len && $source[$i] === "'")
                {
                    $i++;
                    while ($i < $len)
                    {
                        $cc = $source[$i];
                        $is_suffix_char =
                            ($cc >= "a" && $cc <= "z")
                            || ($cc >= "A" && $cc <= "Z")
                            || ($cc >= "0" && $cc <= "9");
                        if ( ! $is_suffix_char)
                        {
                            break;
                        }
                        $i++;
                    }
                }
                $tokens[] = ["number", substr($source, $start, $i - $start), $start_line, $start_col];
                continue;
            }

            // Punctuation: single char (Nim has many multi-char operators
            // but for symbol detection single-char emit is sufficient).
            $tokens[] = ["punctuation", $c, $line, $col_at($i)];
            $i++;
        }

        return $tokens;
    }

    // ----- Walker ---------------------------------------------------------

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // dispatcht Symbol-Erkennung. Datei-Header-Spec ist der erste Spec-Block,
    // der vor dem ersten Top-Level-Deklarations-Keyword (proc/func/method/
    // iterator/template/macro/type/const/let/var) liegt.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count = count($tokens);

        $first_decl_index = nim_parser::first_decl_index($tokens);

        $file_spec       = [];
        $file_spec_taken = false;
        $symbols         = [];
        $warnings        = [];
        $pending_spec    = null;

        $index = 0;

        while ($index < $count)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];
            $col   = $token[3];

            // Skip whitespace, newlines, normal comments, block comments,
            // non-spec doc comments — they don't claim a pending spec.
            if ($kind === "whitespace" || $kind === "newline"
                || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            // Spec-Start
            if (nim_parser::is_spec_start_token($token))
            {
                $spec_block = nim_parser::read_spec_block($tokens, $index, $count);

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

            // Non-spec doc comment lines just sit between things.
            if ($kind === "doc_comment_line")
            {
                $index++;
                continue;
            }

            // Top-level declaration keywords. Only at column 0.
            if ($kind === "identifier" && $col === 0)
            {
                $is_routine_keyword =
                    $text === "proc" || $text === "func" || $text === "method"
                    || $text === "iterator" || $text === "template"
                    || $text === "macro" || $text === "converter";

                if ($is_routine_keyword)
                {
                    $result = nim_parser::read_routine(
                        $tokens, $index, $count, $text,
                        $pending_spec["lines"] ?? []
                    );
                    $symbols[]    = $result["symbol"];
                    $warnings     = array_merge($warnings, $result["warnings"]);
                    $pending_spec = null;
                    $index        = $result["next_index"];
                    continue;
                }

                if ($text === "type")
                {
                    $result = nim_parser::read_type_section(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        /* is_section_keyword = */ true
                    );
                    foreach ($result["symbols"] as $s)
                    {
                        $symbols[] = $s;
                    }
                    $warnings     = array_merge($warnings, $result["warnings"]);
                    $pending_spec = null;
                    $index        = $result["next_index"];
                    continue;
                }

                if ($text === "const" || $text === "let" || $text === "var")
                {
                    $result = nim_parser::read_var_section(
                        $tokens, $index, $count, $text,
                        $pending_spec["lines"] ?? []
                    );
                    foreach ($result["symbols"] as $s)
                    {
                        $symbols[] = $s;
                    }
                    $warnings     = array_merge($warnings, $result["warnings"]);
                    $pending_spec = null;
                    $index        = $result["next_index"];
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
    // (proc/func/method/iterator/template/macro/converter/type/const/let/var)
    // oder null, wenn keines existiert. Dient zur Heuristik
    // "Datei-Header-Spec".
    // @end-spec
    private static function first_decl_index(array $tokens): ?int
    {
        $count = count($tokens);
        $keys  = [
            "proc", "func", "method", "iterator", "template", "macro",
            "converter", "type", "const", "let", "var",
        ];
        for ($i = 0; $i < $count; $i++)
        {
            $t = $tokens[$i];
            if ($t[0] === "identifier" && $t[3] === 0 && in_array($t[1], $keys, true))
            {
                return $i;
            }
        }
        return null;
    }

    // @spec
    // True, wenn das Token ein doc_comment_line-Token mit Inhalt
    // "## @spec" (optional gefolgt von Space + Inline-Inhalt) ist.
    // Normale "#"-Kommentare und Block-Kommentare zaehlen NICHT.
    // @end-spec
    private static function is_spec_start_token($token): bool
    {
        if ($token[0] !== "doc_comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        $is_doc = substr($text, 0, 2) === "##";
        if ( ! $is_doc)
        {
            return false;
        }
        // Reject "###..." — that's still a doc comment but not our marker;
        // we only want exactly "##" prefix.
        if (strlen($text) >= 3 && $text[2] === "#")
        {
            return false;
        }
        $body = ltrim(substr($text, 2));
        return $body === "@spec"
            || str_starts_with($body, "@spec ")
            || str_starts_with($body, "@spec\t");
    }

    // @spec
    // True, wenn das Token ein doc_comment_line-Token mit Inhalt
    // "## @end-spec" ist.
    // @end-spec
    private static function is_spec_end_token($token): bool
    {
        if ($token[0] !== "doc_comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        $is_doc = substr($text, 0, 2) === "##";
        if ( ! $is_doc)
        {
            return false;
        }
        if (strlen($text) >= 3 && $text[2] === "#")
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
    // doc_comment_line-Spec-Start-Token steht). Returnt:
    //   ["lines" => [...], "start_line" => N, "start_index" => I,
    //    "start_col" => C]
    // und avanciert $index hinter das @end-spec-Token. Zwischen Start und
    // Ende werden nur doc_comment_line-Token (Inhalt) und whitespace/newline
    // akzeptiert; alles andere schliesst den Block (Walker nimmt das Token
    // dann als naechstes).
    // @end-spec
    private static function read_spec_block(array $tokens, int &$index, int $count): array
    {
        $start_index = $index;
        $start_token = $tokens[$index];
        $start_line  = $start_token[2];
        $start_col   = $start_token[3];

        $lines = [];

        // First token: ## @spec marker, possibly with inline content.
        $first_text = rtrim($start_token[1]);
        $first_body = ltrim(substr($first_text, 2)); // strip "##"
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

            if ($kind === "whitespace" || $kind === "newline")
            {
                $index++;
                continue;
            }

            if (nim_parser::is_spec_end_token($token))
            {
                $index++;
                return [
                    "lines"       => $lines,
                    "start_line"  => $start_line,
                    "start_index" => $start_index,
                    "start_col"   => $start_col,
                ];
            }

            if ($kind === "doc_comment_line")
            {
                $text = rtrim($token[1]);
                // strip leading "##"
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

            // Anything else (including normal comment_line / comment_block):
            // block did not close with @end-spec. Stop here; caller picks up
            // the current token next. The block is pending — if no symbol
            // follows, walker emits dangling warning.
            return [
                "lines"       => $lines,
                "start_line"  => $start_line,
                "start_index" => $start_index,
                "start_col"   => $start_col,
            ];
        }

        return [
            "lines"       => $lines,
            "start_line"  => $start_line,
            "start_index" => $start_index,
            "start_col"   => $start_col,
        ];
    }

    // @spec
    // Liest eine Routine-Deklaration (proc/func/method/iterator/template/
    // macro/converter). Erwartet $index auf dem keyword-identifier.
    // Sammelt Name + Signatur (alles bis vor "=" oder Zeilenende falls
    // forward-decl) als Source-String. Body-Erkennung: alles bis zur
    // naechsten Top-Level-Zeile (col 0) gehoert zur Routine; lokale
    // Spec-Bloecke werden eingesammelt.
    // @end-spec
    private static function read_routine(
        array $tokens, int $index, int $count, string $kind, array $spec
    ): array
    {
        // Build signature source string, starting at the keyword.
        $sig_start = $index;
        $name = "";

        // Step over keyword.
        $index++;

        // Walk forward, collecting tokens for the signature, until we hit
        // either a top-level "=" punctuation OR a newline that ends the
        // signature line AND no "=" was found (forward-decl style).
        // We track paren/bracket depth so a "=" in default values doesn't
        // terminate the signature prematurely.
        $depth = 0;
        $paren_depth_after_name = false;
        $found_equals = false;
        $sig_end = $index;

        // Collect identifier name first: skip whitespace, then optional "*"
        // (export marker) then identifier. Also possible: backtick-quoted
        // identifiers `like this`.
        $name_idx = nim_parser::skip_ws_inline($tokens, $index, $count);
        if ($name_idx < $count && $tokens[$name_idx][0] === "identifier")
        {
            $name = $tokens[$name_idx][1];
        }
        else if ($name_idx < $count && $tokens[$name_idx][0] === "punctuation"
                 && $tokens[$name_idx][1] === "`")
        {
            // backtick-quoted name: read up to next backtick
            $j = $name_idx + 1;
            $buf = "";
            while ($j < $count)
            {
                $t = $tokens[$j];
                if ($t[0] === "punctuation" && $t[1] === "`")
                {
                    break;
                }
                $buf .= $t[1];
                $j++;
            }
            $name = $buf;
        }

        // Now scan until end of signature.
        $j = $index;
        while ($j < $count)
        {
            $t = $tokens[$j];
            $tk = $t[0];
            $tt = $t[1];
            $tcol = $t[3];

            if ($tk === "punctuation")
            {
                if ($tt === "(" || $tt === "[" || $tt === "{")
                {
                    $depth++;
                    $j++;
                    continue;
                }
                if ($tt === ")" || $tt === "]" || $tt === "}")
                {
                    if ($depth > 0)
                    {
                        $depth--;
                    }
                    $j++;
                    continue;
                }
                if ($tt === "=" && $depth === 0)
                {
                    $found_equals = true;
                    $sig_end = $j;
                    break;
                }
                $j++;
                continue;
            }

            if ($tk === "newline")
            {
                if ($depth === 0)
                {
                    // End of line. Could be forward decl or signature
                    // continues on next line via comma in parens (already
                    // covered by depth>0). Look ahead: if next significant
                    // token is at column 0 OR a non-routine top-level
                    // declaration follows, treat as end. Pragmatic: end
                    // here.
                    $sig_end = $j;
                    break;
                }
                $j++;
                continue;
            }

            $j++;
        }

        // Build signature source string from sig_start..sig_end.
        $sig = nim_parser::tokens_to_source($tokens, $sig_start, $sig_end);

        // Determine routine body extent for local-spec collection.
        $body_members = [];
        $body_warnings = [];

        if ($found_equals)
        {
            // Body starts after "=" up to the first token at col 0 that
            // is NOT inside the routine.
            $body_start = $sig_end + 1;
            $body_extent = nim_parser::find_block_end_by_col(
                $tokens, $body_start, $count, /* base_col = */ 0
            );
            $local = nim_parser::collect_local_specs(
                $tokens, $body_start, $body_extent
            );
            $body_members  = $local["members"];
            $body_warnings = $local["warnings"];
            $j             = $body_extent;
        }
        else
        {
            // Forward decl — no body. j is already past the newline.
            // Nothing to do.
        }

        $sig = nim_parser::normalise_signature($sig);

        $symbol = [
            "kind"      => $kind,
            "name"      => $name,
            "signature" => $sig,
            "spec"      => $spec,
            "members"   => $body_members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => $body_warnings,
            "next_index" => $j,
        ];
    }

    // @spec
    // Liest einen "type"-Block. Zwei Formen:
    //   1) Single-line: "type Foo = int" oder "type Foo = object" mit
    //      eingerueckten Feldern auf Folgezeilen.
    //   2) Section:     "type" auf eigener Zeile, dann eingerueckte
    //      "Foo = ..." Eintraege.
    // Liefert Liste der entstandenen Top-Level-Symbole + warnings.
    // @end-spec
    private static function read_type_section(
        array $tokens, int $index, int $count, array $spec, bool $is_section_keyword
    ): array
    {
        $symbols  = [];
        $warnings = [];

        // Step over "type" keyword.
        $index++;

        // Determine: single decl on same line, or section on next lines?
        $j = $index;
        // Skip inline whitespace.
        while ($j < $count && $tokens[$j][0] === "whitespace")
        {
            $j++;
        }

        $is_single_decl = $j < $count && $tokens[$j][0] !== "newline";

        if ($is_single_decl)
        {
            $one = nim_parser::read_one_type_decl(
                $tokens, $j, $count, $spec, /* base_col = */ 0
            );
            if ($one !== null)
            {
                $symbols[] = $one["symbol"];
                $warnings  = array_merge($warnings, $one["warnings"]);
                return [
                    "symbols"    => $symbols,
                    "warnings"   => $warnings,
                    "next_index" => $one["next_index"],
                ];
            }
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $j,
            ];
        }

        // Section: eat newline, then read indented entries.
        // Skip newline(s).
        while ($j < $count && $tokens[$j][0] === "newline")
        {
            $j++;
        }

        // Find indent of first non-whitespace token after newlines.
        $first_idx = nim_parser::skip_ws_and_newlines($tokens, $j, $count);
        if ($first_idx >= $count)
        {
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $j,
            ];
        }

        $section_indent = $tokens[$first_idx][3];

        if ($section_indent === 0)
        {
            // Empty section, next thing is at col 0 — bail.
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $first_idx,
            ];
        }

        // Section spec carry: spec applies to the FIRST entry only.
        $carry_spec    = $spec;
        $pending_entry_spec = null;

        $cur = $first_idx;
        while ($cur < $count)
        {
            // Skip blank lines.
            if ($tokens[$cur][0] === "whitespace" || $tokens[$cur][0] === "newline")
            {
                $cur++;
                continue;
            }
            // Skip non-spec doc comments and normal comments.
            if ($tokens[$cur][0] === "comment_line" || $tokens[$cur][0] === "comment_block")
            {
                $cur++;
                continue;
            }
            if ($tokens[$cur][0] === "doc_comment_line"
                && ! nim_parser::is_spec_start_token($tokens[$cur])
                && ! nim_parser::is_spec_end_token($tokens[$cur]))
            {
                $cur++;
                continue;
            }

            $tcol = $tokens[$cur][3];
            if ($tcol < $section_indent)
            {
                // End of section.
                break;
            }

            // Spec block before an entry?
            if (nim_parser::is_spec_start_token($tokens[$cur]))
            {
                $block = nim_parser::read_spec_block($tokens, $cur, $count);
                if ($pending_entry_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_entry_spec["start_line"];
                }
                $pending_entry_spec = $block;
                continue;
            }

            // Read one type decl at this indent level.
            $entry_spec = $pending_entry_spec !== null
                ? $pending_entry_spec["lines"]
                : $carry_spec;
            $carry_spec = []; // only first entry inherits the section-level spec
            $pending_entry_spec = null;

            $one = nim_parser::read_one_type_decl(
                $tokens, $cur, $count, $entry_spec, $section_indent
            );
            if ($one === null)
            {
                $cur++;
                continue;
            }
            $symbols[] = $one["symbol"];
            $warnings  = array_merge($warnings, $one["warnings"]);
            $cur = $one["next_index"];
        }

        if ($pending_entry_spec !== null)
        {
            $warnings[] = "dangling spec at line " . $pending_entry_spec["start_line"];
        }

        return [
            "symbols"    => $symbols,
            "warnings"   => $warnings,
            "next_index" => $cur,
        ];
    }

    // @spec
    // Liest eine einzelne type-Deklaration "Name [generic] = body". Erkennt:
    //   - object / ref object / ptr object  -> kind=object, members=fields
    //   - enum                              -> kind=enum
    //   - tuple                             -> kind=type
    //   - alles andere (Alias, distinct ...)-> kind=type
    // Felder werden aus der eingerueckten Folgezeilen-Sektion gesammelt.
    // @end-spec
    private static function read_one_type_decl(
        array $tokens, int $index, int $count, array $spec, int $base_col
    ): ?array
    {
        $name = "";
        $j = nim_parser::skip_ws_inline($tokens, $index, $count);
        if ($j >= $count)
        {
            return null;
        }
        if ($tokens[$j][0] !== "identifier")
        {
            return null;
        }
        $name = $tokens[$j][1];
        $j++;

        // Skip optional "*" (export) and generics "[...]" and pragmas
        // "{....}" until we hit "=" or newline.
        $found_equals = false;
        $eq_index = $j;
        $depth = 0;
        while ($j < $count)
        {
            $t = $tokens[$j];
            if ($t[0] === "punctuation")
            {
                if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                {
                    $depth++;
                    $j++;
                    continue;
                }
                if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                {
                    if ($depth > 0) { $depth--; }
                    $j++;
                    continue;
                }
                if ($t[1] === "=" && $depth === 0)
                {
                    $found_equals = true;
                    $eq_index = $j;
                    break;
                }
            }
            if ($t[0] === "newline" && $depth === 0)
            {
                $eq_index = $j;
                break;
            }
            $j++;
        }

        if ( ! $found_equals)
        {
            // No "=" — treat as plain type without body, kind=type.
            return [
                "symbol" => [
                    "kind"    => "type",
                    "name"    => $name,
                    "spec"    => $spec,
                    "members" => [],
                ],
                "warnings"   => [],
                "next_index" => $j,
            ];
        }

        // After "=", determine the body kind. Scan tokens after "=" on
        // same logical line (no newline) for keywords: object, enum,
        // tuple, distinct, ref/ptr + object.
        $after_eq = nim_parser::skip_ws_inline($tokens, $eq_index + 1, $count);

        $body_kind = "type"; // default
        $is_object = false;
        $is_enum   = false;

        if ($after_eq < $count && $tokens[$after_eq][0] === "identifier")
        {
            $word = $tokens[$after_eq][1];

            if ($word === "object")
            {
                $body_kind = "object";
                $is_object = true;
            }
            else if ($word === "enum")
            {
                $body_kind = "enum";
                $is_enum   = true;
            }
            else if (($word === "ref" || $word === "ptr"))
            {
                // Look ahead: "ref object" / "ptr object"
                $look = nim_parser::skip_ws_inline($tokens, $after_eq + 1, $count);
                if ($look < $count && $tokens[$look][0] === "identifier"
                    && $tokens[$look][1] === "object")
                {
                    $body_kind = "object";
                    $is_object = true;
                }
            }
        }

        // Find end of this type decl. For object/enum: the indented block
        // following the line. Body ends when indent drops back to <= base_col.
        // For aliases: next newline ends it.
        $members = [];
        $warnings = [];

        // Move past this line first (until newline).
        $end_of_line = nim_parser::find_newline_after($tokens, $eq_index, $count);

        $next = $end_of_line; // position right after the newline-skip below

        if ($is_object)
        {
            $field_block = nim_parser::collect_object_fields(
                $tokens, $end_of_line, $count, $base_col
            );
            $members  = $field_block["members"];
            $warnings = $field_block["warnings"];
            $next     = $field_block["next_index"];
        }
        else if ($is_enum)
        {
            // Enum members are simple identifiers — V1: skip body but do
            // not enumerate them as members. Just advance past indented
            // block.
            $next = nim_parser::skip_indented_block(
                $tokens, $end_of_line, $count, $base_col
            );
        }
        else
        {
            // Plain alias / distinct — advance to end of line; nothing
            // indented to claim.
            $next = $end_of_line;
        }

        $symbol = [
            "kind"    => $body_kind,
            "name"    => $name,
            "spec"    => $spec,
            "members" => $members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => $warnings,
            "next_index" => $next,
        ];
    }

    // @spec
    // Sammelt Felder eines "type Foo = object"-Blocks. Beginnt nach dem
    // Newline am Ende der "= object"-Zeile. Iteriert eingerueckte Zeilen
    // und parst pro Zeile "name[, name2]: Type [= default]" zu field-
    // Members. Spec-Bloecke direkt vor einer Field-Zeile gehoeren zu
    // dieser Zeile.
    // base_col = die Spalte des Type-Eintrags selbst (im Section-Modus
    // der section_indent, im Top-Level-Modus 0). Felder muessen
    // strikt > base_col eingerueckt sein.
    // @end-spec
    private static function collect_object_fields(
        array $tokens, int $index, int $count, int $base_col
    ): array
    {
        $members  = [];
        $warnings = [];
        $pending_field_spec = null;
        $field_indent = -1;

        $cur = $index;
        while ($cur < $count)
        {
            $t = $tokens[$cur];
            $tk = $t[0];

            if ($tk === "whitespace" || $tk === "newline")
            {
                $cur++;
                continue;
            }
            if ($tk === "comment_line" || $tk === "comment_block")
            {
                $cur++;
                continue;
            }
            if ($tk === "doc_comment_line"
                && ! nim_parser::is_spec_start_token($t)
                && ! nim_parser::is_spec_end_token($t))
            {
                $cur++;
                continue;
            }

            $tcol = $t[3];

            // First field sets the field_indent.
            if ($field_indent === -1)
            {
                if ($tcol <= $base_col)
                {
                    // No fields at all (or section already ended).
                    break;
                }
                $field_indent = $tcol;
            }

            if ($tcol < $field_indent)
            {
                break;
            }

            // Spec block?
            if (nim_parser::is_spec_start_token($t))
            {
                $block = nim_parser::read_spec_block($tokens, $cur, $count);
                if ($pending_field_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_field_spec["start_line"];
                }
                $pending_field_spec = $block;
                continue;
            }

            // Field line: "name[, name2, ...]: Type [= default]"
            if ($tk !== "identifier")
            {
                $cur++;
                continue;
            }

            $field_spec_lines = $pending_field_spec !== null
                ? $pending_field_spec["lines"]
                : [];
            $pending_field_spec = null;

            $field = nim_parser::read_field_line(
                $tokens, $cur, $count, $field_spec_lines
            );
            if ($field === null)
            {
                // Could not parse — skip line.
                $cur = nim_parser::find_newline_after($tokens, $cur, $count);
                continue;
            }
            foreach ($field["fields"] as $f)
            {
                $members[] = $f;
            }
            $cur = $field["next_index"];
        }

        if ($pending_field_spec !== null)
        {
            $warnings[] = "dangling spec at line " . $pending_field_spec["start_line"];
        }

        return [
            "members"    => $members,
            "warnings"   => $warnings,
            "next_index" => $cur,
        ];
    }

    // @spec
    // Liest eine Field-Zeile in einem object-Block:
    //   name[, name2, ...]*?: Type [= default]
    // Liefert ein oder mehrere field-Members (ein Eintrag pro Name).
    // Pragmas {.something.} hinter dem Namen werden in den Type-String
    // mitgenommen, weil sie zur Deklaration gehoeren.
    // @end-spec
    private static function read_field_line(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        $names = [];
        $j = $index;

        // Names list: ident ("*"|"") ("," ident)*
        while ($j < $count)
        {
            $t = $tokens[$j];
            if ($t[0] === "identifier")
            {
                $names[] = $t[1];
                $j++;
                // Optional export "*"
                if ($j < $count && $tokens[$j][0] === "punctuation"
                    && $tokens[$j][1] === "*")
                {
                    $j++;
                }
                // Optional whitespace
                while ($j < $count && $tokens[$j][0] === "whitespace")
                {
                    $j++;
                }
                // "," -> continue list
                if ($j < $count && $tokens[$j][0] === "punctuation"
                    && $tokens[$j][1] === ",")
                {
                    $j++;
                    while ($j < $count && $tokens[$j][0] === "whitespace")
                    {
                        $j++;
                    }
                    continue;
                }
                break;
            }
            // anything else: stop name list
            break;
        }

        if (empty($names))
        {
            return null;
        }

        // Expect ":" next
        while ($j < $count && $tokens[$j][0] === "whitespace")
        {
            $j++;
        }
        if ($j >= $count || $tokens[$j][0] !== "punctuation" || $tokens[$j][1] !== ":")
        {
            return null;
        }
        $j++;

        // Read type until "=" or newline at depth 0.
        $type_start = $j;
        $depth = 0;
        $type_end = $j;
        $found_default = false;
        while ($j < $count)
        {
            $t = $tokens[$j];
            if ($t[0] === "punctuation")
            {
                if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                {
                    $depth++;
                    $j++;
                    continue;
                }
                if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                {
                    if ($depth > 0) { $depth--; }
                    $j++;
                    continue;
                }
                if ($t[1] === "=" && $depth === 0)
                {
                    $found_default = true;
                    $type_end = $j;
                    break;
                }
            }
            if ($t[0] === "newline" && $depth === 0)
            {
                $type_end = $j;
                break;
            }
            $j++;
        }
        if ( ! isset($type_end) || $type_end === $type_start)
        {
            $type_end = $j;
        }

        $type_text = trim(nim_parser::tokens_to_source($tokens, $type_start, $type_end));

        $default = "";
        if ($found_default)
        {
            // skip "="
            $j++;
            $def_start = $j;
            $depth = 0;
            while ($j < $count)
            {
                $t = $tokens[$j];
                if ($t[0] === "punctuation")
                {
                    if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                    {
                        $depth++;
                        $j++;
                        continue;
                    }
                    if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                    {
                        if ($depth > 0) { $depth--; }
                        $j++;
                        continue;
                    }
                }
                if ($t[0] === "newline" && $depth === 0)
                {
                    break;
                }
                $j++;
            }
            $default = trim(nim_parser::tokens_to_source($tokens, $def_start, $j));
        }

        // Skip the trailing newline
        if ($j < $count && $tokens[$j][0] === "newline")
        {
            $j++;
        }

        $fields = [];
        foreach ($names as $n)
        {
            $entry = [
                "kind" => "field",
                "name" => $n,
                "type" => $type_text,
                "spec" => $spec,
            ];
            if ($default !== "")
            {
                $entry["default"] = $default;
            }
            $fields[] = $entry;
        }

        return [
            "fields"     => $fields,
            "next_index" => $j,
        ];
    }

    // @spec
    // Liest eine var/let/const-Deklaration. Zwei Formen analog zu type:
    //   1) Single: "let x = 1"
    //   2) Section: "var" / "let" / "const" auf eigener Zeile, dann
    //      eingerueckte Eintraege.
    // V1: Default-Source-String wird vom "=" bis zum Zeilenende gelesen,
    // ohne mehrzeilige Ausdruecke zu unterstuetzen.
    // @end-spec
    private static function read_var_section(
        array $tokens, int $index, int $count, string $kind, array $spec
    ): array
    {
        $symbols  = [];
        $warnings = [];

        // Step over keyword.
        $index++;

        // Skip inline whitespace.
        $j = $index;
        while ($j < $count && $tokens[$j][0] === "whitespace")
        {
            $j++;
        }

        $is_single = $j < $count && $tokens[$j][0] !== "newline";

        if ($is_single)
        {
            $one = nim_parser::read_one_var_decl(
                $tokens, $j, $count, $kind, $spec
            );
            if ($one !== null)
            {
                $symbols[] = $one["symbol"];
                return [
                    "symbols"    => $symbols,
                    "warnings"   => $warnings,
                    "next_index" => $one["next_index"],
                ];
            }
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $j,
            ];
        }

        // Section style.
        while ($j < $count && $tokens[$j][0] === "newline")
        {
            $j++;
        }
        $first = nim_parser::skip_ws_and_newlines($tokens, $j, $count);
        if ($first >= $count)
        {
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $j,
            ];
        }
        $section_indent = $tokens[$first][3];
        if ($section_indent === 0)
        {
            return [
                "symbols"    => $symbols,
                "warnings"   => $warnings,
                "next_index" => $first,
            ];
        }

        $carry_spec = $spec;
        $pending_entry_spec = null;
        $cur = $first;

        while ($cur < $count)
        {
            if ($tokens[$cur][0] === "whitespace" || $tokens[$cur][0] === "newline")
            {
                $cur++;
                continue;
            }
            if ($tokens[$cur][0] === "comment_line" || $tokens[$cur][0] === "comment_block")
            {
                $cur++;
                continue;
            }
            if ($tokens[$cur][0] === "doc_comment_line"
                && ! nim_parser::is_spec_start_token($tokens[$cur])
                && ! nim_parser::is_spec_end_token($tokens[$cur]))
            {
                $cur++;
                continue;
            }
            $tcol = $tokens[$cur][3];
            if ($tcol < $section_indent)
            {
                break;
            }

            if (nim_parser::is_spec_start_token($tokens[$cur]))
            {
                $block = nim_parser::read_spec_block($tokens, $cur, $count);
                if ($pending_entry_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_entry_spec["start_line"];
                }
                $pending_entry_spec = $block;
                continue;
            }

            $entry_spec = $pending_entry_spec !== null
                ? $pending_entry_spec["lines"]
                : $carry_spec;
            $carry_spec = [];
            $pending_entry_spec = null;

            $one = nim_parser::read_one_var_decl(
                $tokens, $cur, $count, $kind, $entry_spec
            );
            if ($one === null)
            {
                $cur = nim_parser::find_newline_after($tokens, $cur, $count);
                continue;
            }
            $symbols[] = $one["symbol"];
            $cur = $one["next_index"];
        }

        if ($pending_entry_spec !== null)
        {
            $warnings[] = "dangling spec at line " . $pending_entry_spec["start_line"];
        }

        return [
            "symbols"    => $symbols,
            "warnings"   => $warnings,
            "next_index" => $cur,
        ];
    }

    // @spec
    // Liest eine einzelne var/let/const-Deklaration:
    //   name[*][:Type] = value
    // Default-Source-String ist alles vom "=" bis Zeilenende (depth 0).
    // @end-spec
    private static function read_one_var_decl(
        array $tokens, int $index, int $count, string $kind, array $spec
    ): ?array
    {
        $j = nim_parser::skip_ws_inline($tokens, $index, $count);
        if ($j >= $count || $tokens[$j][0] !== "identifier")
        {
            return null;
        }
        $name = $tokens[$j][1];
        $j++;

        // Walk to "=" or newline at depth 0.
        $depth = 0;
        $found_equals = false;
        $eq_index = $j;
        while ($j < $count)
        {
            $t = $tokens[$j];
            if ($t[0] === "punctuation")
            {
                if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                {
                    $depth++;
                    $j++;
                    continue;
                }
                if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                {
                    if ($depth > 0) { $depth--; }
                    $j++;
                    continue;
                }
                if ($t[1] === "=" && $depth === 0)
                {
                    $found_equals = true;
                    $eq_index = $j;
                    break;
                }
            }
            if ($t[0] === "newline" && $depth === 0)
            {
                $eq_index = $j;
                break;
            }
            $j++;
        }

        $default = "";
        if ($found_equals)
        {
            $def_start = $eq_index + 1;
            $j = $def_start;
            $depth = 0;
            while ($j < $count)
            {
                $t = $tokens[$j];
                if ($t[0] === "punctuation")
                {
                    if ($t[1] === "(" || $t[1] === "[" || $t[1] === "{")
                    {
                        $depth++;
                        $j++;
                        continue;
                    }
                    if ($t[1] === ")" || $t[1] === "]" || $t[1] === "}")
                    {
                        if ($depth > 0) { $depth--; }
                        $j++;
                        continue;
                    }
                }
                if ($t[0] === "newline" && $depth === 0)
                {
                    break;
                }
                $j++;
            }
            $default = trim(nim_parser::tokens_to_source($tokens, $def_start, $j));
        }

        // Consume newline.
        if ($j < $count && $tokens[$j][0] === "newline")
        {
            $j++;
        }

        return [
            "symbol" => [
                "kind"    => $kind,
                "name"    => $name,
                "default" => $default,
                "spec"    => $spec,
            ],
            "next_index" => $j,
        ];
    }

    // @spec
    // Sammelt lokale Spec-Bloecke innerhalb des Routine-Body. Body geht von
    // $start_index bis $end_index (exklusiv). Spec-Bloecke werden als
    // members[] mit kind=local zurueckgegeben.
    // @end-spec
    private static function collect_local_specs(
        array $tokens, int $start_index, int $end_index
    ): array
    {
        $members  = [];
        $warnings = [];
        $cur = $start_index;
        while ($cur < $end_index)
        {
            $t = $tokens[$cur];
            if (nim_parser::is_spec_start_token($t))
            {
                $block = nim_parser::read_spec_block($tokens, $cur, $end_index);
                $first_line = $block["lines"][0] ?? "";
                $name_hint  = mb_substr($first_line, 0, 30);
                $members[] = [
                    "kind" => "local",
                    "name" => $name_hint,
                    "spec" => $block["lines"],
                ];
                continue;
            }
            $cur++;
        }
        return [
            "members"  => $members,
            "warnings" => $warnings,
        ];
    }

    // @spec
    // Findet das Ende eines Indented-Block, der nach $start_index beginnt.
    // Wir suchen das erste signifikante Token mit col <= $base_col.
    // Liefert dessen Index (oder $count wenn keines mehr).
    // Whitespace, Newlines und gewoehnliche Kommentare zaehlen nicht als
    // signifikant. Doc-Kommentare an col <= base_col WERDEN als signifikant
    // gewertet — damit eine "## @spec"-Zeile am Datei-Ende den Body
    // schliesst und der dangling-Block nicht in members[] landet.
    // @end-spec
    private static function find_block_end_by_col(
        array $tokens, int $start_index, int $count, int $base_col
    ): int
    {
        $cur = $start_index;
        while ($cur < $count)
        {
            $t = $tokens[$cur];
            $tk = $t[0];
            if ($tk === "whitespace" || $tk === "newline"
                || $tk === "comment_line" || $tk === "comment_block")
            {
                $cur++;
                continue;
            }
            if ($tk === "doc_comment_line")
            {
                // Doc-comment line at base_col or less ends the body
                // (esp. spec markers — those should be top-level pending,
                // not body-locals). Doc comments deeper than base_col stay
                // inside the body.
                if ($t[3] <= $base_col)
                {
                    return $cur;
                }
                $cur++;
                continue;
            }
            if ($t[3] <= $base_col)
            {
                return $cur;
            }
            $cur++;
        }
        return $count;
    }

    // @spec
    // Springt ueber einen indented-Block hinweg (z.B. Enum-Body). Liefert
    // den Index des ersten Tokens mit col <= $base_col oder $count.
    // @end-spec
    private static function skip_indented_block(
        array $tokens, int $start_index, int $count, int $base_col
    ): int
    {
        return nim_parser::find_block_end_by_col($tokens, $start_index, $count, $base_col);
    }

    // @spec
    // Sucht das naechste newline-Token ab $index und liefert den Index
    // direkt NACH dem newline-Token (also Anfang der naechsten Zeile).
    // Liefert $count wenn kein newline mehr kommt.
    // @end-spec
    private static function find_newline_after(array $tokens, int $index, int $count): int
    {
        $j = $index;
        while ($j < $count && $tokens[$j][0] !== "newline")
        {
            $j++;
        }
        if ($j < $count)
        {
            $j++;
        }
        return $j;
    }

    // @spec
    // Wandelt die Token zwischen $from (inkl) und $to (exkl) wieder in
    // einen Source-String um. Bewahrt Original-Text der Tokens; Whitespace-
    // Tokens werden als " " ausgegeben (Newlines auch — die werden in der
    // Signatur-Normalisierung weiter komprimiert).
    // @end-spec
    private static function tokens_to_source(array $tokens, int $from, int $to): string
    {
        $out = "";
        for ($i = $from; $i < $to; $i++)
        {
            $t = $tokens[$i];
            $tk = $t[0];
            if ($tk === "whitespace" || $tk === "newline")
            {
                $out .= " ";
                continue;
            }
            if ($tk === "comment_line" || $tk === "comment_block"
                || $tk === "doc_comment_line")
            {
                continue;
            }
            $out .= $t[1];
        }
        return $out;
    }

    // @spec
    // Avanciert ueber Inline-Whitespace (kein Newline). Liefert den neuen
    // Index.
    // @end-spec
    private static function skip_ws_inline(array $tokens, int $index, int $count): int
    {
        while ($index < $count && $tokens[$index][0] === "whitespace")
        {
            $index++;
        }
        return $index;
    }

    // @spec
    // Avanciert ueber whitespace, newline, comment_line, comment_block.
    // Doc-comment-Zeilen werden NICHT uebersprungen — nur die echten
    // "harmlosen" Layout-Tokens.
    // @end-spec
    private static function skip_ws_and_newlines(array $tokens, int $index, int $count): int
    {
        while ($index < $count)
        {
            $tk = $tokens[$index][0];
            if ($tk === "whitespace" || $tk === "newline"
                || $tk === "comment_line" || $tk === "comment_block")
            {
                $index++;
                continue;
            }
            break;
        }
        return $index;
    }

    // @spec
    // Komprimiert Whitespace im Signatur-String (uebernommen aus
    // js_parser::normalise_signature). Reine String-Manipulation, kein
    // Regex (Decision 0006).
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
