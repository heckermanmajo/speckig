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
