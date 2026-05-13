// tree_collapse.js — persistiert den open/closed-Zustand jedes <details data-path="…">
// in localStorage. Laedt nach helpers.js. Default: alle Ordner offen (wie Server-Render).
//
// Speicher-Semantik unter Key "speckig.collapse.<path>":
//   true  -> User hat den Ordner aktiv zugeklappt.
//   false -> User hat den Ordner aktiv aufgeklappt (Default-Zustand, selten gespeichert).
//   null/undefined (kein Eintrag) -> nie angefasst, Server-Default (open) bleibt.
//
// Beim DOMContentLoaded wird nur dann ein Eintrag wirksam, wenn er wirklich gesetzt ist;
// fehlt der Key, bleibt das vom Server gerenderte <details open> unveraendert.

(function ()
{
    let collapse_key_prefix = "speckig.collapse.";

    function key_for_path(rel_path)
    {
        return collapse_key_prefix + rel_path;
    }

    function apply_stored_state(details_element)
    {
        let rel_path = details_element.getAttribute("data-path");

        let path_is_missing = rel_path === null || rel_path === "";

        if (path_is_missing)
        {
            return;
        }

        let stored_is_collapsed = local_get(key_for_path(rel_path), null);

        let no_state_stored = stored_is_collapsed === null;

        if (no_state_stored)
        {
            return;
        }

        let should_be_open = stored_is_collapsed === false;

        details_element.open = should_be_open;
    }

    function attach_toggle_listener(details_element)
    {
        details_element.addEventListener("toggle", function ()
        {
            let rel_path = details_element.getAttribute("data-path");

            let path_is_missing = rel_path === null || rel_path === "";

            if (path_is_missing)
            {
                return;
            }

            let is_collapsed_now = ! details_element.open;

            local_set(key_for_path(rel_path), is_collapsed_now);
        });
    }

    function init_tree_collapse()
    {
        let details_elements = document.querySelectorAll("details[data-path]");

        details_elements.forEach(function (details_element)
        {
            apply_stored_state(details_element);
            attach_toggle_listener(details_element);
        });
    }

    document.addEventListener("DOMContentLoaded", init_tree_collapse);
})();
