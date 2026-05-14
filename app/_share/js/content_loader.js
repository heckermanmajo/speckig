// content_loader.js — AJAX-Loader fuer den Content-Bereich (Ticket 002/0005).
// Tree-Klicks laden /file.php per fetch, schreiben das HTML in <article id="content">,
// spiegeln den Pfad via history.pushState in der URL und im Header-Label.
// Laedt nach helpers.js und tree_collapse.js. Top-Level-Script, kein Module-System.
//
// Style (Decision 0004): BSD-Klammern, snake_case, `what_cond_means`-Pattern,
// let/const, async/await, defensive try/catch um fetch (Netzwerk kann wegbrechen).
//
// Endpoint-Vertrag (siehe app/file.php, Ticket 002/0004 + 005/0005):
//   Erfolg : HTTP 200 + { ok: true,  path, html, spec }
//   Fehler : HTTP 400 + { ok: false, message }
//
// `spec` ist null fuer nicht-migrierte Dateien (oder Sprachen ohne Parser),
// sonst das Schema aus app/_share/spec_parser/README.md. Render-Layer:
// render_spec_view(spec_object) — siehe unten.

(function ()
{
    let article_selector       = "article#content";
    let header_label_selector  = "#header-path-label";
    let tree_link_selector     = "nav a[href*=\"?path=\"]";
    let initial_article_html   = "";
    let document_title_base    = "speckig";

    function get_article_element()
    {
        return document.querySelector(article_selector);
    }

    function get_header_label_element()
    {
        return document.querySelector(header_label_selector);
    }

    function set_header_label(text_or_empty)
    {
        let header_label_element = get_header_label_element();

        let header_label_exists = header_label_element !== null;

        if (! header_label_exists)
        {
            return;
        }

        header_label_element.textContent = text_or_empty;
    }

    function set_document_title(path_or_empty)
    {
        let path_is_empty = path_or_empty === "" || path_or_empty === null;

        if (path_is_empty)
        {
            document.title = document_title_base;
            return;
        }

        document.title = path_or_empty + " — " + document_title_base;
    }

    function show_initial_placeholder()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        article_element.innerHTML = initial_article_html;
        set_header_label("");
        set_document_title("");
    }

    function show_invalid_path_message()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        article_element.innerHTML = "<p>Ungültiger Pfad.</p>";
        set_header_label("");
        set_document_title("");
    }

    // --- spec-view rendering (M005/0005) -----------------------------------
    //
    // render_spec_view(spec_object) baut den HTML-String fuer die Spec-View,
    // die ueber dem Code-Inhalt gerendert wird. Schema lebt in
    // app/_share/spec_parser/README.md.
    //
    // XSS-Strategie: jeder String aus dem Parser-Output (file_spec, symbols,
    // signature, name, type, default, spec, warnings) ist Code-/Doku-Inhalt
    // aus dem Repo — also potenziell beliebiger Text. Wir escapen ueber
    // escape_html(), das via DOM-textContent geht (siehe helpers.js-Pattern).
    // KEIN innerHTML mit unescaped Spec-Inhalt.
    //
    // Property-Signaturen werden synthetisiert aus dem Schema-Output
    // (Schema gibt name/type/default separat, nicht als ein Signatur-String).
    // Konvention: `public <type> $<name>[ = <default>]`. PHP-Properties haben
    // immer einen sichtbaren Modifier — der Parser gibt ihn aktuell nicht
    // separat aus, also nehmen wir "public" als Default-Anzeige. Das ist
    // bewusst pragmatisch (siehe Done-Notes im Ticket).
    //
    // Methods werden direkt als signature-String gerendert (PHP-Parser liefert
    // den Source-String inkl. Modifier wie "static function execute(...): self").
    //
    // Members sind rekursiv: class -> property/method, method -> local-spec.

    function escape_html(text_or_value)
    {
        let value_is_nullish = text_or_value === null || text_or_value === undefined;

        if (value_is_nullish)
        {
            return "";
        }

        let text_value = String(text_or_value);

        // textContent-via-temporary-node ist die kanonische DOM-Methode.
        // Schoner als manuelles &amp;/&lt;/...-Stringbasteln und deckt auch
        // Quotes ab (per innerHTML-Read).
        let temporary_div = document.createElement("div");
        temporary_div.textContent = text_value;
        return temporary_div.innerHTML;
    }

    function render_property_signature(symbol_object)
    {
        // Property-Schema: { kind:"property", name, type?, default? }.
        // Synthetisiert "public <type> $<name>[ = <default>]" als
        // signaturartigen Anzeige-String.
        let synthesized_parts = ["public"];

        let type_is_set =
            typeof symbol_object.type === "string"
            && symbol_object.type !== "";

        if (type_is_set)
        {
            synthesized_parts.push(symbol_object.type);
        }

        synthesized_parts.push("$" + (symbol_object.name || ""));

        let default_is_set =
            typeof symbol_object.default === "string"
            && symbol_object.default !== "";

        if (default_is_set)
        {
            synthesized_parts.push("= " + symbol_object.default);
        }

        return synthesized_parts.join(" ");
    }

    function render_const_signature(symbol_object)
    {
        // Const-Schema: { kind:"const", name, type?, default? }.
        // Synthetisiert "const [<type> ]<NAME>[ = <default>]".
        let synthesized_parts = ["const"];

        let type_is_set =
            typeof symbol_object.type === "string"
            && symbol_object.type !== "";

        if (type_is_set)
        {
            synthesized_parts.push(symbol_object.type);
        }

        synthesized_parts.push(symbol_object.name || "");

        let default_is_set =
            typeof symbol_object.default === "string"
            && symbol_object.default !== "";

        if (default_is_set)
        {
            synthesized_parts.push("= " + symbol_object.default);
        }

        return synthesized_parts.join(" ");
    }

    function render_container_signature(symbol_object)
    {
        // class/interface/trait: signaturartiger Header aus kind + name + extends + implements.
        let parts = [symbol_object.kind || "", symbol_object.name || ""];

        let extends_list = Array.isArray(symbol_object.extends) ? symbol_object.extends : [];
        let extends_has_entries = extends_list.length > 0;

        if (extends_has_entries)
        {
            parts.push("extends " + extends_list.join(", "));
        }

        let implements_list = Array.isArray(symbol_object.implements) ? symbol_object.implements : [];
        let implements_has_entries = implements_list.length > 0;

        if (implements_has_entries)
        {
            parts.push("implements " + implements_list.join(", "));
        }

        return parts.join(" ");
    }

    function render_signature_for_symbol(symbol_object)
    {
        let kind = symbol_object.kind || "";

        if (kind === "method" || kind === "function")
        {
            // Schema-Garantie: signature ist gesetzt fuer method/function.
            return symbol_object.signature || "";
        }

        if (kind === "property")
        {
            return render_property_signature(symbol_object);
        }

        if (kind === "const")
        {
            return render_const_signature(symbol_object);
        }

        if (kind === "class" || kind === "interface" || kind === "trait")
        {
            return render_container_signature(symbol_object);
        }

        // local-spec hat keinen Identifier; Anzeige als "(local)"-Marker.
        if (kind === "local")
        {
            let local_name_is_set =
                typeof symbol_object.name === "string"
                && symbol_object.name !== "";

            if (local_name_is_set)
            {
                return "// " + symbol_object.name;
            }

            return "// (local)";
        }

        return kind;
    }

    function render_spec_lines(spec_lines)
    {
        let lines_are_array = Array.isArray(spec_lines);

        if (! lines_are_array)
        {
            return "";
        }

        let lines_have_entries = spec_lines.length > 0;

        if (! lines_have_entries)
        {
            return "";
        }

        let html_pieces = [];

        spec_lines.forEach(function (line_text)
        {
            html_pieces.push(
                "<p class=\"spec-view-spec-line\">"
                + escape_html(line_text)
                + "</p>"
            );
        });

        return html_pieces.join("");
    }

    // Decorators (TS) und Annotations (M010 Groovy/Spring) rendern wir
    // identisch — beide Sprachen tragen Symbol-Schmuck mit `@Name(...)`-Form.
    // Per-Symbol auf decorators[] || annotations[] zugreifen, damit der
    // Renderer keine sprachspezifische Verzweigung braucht (siehe Ticket
    // M009/0004: "Generalisierung fuer M010").
    function render_decorators(symbol_object)
    {
        let decoratorlike = symbol_object.decorators || symbol_object.annotations || [];

        let list_is_array = Array.isArray(decoratorlike);

        if (! list_is_array)
        {
            return "";
        }

        let list_has_entries = decoratorlike.length > 0;

        if (! list_has_entries)
        {
            return "";
        }

        let html_pieces = ["<div class=\"spec-view-decorators\">"];

        decoratorlike.forEach(function (decorator_object)
        {
            let decorator_name = decorator_object && decorator_object.name ? String(decorator_object.name) : "";
            let args_source    = decorator_object ? decorator_object.args_source : null;

            // args_source-Form (siehe README ## TypeScript):
            //   null      -> @Name        (Decorator ohne Klammern)
            //   ""        -> @Name()      (leerer Decorator-Factory-Aufruf)
            //   "<inhalt>"-> @Name(<inhalt>)
            let decorator_text = "@" + decorator_name;

            let args_are_string = typeof args_source === "string";

            if (args_are_string)
            {
                decorator_text += "(" + args_source + ")";
            }

            html_pieces.push(
                "<code class=\"spec-view-decorator\">"
                + escape_html(decorator_text)
                + "</code>"
            );
        });

        html_pieces.push("</div>");

        return html_pieces.join("");
    }

    function render_symbol(symbol_object)
    {
        let kind = symbol_object.kind || "unknown";
        let signature_text = render_signature_for_symbol(symbol_object);

        let symbol_html = "<li class=\"spec-view-symbol spec-view-symbol-" + escape_html(kind) + "\">";

        // Decorators/Annotations stehen oben drueber — Angular-typisch.
        symbol_html += render_decorators(symbol_object);

        symbol_html += "<code class=\"spec-view-signature\">";
        symbol_html += escape_html(signature_text);
        symbol_html += "</code>";

        symbol_html += render_spec_lines(symbol_object.spec);

        let members_list = Array.isArray(symbol_object.members) ? symbol_object.members : [];
        let members_have_entries = members_list.length > 0;

        if (members_have_entries)
        {
            symbol_html += "<ul class=\"spec-view-members\">";

            members_list.forEach(function (member_object)
            {
                symbol_html += render_symbol(member_object);
            });

            symbol_html += "</ul>";
        }

        symbol_html += "</li>";

        return symbol_html;
    }

    function render_warnings(warnings_list)
    {
        let list_is_array = Array.isArray(warnings_list);

        if (! list_is_array)
        {
            return "";
        }

        let list_has_entries = warnings_list.length > 0;

        if (! list_has_entries)
        {
            return "";
        }

        let html_pieces = ["<div class=\"spec-view-warnings\">"];

        warnings_list.forEach(function (warning_text)
        {
            html_pieces.push(
                "<p class=\"spec-view-warning\">"
                + escape_html(warning_text)
                + "</p>"
            );
        });

        html_pieces.push("</div>");

        return html_pieces.join("");
    }

    function spec_object_has_renderable_content(spec_object)
    {
        let object_is_truthy = spec_object !== null && spec_object !== undefined;

        if (! object_is_truthy)
        {
            return false;
        }

        // Error-Schema (z.B. vendor / unsupported language) wird heute nicht
        // gerendert — der zugehoerige Code wird ohnehin als <pre> gezeigt und
        // ein "vendor code not parsed"-Banner ist kein Mehrwert.
        let object_carries_error = typeof spec_object.error === "string" && spec_object.error !== "";

        if (object_carries_error)
        {
            return false;
        }

        let file_spec_has_entries =
            Array.isArray(spec_object.file_spec)
            && spec_object.file_spec.length > 0;

        let symbols_have_entries =
            Array.isArray(spec_object.symbols)
            && spec_object.symbols.length > 0;

        return file_spec_has_entries || symbols_have_entries;
    }

    function render_spec_view(spec_object)
    {
        let has_content = spec_object_has_renderable_content(spec_object);

        if (! has_content)
        {
            return "";
        }

        let html_pieces = [];

        html_pieces.push("<div class=\"spec-view\">");

        let file_spec_list = Array.isArray(spec_object.file_spec) ? spec_object.file_spec : [];
        let file_spec_has_entries = file_spec_list.length > 0;

        if (file_spec_has_entries)
        {
            html_pieces.push("<div class=\"spec-view-file-spec\">");
            html_pieces.push(render_spec_lines(file_spec_list));
            html_pieces.push("</div>");
        }

        let symbols_list = Array.isArray(spec_object.symbols) ? spec_object.symbols : [];
        let symbols_have_entries = symbols_list.length > 0;

        if (symbols_have_entries)
        {
            html_pieces.push("<ul class=\"spec-view-symbols\">");

            symbols_list.forEach(function (symbol_object)
            {
                html_pieces.push(render_symbol(symbol_object));
            });

            html_pieces.push("</ul>");
        }

        html_pieces.push(render_warnings(spec_object.warnings));

        html_pieces.push("</div>");

        return html_pieces.join("");
    }

    // --- tab shell (M005/0005-followup) ------------------------------------
    //
    // render_tabs_shell baut einen Tab-Switcher mit zwei Tabs (Spec / Code).
    // Default-aktiv ist Spec, wenn vorhanden — sonst Code (und Spec-Tab
    // ist disabled). Spec-Inhalt ist die schon gerenderte spec-view (oder
    // ein "keine Spec"-Hinweis), Code-Inhalt ist data.html.
    //
    // Tab-Switching ist reines DOM-Klassen-Toggle, kein Re-Render.

    function render_tabs_shell(spec_view_html, code_html, spec_is_present)
    {
        let spec_panel_class = spec_is_present ? "content-tab-panel active" : "content-tab-panel";
        let code_panel_class = spec_is_present ? "content-tab-panel" : "content-tab-panel active";

        let spec_button_class = spec_is_present ? "content-tab-button active" : "content-tab-button disabled";
        let code_button_class = spec_is_present ? "content-tab-button" : "content-tab-button active";

        let spec_button_attrs = spec_is_present
            ? "data-target=\"spec\""
            : "data-target=\"spec\" disabled aria-disabled=\"true\"";

        let spec_panel_inner = spec_is_present
            ? spec_view_html
            : "<p class=\"content-tab-empty\">Diese Datei hat noch keine Spec.</p>";

        let html = "";

        html += "<div class=\"content-tabs\">";
        html += "<div class=\"content-tab-bar\">";
        html += "<button class=\"" + spec_button_class + "\" " + spec_button_attrs + ">Spec</button>";
        html += "<button class=\"" + code_button_class + "\" data-target=\"code\">Code</button>";
        html += "</div>";
        html += "<div class=\"" + spec_panel_class + "\" data-panel=\"spec\">" + spec_panel_inner + "</div>";
        html += "<div class=\"" + code_panel_class + "\" data-panel=\"code\">" + code_html + "</div>";
        html += "</div>";

        return html;
    }

    function attach_tab_handlers(article_element)
    {
        let buttons = article_element.querySelectorAll(".content-tab-button");

        buttons.forEach(function (button_element)
        {
            let button_is_disabled = button_element.hasAttribute("disabled");

            if (button_is_disabled)
            {
                return;
            }

            button_element.addEventListener("click", function ()
            {
                let target_name = button_element.getAttribute("data-target");

                let all_buttons = article_element.querySelectorAll(".content-tab-button");
                all_buttons.forEach(function (other_button)
                {
                    other_button.classList.remove("active");
                });
                button_element.classList.add("active");

                let all_panels = article_element.querySelectorAll(".content-tab-panel");
                all_panels.forEach(function (panel_element)
                {
                    let panel_name = panel_element.getAttribute("data-panel");
                    let panel_is_target = panel_name === target_name;

                    if (panel_is_target)
                    {
                        panel_element.classList.add("active");
                    }
                    else
                    {
                        panel_element.classList.remove("active");
                    }
                });
            });
        });
    }

    async function load_path(path, do_push_state)
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let fetch_url = "/file.php?path=" + encodeURIComponent(path);

        let response = null;

        try
        {
            response = await fetch(fetch_url, { headers: { "Accept": "application/json" } });
        }
        catch (network_failed)
        {
            console.warn("content_loader: network error for path", path, network_failed);
            show_invalid_path_message();
            return;
        }

        let data = null;

        try
        {
            data = await response.json();
        }
        catch (json_parse_failed)
        {
            console.warn("content_loader: bad json for path", path, json_parse_failed);
            show_invalid_path_message();
            return;
        }

        let response_signals_error =
            response.ok === false
            || data === null
            || data.ok === false;

        if (response_signals_error)
        {
            console.warn("content_loader: server rejected path", path, data);
            show_invalid_path_message();
            return;
        }

        let spec_view_html = render_spec_view(data.spec);
        let spec_view_is_present = spec_view_html !== "";

        article_element.innerHTML = render_tabs_shell(spec_view_html, data.html, spec_view_is_present);
        attach_tab_handlers(article_element);
        set_header_label(path);
        set_document_title(path);

        if (do_push_state)
        {
            let new_url = "/?path=" + encodeURIComponent(path);
            history.pushState({ path: path }, "", new_url);
        }
    }

    function on_tree_link_click(event)
    {
        let clicked_anchor = event.currentTarget;

        let raw_href = clicked_anchor.getAttribute("href");

        let href_is_missing = raw_href === null || raw_href === "";

        if (href_is_missing)
        {
            return;
        }

        let parsed_url = null;

        try
        {
            parsed_url = new URL(clicked_anchor.href);
        }
        catch (parse_failed)
        {
            return;
        }

        let extracted_path = parsed_url.searchParams.get("path");

        let path_is_present = extracted_path !== null && extracted_path !== "";

        if (! path_is_present)
        {
            return;
        }

        event.preventDefault();
        load_path(extracted_path, true);
    }

    function attach_tree_link_handlers()
    {
        let tree_link_elements = document.querySelectorAll(tree_link_selector);

        tree_link_elements.forEach(function (link_element)
        {
            link_element.addEventListener("click", on_tree_link_click);
        });
    }

    function on_popstate(event)
    {
        let state_path = event.state && event.state.path ? event.state.path : null;

        let state_has_path = state_path !== null && state_path !== "";

        if (state_has_path)
        {
            load_path(state_path, false);
            return;
        }

        // Kein state.path: User ist zu einem History-Stand ohne Datei zurueck —
        // initialen Platzhalter wiederherstellen, Header-Label und Title leeren.
        show_initial_placeholder();
    }

    function init_content_loader()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (article_exists)
        {
            initial_article_html = article_element.innerHTML;
        }

        attach_tree_link_handlers();

        window.addEventListener("popstate", on_popstate);

        // Bookmark/Reload mit ?path=… in der URL: initialer Fetch.
        // Der Server-side-Render hat den Content fuer diesen Pfad zwar schon eingesetzt
        // (das wird erst in Ticket 002/0006 entfernt), aber der Fetch ist idempotent
        // und stellt sicher, dass JS-Sicht und URL konsistent sind (Header-Label, Title,
        // sowie ein History-Eintrag mit state.path fuer korrekten Back-Button).
        let url_search_params = new URLSearchParams(window.location.search);
        let initial_path      = url_search_params.get("path");

        let initial_path_is_set = initial_path !== null && initial_path !== "";

        if (initial_path_is_set)
        {
            // pushState=false beim Initial-Load: wir wollen keinen zusaetzlichen Eintrag.
            // Stattdessen replaceState mit state.path, damit popstate auf diesen Stand
            // zurueckkommen kann.
            history.replaceState({ path: initial_path }, "", window.location.pathname + window.location.search);
            load_path(initial_path, false);
        }
    }

    document.addEventListener("DOMContentLoaded", init_content_loader);
})();
