<?php

namespace _share;

use _share\exceptions\IdNotFoundError;
use _share\exceptions\NeedsLoginError;
use _share\exceptions\NotAllowedError;
use user\data\User;

class app
{

    private static $logs = [];

    static function escape(string $text): string
    {
        # html-escape for use inside element content and attribute values.
        # ENT_QUOTES handles both single and double quotes.
        # ENT_SUBSTITUTE replaces invalid UTF-8 instead of returning "".
        return htmlspecialchars(
            $text,
            ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
            "UTF-8"
        );
    }

    static function lang(array $languages): string
    {
        return $languages["de"]; # todo: implement...
    }

    static function redirect(?string $path = null): never 
    {
        if ( !$path ) $path = $_SERVER["REQUEST_URI"];
        ob_get_clean();
        header( "Location: {$path}" );
        exit();
    }

    static function enforce_login(): void 
    {
        if ( !app::somebody_is_logged_in() )
        {
            app::redirect( "/" );
        }
    }

    static function enforce_plattform_admin(): void
    {
        # Hard gate for admin-only endpoints. Defensive layering:
        # 1) somebody is logged in at all
        # 2) the logged-in user still exists in the DB
        # 3) that DB row has is_admin == 1
        # Anything else throws — callers do not get to silently
        # downgrade to a non-admin context.

        if ( ! app::somebody_is_logged_in() )
        {
            throw new NeedsLoginError("admin endpoint requires login");
        }

        $current_user_or_null = db::get_by_id(
            User::class,
            app::get_current_user_id()
        );

        if ( ! $current_user_or_null )
        {
            app::error_log("enforce_plattform_admin: session user_id has no DB row");
            throw new NotAllowedError("session user not found");
        }

        $user_is_plattform_admin = $current_user_or_null->is_admin === 1;

        if ( ! $user_is_plattform_admin )
        {
            app::error_log(
                "enforce_plattform_admin: non-admin user_id={$current_user_or_null->id} hit admin endpoint"
            );
            throw new NotAllowedError("admin only");
        }
    }

    static function get_current_user_id(): int
    {
        if ( !app::somebody_is_logged_in() )
        {
            throw new NeedsLoginError();
        }

        return $_SESSION["user_id"];
    }

    static function get_current_user(): User
    {

        $id = app::get_current_user_id();

        $user = db::get_by_id( User::class, $id );

        if ( !$user )
        {
            throw new IdNotFoundError( "Logged in user id not found in database" );
        }

        return $user;

    }

    static function log(mixed $value, string $fn): void
    {
        # ...
    }

    static function error_log(mixed $value): void
    {
        # ...
    }

    static function is_mobile(): bool
    {
        # Cheap UA sniffing. Good enough for index.php-level
        # redirects between desktop and mobile renderings. Not for
        # security decisions.

        $raw_user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "";

        if ($raw_user_agent === "") return false;

        $mobile_marker_pattern = '/(Android|iPhone|iPad|iPod|Mobile|BlackBerry|IEMobile|Opera Mini)/i';

        return preg_match($mobile_marker_pattern, $raw_user_agent) === 1;
    }

    static function is_debug(): bool
    {
        return true;
    }

    static function somebody_is_logged_in(): bool 
    {
        return (
            isset($_SESSION["user_id"]) 
            && is_int($_SESSION["user_id"]) 
            && $_SESSION["user_id"] > 0
        ); 
    }

    static function perform_login(User $user): void 
    {
        $_SESSION["user_id"] = $user->id;
    }

    static function get_logs(): array {
        return [];
    }

    static function function_log(string $message, string $function, string $file, array $data): void
    {

    }

    // @spec
    // app::pm_write_kind($rel_path): string
    //
    // Whitelist-Klassifizierer fuer Info-Schreibziele unter `pm/`.
    // Liefert eines aus {"idea", "report", "audit", "term", "decision"}
    // oder den leeren String, falls der Pfad keiner der Info-Whitelists
    // entspricht.
    //
    // Konvention (M014/0001):
    //   - idea     : ^pm/ideas/[a-z0-9-]+\.md$
    //   - report   : ^pm/reports/\d{4}-[a-z0-9-]+\.md$
    //   - audit    : ^pm/audits/[a-z0-9-]+\.md$
    //   - term     : ^pm/terms/[a-z0-9-]+\.md$
    //   - decision : ^pm/decisions/\d{4}-[a-z0-9-]+\.md$
    //
    // Diese Funktion macht *nur* die Pfad-Klassifizierung. Die
    // Append-only-Regel fuer `decision` (zweite POST gegen
    // existierende Datei -> 409) lebt im Save-Handler in
    // `app/pm.php`, nicht hier.
    // @end-spec
    static function pm_write_kind(string $rel_path): string
    {
        $patterns = [
            "idea"     => '#^pm/ideas/[a-z0-9-]+\.md$#',
            "report"   => '#^pm/reports/\d{4}-[a-z0-9-]+\.md$#',
            "audit"    => '#^pm/audits/[a-z0-9-]+\.md$#',
            "term"     => '#^pm/terms/[a-z0-9-]+\.md$#',
            "decision" => '#^pm/decisions/\d{4}-[a-z0-9-]+\.md$#',
        ];

        foreach ($patterns as $kind => $pattern)
        {
            $path_matches_pattern = preg_match($pattern, $rel_path) === 1;

            if ($path_matches_pattern)
            {
                return $kind;
            }
        }

        return "";
    }

