// @spec
// plan_loader.js — AJAX-Loader fuer den Content-Bereich der Plan- UND
// Info-View. Eingebunden in `app/plan.php` (M006/0003) und in
// `app/info.php` (M011/0002). Sidebar-Klicks laden /pm.php per fetch,
// schreiben das gerenderte Markdown in <article id="content"> und
// spiegeln den Pfad via history.pushState in der URL.
//
// Dual-Route-faehig: der Push-State-Pfad wird aus
// `window.location.pathname` abgeleitet (statt einer hartcodierten
// Route), damit derselbe Loader auf /plan.php UND /info.php sauber
// arbeitet — beide Seiten verwenden dieselben Sidebar-Selektoren
// `a.plan-milestone-link, a.plan-ticket-link`.
//
// Bewusst getrennt vom content_loader.js: dieser Loader bindet sich an
// die Plan-/Info-Sidebar-Selektoren und laeuft nur dort, wo das Script
// eingebunden ist. Keine Vermischung der Klick-Handler mit der Tree-View.
//
// Style (Decision 0004 + code_style.md): BSD-Klammern, snake_case,
// `what_cond_means`-Pattern, let/const, async/await, defensive try/catch
// um fetch.
//
// Endpoint-Vertrag (siehe app/pm.php):
//   Erfolg : HTTP 200 + { ok: true,  path, html, status, raw }
//   Fehler : HTTP 400 + { ok: false, message }
//
// Render-Strategie: data.html ist Parsedown-Output ueber Repo-kontrolliertem
// Markdown — in V1 setzen wir den HTML-String direkt via innerHTML ein.
// data.status wird mittels textContent (escape via DOM) geschrieben.
// Markdown-XSS-Hardening ist out of scope.
//
// Edit-Flow (M012/0004): nach jedem erfolgreichen Load merken wir den
// Rohinhalt (`current_raw`) und den Pfad (`current_path`) im Modul-State.
// `render_toolbar()` haengt eine `.content-toolbar` mit Edit/Save/Cancel-
// Buttons unter den Markdown-Body — aber nur, wenn ein Pfad geladen ist
// UND der Pfad nicht `/archive/` enthaelt (archivierte Tickets sind
// read-only, siehe milestone.md "Out of scope"). Edit ruft
// `speckig_editor.mount(mount_div, current_raw, current_path)`; Save
// `speckig_editor.save(current_path)` + Re-Load; Cancel verwirft den
// Buffer per `destroy()` + Re-Load. Der Editor-Layer (editor.js) ist
// fuer DOM-Layout/Buttons nicht zustaendig — das macht alles hier.
//
// New-Milestone-Action (M012/0005): `init_new_milestone_form()` bindet
// einen Click-Handler an `.btn-new-milestone` (zeigt das versteckte
// `.new-milestone-form`), einen Cancel-Handler an `.btn-cancel-form`
// (schliesst das Formular wieder und raeumt Inputs/Error) und einen
// Submit-Handler an `.new-milestone-form`. Der Submit ruft
// `POST /pm.php?action=new_milestone` mit `{slug, title}` als JSON
// und reloaded bei `ok:true` die Seite, damit die Sidebar den neuen
// Milestone listet. Bei Server-Fehler wird `.form-error` befuellt.
// @end-spec

