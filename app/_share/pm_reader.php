<?php

declare(strict_types=1);

// @spec
// Read-only Inventur-Buendel fuer den `pm/`-Tree.
// Liefert den Plan-View (M006) eine strukturierte Sicht auf Milestones,
// Tickets und Bugs, ohne Regex und ohne SQL — pure scandir/pathinfo.
// Pfade in der Rueckgabe sind relativ zum Repo-Root (z.B. `pm/milestones/005-spec-parser`).
// Editieren von Tickets/Milestones ist explizit out of scope; nur Lesen.
// @end-spec

namespace _share;

// @spec
// Statisches Funktions-Buendel — keine Instanz, kein Zustand.
// Lowercase-Klassenname nach Decision 0003 (statische Funktions-Buendel
// wie `app`, `db`, `document` sind lowercase).
// @end-spec
class pm_reader
{

    // @spec
    // Liefert ein Array mit zwei Schluesseln:
    //   - `active`: Milestones direkt unter `pm/milestones/`
    //     (z.B. `pm/milestones/005-spec-parser/`), ausser dem `archive/`-Folder.
    //   - `archived`: Milestones unter `pm/milestones/archive/`.
    // Pro Milestone: `slug`, `path`, `title`, `status`,
    // `tickets_open[]` und `tickets_archive[]` (je `{slug, path, title}`).
    // Sortierung nach `slug` aufsteigend (lexikografisch).
    // @end-spec
    static function list_milestones(): array
    {
        $repo_root           = pm_reader::repo_root();
        $milestones_root_abs = $repo_root . "/pm/milestones";

        $active   = pm_reader::collect_milestones_in_dir(
            $milestones_root_abs,
            "pm/milestones",
            $skip_archive_folder = true
        );
        $archived = pm_reader::collect_milestones_in_dir(
            $milestones_root_abs . "/archive",
            "pm/milestones/archive",
            $skip_archive_folder = false
        );

        return [
            "active"   => $active,
            "archived" => $archived,
        ];
    }

