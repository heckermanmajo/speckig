# 0001 — Save-Endpoint fuer Info-Files (Ideas / Reports / Audits / Terms)

## Goal
Inhalte unter `pm/ideas/`, `pm/reports/`, `pm/audits/`, `pm/terms/` lassen
sich aus dem UI speichern. `pm/decisions/` darf nur **neu angelegt**, nie
ueberschrieben werden.

## Notes
- Decisions sind append-only — Supersede via neue Datei, nie Edit
  (siehe `pm/how-to/decisions.md`). Der Endpoint muss das hart
  durchsetzen, nicht nur das UI.
- Pfad-Whitelist wirklich als Whitelist bauen, nicht als Blacklist:
  nur `pm/ideas/`, `pm/reports/`, `pm/audits/`, `pm/terms/` schreibbar;
  `pm/decisions/` nur create wenn der Zielpfad noch nicht existiert.
- Archivierte Tickets / Milestones (`pm/**/archive/`) bleiben
  read-only — kein Schreiben dort, auch wenn die Section es waere.
- Wiederverwendung der Save-Logik aus `app/pm.php` / `app/file.php` ist
  ok, aber keine stille Erweiterung deren Whitelists.
- Body-Limit, Binary-Guard, atomic-write wie in M012/0002 und
  M013/0001.

## Done when
- POST gegen einen Pfad in `pm/ideas/*.md`, `pm/reports/*.md`,
  `pm/audits/*.md`, `pm/terms/*.md` schreibt und liefert
  `{ok:true,path,bytes}`.
- POST gegen einen **neuen** Pfad in `pm/decisions/` schreibt einmal,
  ein zweiter POST auf denselben Pfad wird mit 400/409 abgelehnt.
- POST gegen Pfade ausserhalb der Whitelist (z.B. `pm/how-to/foo.md`,
  `pm/milestones/...`, irgendwas in `archive/`) wird mit 400 abgelehnt.
- Spec-Block am Endpoint dokumentiert Whitelist + Decision-Sonderregel.

## Out of scope
- UI-Wiring (eigenes Ticket 0002).
- "Neue Idea anlegen" / "neuer Report" / "neue Decision" — eigene
  Tickets 0003 / 0004 / 0005.
- Archiv-Schutz (eigenes Ticket 0006 — der Endpoint hier verhindert
  Schreiben ins Archiv schon implizit ueber die Whitelist; das Ticket
  0006 macht es zur expliziten Schicht).

See: pm/how-to/decisions.md, pm/how-to/ideas.md, pm/how-to/reports.md

## Plan
- **Wo der Code lebt**: existierender Save-Handler in `app/pm.php`
  (ab `if ($method_is_post && $action_is_save)`). Der Handler kennt
  heute nur die Schicht `pm/*.md, kein archive/`. Diese Whitelist wird
  feinkoerniger.
- **Whitelist-Helper**: in `app/_share/app.php` eine neue statische
  Funktion `app::pm_write_kind($rel_path): string` einfuehren —
  liefert eines aus `"idea" | "report" | "audit" | "term" | "decision"
  | ""`. Der Save-Handler lehnt `""` mit 400 ab.
  - `idea`: `^pm/ideas/[a-z0-9-]+\.md$`
  - `report`: `^pm/reports/\d{4}-[a-z0-9-]+\.md$`
  - `audit`: `^pm/audits/[a-z0-9-]+\.md$`
  - `term`: `^pm/terms/[a-z0-9-]+\.md$`
  - `decision`: `^pm/decisions/\d{4}-[a-z0-9-]+\.md$`
- **Decision-Sonderregel**: wenn `pm_write_kind === "decision"` und
  `file_exists(target_abs)` → 409 `Decision ist append-only`. Vorher
  Log via `app::error_log()`.
- **Archive bleibt verboten**: die existierende
  `str_contains($path, "/archive/")`-Schicht bleibt VOR der Whitelist
  — Doppelschicht, weil 0006 das explizit absichert.
