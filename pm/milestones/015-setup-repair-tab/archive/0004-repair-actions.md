# 0004 — Repair-Aktionen

## Goal
Pro Check, der `can_repair: true` meldet, gibt es einen Knopf, der eine
benannte Reparatur ausloest. Die Reparatur ist immer **user-getriggert**;
nichts laeuft automatisch.

## Notes
- Repair-Endpoint nimmt nur eine **Whitelist** von Repair-IDs an —
  keine freie Eingabe. Unbekannte IDs → 400.
- Reparaturen muessen idempotent sein: zweimal druecken darf nichts
  kaputt machen.
- Erste Repair-Art ist `restore_how_to:<name>`: legt eine fehlende
  `pm/how-to/<name>.md` aus dem Baseline-Bundle (0005) wieder an.
  Solange 0005 noch nicht da ist, kann der Endpoint die ID schon
  kennen, aber `not_implemented` zurueckgeben.
- Nach einem Repair re-rendert die Setup-Seite die Check-Liste, damit
  der User sofort sieht, ob es geholfen hat.
- Logging: jeder Repair-Versuch geht via `app::error_log()` raus mit
  ID + Resultat.

## Done when
- Endpoint `POST setup.php?action=repair&id=<name>` existiert.
- Mit unbekannter ID → 400 + Logeintrag.
- Mit bekannter, aber nicht-implementierter ID (z.B. `restore_how_to:
  bugs.md` bei fehlendem Baseline-Bundle) → 200 + `{ok:false, status:
  "not_implemented"}` o.ae., Daten unveraendert.
- Klick auf Repair-Button im UI fuehrt den Endpoint aus und re-rendert
  die Check-Liste. Erfolg/Misserfolg sichtbar.

## Out of scope
- Konkrete Repairs jenseits `restore_how_to` — eigene Tickets, sobald
  Bedarf da ist.
- Self-Update / git pull (siehe M016 Out-of-scope).
- Bulk-Repair.

See: pm/how-to/code_style.md

## Plan
- **Endpoint** in `app/setup.php`:
  - `POST ?action=repair&id=<repair-id>`-Handler ergaenzen analog zum
    Method-Dispatch in `pm.php` (oben im File, vor dem Rendering).
  - Whitelist der bekannten Repair-IDs als Konstante:
    ```php
    const REPAIR_IDS = [
        "restore_how_to:bugs.md"      => "restore_how_to",
        "restore_how_to:code_style.md"=> "restore_how_to",
        // ... pro how-to-Datei eine ID
    ];
    ```
    Unbekannte ID → 400 + `app::error_log()`.
  - Dispatch: ID-Praefix bestimmt den Handler (`restore_how_to:...`
    → `restore_how_to_handler($filename)`).
  - Solange 0005 (Baseline-Bundle) nicht da ist:
    `restore_how_to_handler` liefert
    `{ok:false, status:"not_implemented", message:"Baseline-Bundle
    fehlt (siehe 015/0005)"}` mit HTTP 200.
- **Idempotenz**: Repair-Handler so bauen, dass zweimaliger Aufruf
  kein zweites Schreiben macht (z.B. wenn Datei schon existiert →
  `{ok:true, status:"unchanged"}`).
- **Logging**: jeder Repair-Aufruf logged ID + Resultat-Status.
- **UI**: Wenn ein Check `can_repair: true` liefert, rendert
  `setup.php` einen Knopf `<button data-repair-id="...">Repair</button>`
  in der Action-Spalte.
- **JS**: kleines neues Script `app/_share/js/setup_loader.js`:
  - Click-Handler an `[data-repair-id]`: `fetch("/setup.php?action=repair&id=...",
    {method:"POST"})`, dann `window.location.reload()` damit die
    Check-Liste re-rendert wird.
  - In `setup.php` einbinden.
- **Spec-Blocks** an Endpoint, REPAIR_IDS-Konstante, und JS-Datei.
- **Files touched**: `app/setup.php` (Endpoint + UI),
  `app/_share/js/setup_loader.js` (neu).

