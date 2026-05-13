// helpers.js — generische Browser-Wrapper fuer den Speckig-UX-Layer.
// JSON-safe localStorage-Wrapper. Geladen als Top-Level-Script, keine Module.
// Style: BSD-Klammern (analog Decision 0004), snake_case, let/const.

function local_get(key, fallback)
{
    let raw_value = null;

    try
    {
        raw_value = window.localStorage.getItem(key);
    }
    catch (storage_unavailable)
    {
        return fallback;
    }

    let no_value_stored = raw_value === null;

    if (no_value_stored)
    {
        return fallback;
    }

    try
    {
        return JSON.parse(raw_value);
    }
    catch (parse_failed)
    {
        return fallback;
    }
}

function local_set(key, value)
{
    let serialized_value = JSON.stringify(value);

    try
    {
        window.localStorage.setItem(key, serialized_value);
    }
    catch (storage_unavailable)
    {
        // localStorage kann blockiert sein (private mode, quota voll).
        // UX-Layer soll dann lautlos weiterlaufen, kein User-Schaden.
    }
}
