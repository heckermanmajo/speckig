// content_loader.js — AJAX-Loader fuer den Content-Bereich (Ticket 002/0005).
// Tree-Klicks laden /file.php per fetch, schreiben das HTML in <article id="content">,
// spiegeln den Pfad via history.pushState in der URL und im Header-Label.
// Laedt nach helpers.js und tree_collapse.js. Top-Level-Script, kein Module-System.
//
// Style (Decision 0004): BSD-Klammern, snake_case, `what_cond_means`-Pattern,
// let/const, async/await, defensive try/catch um fetch (Netzwerk kann wegbrechen).
//
// Endpoint-Vertrag (siehe app/file.php, Ticket 002/0004):
//   Erfolg : HTTP 200 + { ok: true,  path, html }
//   Fehler : HTTP 400 + { ok: false, message }

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

        article_element.innerHTML = data.html;
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
