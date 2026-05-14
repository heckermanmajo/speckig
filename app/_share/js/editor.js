// @spec
// editor.js — duenner Wrapper um CodeMirror 5 (vendored unter
// /_share/vendor/js/codemirror.min.js). Eingebunden in `app/plan.php`
// und `app/info.php` zusammen mit dem `markdown`-Mode und der
// `codemirror.css`. Decision-File: `pm/decisions/0007-editor-vendoring.md`.
//
// Verantwortung: eine einzelne Editor-Instanz pro Seite mounten,
// den Buffer-Inhalt abfragen, Editor wieder abbauen, oder den
// Buffer per POST an `/pm.php?action=save&path=...` speichern.
// Modul-State ist `cm_instance` — wir halten genau eine Instanz
// fest, mount() raeumt eine vorhandene Instanz vorher per destroy()
// ab (Out-of-scope: mehrere Editor-Instanzen parallel).
//
// NICHT zustaendig fuer: DOM-Layout, Edit/Save/Cancel-Buttons,
// Reload nach Save, Sichtbarkeits-Regeln (z.B. archive/ read-only).
// Aufrufer (plan_loader.js, info-loader, ...) bauen ihre Buttons
// selbst und rufen mount/save/destroy. Save aus diesem Layer wird
// nicht automatisch getriggert — der Aufrufer entscheidet wann.
//
// Expose: window.speckig_editor = { mount, get_value, destroy, save }.
// Style (Decision 0004 + code_style.md): BSD-Klammern, snake_case,
// `what_cond_means`-Pattern, let/const, async/await, defensive
// try/catch um fetch — Vorbild ist `plan_loader.js`.
//
// Endpoint-Vertrag (siehe app/pm.php, M012/0002):
//   POST /pm.php?action=save&path=<pm/...md>
//   Body: rohes Markdown (text/plain).
//   Erfolg : HTTP 200 + { ok: true,  path }
//   Fehler : HTTP 400 + { ok: false, message }
// save() reicht das Resultat als { ok, message? } an den Aufrufer.
// @end-spec

(function ()
{
    let cm_instance = null;

    function mount(article_element, raw_markdown, path)
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

        cm_instance = window.CodeMirror.fromTextArea(textarea_element, {
            mode:          "markdown",
            lineNumbers:   true,
            lineWrapping:  true,
        });

        return cm_instance;
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

        let fetch_url = "/pm.php?action=save&path=" + encodeURIComponent(path);
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
        mount:     mount,
        get_value: get_value,
        destroy:   destroy,
        save:      save,
    };
})();
