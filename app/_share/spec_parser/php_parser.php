<?php

declare(strict_types=1);

// @spec
// PHP-Spec-Parser. Liest // @spec ... // @end-spec-Bloecke aus PHP-Dateien
// und ordnet sie dem direkt darauffolgenden Symbol zu (Klasse, Interface,
// Trait, Funktion, Methode, Property, Konstante, oder lokale Stelle in
// einer Methode). Schema und Beispiele in app/_share/spec_parser/README.md.
// Implementierung nutzt ausschliesslich token_get_all (Decision 0006),
// kein Regex auf Quelltext. Strings, Heredoc, Nowdoc und Doc-Comments
// werden vom Tokenizer korrekt von One-Line-T_COMMENT getrennt — Spec-
// Marker innerhalb von Strings/Heredoc/Doc-Comments werden nie erkannt.
// @end-spec

namespace _share\spec_parser;

// @spec
// Statisches Funktions-Buendel — Lowercase-Klassenname nach Decision 0003.
// @end-spec
class php_parser
{

    // @spec
    // Parst eine PHP-Datei und liefert das Schema-Array
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

        $tokens = token_get_all($source);

        return php_parser::walk_tokens($tokens);
    }

    // @spec
    // Top-Level-Walker. Geht durch die Token-Liste, sammelt Spec-Bloecke,
    // dispatcht Symbol-Erkennung. Datei-Header-Spec ist der erste Spec-Block,
    // der vor dem ersten T_NAMESPACE liegt — alles andere ist Symbol-Spec.
    // @end-spec
    private static function walk_tokens(array $tokens): array
    {
        $count          = count($tokens);
        $namespace_line = php_parser::first_namespace_line($tokens);

        $file_spec        = [];
        $file_spec_taken  = false;
        $symbols          = [];
        $warnings         = [];
        $pending_spec     = null;

        $index = 0;

        while ($index < $count)
        {
            $token = $tokens[$index];

            $is_spec_start = php_parser::is_spec_start_token($token);

            if ($is_spec_start)
            {
                $spec_block = php_parser::read_spec_block($tokens, $index, $count);

                $spec_starts_before_namespace =
                    $namespace_line !== null
                    && $spec_block["start_line"] < $namespace_line;

                $file_header_unclaimed_yet = ! $file_spec_taken;

                if ($spec_starts_before_namespace && $file_header_unclaimed_yet)
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

            // class / interface / trait at top-level
            if (php_parser::token_type($token) === T_CLASS
                || php_parser::token_type($token) === T_INTERFACE
                || php_parser::token_type($token) === T_TRAIT)
            {
                $kind_map = [
                    T_CLASS     => "class",
                    T_INTERFACE => "interface",
                    T_TRAIT     => "trait",
                ];
                $kind = $kind_map[php_parser::token_type($token)];

                $symbol = php_parser::read_class_like(
                    $tokens, $index, $count, $kind,
                    $pending_spec["lines"] ?? []
                );

                $symbols[]    = $symbol["symbol"];
                $warnings     = array_merge($warnings, $symbol["warnings"]);
                $pending_spec = null;
                $index        = $symbol["next_index"];
                continue;
            }

            // top-level function
            if (php_parser::token_type($token) === T_FUNCTION)
            {
                $symbol = php_parser::read_function(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? [],
                    /* inside_class = */ false
                );

                if ($symbol !== null)
                {
                    $symbol["symbol"]["kind"] = "function";
                    $symbols[]                = $symbol["symbol"];
                    $warnings                 = array_merge($warnings, $symbol["warnings"]);
                    $pending_spec             = null;
                    $index                    = $symbol["next_index"];
                    continue;
                }
            }

            // top-level const
            if (php_parser::token_type($token) === T_CONST)
            {
                $symbol = php_parser::read_const(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? []
                );
                $symbols[]    = $symbol["symbol"];
                $pending_spec = null;
                $index        = $symbol["next_index"];
                continue;
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
    // Liefert die Zeilennummer des ersten T_NAMESPACE-Tokens,
    // oder null wenn die Datei keines hat.
    // @end-spec
    private static function first_namespace_line(array $tokens): ?int
    {
        foreach ($tokens as $token)
        {
            if (php_parser::token_type($token) === T_NAMESPACE)
            {
                return $token[2];
            }
        }
        return null;
    }

    // @spec
    // Liefert true, wenn das Token ein T_COMMENT mit getrimmtem Inhalt
    // "// @spec" oder "// @spec <text>" ist. Doc-Comments (T_DOC_COMMENT)
    // und #-Kommentare zaehlen NICHT als Spec-Marker.
    // @end-spec
    private static function is_spec_start_token($token): bool
    {
        if (php_parser::token_type($token) !== T_COMMENT)
        {
            return false;
        }

        $text    = rtrim($token[1]);
        $is_slash = substr($text, 0, 2) === "//";

        if ( ! $is_slash)
        {
            return false;
        }

        $body = ltrim(substr($text, 2));

        return $body === "@spec" || str_starts_with($body, "@spec ") || str_starts_with($body, "@spec\t");
    }

    // @spec
    // Liefert true, wenn das Token ein T_COMMENT mit "// @end-spec" ist.
    // @end-spec
    private static function is_spec_end_token($token): bool
    {
        if (php_parser::token_type($token) !== T_COMMENT)
        {
            return false;
        }

        $text     = rtrim($token[1]);
        $is_slash = substr($text, 0, 2) === "//";

        if ( ! $is_slash)
        {
            return false;
        }

        $body = ltrim(substr($text, 2));

        return $body === "@end-spec" || str_starts_with($body, "@end-spec ") || str_starts_with($body, "@end-spec\t");
    }

    // @spec
    // Liest einen kompletten Spec-Block ab Index $index (der auf einem
    // Spec-Start-Token steht). Returnt:
    //   ["lines" => [<spec content lines>], "start_line" => N]
    // und avanciert $index hinter das @end-spec-Token. Wenn kein @end-spec
    // gefunden wird, behandelt der Walker das nachher als dangling.
    // Format-Varianten:
    //   "// @spec"                -> start, no inline content
    //   "// @spec login handle"   -> start, "login handle" als erste spec-zeile
    //   Zwischenzeilen "// foo"   -> "foo" als spec-zeile
    //   "// @end-spec"            -> end
    // @end-spec
    private static function read_spec_block(array $tokens, int &$index, int $count): array
    {
        $start_token = $tokens[$index];
        $start_line  = $start_token[2];
        $lines       = [];

        // First token: @spec marker, possibly with inline content.
        $first_text = rtrim($start_token[1]);
        $first_body = ltrim(substr($first_text, 2)); // strip "//"
        // strip leading "@spec"
        $after_marker = ltrim(substr($first_body, strlen("@spec")));

        if ($after_marker !== "")
        {
            $lines[] = $after_marker;
        }

        $index++;

        while ($index < $count)
        {
            $token = $tokens[$index];

            if (php_parser::token_type($token) === T_WHITESPACE)
            {
                $index++;
                continue;
            }

            if (php_parser::is_spec_end_token($token))
            {
                $index++;
                return ["lines" => $lines, "start_line" => $start_line];
            }

            if (php_parser::token_type($token) === T_COMMENT)
            {
                $text    = rtrim($token[1]);
                $is_slash = substr($text, 0, 2) === "//";

                if ( ! $is_slash)
                {
                    // # comment between spec markers — block is broken.
                    return ["lines" => $lines, "start_line" => $start_line];
                }

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

            // any non-comment, non-whitespace token: spec block was not
            // closed — treat what we have as the block end (dangling will
            // be detected later because $pending_spec stays unconsumed only
            // if the caller left it; but here we do close, so the caller
            // gets a regular block back). To keep behaviour simple and
            // safe: stop the block; the calling walker picks up the
            // current token next.
            return ["lines" => $lines, "start_line" => $start_line];
        }

        return ["lines" => $lines, "start_line" => $start_line];
    }

    // @spec
    // Token-Typ-Helper: gibt T_*-Konstante zurueck, oder null bei String-Token.
    // @end-spec
    private static function token_type($token): ?int
    {
        if (is_array($token))
        {
            return $token[0];
        }
        return null;
    }

    // @spec
    // Token-Text-Helper: gibt den Quelltext-String des Tokens zurueck.
    // @end-spec
    private static function token_text($token): string
    {
        if (is_array($token))
        {
            return $token[1];
        }
        return $token;
    }

    // @spec
    // Liest eine class/interface/trait-Deklaration ab dem T_CLASS/T_INTERFACE/
    // T_TRAIT-Token. Sammelt Name, extends, implements und parst den Body
    // (Properties, Konstanten, Methoden) bis zum schliessenden "}".
    // Returns ["symbol" => ..., "warnings" => [...], "next_index" => N].
    // @end-spec
    private static function read_class_like(
        array $tokens, int $index, int $count,
        string $kind, array $spec
    ): array
    {
        $warnings = [];

        // Move past T_CLASS/T_INTERFACE/T_TRAIT.
        $index++;
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        $name = "";
        if ($index < $count && php_parser::token_type($tokens[$index]) === T_STRING)
        {
            $name = $tokens[$index][1];
            $index++;
        }

        $extends    = [];
        $implements = [];

        // extends / implements clauses, until "{".
        while ($index < $count)
        {
            $token = $tokens[$index];

            if (is_string($token) && $token === "{")
            {
                $index++;
                break;
            }

            if (php_parser::token_type($token) === T_EXTENDS)
            {
                $index++;
                $list = php_parser::read_name_list($tokens, $index, $count);
                $extends = array_merge($extends, $list);
                continue;
            }

            if (php_parser::token_type($token) === T_IMPLEMENTS)
            {
                $index++;
                $list = php_parser::read_name_list($tokens, $index, $count);
                $implements = array_merge($implements, $list);
                continue;
            }

            $index++;
        }

        // Now inside the class body. Parse members until matching "}".
        $body = php_parser::read_class_body($tokens, $index, $count);

        $symbol = [
            "kind"       => $kind,
            "name"       => $name,
            "extends"    => $extends,
            "implements" => $implements,
            "spec"       => $spec,
            "members"    => $body["members"],
        ];

        // interfaces/traits don't use implements
        if ($kind !== "class")
        {
            unset($symbol["implements"]);
        }

        return [
            "symbol"     => $symbol,
            "warnings"   => array_merge($warnings, $body["warnings"]),
            "next_index" => $body["next_index"],
        ];
    }

    // @spec
    // Liest eine kommaseparierte Liste qualifizierter Namen
    // (T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED) bis zum
    // naechsten "{", T_EXTENDS oder T_IMPLEMENTS. Avanciert $index nicht
    // ueber das Trenner-Token hinaus.
    // @end-spec
    private static function read_name_list(array $tokens, int &$index, int $count): array
    {
        $names = [];

        while ($index < $count)
        {
            $token = $tokens[$index];

            if (is_string($token))
            {
                if ($token === ",")
                {
                    $index++;
                    continue;
                }
                if ($token === "{")
                {
                    return $names;
                }
                $index++;
                continue;
            }

            $type = php_parser::token_type($token);

            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            if ($type === T_EXTENDS || $type === T_IMPLEMENTS)
            {
                return $names;
            }

            if ($type === T_STRING
                || (defined("T_NAME_QUALIFIED") && $type === T_NAME_QUALIFIED)
                || (defined("T_NAME_FULLY_QUALIFIED") && $type === T_NAME_FULLY_QUALIFIED)
                || (defined("T_NAME_RELATIVE") && $type === T_NAME_RELATIVE))
            {
                $names[] = $token[1];
                $index++;
                continue;
            }

            // unknown — bail.
            return $names;
        }

        return $names;
    }

    // @spec
    // Liest den Body einer Klasse/Interface/Trait. Erwartet, dass $index
    // direkt hinter dem oeffnenden "{" steht. Sammelt Spec-Bloecke und
    // ordnet sie dem darauffolgenden Member zu (property, const, method).
    // Endet beim matched "}". Returnt members[], warnings, next_index.
    // @end-spec
    private static function read_class_body(array $tokens, int $index, int $count): array
    {
        $members      = [];
        $warnings     = [];
        $pending_spec = null;
        $depth        = 1; // we're inside one "{"

        while ($index < $count && $depth > 0)
        {
            $token = $tokens[$index];

            // Track raw braces to find the closing one.
            if (is_string($token))
            {
                if ($token === "{")
                {
                    // We shouldn't normally hit a "{" here at depth 1 unless
                    // it's an inline expression (uncommon at class body level).
                    $depth++;
                    $index++;
                    continue;
                }
                if ($token === "}")
                {
                    $depth--;
                    $index++;
                    if ($depth === 0)
                    {
                        break;
                    }
                    continue;
                }
                $index++;
                continue;
            }

            $token_type = php_parser::token_type($token);

            // Curly-open inside an interpolated string carries a matching raw "}"
            // — bump depth so it pops correctly.
            if ($token_type === T_CURLY_OPEN
                || (defined("T_DOLLAR_OPEN_CURLY_BRACES") && $token_type === T_DOLLAR_OPEN_CURLY_BRACES))
            {
                $depth++;
                $index++;
                continue;
            }

            if (php_parser::is_spec_start_token($token))
            {
                $spec_block = php_parser::read_spec_block($tokens, $index, $count);

                if ($pending_spec !== null)
                {
                    $warnings[] = "dangling spec at line " . $pending_spec["start_line"];
                }
                $pending_spec = $spec_block;
                continue;
            }

            // Collect modifiers ahead of a member declaration.
            if (php_parser::is_modifier_type($token_type))
            {
                $member_result = php_parser::try_read_member(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? []
                );
                if ($member_result !== null)
                {
                    $members[]    = $member_result["symbol"];
                    $warnings     = array_merge($warnings, $member_result["warnings"]);
                    $pending_spec = null;
                    $index        = $member_result["next_index"];
                    continue;
                }
                $index++;
                continue;
            }

            if ($token_type === T_FUNCTION)
            {
                $method = php_parser::read_function(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? [],
                    /* inside_class = */ true
                );
                if ($method !== null)
                {
                    $method["symbol"]["kind"] = "method";
                    $members[]                = $method["symbol"];
                    $warnings                 = array_merge($warnings, $method["warnings"]);
                    $pending_spec             = null;
                    $index                    = $method["next_index"];
                    continue;
                }
                $index++;
                continue;
            }

            if ($token_type === T_CONST)
            {
                $const = php_parser::read_const(
                    $tokens, $index, $count,
                    $pending_spec["lines"] ?? []
                );
                $members[]    = $const["symbol"];
                $pending_spec = null;
                $index        = $const["next_index"];
                continue;
            }

            // T_VARIABLE without a preceding modifier (legacy "var $x" or
            // typed prop without modifier). Treat as property.
            if ($token_type === T_VARIABLE)
            {
                $prop = php_parser::read_property(
                    $tokens, $index, $count,
                    /* modifiers = */ [],
                    /* type_text = */ "",
                    $pending_spec["lines"] ?? []
                );
                $members[]    = $prop["symbol"];
                $pending_spec = null;
                $index        = $prop["next_index"];
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
    // True fuer Sichtbarkeits-/Modifier-Tokens, die einer Property- oder
    // Methoden-Deklaration vorangehen koennen.
    // @end-spec
    private static function is_modifier_type(?int $type): bool
    {
        if ($type === null)
        {
            return false;
        }

        $modifiers = [
            T_PUBLIC, T_PROTECTED, T_PRIVATE,
            T_STATIC, T_FINAL, T_ABSTRACT,
        ];

        if (defined("T_READONLY"))
        {
            $modifiers[] = T_READONLY;
        }

        // PHP 8.4 asymmetric visibility (public(set) etc.)
        if (defined("T_PUBLIC_SET"))    { $modifiers[] = T_PUBLIC_SET; }
        if (defined("T_PROTECTED_SET")) { $modifiers[] = T_PROTECTED_SET; }
        if (defined("T_PRIVATE_SET"))   { $modifiers[] = T_PRIVATE_SET; }

        return in_array($type, $modifiers, true);
    }

    // @spec
    // Versucht, ab einem Modifier-Token einen kompletten Member zu lesen
    // (property, method, const). Sammelt alle Modifier, dann optionalen Typ
    // (bei property), dann Variable / function / const. Returns null wenn
    // sich kein Member ergibt.
    // @end-spec
    private static function try_read_member(
        array $tokens, int $index, int $count, array $spec
    ): ?array
    {
        $modifiers = [];

        while ($index < $count)
        {
            $token = $tokens[$index];
            $type  = php_parser::token_type($token);

            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            if (php_parser::is_modifier_type($type))
            {
                $modifiers[] = $tokens[$index][1];
                $index++;
                continue;
            }

            break;
        }

        if ($index >= $count)
        {
            return null;
        }

        $token = $tokens[$index];
        $type  = php_parser::token_type($token);

        if ($type === T_FUNCTION)
        {
            $method = php_parser::read_function(
                $tokens, $index, $count, $spec, /* inside_class = */ true,
                /* leading_modifiers = */ $modifiers
            );
            if ($method === null)
            {
                return null;
            }
            $method["symbol"]["kind"] = "method";
            return $method;
        }

        if ($type === T_CONST)
        {
            return php_parser::read_const($tokens, $index, $count, $spec);
        }

        // Otherwise: property declaration. Read optional type, then T_VARIABLE.
        $type_text = "";
        $type_collected = "";

        // Read type tokens until we hit T_VARIABLE.
        while ($index < $count && php_parser::token_type($tokens[$index]) !== T_VARIABLE)
        {
            $tok = $tokens[$index];
            $tok_type = php_parser::token_type($tok);

            if ($tok_type === T_WHITESPACE)
            {
                // condense multiple spaces into one
                if ($type_collected !== "" && substr($type_collected, -1) !== " ")
                {
                    $type_collected .= " ";
                }
                $index++;
                continue;
            }

            if ($tok_type === T_COMMENT || $tok_type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            $type_collected .= php_parser::token_text($tok);
            $index++;
        }

        $type_text = trim($type_collected);

        if ($index >= $count || php_parser::token_type($tokens[$index]) !== T_VARIABLE)
        {
            return null;
        }

        return php_parser::read_property(
            $tokens, $index, $count, $modifiers, $type_text, $spec
        );
    }

    // @spec
    // Liest eine Property-Deklaration ab dem T_VARIABLE-Token. Sammelt
    // optionalen Default-Wert (alles bis ";"). Returns Symbol mit
    // kind=property, name, type, default, spec.
    // @end-spec
    private static function read_property(
        array $tokens, int $index, int $count,
        array $modifiers, string $type_text, array $spec
    ): array
    {
        $name = ltrim($tokens[$index][1], "$");
        $index++;

        $default = "";

        // Skip whitespace, look for "=" or ";".
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === "=")
        {
            $index++;
            $index = php_parser::skip_whitespace($tokens, $index, $count);

            // Read default until ";", respecting nested () [] {}.
            $default = php_parser::read_expression_until_semicolon($tokens, $index, $count);
        }

        // advance past ";" if present
        while ($index < $count)
        {
            $tok = $tokens[$index];
            if (is_string($tok) && $tok === ";")
            {
                $index++;
                break;
            }
            $index++;
        }

        $symbol = [
            "kind"    => "property",
            "name"    => $name,
            "type"    => $type_text,
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
    // Liest einen Ausdruck bis zum naechsten ";" auf Top-Level (Klammer-
    // Tiefe 0) und gibt ihn als Source-String zurueck. Avanciert $index
    // bis direkt vor das ";". Whitespace wird zu einzelnen Spaces komprimiert.
    // @end-spec
    private static function read_expression_until_semicolon(
        array $tokens, int &$index, int $count
    ): string
    {
        $depth = 0;
        $out   = "";

        while ($index < $count)
        {
            $tok = $tokens[$index];

            if (is_string($tok))
            {
                if ($tok === ";" && $depth === 0)
                {
                    return rtrim($out);
                }
                if ($tok === "(" || $tok === "[" || $tok === "{")
                {
                    $depth++;
                }
                else if ($tok === ")" || $tok === "]" || $tok === "}")
                {
                    $depth--;
                }
                $out .= $tok;
                $index++;
                continue;
            }

            $type = php_parser::token_type($tok);

            if ($type === T_WHITESPACE)
            {
                if ($out !== "" && substr($out, -1) !== " ")
                {
                    $out .= " ";
                }
                $index++;
                continue;
            }

            if ($type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            // String-interpolation curly-open carries a matching raw "}".
            if ($type === T_CURLY_OPEN
                || (defined("T_DOLLAR_OPEN_CURLY_BRACES") && $type === T_DOLLAR_OPEN_CURLY_BRACES))
            {
                $depth++;
            }

            $out .= $tok[1];
            $index++;
        }

        return rtrim($out);
    }

    // @spec
    // Liest eine const-Deklaration ab dem T_CONST-Token. Erwartet:
    // const NAME[: type] = value;  -> kind=const, name, type, default.
    // Klassen-Constants koennen typed sein (PHP 8.3+); einfache Variante.
    // @end-spec
    private static function read_const(
        array $tokens, int $index, int $count, array $spec
    ): array
    {
        // skip T_CONST
        $index++;
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        // Optional type before the name (PHP 8.3+ typed constants).
        $type_text   = "";
        $type_buffer = "";

        // Look ahead: if next non-whitespace after the name is "=", then
        // there was no type. Otherwise the first sequence is type. Easier:
        // collect tokens until we see T_STRING followed by whitespace and then "=".
        // Simpler approach: read tokens, last T_STRING before "=" is the name,
        // anything before is type.
        $collected = [];
        while ($index < $count)
        {
            $tok = $tokens[$index];
            if (is_string($tok) && $tok === "=")
            {
                break;
            }
            $type = php_parser::token_type($tok);
            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }
            $collected[] = $tok;
            $index++;
        }

        // Last entry is the name; everything before is the type expression.
        $name = "";
        if ( ! empty($collected))
        {
            $last = array_pop($collected);
            if (is_array($last))
            {
                $name = $last[1];
            }
            $type_text = "";
            foreach ($collected as $t)
            {
                $type_text .= is_array($t) ? $t[1] : $t;
            }
        }

        $default = "";

        if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === "=")
        {
            $index++;
            $index = php_parser::skip_whitespace($tokens, $index, $count);
            $default = php_parser::read_expression_until_semicolon($tokens, $index, $count);
        }

        // advance past ";"
        while ($index < $count)
        {
            $tok = $tokens[$index];
            if (is_string($tok) && $tok === ";")
            {
                $index++;
                break;
            }
            $index++;
        }

        return [
            "symbol" => [
                "kind"    => "const",
                "name"    => $name,
                "type"    => $type_text,
                "default" => $default,
                "spec"    => $spec,
            ],
            "warnings"   => [],
            "next_index" => $index,
        ];
    }

    // @spec
    // Liest eine Funktions- oder Methoden-Deklaration ab dem T_FUNCTION-Token
    // (oder einem davorliegenden Modifier, wenn $leading_modifiers gesetzt).
    // Rekonstruiert die Signatur als Source-String, parst optional den Body
    // fuer lokale Spec-Bloecke (members[] mit kind=local).
    // Returns null bei Closure (function() use {}) ohne Namen.
    // @end-spec
    private static function read_function(
        array $tokens, int $index, int $count,
        array $spec, bool $inside_class,
        array $leading_modifiers = []
    ): ?array
    {
        // We may start at a modifier token or at T_FUNCTION.
        // Modifiers up front -> render in signature.
        $sig = "";

        foreach ($leading_modifiers as $mod)
        {
            $sig .= $mod . " ";
        }

        // Skip ahead to T_FUNCTION.
        while ($index < $count && php_parser::token_type($tokens[$index]) !== T_FUNCTION)
        {
            $tok = $tokens[$index];
            $type = php_parser::token_type($tok);
            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }
            // shouldn't happen, but keep going
            $index++;
        }

        if ($index >= $count)
        {
            return null;
        }

        // append "function"
        $sig .= "function";
        $index++;

        // skip whitespace, get optional & for return-by-ref, then name
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        $by_ref = "";
        if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === "&")
        {
            $by_ref = "&";
            $index++;
            $index = php_parser::skip_whitespace($tokens, $index, $count);
        }

        $name = "";
        if ($index < $count && php_parser::token_type($tokens[$index]) === T_STRING)
        {
            $name = $tokens[$index][1];
            $index++;
        }
        else
        {
            // Anonymous function. Skip silently — not a top-level symbol.
            return null;
        }

        $sig .= " " . $by_ref . $name;

        // Parse parameter list "( ... )"
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        if ($index >= $count || ! (is_string($tokens[$index]) && $tokens[$index] === "("))
        {
            return null;
        }

        $params = php_parser::read_balanced_parens($tokens, $index, $count);
        $sig .= $params;

        // Optional return type ": Type"
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === ":")
        {
            $sig .= ":";
            $index++;
            $index = php_parser::skip_whitespace($tokens, $index, $count);

            $return_type = "";
            // Read tokens until "{" (body) or ";" (abstract/interface).
            while ($index < $count)
            {
                $tok = $tokens[$index];
                if (is_string($tok) && ($tok === "{" || $tok === ";"))
                {
                    break;
                }
                $type = php_parser::token_type($tok);
                if ($type === T_WHITESPACE)
                {
                    if ($return_type !== "" && substr($return_type, -1) !== " ")
                    {
                        $return_type .= " ";
                    }
                    $index++;
                    continue;
                }
                if ($type === T_COMMENT || $type === T_DOC_COMMENT)
                {
                    $index++;
                    continue;
                }
                $return_type .= php_parser::token_text($tok);
                $index++;
            }
            $sig .= " " . trim($return_type);
        }

        // Body or ";" (interface/abstract).
        $body_members = [];

        // Skip whitespace to find "{" or ";".
        $index = php_parser::skip_whitespace($tokens, $index, $count);

        if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === ";")
        {
            $index++;
            // no body
        }
        else if ($index < $count && is_string($tokens[$index]) && $tokens[$index] === "{")
        {
            $body = php_parser::read_function_body($tokens, $index, $count);
            $body_members = $body["members"];
            $index        = $body["next_index"];
        }

        // Normalise signature whitespace.
        $sig = php_parser::normalise_signature($sig);

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
    // Liest "( ... )" als Source-String inkl. der aeusseren Klammern,
    // Whitespace zu einzelnen Spaces komprimiert. Avanciert $index hinter
    // die schliessende ")".
    // @end-spec
    private static function read_balanced_parens(
        array $tokens, int &$index, int $count
    ): string
    {
        $out   = "";
        $depth = 0;

        while ($index < $count)
        {
            $tok = $tokens[$index];

            if (is_string($tok))
            {
                if ($tok === "(")
                {
                    $depth++;
                    $out .= $tok;
                    $index++;
                    continue;
                }
                if ($tok === ")")
                {
                    $depth--;
                    $out .= $tok;
                    $index++;
                    if ($depth === 0)
                    {
                        return $out;
                    }
                    continue;
                }
                $out .= $tok;
                $index++;
                continue;
            }

            $type = php_parser::token_type($tok);

            if ($type === T_WHITESPACE)
            {
                if ($out !== "" && substr($out, -1) !== " " && substr($out, -1) !== "(")
                {
                    $out .= " ";
                }
                $index++;
                continue;
            }

            if ($type === T_COMMENT || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            $out .= $tok[1];
            $index++;
        }

        return $out;
    }

    // @spec
    // Komprimiert Whitespace im Signatur-String: mehrfache Spaces werden zu
    // einem, Spaces vor "(", ")", ",", ":" entfernt; nach "," und ":" wird
    // wieder genau ein Space gesetzt. Reine String-Manipulation, kein Regex.
    // @end-spec
    private static function normalise_signature(string $sig): string
    {
        // Pass 1: collapse all whitespace runs to single spaces.
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

        // Pass 2: char-by-char rewrite to enforce spacing around "(", ")",
        // ",", ":". Drop spaces before these; ensure single space after
        // "," and ":" (when followed by a non-space, non-":" / non-")" char).
        $out = "";
        $clen = strlen($collapsed);

        for ($i = 0; $i < $clen; $i++)
        {
            $c    = $collapsed[$i];
            $next = $i + 1 < $clen ? $collapsed[$i + 1] : "";

            // Drop a space that sits directly before "(", ")", ",", ":".
            if ($c === " " && ($next === "(" || $next === ")" || $next === "," || $next === ":"))
            {
                continue;
            }

            // Drop a space that sits directly after "(".
            if ($c === " " && substr($out, -1) === "(")
            {
                continue;
            }

            $out .= $c;

            // Ensure exactly one space after "," and ":" when followed by
            // a non-space, non-")" / non-":" character.
            if (($c === "," || $c === ":") && $next !== "" && $next !== " "
                && $next !== ")" && $next !== ":")
            {
                $out .= " ";
            }
        }

        return trim($out);
    }

    // @spec
    // Liest den Body einer Funktion/Methode ab "{". Sammelt nur lokale
    // Spec-Bloecke und gibt sie als members[] mit kind=local zurueck.
    // Avanciert $index hinter das matched "}".
    // @end-spec
    private static function read_function_body(
        array $tokens, int $index, int $count
    ): array
    {
        // expects $index on "{"
        if ( ! (is_string($tokens[$index]) && $tokens[$index] === "{"))
        {
            return ["members" => [], "next_index" => $index];
        }

        $depth   = 1;
        $index++;
        $members = [];

        while ($index < $count && $depth > 0)
        {
            $tok = $tokens[$index];

            if (is_string($tok))
            {
                if ($tok === "{")
                {
                    $depth++;
                    $index++;
                    continue;
                }
                if ($tok === "}")
                {
                    $depth--;
                    $index++;
                    if ($depth === 0)
                    {
                        break;
                    }
                    continue;
                }
                $index++;
                continue;
            }

            $type = php_parser::token_type($tok);

            // String interpolation tokens "{$x}" / "${x}" inject a "{" that
            // is closed by a raw "}". Bump depth so the closing "}" doesn't
            // pop the function body's depth.
            if ($type === T_CURLY_OPEN
                || (defined("T_DOLLAR_OPEN_CURLY_BRACES") && $type === T_DOLLAR_OPEN_CURLY_BRACES))
            {
                $depth++;
                $index++;
                continue;
            }

            if (php_parser::is_spec_start_token($tok))
            {
                $spec_block = php_parser::read_spec_block($tokens, $index, $count);

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
    // Avanciert $index ueber T_WHITESPACE, T_COMMENT (nicht-Spec), T_DOC_COMMENT.
    // @end-spec
    private static function skip_whitespace(array $tokens, int $index, int $count): int
    {
        while ($index < $count)
        {
            $tok  = $tokens[$index];
            $type = php_parser::token_type($tok);

            if ($type === T_WHITESPACE || $type === T_DOC_COMMENT)
            {
                $index++;
                continue;
            }

            if ($type === T_COMMENT && ! php_parser::is_spec_start_token($tok)
                && ! php_parser::is_spec_end_token($tok))
            {
                $index++;
                continue;
            }

            break;
        }

        return $index;
    }
}
