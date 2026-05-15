// @spec
// setup_loader.js — kleiner Click-Handler fuer die Repair-Buttons in der
// Setup-View (M015/0004). Eingebunden in `app/setup.php`. Andere Views
// binden dieses Script bewusst NICHT ein — es ist setup-spezifisch.
//
// Vertrag:
//   - Bindet einen Click-Handler an jedes Element mit `[data-repair-id]`
//     im aktuellen DOM (typischerweise `<button data-repair-id="...">`
//     in der Action-Spalte der Setup-Tabelle).
//   - Klick → `POST /setup.php?action=repair&id=<encoded-id>` per fetch,
//     ohne Body (die Repair-ID steckt komplett in der Query). Nach dem
//     Request (egal ob ok oder fail) wird die Seite einfach reloaded —
//     so re-rendern sich die Check-Stati frisch, und der User sieht
//     sofort, ob die Reparatur geholfen hat (siehe 0004 "## Notes":
//     "Nach einem Repair re-rendert die Setup-Seite die Check-Liste").
//   - Reload statt In-Place-Patch ist bewusst: solange 0005 noch nicht
//     da ist, sind die einzigen Repairs `not_implemented`-Stubs; die
//     UI muss in dem Fall nur sichtbar machen, dass der Endpoint
//     erreichbar war. Reload-Latenz auf einer lokalen PHP-Instanz ist
//     irrelevant.
//
// Self-guarded: tut nichts, wenn keine `[data-repair-id]`-Elemente im
// DOM stehen (Healthy-State: alle Checks `can_repair:false`, also
// kein Button im Markup — siehe setup.php).
//
// Style (Decision 0004 + code_style.md): BSD-Klammern, snake_case,
// `what_cond_means`-Pattern, defensive try/catch um fetch.
// @end-spec

(function ()
{
    async function on_repair_button_click(event)
    {
        let clicked_button = event.currentTarget;

        let button_exists = clicked_button !== null;

        if (! button_exists)
        {
            return;
        }

        let repair_id = clicked_button.getAttribute("data-repair-id");

        let repair_id_is_present = repair_id !== null && repair_id !== "";

        if (! repair_id_is_present)
        {
            return;
        }

        // UI-Lock waehrend des Requests, damit Doppelklicks nicht
        // mehrere parallele POSTs ausloesen. Defensive Massnahme; der
        // Endpoint selbst ist nach 0004 Notes idempotent zu bauen.
        clicked_button.disabled = true;

        let fetch_url = "/setup.php?action=repair&id=" + encodeURIComponent(repair_id);

        try
        {
            await fetch(fetch_url, { method: "POST" });
        }
        catch (network_failed)
        {
            console.warn("setup_loader: repair request failed for id", repair_id, network_failed);
            // Trotz Netzwerkfehler reloaden — der User soll den frischen
            // Check-Stand sehen, statt mit einem disabled Button zu
            // stranden.
        }

        window.location.reload();
    }

    function init_setup_loader()
    {
        let repair_buttons = document.querySelectorAll("[data-repair-id]");

        repair_buttons.forEach(function (button_element)
        {
            button_element.addEventListener("click", on_repair_button_click);
        });
    }

    document.addEventListener("DOMContentLoaded", init_setup_loader);
})();