    // @spec
    // Liefert ein Array mit zwei Schluesseln `open` und `archive`.
    // Pro Bug: `{slug, path, title}` — slug = Filename ohne `.md`,
    // path relativ zum Repo-Root (z.B. `pm/bugs/open/0001-foo.md`),
    // title = erste `# `-Zeile aus der Datei.
    // Sortierung nach `slug` aufsteigend.
    // @end-spec
    static function list_bugs(): array
    {
        $repo_root = pm_reader::repo_root();

        $open_bugs    = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/bugs/open",
            "pm/bugs/open"
        );
        $archive_bugs = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/bugs/archive",
            "pm/bugs/archive"
        );

        return [
            "open"    => $open_bugs,
            "archive" => $archive_bugs,
        ];
    }

    // @spec
    // Liefert ein assoziatives Array mit fuenf Schluesseln in fixer Reihenfolge:
    //   `ideas`, `reports`, `decisions`, `audits`, `terms`.
    // Pro Schluessel: Liste von `{slug, path, title}` — gleiche Form wie
    // `list_bugs()["open"]`, sortiert nach Slug.
    // Quelle pro Schluessel ist `pm/<key>/` direkt (keine Unterordner).
    // Leere Sektion liefert leeres Array (kein Fehler).
    // Nutzt `collect_tickets_in_dir()`, keine eigene Datei-Iteration.
    // @end-spec
    static function list_info_sections(): array
    {
        $repo_root = pm_reader::repo_root();

        $ideas = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/ideas",
            "pm/ideas"
        );
        $reports = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/reports",
            "pm/reports"
        );
        $decisions = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/decisions",
            "pm/decisions"
        );
        $audits = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/audits",
            "pm/audits"
        );
        $terms = pm_reader::collect_tickets_in_dir(
            $repo_root . "/pm/terms",
            "pm/terms"
        );

        return [
            "ideas"     => $ideas,
            "reports"   => $reports,
            "decisions" => $decisions,
            "audits"    => $audits,
            "terms"     => $terms,
        ];
    }

    // @spec
    // Liest eine Datei unter `pm/` und gibt deren Inhalt zurueck.
    // Pfad-Traversal-Schutz:
    //   - kein `..` im Pfad,
    //   - kein fuehrender `/`,
    //   - Pfad MUSS mit `pm/` beginnen.
    // Bei Verstoss oder fehlender Datei: leerer String.
    // Aufrufer entscheidet, was er damit macht (Render leerer Render-Block etc.).
    // @end-spec
    static function read_markdown(string $repo_relative_path): string
    {
        $path_is_empty = $repo_relative_path === "";

        if ($path_is_empty)
        {
            return "";
        }

        $path_has_traversal = str_contains($repo_relative_path, "..");
        $path_is_absolute   = $repo_relative_path[0] === "/";
        $path_is_under_pm   = str_starts_with($repo_relative_path, "pm/");

        $path_is_safe =
            ! $path_has_traversal
            && ! $path_is_absolute
            && $path_is_under_pm;

        if (! $path_is_safe)
        {
            return "";
        }

        $absolute_path = pm_reader::repo_root() . "/" . $repo_relative_path;

        if (! is_file($absolute_path))
        {
            return "";
        }

        $contents = @file_get_contents($absolute_path);

        if ($contents === false)
        {
            return "";
        }

        return $contents;
    }

    // @spec
    // Repo-Root absolut: `app/_share/` -> zwei Level hoch.
    // Liefert den realpath, damit Pfade konsistent zusammensetzbar sind.
    // @end-spec
    private static function repo_root(): string
    {
        return realpath(__DIR__ . "/../..");
    }

    // @spec
    // Sammelt alle Milestone-Ordner direkt unter `$dir_abs`.
    // Wenn $skip_archive_folder true ist, wird ein Eintrag namens `archive`
    // ausgelassen (gilt nur fuer `pm/milestones/`, nicht fuer
    // `pm/milestones/archive/`).
    // Sortierung nach Ordnername aufsteigend.
    // @end-spec
    private static function collect_milestones_in_dir(
        string $dir_abs,
        string $dir_rel,
        bool $skip_archive_folder
    ): array
    {
        if (! is_dir($dir_abs))
        {
            return [];
        }

        $entries = @scandir($dir_abs);

        if ($entries === false)
        {
            return [];
        }

        $milestone_names = [];

        foreach ($entries as $entry_name)
        {
            $entry_is_hidden = $entry_name === "" || $entry_name[0] === ".";

            if ($entry_is_hidden)
            {
                continue;
            }

            $entry_is_archive_root = $skip_archive_folder && $entry_name === "archive";

            if ($entry_is_archive_root)
            {
                continue;
            }

            $entry_abs = $dir_abs . "/" . $entry_name;

            if (! is_dir($entry_abs))
            {
                continue;
            }

            $milestone_names[] = $entry_name;
        }

        sort($milestone_names, SORT_STRING);

        $milestones = [];

        foreach ($milestone_names as $milestone_slug)
        {
            $milestone_abs    = $dir_abs . "/" . $milestone_slug;
            $milestone_rel    = $dir_rel . "/" . $milestone_slug;
            $milestone_md_abs = $milestone_abs . "/milestone.md";

            $title  = pm_reader::read_first_title_line($milestone_md_abs);
            $status = pm_reader::read_status_line($milestone_md_abs);

            $tickets_open    = pm_reader::collect_tickets_in_dir(
                $milestone_abs . "/open",
                $milestone_rel . "/open"
            );
            $tickets_archive = pm_reader::collect_tickets_in_dir(
                $milestone_abs . "/archive",
                $milestone_rel . "/archive"
            );

            $milestones[] = [
                "slug"            => $milestone_slug,
                "path"            => $milestone_rel,
                "title"           => $title,
                "status"          => $status,
                "tickets_open"    => $tickets_open,
                "tickets_archive" => $tickets_archive,
            ];
        }

        return $milestones;
    }

    // @spec
    // Sammelt alle `*.md`-Dateien direkt unter `$dir_abs`.
    // Slug = Dateiname ohne `.md`, Title = erste `# `-Zeile aus der Datei.
    // Sortierung nach Slug aufsteigend.
    // Versteckte Eintraege und nicht-md-Dateien werden uebersprungen.
    // @end-spec
    private static function collect_tickets_in_dir(string $dir_abs, string $dir_rel): array
    {
        if (! is_dir($dir_abs))
        {
            return [];
        }

        $entries = @scandir($dir_abs);

        if ($entries === false)
        {
            return [];
        }

        $ticket_filenames = [];

        foreach ($entries as $entry_name)
        {
            $entry_is_hidden = $entry_name === "" || $entry_name[0] === ".";

            if ($entry_is_hidden)
            {
                continue;
            }

            $entry_abs = $dir_abs . "/" . $entry_name;

            if (! is_file($entry_abs))
            {
                continue;
            }

            $extension       = pathinfo($entry_name, PATHINFO_EXTENSION);
            $entry_is_markdown = strtolower($extension) === "md";

            if (! $entry_is_markdown)
            {
                continue;
            }

            $ticket_filenames[] = $entry_name;
        }

        sort($ticket_filenames, SORT_STRING);

        $tickets = [];

        foreach ($ticket_filenames as $filename)
        {
            $ticket_abs  = $dir_abs . "/" . $filename;
            $ticket_rel  = $dir_rel . "/" . $filename;
            $ticket_slug = pathinfo($filename, PATHINFO_FILENAME);
            $ticket_title = pm_reader::read_first_title_line($ticket_abs);

            $tickets[] = [
                "slug"  => $ticket_slug,
                "path"  => $ticket_rel,
                "title" => $ticket_title,
            ];
        }

        return $tickets;
    }

    // @spec
    // Liest die erste Zeile, die mit `# ` beginnt, und gibt den Text
    // dahinter (getrimmt) zurueck. Leerer String wenn die Datei nicht
    // existiert oder keine `# `-Zeile enthaelt.
    // Kein Regex, zeilenweise via `file()`.
    // @end-spec
    private static function read_first_title_line(string $absolute_path): string
    {
        if (! is_file($absolute_path))
        {
            return "";
        }

        $lines = @file($absolute_path, FILE_IGNORE_NEW_LINES);

        if ($lines === false)
        {
            return "";
        }

        foreach ($lines as $line)
        {
            $line_is_title = str_starts_with($line, "# ");

            if ($line_is_title)
            {
                return trim(substr($line, 2));
            }
        }

        return "";
    }

    // @spec
    // Liest die erste Zeile, die mit `Status:` beginnt, und gibt den Text
    // dahinter (getrimmt) zurueck. Leerer String wenn die Datei nicht
    // existiert oder keine `Status:`-Zeile enthaelt.
    // @end-spec
    private static function read_status_line(string $absolute_path): string
    {
        if (! is_file($absolute_path))
        {
            return "";
        }

        $lines = @file($absolute_path, FILE_IGNORE_NEW_LINES);

        if ($lines === false)
        {
            return "";
        }

        foreach ($lines as $line)
        {
            $line_is_status = str_starts_with($line, "Status:");

            if ($line_is_status)
            {
                return trim(substr($line, strlen("Status:")));
            }
        }

        return "";
    }

}
