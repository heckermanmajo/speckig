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
//   Erfolg : HTTP 200 + { ok: true,  path, html, status }
//   Fehler : HTTP 400 + { ok: false, message }
//
// Render-Strategie: data.html ist Parsedown-Output ueber Repo-kontrolliertem
// Markdown — in V1 setzen wir den HTML-String direkt via innerHTML ein.
// data.status wird mittels textContent (escape via DOM) geschrieben.
// Markdown-XSS-Hardening ist out of scope.
// @end-spec

(function ()
{
    let article_selector       = "article#content";
    let header_label_selector  = "#header-path-label";
    let plan_link_selector     = "a.plan-milestone-link, a.plan-ticket-link";
    let document_title_base    = "speckig";
    let initial_article_html   = "";

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
    }

    document.addEventListener("DOMContentLoaded", init_plan_loader);
})();
