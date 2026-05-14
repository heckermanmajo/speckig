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

        $tokens = lua_parser::tokenize($source);

        return lua_parser::walk_tokens($tokens);
    }

    // ----- Tokenizer ------------------------------------------------------

    // @spec
    // State-Machine-Tokenizer fuer Lua-Subset.
    // Ausgabe: array von Tokens, jedes Token = [kind, text, line].
    // kinds:
    //   "whitespace"     - Spaces/Tabs/CR (kein Newline)
    //   "newline"        - "\n"
    //   "comment_line"   - "-- ..." bis Zeilenende
    //   "comment_block"  - "--[[ ... ]]" / "--[=[ ... ]=]" (matched levels)
    //   "string_single"  - '...' mit \-Escape
    //   "string_double"  - "..." mit \-Escape
    //   "string_long"    - [[ ... ]] / [=[ ... ]=] (matched levels, KEINE Escapes)
    //   "number"         - Zahlen (grobe Erkennung)
    //   "identifier"     - Identifier oder Keyword
    //   "punctuation"    - Operatoren, Klammern, Sonstiges
    //
    // KRITISCH: --[[ vs. [[. Token-Anfang nach "--" ist Block-Kommentar.
    // [[ allein (Token-Start nicht durch -- praefiziert) ist Long-String.
    // Das wird hier dadurch garantiert, dass der "--"-Pfad VOR dem allgemeinen
    // "["-Pfad steht und beim Sehen von "--" entscheidet, ob es ein
    // Block-Kommentar (--[[ / --[=[ ... ]) oder Zeilen-Kommentar (-- ...) ist.
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

            // Newline
            if ($c === "\n")
            {
                $tokens[] = ["newline", "\n", $line];
                $i++;
                $line++;
                continue;
            }

            // CR — emit as whitespace; Lua source files normally use LF.
            if ($c === "\r")
            {
                $tokens[] = ["whitespace", "\r", $line];
                $i++;
                continue;
            }

            // Whitespace (spaces, tabs)
            if ($c === " " || $c === "\t")
            {
                $start      = $i;
                $start_line = $line;
                while ($i < $len && ($source[$i] === " " || $source[$i] === "\t"))
                {
                    $i++;
                }
                $tokens[] = ["whitespace", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // "--" introduces a comment. May be block comment (--[[ ... ]]
            // / --[=[ ... ]=]) or simple line comment (-- ...).
            if ($c === "-" && $i + 1 < $len && $source[$i + 1] === "-")
            {
                $start      = $i;
                $start_line = $line;
                $i += 2; // past "--"

                // Try long-bracket open immediately after "--": "[" "="* "["
                $open_level = lua_parser::try_read_long_bracket_open($source, $i, $len);

                if ($open_level !== null)
                {
                    // Block comment with matched level.
                    $i = $open_level["next"];
                    $level = $open_level["level"];

                    while ($i < $len)
                    {
                        if ($source[$i] === "]")
                        {
                            $close = lua_parser::try_read_long_bracket_close(
                                $source, $i, $len, $level
                            );
                            if ($close !== null)
                            {
                                $i = $close;
                                break;
                            }
                            $i++;
                            continue;
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

                // Simple line comment "-- ..."
                while ($i < $len && $source[$i] !== "\n")
                {
                    $i++;
                }
                $tokens[] = ["comment_line", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Long string "[[ ... ]]" / "[=[ ... ]=]" — only when not preceded
            // by "--" (that case was handled above).
            if ($c === "[")
            {
                $start_pos = $i;
                $open_level = lua_parser::try_read_long_bracket_open($source, $i, $len);

                if ($open_level !== null)
                {
                    $start_line = $line;
                    $i = $open_level["next"];
                    $level = $open_level["level"];

                    // Lua quirk: a long string opening followed immediately
                    // by a newline drops that first newline. We don't strip
                    // anything from the source string — we just count it for
                    // line tracking and emit the verbatim slice as token
                    // text (downstream code only cares about token kind).
                    while ($i < $len)
                    {
                        if ($source[$i] === "]")
                        {
                            $close = lua_parser::try_read_long_bracket_close(
                                $source, $i, $len, $level
                            );
                            if ($close !== null)
                            {
                                $i = $close;
                                break;
                            }
                            $i++;
                            continue;
                        }
                        if ($source[$i] === "\n")
                        {
                            $line++;
                        }
                        $i++;
                    }

                    $tokens[] = ["string_long", substr($source, $start_pos, $i - $start_pos), $start_line];
                    continue;
                }

                // Plain "[" punctuation
                $tokens[] = ["punctuation", "[", $line];
                $i++;
                continue;
            }

            // Single-quoted string '...'
            if ($c === "'")
            {
                $start      = $i;
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
                        // unterminated — bail out
                        $line++;
                        $i++;
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["string_single", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Double-quoted string "..."
            if ($c === "\"")
            {
                $start      = $i;
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

            // Number (rough): digit start
            $is_digit_start = $c >= "0" && $c <= "9";

            if ($is_digit_start)
            {
                $start      = $i;
                $start_line = $line;
                $i++;
                while ($i < $len)
                {
                    $cc = $source[$i];
                    $is_num_char =
                        ($cc >= "0" && $cc <= "9")
                        || $cc === "."
                        || $cc === "e" || $cc === "E"
                        || $cc === "x" || $cc === "X"
                        || ($cc >= "a" && $cc <= "f")
                        || ($cc >= "A" && $cc <= "F");
                    if ( ! $is_num_char)
                    {
                        break;
                    }
                    $i++;
                }
                $tokens[] = ["number", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Identifier / keyword. Lua identifiers: letter or _, then
            // letter/digit/_. ASCII only — sufficient for V1.
            $is_ident_start =
                ($c >= "a" && $c <= "z")
                || ($c >= "A" && $c <= "Z")
                || $c === "_";

            if ($is_ident_start)
            {
                $start      = $i;
                $start_line = $line;
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
                $tokens[] = ["identifier", substr($source, $start, $i - $start), $start_line];
                continue;
            }

            // Punctuation: try a few multi-char operators first.
            $two = $i + 1 < $len ? $c . $source[$i + 1] : "";

            $two_char_ops = ["==", "~=", "<=", ">=", "..", "::"];

            $three = $i + 2 < $len ? $c . $source[$i + 1] . $source[$i + 2] : "";

            if ($three === "...")
            {
                $tokens[] = ["punctuation", "...", $line];
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
    // Versucht, ab Position $i in $source eine Lua-Long-Bracket-Eroeffnung
    // "[" "="* "[" zu lesen. Liefert null wenn das Muster nicht passt; sonst
    // ["next" => Index nach dem zweiten "[", "level" => Anzahl "="-Zeichen].
    // Macht keine Side-Effects.
    // @end-spec
    private static function try_read_long_bracket_open(string $source, int $i, int $len): ?array
    {
        if ($i >= $len || $source[$i] !== "[")
        {
            return null;
        }
        $j = $i + 1;
        $level = 0;
        while ($j < $len && $source[$j] === "=")
        {
            $level++;
            $j++;
        }
        if ($j >= $len || $source[$j] !== "[")
        {
            return null;
        }
        return ["next" => $j + 1, "level" => $level];
    }

    // @spec
    // Versucht, ab Position $i (auf "]") eine Long-Bracket-Schliessung
    // "]" "="*$level "]" zu matchen. Liefert null wenn nicht; sonst den
    // Index NACH dem schliessenden "]" .
    // @end-spec
    private static function try_read_long_bracket_close(string $source, int $i, int $len, int $level): ?int
    {
        if ($i >= $len || $source[$i] !== "]")
        {
            return null;
        }
        $j = $i + 1;
        $eq = 0;
        while ($j < $len && $source[$j] === "=")
        {
            $eq++;
            $j++;
        }
        if ($eq !== $level)
        {
            return null;
        }
        if ($j >= $len || $source[$j] !== "]")
        {
            return null;
        }
        return $j + 1;
    }

    // ----- Walker ---------------------------------------------------------

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // dispatcht Symbol-Erkennung. Datei-Header-Spec ist der erste Spec-Block,
    // der vor dem ersten Top-Level-Deklarations-Keyword (function/local) liegt.
    // Spec-Bloecke werden NUR auf Top-Level (depth == 0) als Header- oder
    // Symbol-Spec gewertet — verschachtelte Specs in Funktions-Bodies
    // werden im Body-Walker eingesammelt.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count = count($tokens);

        $first_decl_index = lua_parser::first_decl_index($tokens);

        $file_spec        = [];
        $file_spec_taken  = false;
        $symbols          = [];
        $warnings         = [];
        $pending_spec     = null;
        $depth            = 0;

        $index = 0;

        while ($index < $count)
        {
            $token = $tokens[$index];
            $kind  = $token[0];
            $text  = $token[1];

            // Track depth so we only recognise top-level declarations.
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

            // Spec start (only meaningful at depth 0 — nested specs are
            // collected later by the body walker as they're encountered).
            if ($depth === 0 && lua_parser::is_spec_start_token($token))
            {
                $spec_block = lua_parser::read_spec_block($tokens, $index, $count);

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

            // Skip everything that doesn't claim a pending spec.
            if ($kind === "whitespace" || $kind === "newline"
                || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            // Top-level declaration keywords.
            if ($depth === 0 && $kind === "identifier")
            {
                if ($text === "function")
                {
                    $result = lua_parser::read_function_decl(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? [],
                        /* is_local = */ false
                    );
                    if ($result !== null)
                    {
                        $symbols[]    = $result["symbol"];
                        $warnings     = array_merge($warnings, $result["warnings"]);
                        $pending_spec = null;
                        $index        = $result["next_index"];
                        continue;
                    }
                }

                if ($text === "local")
                {
                    // "local function name(...)" or "local name = ..."
                    $look = lua_parser::peek_next_significant($tokens, $index + 1, $count);
                    if ($look !== null
                        && $look["token"][0] === "identifier"
                        && $look["token"][1] === "function")
                    {
                        $result = lua_parser::read_function_decl(
                            $tokens, $look["index"], $count,
                            $pending_spec["lines"] ?? [],
                            /* is_local = */ true
                        );
                        if ($result !== null)
                        {
                            $symbols[]    = $result["symbol"];
                            $warnings     = array_merge($warnings, $result["warnings"]);
                            $pending_spec = null;
                            $index        = $result["next_index"];
                            continue;
                        }
                    }

                    $result = lua_parser::read_local_var(
                        $tokens, $index, $count,
                        $pending_spec["lines"] ?? []
                    );
                    if ($result !== null)
                    {
                        $symbols[]    = $result["symbol"];
                        $warnings     = array_merge($warnings, $result["warnings"]);
                        $pending_spec = null;
                        $index        = $result["next_index"];
                        continue;
                    }
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
    // (function, local) oder null wenn keines existiert. Dient zur
    // Heuristik "Datei-Header-Spec".
    // @end-spec
    private static function first_decl_index(array $tokens): ?int
    {
        $count = count($tokens);
        $depth = 0;
        for ($i = 0; $i < $count; $i++)
        {
            $t = $tokens[$i];
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
            if ($depth === 0 && $t[0] === "identifier"
                && ($t[1] === "function" || $t[1] === "local"))
            {
                return $i;
            }
        }
        return null;
    }

    // @spec
    // True, wenn das Token ein comment_line-Token mit Inhalt "-- @spec"
    // (optional gefolgt von Space + Inline-Inhalt) ist.
    // Block-Kommentare und Long-Strings zaehlen NICHT.
    // @end-spec
    private static function is_spec_start_token($token): bool
    {
        if ($token[0] !== "comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        if (substr($text, 0, 2) !== "--")
        {
            return false;
        }
        $body = ltrim(substr($text, 2));
        return $body === "@spec"
            || str_starts_with($body, "@spec ")
            || str_starts_with($body, "@spec\t");
    }

    // @spec
    // True, wenn das Token ein comment_line-Token mit Inhalt "-- @end-spec"
    // ist.
    // @end-spec
    private static function is_spec_end_token($token): bool
    {
        if ($token[0] !== "comment_line")
        {
            return false;
        }
        $text = rtrim($token[1]);
        if (substr($text, 0, 2) !== "--")
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
    // Ende werden nur comment_line-Token (Inhalt) und whitespace/newline
    // akzeptiert; alles andere schliesst den Block (Walker nimmt das Token
    // dann als naechstes).
    // @end-spec
    private static function read_spec_block(array $tokens, int &$index, int $count): array
    {
        $start_index = $index;
        $start_token = $tokens[$index];
        $start_line  = $start_token[2];

        $lines = [];

        // First token: -- @spec marker, possibly with inline content.
        $first_text = rtrim($start_token[1]);
        $first_body = ltrim(substr($first_text, 2)); // strip "--"
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

            if (lua_parser::is_spec_end_token($token))
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
                // strip leading "--"
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

            // Anything else (block comment, long string, code) closes the
            // block without @end-spec — pending; if no symbol follows it
            // becomes dangling.
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
    // "function"-Identifier-Token. Erkennt:
    //   function name(args)              -> kind=function
    //   function table.method(args)      -> kind=method, name="table.method"
    //   function obj:method(args)        -> kind=method, name="obj:method"
    //   local function name(args)        -> kind=local-function ($is_local)
    // Sammelt Signatur als Source-String, springt dann ueber den Body
    // (end-counting) und sammelt lokale Spec-Bloecke als members[].
    // @end-spec
    private static function read_function_decl(
        array $tokens, int $index, int $count, array $spec, bool $is_local
    ): ?array
    {
        $sig_start = $index;
        // Step over "function".
        $index++;

        // Read qualified name: ident ("." ident)* (":" ident)?
        $index = lua_parser::skip_ws_inline($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name_parts = [];
        $name_parts[] = $tokens[$index][1];
        $index++;

        $is_qualified = false;
        $is_colon     = false;

        while ($index < $count)
        {
            $next = lua_parser::peek_next_significant($tokens, $index, $count);
            if ($next === null)
            {
                break;
            }
            $tk = $next["token"];
            if ($tk[0] === "punctuation" && ($tk[1] === "." || $tk[1] === ":"))
            {
                $sep = $tk[1];
                if ($sep === ":") { $is_colon = true; }
                $is_qualified = true;
                $index = $next["index"] + 1;
                $look = lua_parser::peek_next_significant($tokens, $index, $count);
                if ($look === null || $look["token"][0] !== "identifier")
                {
                    break;
                }
                $name_parts[] = $sep . $look["token"][1];
                $index = $look["index"] + 1;
                continue;
            }
            break;
        }

        $name = "";
        foreach ($name_parts as $p)
        {
            $name .= $p;
        }

        // Parameter list "(...)"
        $index = lua_parser::skip_ws_inline($tokens, $index, $count);

        if ($index >= $count
            || $tokens[$index][0] !== "punctuation"
            || $tokens[$index][1] !== "(")
        {
            return null;
        }

        $params = lua_parser::read_balanced($tokens, $index, $count, "(", ")");

        // Build signature source string.
        $sig = "function " . $name . $params;
        $sig = lua_parser::normalise_signature($sig);

        // Now skip body. In Lua a function body ends at "end". Track block
        // openers/closers.
        $body_start = $index;
        $body_end   = lua_parser::skip_to_block_end($tokens, $body_start, $count);

        // Collect local spec blocks inside body.
        $body_members = lua_parser::collect_local_specs($tokens, $body_start, $body_end);

        if ($is_local)
        {
            $kind = "local-function";
        }
        else if ($is_qualified)
        {
            $kind = "method";
        }
        else
        {
            $kind = "function";
        }

        $symbol = [
            "kind"      => $kind,
            "name"      => $name,
            "signature" => $sig,
            "spec"      => $spec,
            "members"   => $body_members,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $body_end,
        ];
    }

    // @spec
    // Liest eine "local name = ..." Top-Level-Deklaration. Erwartet $index
    // auf dem "local"-Identifier-Token (NICHT "local function" — das wird
    // im Walker bereits abgefangen).
    // Wenn die RHS mit "{" startet -> kind="table", die Felder im Tabellen-
    // Literal werden auf Spec-Bloecke gescannt und als members[] mit
    // kind="field" zurueckgegeben.
    // Sonst -> kind="local-var", default = Source-String der RHS bis Zeilen-
    // ende (depth 0).
    // @end-spec
    private static function read_local_var(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        // skip "local"
        $index++;
        $index = lua_parser::skip_ws_inline($tokens, $index, $count);

        if ($index >= $count || $tokens[$index][0] !== "identifier")
        {
            return null;
        }

        $name = $tokens[$index][1];
        $index++;

        // Look for "=" — if absent, "local name" alone is a forward-decl
        // and gets kind=local-var with empty default.
        $index = lua_parser::skip_ws_inline($tokens, $index, $count);

        $found_equals = false;
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "=")
        {
            $found_equals = true;
            $index++;
            $index = lua_parser::skip_ws_inline($tokens, $index, $count);
        }

        if ( ! $found_equals)
        {
            // "local name" alone — forward-style decl, no value.
            $symbol = [
                "kind" => "local-var",
                "name" => $name,
                "spec" => $spec,
            ];
            return [
                "symbol"     => $symbol,
                "warnings"   => [],
                "next_index" => $index,
            ];
        }

        // RHS starts with "{" -> table literal.
        if ($index < $count
            && $tokens[$index][0] === "punctuation"
            && $tokens[$index][1] === "{")
        {
            $tab = lua_parser::read_table_literal($tokens, $index, $count);

            $symbol = [
                "kind"    => "table",
                "name"    => $name,
                "spec"    => $spec,
                "members" => $tab["members"],
            ];

            return [
                "symbol"     => $symbol,
                "warnings"   => $tab["warnings"],
                "next_index" => $tab["next_index"],
            ];
        }

        // Scalar local. Read RHS as source string until top-level newline.
        $default = lua_parser::read_expression_until_newline($tokens, $index, $count);

        $symbol = [
            "kind"    => "local-var",
            "name"    => $name,
            "default" => $default["text"],
            "spec"    => $spec,
        ];

        return [
            "symbol"     => $symbol,
            "warnings"   => [],
            "next_index" => $default["next_index"],
        ];
    }

    // @spec
    // Liest ein Tabellen-Literal "{ ... }" ab $index (der auf "{" steht).
    // Sammelt Felder als kind=field-Members. Erkannt werden Felder der
    // Form "name = value" (oder "name = value,") — Spec-Bloecke direkt
    // davor gehoeren zum Feld. Pures positional table content (ohne Name)
    // wird nicht als field gemeldet.
    // Avanciert hinter das matched "}".
    // @end-spec
    private static function read_table_literal(array $tokens, int $index, int $count): array
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

            if ($kind === "punctuation")
            {
                if ($text === "{" || $text === "(" || $text === "[")
                {
                    $depth++;
                    $index++;
                    continue;
                }
                if ($text === "}" || $text === ")" || $text === "]")
                {
                    $depth--;
                    $index++;
                    if ($depth === 0)
                    {
                        break;
                    }
                    continue;
                }
                // "," at depth 1 separates entries; nothing special to do.
                $index++;
                continue;
            }

            if ($kind === "whitespace" || $kind === "newline")
            {
                $index++;
                continue;
            }

            if ($depth === 1 && lua_parser::is_spec_start_token($token))
            {
                $block = lua_parser::read_spec_block($tokens, $index, $count);
                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $block;
                continue;
            }

            // Skip non-spec line/block comments.
            if ($kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }

            // Try to read a field "name = value(,|})" at depth 1.
            if ($depth === 1 && $kind === "identifier")
            {
                $field_start = $index;
                $look_eq = lua_parser::peek_next_significant($tokens, $index + 1, $count);
                if ($look_eq !== null
                    && $look_eq["token"][0] === "punctuation"
                    && $look_eq["token"][1] === "=")
                {
                    $field_name = $token[1];
                    $j = $look_eq["index"] + 1; // past "="
                    $j = lua_parser::skip_ws_inline($tokens, $j, $count);

                    $rhs = lua_parser::read_field_rhs($tokens, $j, $count);

                    $entry = [
                        "kind"    => "field",
                        "name"    => $field_name,
                        "default" => $rhs["text"],
                        "spec"    => $pending_spec !== null ? $pending_spec["lines"] : [],
                    ];
                    $members[]    = $entry;
                    $pending_spec = null;
                    $index        = $rhs["next_index"];
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
    // Liest die RHS eines Tabellen-Felds ab $index. Stoppt bei Top-Level-
    // (Tiefe-0-)"," oder "}". Liefert ["text"=>String, "next_index"=>nach
    // Wert, exklusive des Stop-Tokens]. Whitespace komprimiert auf Leerzeichen.
    // @end-spec
    private static function read_field_rhs(array $tokens, int $index, int $count): array
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
                if ($depth === 0 && ($text === "," || $text === "}"))
                {
                    return ["text" => trim($out), "next_index" => $index];
                }
                if ($text === "(" || $text === "[" || $text === "{")
                {
                    $depth++;
                }
                else if ($text === ")" || $text === "]" || $text === "}")
                {
                    if ($depth > 0)
                    {
                        $depth--;
                    }
                    else
                    {
                        return ["text" => trim($out), "next_index" => $index];
                    }
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace" || $kind === "newline")
            {
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

        return ["text" => trim($out), "next_index" => $index];
    }

    // @spec
    // Liest einen Expression-Source-String bis zum naechsten Top-Level-
    // Newline. Respektiert geschachtelte (), [], {}. Whitespace zu einzelnen
    // Spaces komprimiert. Genutzt fuer "local name = <skalar>"-RHS.
    // @end-spec
    private static function read_expression_until_newline(array $tokens, int $index, int $count): array
    {
        $depth = 0;
        $out   = "";

        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind === "newline" && $depth === 0)
            {
                return ["text" => trim($out), "next_index" => $index];
            }

            if ($kind === "punctuation")
            {
                if ($text === "(" || $text === "[" || $text === "{")
                {
                    $depth++;
                }
                else if ($text === ")" || $text === "]" || $text === "}")
                {
                    if ($depth > 0)
                    {
                        $depth--;
                    }
                    else
                    {
                        return ["text" => trim($out), "next_index" => $index];
                    }
                }
                $out .= $text;
                $index++;
                continue;
            }

            if ($kind === "whitespace" || $kind === "newline")
            {
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

        return ["text" => trim($out), "next_index" => $index];
    }

    // @spec
    // Liest "(....)" als Source-String inkl. der aeusseren Klammern.
    // Whitespace zu einzelnen Spaces komprimiert. Avanciert $index hinter
    // die schliessende Klammer.
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

            if ($kind === "whitespace" || $kind === "newline")
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
    // Springt ueber einen Lua-Funktions-Body hinweg. Erwartet $index direkt
    // nach der Argument-Liste (also nach dem schliessenden ")"). In Lua
    // schliessen "if/for/while/function/do" mit "end"; "repeat" schliesst
    // mit "until". Wir starten bei block-depth 1 (die Funktion selbst) und
    // stoppen, wenn wir das matched "end" finden. Strings, Long-Strings,
    // Block- und Zeilen-Kommentare werden vom Tokenizer schon klassifiziert
    // — wir muessen also nur Identifier-Token-Matchen.
    //
    // Liefert den Index direkt NACH dem schliessenden "end".
    //
    // Edge-Cases:
    //   - "return ... end" ist guelting; wir sehen "end" nur nach einer
    //     "return"-Statement-Position, das bricht nichts.
    //   - "elseif"/"else" sind keine Block-Opener — sie sind Block-
    //     Mid-Markers innerhalb eines if. Sie zaehlen weder hoch noch runter.
    //   - "in" / "then" sind Mid-Keywords ohne Effekt auf den Counter.
    // @end-spec
    private static function skip_to_block_end(array $tokens, int $index, int $count): int
    {
        $depth = 1;

        // Use a stack to know which closer matches: "end" or "until".
        // Stack entries: "end" or "until".
        $stack = ["end"]; // outer function body itself

        while ($index < $count && $depth > 0)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            $text = $t[1];

            if ($kind !== "identifier")
            {
                $index++;
                continue;
            }

            // Block openers that pair with "end".
            //
            // Each of {function, if, do} pairs with exactly one "end".
            // For "while x do ... end" and "for ... do ... end" we
            // intentionally do NOT count "while" / "for" as openers — the
            // following "do" is the actual block opener that pairs with
            // "end". Counting both would expect two "end"s. "elseif",
            // "else", "then", "in" are mid-keywords with no counter effect.
            if ($text === "function" || $text === "if" || $text === "do")
            {
                $depth++;
                $stack[] = "end";
                $index++;
                continue;
            }

            if ($text === "repeat")
            {
                $depth++;
                $stack[] = "until";
                $index++;
                continue;
            }

            if ($text === "until")
            {
                if ( ! empty($stack) && end($stack) === "until")
                {
                    array_pop($stack);
                    $depth--;
                }
                $index++;
                if ($depth === 0)
                {
                    return $index;
                }
                continue;
            }

            if ($text === "end")
            {
                if ( ! empty($stack) && end($stack) === "end")
                {
                    array_pop($stack);
                    $depth--;
                }
                else
                {
                    // Mismatched — pop conservatively.
                    if ( ! empty($stack)) { array_pop($stack); }
                    $depth--;
                }
                $index++;
                if ($depth === 0)
                {
                    return $index;
                }
                continue;
            }

            $index++;
        }

        return $index;
    }

    // @spec
    // Sammelt lokale Spec-Bloecke innerhalb eines Funktions-Body. Body geht
    // von $start_index bis $end_index (exklusiv, also genau die Tokens, die
    // skip_to_block_end vor dem schliessenden "end" konsumiert hat — wir
    // bekommen end_index = position NACH dem "end", aber das Spec-Sammeln
    // ist tolerant gegenueber dem letzten "end"-Identifier am Ende, weil
    // is_spec_start_token nur auf comment_line matcht).
    // Liefert eine members[]-Liste mit kind=local; warnings landen NICHT
    // in einer separaten Liste — pragmatisch verwerfen wir dangling locals
    // im Body still (sie haben keinen folgenden Symbol-Zielcontext).
    // @end-spec
    private static function collect_local_specs(array $tokens, int $start_index, int $end_index): array
    {
        $members = [];
        $cur = $start_index;
        while ($cur < $end_index)
        {
            $t = $tokens[$cur];
            if (lua_parser::is_spec_start_token($t))
            {
                $block = lua_parser::read_spec_block($tokens, $cur, $end_index);
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
        return $members;
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
    // Peekt das naechste signifikante Token (nicht whitespace/newline/comment)
    // ab Position $index. Liefert ["token" => ..., "index" => i] oder null.
    // @end-spec
    private static function peek_next_significant(array $tokens, int $index, int $count): ?array
    {
        while ($index < $count)
        {
            $t = $tokens[$index];
            $kind = $t[0];
            if ($kind === "whitespace" || $kind === "newline"
                || $kind === "comment_line" || $kind === "comment_block")
            {
                $index++;
                continue;
            }
            return ["token" => $t, "index" => $index];
        }
        return null;
    }

    // @spec
    // Komprimiert Whitespace im Signatur-String. Spaces vor "(", ")",
    // ",", ":" entfernt; nach "," wieder genau ein Space. Reine String-
    // Manipulation, kein Regex (uebernommen aus js_parser/nim_parser).
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

            if ($c === "," && $next !== "" && $next !== " " && $next !== ")")
            {
                $out .= " ";
            }
        }

        return trim($out);
    }
}
