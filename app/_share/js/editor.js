// @spec
// editor.js — duenner Wrapper um CodeMirror 5 (vendored unter
// /_share/vendor/js/codemirror.min.js). Eingebunden in `app/plan.php`
// und `app/info.php` zusammen mit Mode-Files unter
// `/_share/vendor/js/codemirror-modes/` und der `codemirror.css`.
// Decision-File: `pm/decisions/0007-editor-vendoring.md`.
//
// Verantwortung: eine einzelne Editor-Instanz pro Seite mounten,
// den Buffer-Inhalt abfragen, Editor wieder abbauen, oder den
// Buffer per POST an `/pm.php?action=save&path=...` speichern.
// Modul-State ist `cm_instance` — wir halten genau eine Instanz
// fest, mount() raeumt eine vorhandene Instanz vorher per destroy()
// ab (Out-of-scope: mehrere Editor-Instanzen parallel).
//
// Seit M013/0002 stehen mehrere Modes zur Verfuegung (markdown, php,
// javascript, clike, shell, css, xml/htmlmixed, yaml). `mount()` hat
// einen optionalen `mode_name`-Parameter; Default ist `"markdown"`
// fuer Rueckwaertskompatibilitaet mit Plan- und Info-View. Der
// Helper `extension_to_mode(extension)` mappt eine Datei-Extension
// (z.B. `"php"`, `".ts"`, `"lua"`) auf den passenden CodeMirror-
// Mode-String — Aufrufer in der Tree-View ziehen sich den Mode so
// aus dem Dateipfad. Unbekannte Extensions liefern `null`; mount()
// behandelt `null`/leer als „kein Mode setzen", also Plaintext.
//
// NICHT zustaendig fuer: DOM-Layout, Edit/Save/Cancel-Buttons,
// Reload nach Save, Sichtbarkeits-Regeln (z.B. archive/ read-only).
// Aufrufer (plan_loader.js, info-loader, ...) bauen ihre Buttons
// selbst und rufen mount/save/destroy. Save aus diesem Layer wird
// nicht automatisch getriggert — der Aufrufer entscheidet wann.
//
// Expose: window.speckig_editor = { mount, get_value, destroy, save,
// extension_to_mode }.
// Style (Decision 0004 + code_style.md): BSD-Klammern, snake_case,
// `what_cond_means`-Pattern, let/const, async/await, defensive
// try/catch um fetch — Vorbild ist `plan_loader.js`.
//
// Endpoint-Vertrag (siehe app/pm.php, M012/0002 und app/file.php, M013/0001):
//   save(path) routet nach Pfad-Prefix:
//     - path.startsWith("pm/")  ->  POST /pm.php?action=save&path=...
//     - sonst                   ->  POST /file.php?action=save&path=...
//   Body: roher Datei-Inhalt (text/plain).
//   Erfolg : HTTP 200 + { ok: true,  path }
//   Fehler : HTTP 400 + { ok: false, message }
// save() reicht das Resultat als { ok, message? } an den Aufrufer.
// Die Routing-Heuristik macht die Plan-/Info-View-Saves (alle Pfade unter
// `pm/`) weiter ueber pm.php; alle anderen Pfade (Code-Files in der
// Tree-View) gehen an file.php — das M013/0001-Endpoint mit
// SPECKIG_ROOT-Scope und der gleichen Schwarzliste wie GET.
// @end-spec