## Verifikation
- `php -l app/setup.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- `curl -s -X POST "http://127.0.0.1:8086/setup.php?action=repair&id=unbekannt"`
  → 400 + `{ok:false}`.
- `curl -s -X POST "http://127.0.0.1:8086/setup.php?action=repair&id=restore_how_to:bugs.md"`
  → 200 + `{ok:false, status:"not_implemented"}`.
- Browser auf setup.php: Repair-Button nur in Zeilen mit
  `can_repair:true` sichtbar. (Aktuell evtl. keine Zeile, weil 0003
  alle `can_repair:false` setzt — Test mit kuenstlichem Check, der
  `can_repair:true` zurueckgibt.)
- Klick auf Repair-Button → `fetch` feuert, Seite reloaded, Resultat
  sichtbar.
- `git status` clean.

## Out of scope (Plan)
- Tatsaechliche Restore-Implementierung (0005).
- Bulk-Repair.

## Done
- POST-Endpoint `setup.php?action=repair&id=...` ist verdrahtet — Method-
  Dispatch sitzt VOR Header-Setup/Rendering und macht den eigenen
  `Content-Type: application/json` plus `exit()`.
- `REPAIR_IDS`-Konstante als feste Whitelist in `app/setup.php`:
  16 Eintraege `restore_how_to:<filename>` → `restore_how_to`, synchron
  zu `setup_checks::HOWTO_FILES` (alle 16 how-to-Files aus
  `pm/how-to/`). Schema `<handler>:<argument>`; Dispatch splitted
  defensiv mit `explode(":", $id, 2)`.
- Unbekannte / leere ID → HTTP 400 + `{ok:false, status:"unknown_id",
  message:"Unbekannte Repair-ID."}` + `app::error_log()` (inklusive
  `<empty>` als Marker fuer leeren Wert).
- `restore_how_to_handler($filename)` liefert HTTP 200 +
  `{ok:false, status:"not_implemented", message:"Baseline-Bundle fehlt
  (siehe 015/0005).", id:"restore_how_to:<filename>"}`. Bewusst 200
  (nicht 501): der Endpoint funktioniert, nur die Aktion ist noch nicht
  verdrahtet. Idempotenz-Logik (Datei existiert schon → unchanged) ist
  kommentiert vorgemerkt, kommt aber erst mit 0005 zum Tragen, sobald
  das Baseline-Bundle echtes Restore ermoeglicht.
- Logging pro Aufruf: ID + `result=<status>` via `app::error_log()` —
  laeuft VOR `exit()`, damit keine spaete Exception das Log frisst.
  Defensive `no_handler`-Fallback (sollte nie passieren) → HTTP 500
  mit eigenem Log.
- UI in `setup.php`: Action-Spalte rendert nur dann
  `<button type="button" data-repair-id="...">Repair</button>`, wenn
  ein Check `can_repair:true` UND `repair_action !== ""` liefert. Im
  aktuellen Healthy-State (0003 setzt alle 6 Checks auf
  `can_repair:false`) ist also kein Repair-Button im DOM — erwartet.
- Neue JS-Datei `app/_share/js/setup_loader.js`: bindet auf
  DOMContentLoaded einen Click-Handler an `[data-repair-id]`, sperrt
  den Button waehrend des Requests (Doppelklick-Guard), feuert
  `fetch("/setup.php?action=repair&id=<encoded-id>", {method:"POST"})`
  und reloaded danach immer die Seite (auch nach Netzwerkfehler). In
  `setup.php` via `<script src="/_share/js/setup_loader.js"></script>`
  unten in `<body>` eingebunden.
- Spec-Blocks an REPAIR_IDS, `restore_how_to_handler`, dem File-Header
  von `setup.php` (Repair-Endpoint-Vertrag) und der neuen
  `setup_loader.js`. Hinweis zur Sync-Pflicht `REPAIR_IDS` ↔
  `setup_checks::HOWTO_FILES` ist im Spec-Block der Konstante
  festgeschrieben.

Files touched:
- `app/setup.php` (Endpoint-Dispatch + REPAIR_IDS + Handler + UI-Button +
  JS-Include).
- `app/_share/js/setup_loader.js` (neu).
- `pm/milestones/015-setup-repair-tab/milestone.md` (Haekchen +
  archive-Pfad fuer 0004).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server `SPECKIG_ROOT="$(pwd)" php -S 127.0.0.1:8086 -t app`):
- `php -l app/setup.php` → clean.
- `curl -s "http://127.0.0.1:8086/setup.php" | grep -oE 'status-(ok|warn|fail)' | sort | uniq -c`
  → `6 status-ok` (alle Baseline-Checks gruen).
- `curl -s "http://127.0.0.1:8086/setup.php" | grep -c 'data-repair-id'`
  → `0` (Healthy-State, erwartet — keine `can_repair:true`-Checks).
- `curl -s "http://127.0.0.1:8086/setup.php" | grep -c 'setup_loader.js'`
  → `1`.
- `aria-current="page"` exakt 1x im Setup-Response.
- Stub-Test `POST .../setup.php?action=repair&id=restore_how_to:bugs.md`
  → HTTP 200 + `{"ok":false,"status":"not_implemented",...}`.
- Stub-Test mit `id=restore_how_to:README.md` (Capital) → HTTP 200 +
  `not_implemented` (Whitelist trifft auf den exakten Key).
- Unbekannte ID (`id=unknown`) → HTTP 400 +
  `{"ok":false,"status":"unknown_id",...}`.
- Leere ID (`id=`) → HTTP 400 + selber Body.
- GET auf `.../setup.php?action=repair&id=...` → HTTP 200, content-type
  `text/html` (Dispatch greift nur bei POST, GET rendert normal die
  Tabelle).
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./.git/*"`
  zeigt nur `./app.sqlite`. Keine `*.tmp.*`, keine `*.bak` neu.

Plan-Abweichungen:
- Keine. Die im Plan erwaehnte "Idempotenz: zweimaliger Aufruf macht
  kein zweites Schreiben" ist im aktuellen Stub trivial erfuellt — der
  Handler schreibt nichts. Die echte Idempotenz-Verzweigung
  (`unchanged` vs `restored`) wird erst in 0005 implementiert, sobald
  ein Baseline-Inhalt existiert; der Spec-Block am Handler nennt das
  als naechsten Schritt.