(function ()
{
    let article_selector       = "article#content";
    let header_label_selector  = "#header-path-label";
    let plan_link_selector     = "a.plan-milestone-link, a.plan-ticket-link";
    let document_title_base    = "speckig";
    let initial_article_html   = "";

    // Edit-Flow-State (M012/0004): rohes Markdown + Pfad der zuletzt
    // geladenen Datei. `edit_is_active` togglet die Toolbar-Buttons,
    // ohne dass wir den DOM-Zustand befragen muessen.
    let current_raw       = "";
    let current_path      = "";
    let edit_is_active    = false;

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
            document.title = document_title_base + " — plan";
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
        clear_active_link_marker();

        // Edit-State zuruecksetzen: ohne geladenen Pfad gibt es nichts
        // zu editieren — die Toolbar erscheint dann auch nicht (siehe
        // render_toolbar Archive-/Empty-Guard).
        current_raw    = "";
        current_path   = "";
        edit_is_active = false;
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

        current_raw    = "";
        current_path   = "";
        edit_is_active = false;
    }

    function clear_active_link_marker()
    {
        let active_links = document.querySelectorAll(plan_link_selector + ".active");

        active_links.forEach(function (link_element)
        {
            link_element.classList.remove("active");
        });
    }

    function mark_active_link(path)
    {
        clear_active_link_marker();

        let all_plan_links = document.querySelectorAll(plan_link_selector);

        all_plan_links.forEach(function (link_element)
        {
            let link_path = extract_path_from_anchor(link_element);

            let link_matches_current = link_path === path;

            if (link_matches_current)
            {
                link_element.classList.add("active");
            }
        });
    }

    function extract_path_from_anchor(anchor_element)
    {
        let raw_href = anchor_element.getAttribute("href");

        let href_is_missing = raw_href === null || raw_href === "";

        if (href_is_missing)
        {
            return "";
        }

        let parsed_url = null;

        try
        {
            parsed_url = new URL(anchor_element.href);
        }
        catch (parse_failed)
        {
            return "";
        }

        let extracted_path = parsed_url.searchParams.get("path");

        let path_is_present = extracted_path !== null && extracted_path !== "";

        if (! path_is_present)
        {
            return "";
        }

        return extracted_path;
    }

    function render_status_header(status_text)
    {
        let status_is_empty = status_text === "" || status_text === null || status_text === undefined;

        if (status_is_empty)
        {
            return null;
        }

        // Header via DOM-API + textContent — sicheres Escapen.
        let header_div = document.createElement("div");
        header_div.className = "plan-status-header";
        header_div.textContent = "Status: " + status_text;

        return header_div;
    }

    // ---- Edit-Toolbar (M012/0004) -----------------------------------------
    // Haengt Edit/Save/Cancel an article#content, falls ein Pfad geladen
    // ist und der Pfad nicht unter `/archive/` liegt. Archive-Tickets
    // bleiben read-only — die Toolbar wird in dem Fall gar nicht gerendert,
    // statt nur den Edit-Button auszublenden.

    function render_toolbar(article_element)
    {
        let path_is_archive = current_path.indexOf("/archive/") !== -1;
        let path_is_present = current_path !== "";

        let edit_is_allowed = path_is_present && ! path_is_archive;

        if (! edit_is_allowed)
        {
            return;
        }

        let toolbar = document.createElement("div");
        toolbar.className = "content-toolbar";

        let btn_edit = document.createElement("button");
        btn_edit.className   = "btn-edit";
        btn_edit.textContent = "Edit";
        btn_edit.addEventListener("click", on_edit_click);

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
        toolbar.appendChild(btn_save);
        toolbar.appendChild(btn_cancel);

        article_element.appendChild(toolbar);
    }

    function toggle_toolbar_buttons()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let btn_edit   = article_element.querySelector(".btn-edit");
        let btn_save   = article_element.querySelector(".btn-save");
        let btn_cancel = article_element.querySelector(".btn-cancel");

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
            && typeof window.speckig_editor.mount === "function";

        if (! editor_is_available)
        {
            console.warn("plan_loader: speckig_editor missing — cannot mount");
            return;
        }

        // Status-Header + Markdown-Body raeumen, Toolbar belassen.
        // Eine eventuell stehengebliebene Error-Zeile aus dem letzten
        // Save-Versuch raeumen wir mit ab, damit der frische Edit-Modus
        // visuell sauber startet.
        let markdown_node = article_element.querySelector(".plan-markdown");
        let status_node   = article_element.querySelector(".plan-status-header");
        let toolbar_node  = article_element.querySelector(".content-toolbar");
        let error_node    = article_element.querySelector(".toolbar-error");

        if (markdown_node !== null) { markdown_node.remove(); }
        if (status_node !== null)   { status_node.remove();   }
        if (error_node !== null)    { error_node.remove();    }

        // Mount-Container fuer den Editor: leerer div, eingehaengt VOR
        // der Toolbar, damit Edit/Save/Cancel unter dem Editor bleiben.
        let mount_div = document.createElement("div");
        mount_div.className = "editor-mount";
        article_element.insertBefore(mount_div, toolbar_node);

        window.speckig_editor.mount(mount_div, current_raw, current_path);

        edit_is_active = true;
        toggle_toolbar_buttons();
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
            console.warn("plan_loader: speckig_editor missing — cannot save");
            return;
        }

        let toolbar_node = article_element.querySelector(".content-toolbar");

        let existing_error = article_element.querySelector(".toolbar-error");

        if (existing_error !== null)
        {
            existing_error.remove();
        }

        let save_result = await window.speckig_editor.save(current_path);

        let save_ok = save_result !== null && save_result !== undefined && save_result.ok === true;

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

            // Error-Zeile direkt ueber der Toolbar einhaengen, damit sie
            // im Edit-Modus sichtbar bleibt und der Editor offen
            // bleibt — der User hat den Puffer noch im Buffer.
            let toolbar_is_present = toolbar_node !== null;

            if (toolbar_is_present)
            {
                article_element.insertBefore(error_div, toolbar_node);
            }
            else
            {
                article_element.appendChild(error_div);
            }
            return;
        }

        window.speckig_editor.destroy();
        load_plan_path(current_path, false);
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

        load_plan_path(current_path, false);
    }

    async function load_plan_path(path, do_push_state)
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (! article_exists)
        {
            return;
        }

        let fetch_url = "/pm.php?path=" + encodeURIComponent(path);

        let response = null;

        try
        {
            response = await fetch(fetch_url, { headers: { "Accept": "application/json" } });
        }
        catch (network_failed)
        {
            console.warn("plan_loader: network error for path", path, network_failed);
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
            console.warn("plan_loader: bad json for path", path, json_parse_failed);
            show_invalid_path_message();
            return;
        }

        let response_signals_error =
            response.ok === false
            || data === null
            || data.ok === false;

        if (response_signals_error)
        {
            console.warn("plan_loader: server rejected path", path, data);
            show_invalid_path_message();
            return;
        }

        // Edit-Flow-State aktualisieren BEVOR wir die Toolbar rendern.
        // `data.raw` kommt aus pm.php seit M012/0004; ein leerer String
        // ist ein gueltiger Fallback fuer alte Antworten.
        let raw_is_string = typeof data.raw === "string";

        current_raw    = raw_is_string ? data.raw : "";
        current_path   = typeof data.path === "string" && data.path !== "" ? data.path : path;
        edit_is_active = false;

        // Markdown-Body als Wrapper-div mit innerHTML (Parsedown-Output,
        // Repo-kontrolliert). Status-Header per DOM-API mit textContent
        // (sicher escaped).
        let markdown_div = document.createElement("div");
        markdown_div.className = "plan-markdown";
        markdown_div.innerHTML = data.html;

        // Article leeren, dann Status-Header (falls vorhanden) + Markdown einsetzen.
        article_element.innerHTML = "";

        let status_header = render_status_header(data.status);

        let status_header_exists = status_header !== null;

        if (status_header_exists)
        {
            article_element.appendChild(status_header);
        }

        article_element.appendChild(markdown_div);

        // Toolbar zuletzt anhaengen — sie sitzt unter dem Markdown-Body
        // im rechten Content-Panel. render_toolbar() entscheidet selbst,
        // ob die Toolbar angezeigt wird (Archive-Guard).
        render_toolbar(article_element);

        set_header_label(path);
        set_document_title(path);
        mark_active_link(path);

        if (do_push_state)
        {
            let new_url = window.location.pathname + "?path=" + encodeURIComponent(path);
            history.pushState({ path: path }, "", new_url);
        }
    }

    function on_plan_link_click(event)
    {
        let clicked_anchor = event.currentTarget;

        let extracted_path = extract_path_from_anchor(clicked_anchor);

        let path_is_present = extracted_path !== "";

        if (! path_is_present)
        {
            return;
        }

        event.preventDefault();
        load_plan_path(extracted_path, true);
    }

    function attach_plan_link_handlers()
    {
        let plan_link_elements = document.querySelectorAll(plan_link_selector);

        plan_link_elements.forEach(function (link_element)
        {
            link_element.addEventListener("click", on_plan_link_click);
        });
    }

    function on_popstate(event)
    {
        let state_path = event.state && event.state.path ? event.state.path : null;

        let state_has_path = state_path !== null && state_path !== "";

        if (state_has_path)
        {
            load_plan_path(state_path, false);
            return;
        }

        // Kein state.path: User ist zu einem History-Stand ohne Datei zurueck —
        // initialen Platzhalter wiederherstellen.
        show_initial_placeholder();
    }

    // ---- New-Milestone-Action (M012/0005) ---------------------------------
    // Sidebar-Button "+ Milestone" zeigt ein verstecktes Inline-Formular;
    // Submit POSTet JSON an /pm.php?action=new_milestone und reloaded
    // bei Erfolg die Seite, damit die Sidebar den neuen Milestone listet.

    function on_new_milestone_button_click()
    {
        let form_element   = document.querySelector(".new-milestone-form");
        let button_element = document.querySelector(".btn-new-milestone");

        let form_and_button_exist = form_element !== null && button_element !== null;

        if (! form_and_button_exist)
        {
            return;
        }

        form_element.hidden   = false;
        button_element.hidden = true;

        let slug_input = form_element.querySelector(".input-slug");

        let slug_input_exists = slug_input !== null;

        if (slug_input_exists)
        {
            slug_input.focus();
        }
    }

    function on_new_milestone_cancel_click()
    {
        let form_element   = document.querySelector(".new-milestone-form");
        let button_element = document.querySelector(".btn-new-milestone");

        let form_and_button_exist = form_element !== null && button_element !== null;

        if (! form_and_button_exist)
        {
            return;
        }

        let slug_input  = form_element.querySelector(".input-slug");
        let title_input = form_element.querySelector(".input-title");
        let error_node  = form_element.querySelector(".form-error");

        if (slug_input !== null)  { slug_input.value  = ""; }
        if (title_input !== null) { title_input.value = ""; }

        if (error_node !== null)
        {
            error_node.textContent = "";
            error_node.hidden      = true;
        }

        form_element.hidden   = true;
        button_element.hidden = false;
    }

    function show_new_milestone_error(form_element, message)
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

    async function on_new_milestone_submit(event)
    {
        event.preventDefault();

        let form_element = event.currentTarget;

        let form_exists = form_element !== null;

        if (! form_exists)
        {
            return;
        }

        let slug_input  = form_element.querySelector(".input-slug");
        let title_input = form_element.querySelector(".input-title");

        let inputs_exist = slug_input !== null && title_input !== null;

        if (! inputs_exist)
        {
            return;
        }

        let slug_value  = slug_input.value.trim();
        let title_value = title_input.value.trim();

        let slug_is_present  = slug_value !== "";
        let title_is_present = title_value !== "";

        let inputs_are_valid = slug_is_present && title_is_present;

        if (! inputs_are_valid)
        {
            show_new_milestone_error(form_element, "Slug und Titel sind Pflicht.");
            return;
        }

        let response = null;

        try
        {
            response = await fetch(
                "/pm.php?action=new_milestone",
                {
                    method:  "POST",
                    headers: { "Content-Type": "application/json" },
                    body:    JSON.stringify({ slug: slug_value, title: title_value }),
                }
            );
        }
        catch (network_failed)
        {
            console.warn("plan_loader: new_milestone network error", network_failed);
            show_new_milestone_error(form_element, "Netzwerkfehler.");
            return;
        }

        let data = null;

        try
        {
            data = await response.json();
        }
        catch (json_parse_failed)
        {
            console.warn("plan_loader: new_milestone bad json", json_parse_failed);
            show_new_milestone_error(form_element, "Server-Antwort unverstaendlich.");
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

            show_new_milestone_error(form_element, server_message);
            return;
        }

        window.location.reload();
    }

    function init_new_milestone_form()
    {
        let button_element = document.querySelector(".btn-new-milestone");
        let form_element   = document.querySelector(".new-milestone-form");

        let elements_exist = button_element !== null && form_element !== null;

        if (! elements_exist)
        {
            return;
        }

        button_element.addEventListener("click", on_new_milestone_button_click);

        let cancel_button = form_element.querySelector(".btn-cancel-form");

        let cancel_button_exists = cancel_button !== null;

        if (cancel_button_exists)
        {
            cancel_button.addEventListener("click", on_new_milestone_cancel_click);
        }

        form_element.addEventListener("submit", on_new_milestone_submit);
    }

    function init_plan_loader()
    {
        let article_element = get_article_element();

        let article_exists = article_element !== null;

        if (article_exists)
        {
            initial_article_html = article_element.innerHTML;
        }

        attach_plan_link_handlers();

        window.addEventListener("popstate", on_popstate);

        // Bookmark/Reload mit ?path=… in der URL: initialer Fetch.
        // Plan.php hat heute keinen Server-Side-Render des Markdown, also
        // ist der Fetch zwingend, damit der Inhalt erscheint.
        let url_search_params = new URLSearchParams(window.location.search);
        let initial_path      = url_search_params.get("path");

        let initial_path_is_set = initial_path !== null && initial_path !== "";

        if (initial_path_is_set)
        {
            // pushState=false beim Initial-Load: replaceState mit state.path,
            // damit popstate auf diesen Stand zurueckkommen kann.
            history.replaceState({ path: initial_path }, "", window.location.pathname + window.location.search);
            load_plan_path(initial_path, false);
        }

        // New-Milestone-Form (M012/0005) — nur in plan.php vorhanden;
        // init_new_milestone_form ist self-guarded und tut nichts, wenn
        // Button/Form fehlen (z.B. auf info.php).
        init_new_milestone_form();
    }

    document.addEventListener("DOMContentLoaded", init_plan_loader);
})();