(function ()
{
    let cm_instance = null;

    function mount(article_element, raw_markdown, path, mode_name)
    {
        let article_exists = article_element !== null && article_element !== undefined;

        if (! article_exists)
        {
            return null;
        }

        let codemirror_is_loaded = typeof window.CodeMirror === "function";

        if (! codemirror_is_loaded)
        {
            console.warn("speckig_editor.mount: CodeMirror global missing");
            return null;
        }

        // Nur eine Instanz pro Seite — alte Instanz vorher sauber abbauen,
        // sonst leakt der vorherige Editor im DOM (toTextArea() wird nicht
        // aufgerufen) und cm_instance zeigt auf einen toten Knoten.
        let previous_instance_exists = cm_instance !== null;

        if (previous_instance_exists)
        {
            destroy();
        }

        article_element.innerHTML = "";

        let textarea_element = document.createElement("textarea");
        textarea_element.className = "speckig-editor-textarea";

        let initial_buffer_is_string = typeof raw_markdown === "string";

        if (initial_buffer_is_string)
        {
            textarea_element.value = raw_markdown;
        }
        else
        {
            textarea_element.value = "";
        }

        // `path` ist rein informativ — als data-Attribut sichtbar in DevTools
        // (kein funktionaler Effekt). Ticket 0004 reicht den Pfad so durch.
        let path_is_set = typeof path === "string" && path !== "";

        if (path_is_set)
        {
            textarea_element.setAttribute("data-path", path);
        }

        article_element.appendChild(textarea_element);

        // mode_name-Auswahl:
        // - undefined  (alter 3-Arg-Aufruf)  → Fallback auf "markdown".
        // - null / ""                        → kein mode-Option ⇒ Plaintext.
        // - sonst                            → mode_name wird durchgereicht.
        let mode_arg_omitted = mode_name === undefined;
        let mode_explicit_plain =
            mode_name === null
            || (typeof mode_name === "string" && mode_name === "");

        let cm_options = {
            lineNumbers:  true,
            lineWrapping: true,
        };

        if (mode_arg_omitted)
        {
            cm_options.mode = "markdown";
        }
        else if (mode_explicit_plain)
        {
            // bewusst kein mode setzen → CodeMirror laeuft im text/plain-Default
        }
        else
        {
            cm_options.mode = mode_name;
        }

        cm_instance = window.CodeMirror.fromTextArea(textarea_element, cm_options);

        return cm_instance;
    }

    function extension_to_mode(extension)
    {
        let ext_is_string = typeof extension === "string";

        if (! ext_is_string)
        {
            return null;
        }

        let ext_normalized = extension.toLowerCase().replace(/^\./, "");

        // Mapping. Kein Switch, plain if-Chain damit jeder Mapping-Pfad
        // visuell einzeln steht.
        let is_markdown = ext_normalized === "md" || ext_normalized === "markdown";
        if (is_markdown) { return "markdown"; }

        let is_php = ext_normalized === "php" || ext_normalized === "phtml";
        if (is_php) { return "application/x-httpd-php"; }

        let is_js_family =
            ext_normalized === "js"
            || ext_normalized === "ts"
            || ext_normalized === "tsx"
            || ext_normalized === "jsx"
            || ext_normalized === "mjs";
        if (is_js_family) { return "javascript"; }

        let is_clike_family =
            ext_normalized === "lua"
            || ext_normalized === "nim"
            || ext_normalized === "groovy"
            || ext_normalized === "java"
            || ext_normalized === "c"
            || ext_normalized === "cpp"
            || ext_normalized === "h"
            || ext_normalized === "cs";
        if (is_clike_family) { return "text/x-csrc"; }

        let is_shell =
            ext_normalized === "sh"
            || ext_normalized === "bash"
            || ext_normalized === "zsh";
        if (is_shell) { return "shell"; }

        let is_css = ext_normalized === "css";
        if (is_css) { return "css"; }

        let is_xml_family =
            ext_normalized === "xml"
            || ext_normalized === "html"
            || ext_normalized === "htm";
        if (is_xml_family) { return "xml"; }

        let is_yaml = ext_normalized === "yaml" || ext_normalized === "yml";
        if (is_yaml) { return "yaml"; }

        return null;
    }

    function get_value()
    {
        let instance_exists = cm_instance !== null;

        if (! instance_exists)
        {
            return "";
        }

        return cm_instance.getValue();
    }

    function destroy()
    {
        let instance_exists = cm_instance !== null;

        if (! instance_exists)
        {
            return;
        }

        cm_instance.toTextArea();
        cm_instance = null;
    }

    async function save(path)
    {
        let path_is_valid = typeof path === "string" && path !== "";

        if (! path_is_valid)
        {
            return { ok: false, message: "Kein Pfad zum Speichern." };
        }

        // Pfad-Routing: pm/-Pfade gehen weiter an pm.php (M012/0002),
        // alles andere an file.php (M013/0001). startsWith("pm/") ist
        // bewusst praefix-exakt — ein Pfad wie "app/pm.php" matched
        // nicht, was korrekt ist (das ist Code, kein PM-Markdown).
        let path_is_pm = path.indexOf("pm/") === 0;

        let fetch_url = path_is_pm
            ? "/pm.php?action=save&path=" + encodeURIComponent(path)
            : "/file.php?action=save&path=" + encodeURIComponent(path);

        let body_text = get_value();

        let response = null;

        try
        {
            response = await fetch(fetch_url, {
                method:  "POST",
                headers: { "Content-Type": "text/plain" },
                body:    body_text,
            });
        }
        catch (network_failed)
        {
            console.warn("speckig_editor.save: network error for path", path, network_failed);
            return { ok: false, message: "Netzwerkfehler beim Speichern." };
        }

        let data = null;

        try
        {
            data = await response.json();
        }
        catch (json_parse_failed)
        {
            console.warn("speckig_editor.save: bad json for path", path, json_parse_failed);
            return { ok: false, message: "Speichern fehlgeschlagen." };
        }

        let response_signals_error =
            response.ok === false
            || data === null
            || data.ok === false;

        if (response_signals_error)
        {
            let server_message =
                data !== null && typeof data.message === "string" && data.message !== ""
                    ? data.message
                    : "Speichern fehlgeschlagen.";

            return { ok: false, message: server_message };
        }

        return { ok: true };
    }

    window.speckig_editor = {
        mount:             mount,
        get_value:         get_value,
        destroy:           destroy,
        save:              save,
        extension_to_mode: extension_to_mode,
    };
})();