- **Bestehende Whitelist erweitern, nicht ersetzen**: der heutige
  Handler erlaubt z.B. milestone-Files (`pm/milestones/.../open/...md`)
  zum Editieren. Die existierende M012-Funktionalitaet darf nicht
  brechen. Vorgehen: nach dem Markdown-Check eine
  `$save_kind = app::pm_write_kind($save_raw_path)` Probe; **wenn
  leer**, naechste Fallback-Schicht (Milestone-Tickets / `milestone.md`
  wie bisher) durchpruefen. Nur wenn beide Schichten ablehnen → 400.
  - Konkret: Hilfsfunktion `app::pm_path_kind_legacy($rel)` extrahieren,
    die genau die heute geltenden Regeln kapselt (milestone.md,
    `open/NNNN-foo.md`). Save akzeptiert wenn `pm_write_kind !== ""`
    ODER `pm_path_kind_legacy !== ""`.
- **Binary-Guard**: bereits in M013/0001 implementiert; analog
  uebernehmen — `str_contains(substr($body, 0, 8192), "\0")` → 400
  `Binaere Inhalte nicht erlaubt`.
- **Body-Limit**: bereits 1 MB in pm.php; bleibt.
- **Atomic write**: bereits tmp+rename in pm.php; bleibt.
- **Spec-Block**: `@spec`-Kommentar oberhalb des Save-Blocks
  erweitern mit der Whitelist-Tabelle und der Decision-Sonderregel.
- **Files touched**: `app/pm.php` (Save-Handler), `app/_share/app.php`
  (zwei neue static helpers + Spec-Blocks).

