<?php

declare(strict_types=1);

// @spec
// Geteilter Header fuer Speckig-Hauptansichten.
// Wird sowohl von der Tree-View (`app/index.php`, files-View) als auch von
// der spaeteren Plan-View (`app/plan.php`, M006/0002) genutzt.
// Liefert HTML als String zurueck (kein Echo) — der Aufrufer setzt es
// dort ein, wo er den `<header>`-Block braucht.
// XSS-Sicherheit: `$path_label` und `$repo_label` werden in `render()`
// selbst escaped; Aufrufer muss nicht escapen.
// @end-spec

namespace _share\html;

use _share\app;

// @spec
// Statisches Funktions-Buendel — keine Instanz.
// Lowercase-Klassenname nach Decision 0003.
// @end-spec
class header
{

    // @spec
    // Rendert den `<header>`-Block.
    // $active_view: "files", "plan" oder "info" — markiert den
    // entsprechenden Nav-Link mit `aria-current="page"` und Klasse
    // `active`. Reihenfolge der Tabs im Header: Files, Plan, Info.
    // $path_label: rechts angezeigter Pfad (z.B. die aktuell angeklickte
    // Datei). Leerer String = nichts anzeigen.
    // $repo_label: Name des Repos/Roots (z.B. `speckig`). Leerer String
    // = nichts anzeigen.
    // Beide Labels werden hier mit `app::escape()` escaped.
    // @end-spec
    static function render(string $active_view, string $path_label, string $repo_label): string
    {
        $files_is_active = $active_view === "files";
        $plan_is_active  = $active_view === "plan";
        $info_is_active  = $active_view === "info";

        $files_link_attrs = header::build_nav_link_attrs($files_is_active);
        $plan_link_attrs  = header::build_nav_link_attrs($plan_is_active);
        $info_link_attrs  = header::build_nav_link_attrs($info_is_active);

        $repo_label_is_present = $repo_label !== "";
        $path_label_is_present = $path_label !== "";

        $rendered = "<header>";
        $rendered .= "<nav class=\"header-nav\" aria-label=\"Hauptnavigation\">";
        $rendered .= "<a href=\"/index.php\"" . $files_link_attrs . ">Files</a>";
        $rendered .= "<a href=\"/plan.php\""  . $plan_link_attrs  . ">Plan</a>";
        $rendered .= "<a href=\"/info.php\""  . $info_link_attrs  . ">Info</a>";
        $rendered .= "</nav>";
        $rendered .= "<strong>speckig</strong>";

        if ($repo_label_is_present)
        {
            $rendered .= " · <code>" . app::escape($repo_label) . "</code>";
        }

        $rendered .= " — <span id=\"header-path-label\">";

        if ($path_label_is_present)
        {
            $rendered .= app::escape($path_label);
        }

        $rendered .= "</span>";
        $rendered .= "</header>";

        return $rendered;
    }

    // @spec
    // Baut die HTML-Attribute fuer einen Nav-Link.
    // Aktiv = Klasse `header-nav-link active` und `aria-current="page"`.
    // Inaktiv = nur Klasse `header-nav-link`.
    // Rueckgabe enthaelt fuehrendes Leerzeichen, damit sie direkt nach dem
    // `href`-Attribut konkateniert werden kann.
    // @end-spec
    private static function build_nav_link_attrs(bool $link_is_active): string
    {
        if ($link_is_active)
        {
            return " class=\"header-nav-link active\" aria-current=\"page\"";
        }

        return " class=\"header-nav-link\"";
    }

}
