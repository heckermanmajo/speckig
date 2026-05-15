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