## Verifikation
- `php -l app/pm.php app/_share/app.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- M012 Regression: `curl -s -X POST --data 'x' "http://127.0.0.1:8086/pm.php?action=save&path=pm/ideas/talk_about_code_base.md"` → restore via git checkout.
  (Falls Datei nicht existiert: ein anderes Idea-File benutzen — vorher `ls pm/ideas/`.)
- Happy path Idea: POST auf `pm/ideas/plan-smoke.md` → 200,
  `cat` zeigt Body, `rm` zum cleanup.
- Happy path Report: POST auf `pm/reports/9999-smoke.md` → 200, `rm`.
- Decision create: POST auf `pm/decisions/9999-smoke.md` → 200.
- Decision overwrite: zweiter POST auf denselben Pfad → 409,
  `cat` zeigt unveraenderten Inhalt. Cleanup: `rm`.
- Reject: POST auf `pm/how-to/foo.md` → 400 (nicht in Whitelist und
  auch nicht im Legacy-Pfad).
- Reject Archive: POST auf `pm/ideas/archive/foo.md` → 400 (gar nicht
  erst eine Whitelist-Verletzung — kommt vom existierenden Archive-
  Guard).
- M013 Regression: GET/POST gegen `file.php` (M013-Save) unveraendert
  funktional — separater Endpoint, sollte nicht betroffen sein, aber
  ein curl-Check schadet nicht.
- `find . -name "*.tmp.*" -not -path "./.git/*"` leer.
- `git status` clean.
- Server stoppen, 8083 nicht anfassen.

## Out of scope (Plan)
- Keine Refactoring-Welle in `app/pm.php` — nur die Erweiterung der
  Whitelist und der Decision-Append-only-Pfad.
- Keine UTF-8-Validierung jenseits Binary-Guard.

## Done
- `app/_share/app.php` um zwei statische Helper erweitert:
  - `app::pm_write_kind($rel)` -> `"idea" | "report" | "audit" | "term" |
    "decision" | ""`. Regex-Whitelist wie im Plan; Audit-/Idea-/Term-
    Slugs sind `[a-z0-9-]+`, Report-/Decision-Files erzwingen das
    `NNNN-<slug>`-Praefix.
  - `app::pm_path_kind_legacy($rel)` -> `"milestone" |
    "milestone_ticket" | "bug_ticket" | ""`. Kapselt die bisher in
    pm.php implizit erlaubten Schreibziele (milestone.md, aktive
    Milestone-Tickets unter `open/`, aktive Bug-Tickets unter
    `pm/bugs/open/`). `pm/decisions/` taucht in der Legacy-Liste
    bewusst NICHT auf — Decisions laufen ueber pm_write_kind, damit
    die Append-only-Schicht greift.
- `app/pm.php` Save-Handler nach dem bestehenden String-/Markdown-/
  Archive-Check um folgende Schichten erweitert:
  - **Whitelist**: `pm_write_kind(...) !== "" || pm_path_kind_legacy(...) !== ""`,
    sonst 400 `Ungueltiger Pfad.` + Log mit `(whitelist)`-Marker.
  - **Decision-Append-only**: wenn `pm_write_kind === "decision"` und
    Zielpfad existiert -> 409 `Decision ist append-only.` + Log.
    Der Check sitzt nach dem parent-Check und vor dem Body-Read,
    damit wir keine Bytes umsonst lesen.
  - **Binary-Guard** (M013/0001-Vorbild): erste 8 KB nach `\0`
    durchsuchen -> 400 `Binaere Inhalte nicht erlaubt.` + Log.
- Spec-Block am Datei-Kopf um den M014/0001-Vertrag erweitert,
  inline `@spec` ueber dem Save-Handler um Whitelist-Tabelle,
  Decision-Sonderregel und Binary-Guard erweitert.
- Bestehende Schichten (Pfad-String, `archive/`-Defense, parent-
  realpath, 1 MB-Limit, atomic tmp+rename, Logging) bleiben
  unveraendert.

Files touched:
- `app/_share/app.php` (+95 / -1, zwei neue static helpers + Spec-
  Blocks).
- `app/pm.php` (+~80 / -5, Header-Spec + Save-Handler-Spec + neue
  Whitelist-, Decision-, Binary-Guard-Schichten).
- `pm/milestones/014-edit-info-cm/milestone.md` (Haekchen +
  archive/-Pfad).
- ticket selbst nach `archive/`.

Smoketest-Belege:
- `php -l app/pm.php app/_share/app.php` -> clean.
- Happy Idea  `pm/ideas/plan-smoke.md`        -> 200 + `bytes:10`, cat zeigt Body, cleanup.
- Happy Report `pm/reports/9999-smoke.md`     -> 200 + `bytes:12`, cleanup.
- Happy Audit `pm/audits/plan-smoke.md`       -> 200 + `bytes:11`, cleanup.
- Decision create `pm/decisions/9999-smoke.md` -> 200 + `bytes:14`, Inhalt `first decision`.
- Decision overwrite (zweiter POST)            -> 409 + `Decision ist append-only.`; `cat` zeigt UNVERAENDERTEN Inhalt; cleanup.
- Reject Whitelist-Miss `pm/how-to/foo.md`     -> 400 + `Ungueltiger Pfad.`.
- Reject Archive `pm/ideas/archive/foo.md`    -> 400 (greift bereits am Archive-Guard, also vor Whitelist).
- Reject Traversal `path=../etc/passwd`       -> 400.
- Binary-Guard NUL-Byte                       -> 400 + `Binaere Inhalte nicht erlaubt.`; `bin-smoke.md` nicht entstanden.
- Body-Limit ~1.1 MB                          -> 413 + `Body zu gross.`; `big-smoke.md` nicht entstanden.
- M012 Regression (`pm/milestones/014-edit-info-cm/open/0001-save-info-endpoint.md` ueberschreiben) -> 200 + `bytes:20`, danach via Backup restauriert, `git diff` fuer das File leer.
- Streu-File-Check: nur `./app.sqlite`; keine `*.tmp.*`.