    // @spec
    // app::is_archive_path($rel_path): bool
    //
    // Hard-Guard fuer M014/0006: liefert true genau dann, wenn der Pfad
    // unter einem archivierten Bereich liegt. Wahrheit fuer die Server-
    // Schicht — alle Schreib-/Loesch-Endpunkte (pm.php save, pm.php
    // new_idea/new_report/new_decision/new_ticket/new_milestone, file.php
    // save/new_file/delete_file) lehnen Archiv-Pfade hart mit 400 ab.
    //
    // Regeln:
    //   - true wenn `pm/<x>/.../archive/...` matched — das Segment
    //     `archive` muss als Verzeichnis-Bestandteil unterhalb von
    //     `pm/<x>/` stehen. Regex `^pm/[^/]+/(.*/)?archive(/|$)`.
    //     Beispiele:
    //       * `pm/ideas/archive/foo.md` -> true
    //       * `pm/milestones/014-foo/archive/0001-x.md` -> true
    //       * `pm/milestones/archive/013-x/milestone.md` -> true (faellt
    //         auch hier rein, weil `archive` direkt unter
    //         `pm/milestones/` liegt; der explizite Prefix-Check unten
    //         ist defensiv-redundant)
    //   - true wenn der Pfad mit `pm/milestones/archive/` startet
    //     (archivierter Milestone als Folder, M013-Stil; redundant zur
    //     Regex, defensive Doppelschicht).
    //   - true wenn der Pfad mit `pm/bugs/archive/` startet (redundant
    //     zur Regex, defensive Doppelschicht).
    //   - sonst false.
    //
    // Bewusst: matched NICHT `pm/decisions/0001-archive.md` und
    // aehnliche Substring-Treffer — das Segment `archive` muss als
    // Verzeichnis stehen, nicht als Teil eines Dateinamens.
    // @end-spec
    static function is_archive_path(string $rel_path): bool
    {
        $under_milestone_internal_archive =
            preg_match('#^pm/[^/]+/(.*/)?archive(/|$)#', $rel_path) === 1;

        if ($under_milestone_internal_archive)
        {
            return true;
        }

        $under_milestones_archive_folder =
            str_starts_with($rel_path, "pm/milestones/archive/");

        if ($under_milestones_archive_folder)
        {
            return true;
        }

        $under_bugs_archive_folder =
            str_starts_with($rel_path, "pm/bugs/archive/");

        if ($under_bugs_archive_folder)
        {
            return true;
        }

        return false;
    }

    // @spec
    // app::pm_path_kind_legacy($rel_path): string
    //
    // Klassifiziert die *bisher* (M012/0002) erlaubten Schreibziele
    // unter `pm/milestones/` und `pm/bugs/`. Liefert eines aus
    // {"milestone", "milestone_ticket", "bug_ticket"} oder den
    // leeren String.
    //
    // Bestehende Schreibflows (Plan-View-Edit von milestone.md, Edit
    // eines aktiven Tickets unter `open/`) muessen weiter funktionieren;
    // diese Funktion kapselt die Regeln dafuer, damit der Save-Handler
    // in pm.php die neue Info-Whitelist UND den Legacy-Pfad
    // gemeinsam akzeptieren kann.
    //
    // Konvention (M014/0001):
    //   - milestone        : ^pm/milestones/[a-z0-9-]+/milestone\.md$
    //                        (Archive-Pfad wird hier explizit
    //                        ausgeschlossen — der Aufrufer hat eh schon
    //                        die "/archive/"-Schicht davor.)
    //   - milestone_ticket : ^pm/milestones/[a-z0-9-]+/open/\d{4}-[a-z0-9-]+\.md$
    //   - bug_ticket       : ^pm/bugs/open/\d{4}-[a-z0-9-]+\.md$
    //
    // Bewusst eng gehalten: kein Schreiben in Top-Level wie
    // `pm/how-to/...`, kein Schreiben in `pm/decisions/` via Legacy
    // (Decision laeuft ueber pm_write_kind als eigener Pfad mit
    // Append-only-Regel).
    // @end-spec
    static function pm_path_kind_legacy(string $rel_path): string
    {
        $patterns = [
            "milestone"        => '#^pm/milestones/[a-z0-9-]+/milestone\.md$#',
            "milestone_ticket" => '#^pm/milestones/[a-z0-9-]+/open/\d{4}-[a-z0-9-]+\.md$#',
            "bug_ticket"       => '#^pm/bugs/open/\d{4}-[a-z0-9-]+\.md$#',
        ];

        foreach ($patterns as $kind => $pattern)
        {
            $path_matches_pattern = preg_match($pattern, $rel_path) === 1;

            if ($path_matches_pattern)
            {
                return $kind;
            }
        }

        return "";
    }

}
