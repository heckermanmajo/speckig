// content_loader.js — AJAX-Loader fuer den Content-Bereich (Ticket 002/0005).
// Tree-Klicks laden /file.php per fetch, schreiben das HTML in <article id="content">,
// spiegeln den Pfad via history.pushState in der URL und im Header-Label.
// Laedt nach helpers.js und tree_collapse.js. Top-Level-Script, kein Module-System.
//
// Style (Decision 0004): BSD-Klammern, snake_case, `what_cond_means`-Pattern,
// let/const, async/await, defensive try/catch um fetch (Netzwerk kann wegbrechen).
//
// Endpoint-Vertrag (siehe app/file.php, Ticket 002/0004 + 005/0005 + M013/0003):
//   Erfolg : HTTP 200 + { ok: true,  path, html, spec, raw, editable }
//   Fehler : HTTP 400 + { ok: false, message }
//
// `spec` ist null fuer nicht-migrierte Dateien (oder Sprachen ohne Parser),
// sonst das Schema aus app/_share/spec_parser/README.md. Render-Layer:
// render_spec_view(spec_object) — siehe unten.
//
// `raw` traegt den rohen Datei-Inhalt (fuer den Editor-Buffer), `editable`
// ist false fuer Pfade in der Schwarzliste (vendor/.git/spec_parser).
//
// Edit-Flow (M013/0003): nach jedem erfolgreichen Load merken wir `raw`,
// `path` und `editable` im Modul-State. Wenn `editable=true`, rendert
// `render_code_toolbar()` eine `.content-toolbar` mit Edit/Save/Cancel-
// Buttons ans Ende des Code-Tab-Panels. Edit toggelt das `<pre>` gegen
// einen CodeMirror-Editor (Mode aus Extension via
// `speckig_editor.extension_to_mode()` aus M013/0002); Save speichert via
// `speckig_editor.save(current_path)` (M013/0001-Endpoint via Pfad-Routing
// in editor.js); Cancel verwirft den Buffer und re-loaded den Pfad. Der
// Spec-Tab bleibt komplett unangetastet — der Edit-Modus lebt nur im
// Code-Tab. Logik parallel zu plan_loader.js, mit Anpassung an die
// Tab-Struktur (Toolbar im Code-Panel statt direkt unter dem Article).
//
// New-File-Action (M013/0004): `init_new_file_forms()` haengt Handler an
// `.btn-new-file` und `.new-file-form` im Tree. Submit POSTet JSON an
// `/file.php?action=new_file` und reloaded die Seite. Eine Form pro
// Ordner-`<details>` plus eine fuer Repo-Root (data-dir=""). Selektoren
// laufen RELATIV zur eigenen Form (`form.querySelector(".form-error")`
// usw.), damit nichts in die plan_loader-Forms bleedet — `.btn-cancel-form`
// und `.form-error` existieren in beiden Welten.
//
// Delete-Action (M013/0005): vierter Button `.btn-delete` in der Code-Tab-
// Toolbar, sichtbar exakt wenn der Edit-Modus NICHT aktiv ist (also parallel
// zum `.btn-edit`). Click triggert `window.confirm("…?")` und POSTet bei
// Bestaetigung an `/file.php?action=delete_file&path=…`. Bei Erfolg geht
// das Content-Panel zurueck auf den Platzhalter und die Seite reloaded
// (Tree-Refresh). Bei Fehler erscheint eine `.toolbar-error` ueber der
// Toolbar — analog zum Save-Error-Pfad.

(function ()
{
    let article_selector       = "article#content";
    let header_label_selector  = "#header-path-label";
    let tree_link_selector     = "nav a[href*=\"?path=\"]";
    let initial_article_html   = "";
    let document_title_base    = "speckig";

    // Edit-Flow-State (M013/0003): roher Datei-Inhalt + Pfad der zuletzt
    // geladenen Datei, plus `editable`-Flag aus der JSON-Response.
    // `edit_is_active` togglet die Toolbar-Buttons, ohne dass wir den
    // DOM-Zustand befragen muessen. `previous_code_pre_html` haelt das
    // urspruengliche <pre>-Markup fuer Cancel-Restore (aktuell loesen wir
    // Cancel ueber Re-Load, daher wird der Slot defensiv mitgefuehrt).
    let current_raw            = "";
    let current_path           = "";
    let current_editable       = false;
    let edit_is_active         = false;
    let previous_code_pre_html = "";

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

        // Edit-State zuruecksetzen: ohne geladenen Pfad gibt es keinen
        // sinnvollen Save-Target — die Toolbar erscheint auch nicht.
        current_raw            = "";
        current_path           = "";
        current_editable       = false;
        edit_is_active         = false;
        previous_code_pre_html = "";
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

        current_raw            = "";
        current_path           = "";
        current_editable       = false;
        edit_is_active         = false;
        previous_code_pre_html = "";
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

    // --- Edit-Flow (M013/0003) ---------------------------------------------
    //
    // Toolbar lebt im Code-Tab-Panel. Edit-Click toggelt das <pre> gegen
    // einen CodeMirror-Editor und switcht den Tab auf Code (falls Spec
    // gerade aktiv ist). Save speichert via speckig_editor.save() — das
    // routet pm/-Pfade weiter an pm.php, alles andere an file.php
    // (M013/0001). Cancel verwirft den Buffer und re-loaded den Pfad,
    // damit das <pre> wieder erscheint und der State frisch ist.

    function extract_extension(path)
    {
        let path_is_string = typeof path === "string";

        if (! path_is_string)
        {
            return "";
        }

        let last_dot = path.lastIndexOf(".");

        let no_extension = last_dot === -1;

        if (no_extension)
        {
            return "";
        }

        return path.substring(last_dot + 1);
    }

    function activate_code_tab(article_element)
    {
        let buttons = article_element.querySelectorAll(".content-tab-button");
        let panels  = article_element.querySelectorAll(".content-tab-panel");

        buttons.forEach(function (button_element)
        {
            let target_name = button_element.getAttribute("data-target");
            let is_code     = target_name === "code";

            if (is_code)
            {
                button_element.classList.add("active");
            }
            else
            {
                button_element.classList.remove("active");
            }
        });

        panels.forEach(function (panel_element)
        {
            let panel_name = panel_element.getAttribute("data-panel");
            let is_code    = panel_name === "code";

            if (is_code)
            {
                panel_element.classList.add("active");
            }
            else
            {
                panel_element.classList.remove("active");
            }
        });
    }

    function get_code_panel(article_element)
    {
        let panel_exists = article_element !== null;

        if (! panel_exists)
        {
            return null;
        }

        return article_element.querySelector(".content-tab-panel[data-panel=\"code\"]");
    }

    function render_code_toolbar(article_element)
    {
        let code_panel = get_code_panel(article_element);

        let panel_exists = code_panel !== null;

        if (! panel_exists)
        {
            return;
        }

        let toolbar = document.createElement("div");
        toolbar.className = "content-toolbar";

        let btn_edit = document.createElement("button");
        btn_edit.className   = "btn-edit";
        btn_edit.textContent = "Edit";
        btn_edit.addEventListener("click", on_edit_click);

        // Delete-Button (M013/0005): sichtbar parallel zum Edit-Button,
        // d.h. hidden sobald der Edit-Modus aktiv ist. Confirm-Dialog im
        // Click-Handler, kein eigenes Modal.
        let btn_delete = document.createElement("button");
        btn_delete.className   = "btn-delete";
        btn_delete.textContent = "Delete";
        btn_delete.addEventListener("click", on_delete_click);

        let btn_save = document.createElement("button");
        btn_save.className   = "btn-save";
        btn_save.textContent = "Save";
        btn_save.hidden      = true;
        btn_save.addEventListener("click", on_save_click);

        let btn_cancel = document.createElement("button");
        btn_cancel.className   = "btn-cancel";
        btn_cancel.textContent = "Cancel";
        btn_cancel.hidden      = true;
        btn_cancel.addEventListener("click", on_cancel_click);

        toolbar.appendChild(btn_edit);
        toolbar.appendChild(btn_delete);
        toolbar.appendChild(btn_save);
        toolbar.appendChild(btn_cancel);

        code_panel.appendChild(toolbar);
    }

    function toggle_toolbar_buttons(toolbar_node)
    {
        let toolbar_exists = toolbar_node !== null && toolbar_node !== undefined;

        if (! toolbar_exists)
        {
            return;
        }

        let btn_edit   = toolbar_node.querySelector(".btn-edit");
        let btn_delete = toolbar_node.querySelector(".btn-delete");
        let btn_save   = toolbar_node.querySelector(".btn-save");
        let btn_cancel = toolbar_node.querySelector(".btn-cancel");

        let toolbar_is_complete =
            btn_edit !== null
            && btn_save !== null
            && btn_cancel !== null;

        if (! toolbar_is_complete)
        {
            return;
        }

        btn_edit.hidden   = edit_is_active;
        btn_save.hidden   = ! edit_is_active;
        btn_cancel.hidden = ! edit_is_active;

        // Delete-Button (M013/0005): parallel zum Edit-Button — d.h. nur
        // sichtbar wenn der Edit-Modus NICHT aktiv ist. Defensiver Null-
        // Check, weil aeltere Toolbar-Renders den Button noch nicht hatten.
        let btn_delete_exists = btn_delete !== null;

        if (btn_delete_exists)
        {
            btn_delete.hidden = edit_is_active;
        }
    }

    function on_edit_click()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let editor_is_available =
            window.speckig_editor !== undefined
            && window.speckig_editor !== null
            && typeof window.speckig_editor.mount === "function"
            && typeof window.speckig_editor.extension_to_mode === "function";

        if (! editor_is_available)
        {
            console.warn("content_loader: speckig_editor missing — cannot mount");
            return;
        }

        let code_panel   = get_code_panel(article_element);
        let panel_exists = code_panel !== null;

        if (! panel_exists)
        {
            return;
        }

        let toolbar_node = code_panel.querySelector(".content-toolbar");

        // <pre> wegnehmen, fuer eventuelles Restore merken. Eine eventuell
        // stehengebliebene Error-Zeile aus dem letzten Save raeumen wir
        // mit ab — der frische Edit-Modus startet visuell sauber.
        let existing_pre   = code_panel.querySelector("pre");
        let existing_error = code_panel.querySelector(".toolbar-error");

        if (existing_pre !== null)
        {
            previous_code_pre_html = existing_pre.outerHTML;
            existing_pre.remove();
        }

        if (existing_error !== null)
        {
            existing_error.remove();
        }

        // Tab-Switch zu Code, falls Spec gerade aktiv ist.
        activate_code_tab(article_element);

        // Mount-Container fuer den Editor: leerer div, VOR der Toolbar
        // eingehaengt, damit Edit/Save/Cancel unter dem Editor bleiben.
        let mount_div = document.createElement("div");
        mount_div.className = "editor-mount";

        let toolbar_is_present = toolbar_node !== null;

        if (toolbar_is_present)
        {
            code_panel.insertBefore(mount_div, toolbar_node);
        }
        else
        {
            code_panel.appendChild(mount_div);
        }

        let extension = extract_extension(current_path);
        let mode_name = window.speckig_editor.extension_to_mode(extension);

        window.speckig_editor.mount(mount_div, current_raw, current_path, mode_name);

        edit_is_active = true;
        toggle_toolbar_buttons(toolbar_node);
    }

    async function on_save_click()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let editor_is_available =
            window.speckig_editor !== undefined
            && window.speckig_editor !== null
            && typeof window.speckig_editor.save === "function";

        if (! editor_is_available)
        {
            console.warn("content_loader: speckig_editor missing — cannot save");
            return;
        }

        let code_panel   = get_code_panel(article_element);
        let panel_exists = code_panel !== null;

        if (! panel_exists)
        {
            return;
        }

        let toolbar_node = code_panel.querySelector(".content-toolbar");

        let existing_error = code_panel.querySelector(".toolbar-error");

        if (existing_error !== null)
        {
            existing_error.remove();
        }

        let save_result = await window.speckig_editor.save(current_path);

        let save_ok =
            save_result !== null
            && save_result !== undefined
            && save_result.ok === true;

        if (! save_ok)
        {
            let server_message =
                save_result !== null
                && save_result !== undefined
                && typeof save_result.message === "string"
                && save_result.message !== ""
                    ? save_result.message
                    : "Speichern fehlgeschlagen.";

            let error_div = document.createElement("div");
            error_div.className   = "toolbar-error";
            error_div.textContent = server_message;

            // Error-Zeile direkt ueber der Toolbar einhaengen, damit der
            // Editor offen bleibt und der Buffer erhalten ist.
            let toolbar_is_present = toolbar_node !== null;

            if (toolbar_is_present)
            {
                code_panel.insertBefore(error_div, toolbar_node);
            }
            else
            {
                code_panel.appendChild(error_div);
            }
            return;
        }

        let editor_can_be_destroyed =
            typeof window.speckig_editor.destroy === "function";

        if (editor_can_be_destroyed)
        {
            window.speckig_editor.destroy();
        }

        load_path(current_path, false);
    }

    function on_cancel_click()
    {
        let editor_can_be_destroyed =
            window.speckig_editor !== undefined
            && window.speckig_editor !== null
            && typeof window.speckig_editor.destroy === "function";

        if (editor_can_be_destroyed)
        {
            window.speckig_editor.destroy();
        }

        load_path(current_path, false);
    }

    // Delete-Action (M013/0005): Confirm-Dialog + POST an
    // /file.php?action=delete_file. Bei Erfolg geht das Content-Panel auf
    // den Platzhalter und die Seite reloaded (Tree-Refresh). Fehler
    // landen in einer .toolbar-error ueber der Toolbar — analog
    // on_save_click().
    async function on_delete_click()
    {
        let path_is_known = typeof current_path === "string" && current_path !== "";

        if (! path_is_known)
        {
            return;
        }

        let confirmed = window.confirm("Diese Datei wirklich loeschen?");

        if (! confirmed)
        {
            return;
        }

        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let code_panel   = get_code_panel(article_element);
        let panel_exists = code_panel !== null;

        if (! panel_exists)
        {
            return;
        }

        let toolbar_node = code_panel.querySelector(".content-toolbar");

        let existing_error = code_panel.querySelector(".toolbar-error");

        if (existing_error !== null)
        {
            existing_error.remove();
        }

        let fetch_url = "/file.php?action=delete_file&path=" + encodeURIComponent(current_path);

        let response = null;

        try
        {
            response = await fetch(fetch_url, { method: "POST" });
        }
        catch (network_failed)
        {
            console.warn("content_loader: delete network error", current_path, network_failed);
            show_delete_error(code_panel, toolbar_node, "Netzwerkfehler beim Loeschen.");
            return;
        }

        let data = null;

        try
        {
            data = await response.json();
        }
        catch (json_parse_failed)
        {
            console.warn("content_loader: delete bad json", current_path, json_parse_failed);
            show_delete_error(code_panel, toolbar_node, "Server-Antwort unleserlich.");
            return;
        }

        let delete_ok =
            response.ok === true
            && data !== null
            && data.ok === true;

        if (! delete_ok)
        {
            let server_message =
                data !== null
                && data !== undefined
                && typeof data.message === "string"
                && data.message !== ""
                    ? data.message
                    : "Loeschen fehlgeschlagen.";

            show_delete_error(code_panel, toolbar_node, server_message);
            return;
        }

        // Erfolg: Content-Panel zurueck auf Platzhalter, Tree per Reload
        // aktualisieren (der frisch geladene Tree zeigt die Datei nicht
        // mehr). pushState ist nicht noetig, weil location.reload() ohnehin
        // einen Full-Reload macht.
        show_initial_placeholder();
        window.location.reload();
    }

    function show_delete_error(code_panel, toolbar_node, message)
    {
        let panel_exists = code_panel !== null && code_panel !== undefined;

        if (! panel_exists)
        {
            return;
        }

        let error_div = document.createElement("div");
        error_div.className   = "toolbar-error";
        error_div.textContent = message;

        let toolbar_is_present = toolbar_node !== null && toolbar_node !== undefined;

        if (toolbar_is_present)
        {
            code_panel.insertBefore(error_div, toolbar_node);
        }
        else
        {
            code_panel.appendChild(error_div);
        }
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

        // Edit-Flow-State BEVOR DOM-Update setzen, damit eventuelle
        // Toolbar-Renders danach den korrekten State sehen.
        let raw_is_string = typeof data.raw === "string";

        current_raw            = raw_is_string ? data.raw : "";
        current_path           = typeof data.path === "string" && data.path !== "" ? data.path : path;
        current_editable       = data.editable === true;
        edit_is_active         = false;
        previous_code_pre_html = "";

        let spec_view_html = render_spec_view(data.spec);
        let spec_view_is_present = spec_view_html !== "";

        article_element.innerHTML = render_tabs_shell(spec_view_html, data.html, spec_view_is_present);
        attach_tab_handlers(article_element);

        // Toolbar nur rendern, wenn der Server das File als editierbar
        // markiert hat (Schwarzliste: vendor/.git/spec_parser).
        if (current_editable)
        {
            render_code_toolbar(article_element);
        }

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

    // --- New-File-Action (M013/0004) ---------------------------------------
    //
    // Pro Ordner-`<details>` im Tree gibt es einen `.btn-new-file` und eine
    // initial-hidden `.new-file-form`. Beide tragen `data-dir="<rel>"`
    // (leer = Repo-Root) zum Pairing. Submit POSTet `{dir, name}` an
    // `/file.php?action=new_file`; bei Erfolg `window.location.reload()`,
    // sonst `.form-error` im jeweiligen Form fuellen.
    //
    // Selektoren laufen RELATIV zur eigenen `.tree-action-block` bzw.
    // `.new-file-form`, damit wir nicht versehentlich auf die plan_loader-
    // Forms treffen (`.btn-cancel-form` / `.form-error` existieren dort
    // identisch).

    function show_new_file_error(form_element, message)
    {
        let error_node = form_element.querySelector(".form-error");

        let error_node_exists = error_node !== null;

        if (! error_node_exists)
        {
            return;
        }

        error_node.textContent = message;
        error_node.hidden      = false;
    }

    function on_new_file_button_click(event)
    {
        let button_element = event.currentTarget;

        // Action-Block ist der gemeinsame Wrapper von Button + Form.
        // closest() laeuft strict vom Button nach oben — Pairing isoliert,
        // unabhaengig von data-dir-Werten (Root hat data-dir="").
        let action_block = button_element.closest(".tree-action-block");

        let action_block_exists = action_block !== null;

        if (! action_block_exists)
        {
            return;
        }

        let form_element = action_block.querySelector(".new-file-form");

        let form_exists = form_element !== null;

        if (! form_exists)
        {
            return;
        }

        form_element.hidden   = false;
        button_element.hidden = true;

        let name_input = form_element.querySelector(".input-name");

        let name_input_exists = name_input !== null;

        if (name_input_exists)
        {
            name_input.focus();
        }
    }

    function on_new_file_cancel_click(event)
    {
        let cancel_button = event.currentTarget;

        let form_element = cancel_button.closest(".new-file-form");

        let form_exists = form_element !== null;

        if (! form_exists)
        {
            return;
        }

        let action_block = form_element.closest(".tree-action-block");

        let action_block_exists = action_block !== null;

        if (! action_block_exists)
        {
            return;
        }

        let button_element = action_block.querySelector(".btn-new-file");
        let name_input     = form_element.querySelector(".input-name");
        let error_node     = form_element.querySelector(".form-error");

        if (name_input !== null)
        {
            name_input.value = "";
        }

        if (error_node !== null)
        {
            error_node.textContent = "";
            error_node.hidden      = true;
        }

        form_element.hidden = true;

        if (button_element !== null)
        {
            button_element.hidden = false;
        }
    }

    async function on_new_file_submit(event)
    {
        event.preventDefault();

        let form_element = event.currentTarget;

        let form_exists = form_element !== null;

        if (! form_exists)
        {
            return;
        }

        let dir_value = form_element.getAttribute("data-dir");

        let dir_is_string = typeof dir_value === "string";

        if (! dir_is_string)
        {
            show_new_file_error(form_element, "Ordner fehlt.");
            return;
        }

        let name_input = form_element.querySelector(".input-name");

        let name_input_exists = name_input !== null;

        if (! name_input_exists)
        {
            return;
        }

        let name_value = name_input.value.trim();

        let name_is_present = name_value !== "";

        if (! name_is_present)
        {
            show_new_file_error(form_element, "Dateiname ist Pflicht.");
            return;
        }

        let response = null;

        try
        {
            response = await fetch(
                "/file.php?action=new_file",
                {
                    method:  "POST",
                    headers: { "Content-Type": "application/json" },
                    body:    JSON.stringify({
                        dir:  dir_value,
                        name: name_value,
                    }),
                }
            );
        }
        catch (network_failed)
        {
            console.warn("content_loader: new_file network error", network_failed);
            show_new_file_error(form_element, "Netzwerkfehler.");
            return;
        }

        let data = null;

        try
        {
            data = await response.json();
        }
        catch (json_parse_failed)
        {
            console.warn("content_loader: new_file bad json", json_parse_failed);
            show_new_file_error(form_element, "Server-Antwort unverstaendlich.");
            return;
        }

        let server_signals_ok =
            response.ok === true
            && data !== null
            && data.ok === true;

        if (! server_signals_ok)
        {
            let server_message =
                data !== null
                && typeof data.message === "string"
                && data.message !== ""
                    ? data.message
                    : "Anlegen fehlgeschlagen.";

            show_new_file_error(form_element, server_message);
            return;
        }

        window.location.reload();
    }

    function init_new_file_forms()
    {
        let buttons = document.querySelectorAll(".btn-new-file");

        buttons.forEach(function (button_element)
        {
            button_element.addEventListener("click", on_new_file_button_click);
        });

        let forms = document.querySelectorAll(".new-file-form");

        forms.forEach(function (form_element)
        {
            let cancel_button = form_element.querySelector(".btn-cancel-form");

            let cancel_button_exists = cancel_button !== null;

            if (cancel_button_exists)
            {
                cancel_button.addEventListener("click", on_new_file_cancel_click);
            }

            form_element.addEventListener("submit", on_new_file_submit);
        });
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

        // New-File-Forms (M013/0004) — eine pro Ordner-`<details>` im Tree
        // plus eine fuer den Repo-Root. Self-guarded: no-op, wenn keine
        // `.btn-new-file` / `.new-file-form` im DOM stehen.
        init_new_file_forms();
    }

    document.addEventListener("DOMContentLoaded", init_content_loader);
})();
