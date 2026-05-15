# CLAUDE.md

Kontext für Claude/Bot-Sessions in diesem Repo.

## Wo Konventionen leben

- `pm/how-to/` — vollständige Dokumentation. **Start hier**, vor allem
  `process.md`, `commit.md`, `milestones.md`, `bugs.md`, `code_style.md`.
- `pm/decisions/` — verbindliche Entscheidungen, append-only.
- `*.spec` neben Code — pro Code-Datei.

## Arbeitsmodus: ein Subagent pro Ticket

Jedes Ticket wird in einer **eigenen Subagent-Session** umgesetzt
(Agent-Tool, `subagent_type: general-purpose`). Die Main-Session plant,
schneidet Tickets, eröffnet Decisions — die Subagents implementieren.

Gründe:
- Hauptkontext bleibt klein, auch über lange Milestones.
- Jede Ticket-Umsetzung ist isoliert verifizierbar.
- Streu-Effekte (Streu-Files, vergessene Server-Prozesse) bleiben in der
  Subagent-Welt sichtbar.

Pro Subagent-Prompt liefere mit:

1. **Ticket-Pfad** explizit nennen, sagen "Done-when ist dein Vertrag".
2. **Pflicht-Lektüre** in Reihenfolge: das Ticket selbst, betroffene
   Decisions, relevante how-to-Files, archivierte Tickets als
   `## Done`-Vorlage, der zu ändernde Code.
3. **cwd-Pflicht**: erster Bash-Aufruf muss `cd /home/mo/Schreibtisch/speckig`
   sein. Sonst landen Streu-Files in Unterordnern.
4. **Verifikations-Checkliste** mit konkreten curl/grep/`php -l`-Befehlen,
   Smoketest gegen `php -S` im Hintergrund.
5. **Streu-File-Check**: `find … -name "app.sqlite*"` darf nur den
   kanonischen Pfad zeigen. Aufräumen falls nicht.
6. **Server-Cleanup**: gestartete Server am Ende mit `TaskStop`. Den
   8083-Server des Users NICHT killen — der gehört zur User-Session.
7. **Workflow**: Tickets durchlaufen drei Commits (`open` → `plan` →
   Close). Der Subagent macht in der Regel nur den **Close-Commit**:
   `## Done` ans Ticket, `git mv open/ → archive/`, milestone.md
   häkchen, Code + Spec — **alles in einem Close-Commit** mit Format
   `[MMM/NNNN] <summary>` oder `[bug/NNNN] <summary>`. Wenn das Ticket
   noch keine `## Plan`-Section hat, darf der Subagent vor dem
   Implementieren einen eigenen Plan-Commit (`[MMM/NNNN] plan <title>`)
   davor setzen — niemals Plan und Close in einen Commit zusammen.
   HEREDOC für den Body, kein Co-Authored-By, kein `--no-verify`,
   NICHT pushen. Siehe `pm/how-to/commit.md` und `pm/how-to/process.md`.
8. **Rückmeldung** explizit anfordern: Files, Smoketest-Belege,
   Commit-Hash, Cleanup-Status, Ungewöhnliches.

Nach Subagent-Rückkehr: in der Main-Session immer **selbst verifizieren**
(commit, lint, einen Smoketest gegen den Live-Server), nicht blind dem
Bericht vertrauen. Dann erst pushen.

## Was die Main-Session NICHT macht

- Code für Ticket selbst schreiben — das ist Subagent-Aufgabe.
- Push ohne explizites OK des Users.
- Decisions editieren (immer supersede statt edit, siehe
  `pm/how-to/decisions.md`).
- Archivierte Tickets editieren.

## Wichtige Spezifika

- PHP 8.5, kein Composer, kein npm, kein Bundler.
- Vendored Code unter `app/_share/vendor/` (PHP) bzw. `app/_share/js/` (JS).
- BSD-Klammern in PHP **und** JS (Decision 0004).
- Methoden/Funktionen immer `snake_case`. Klassen `PascalCase` nur wenn
  instanziierbar; statische Funktionsbündel wie `app`, `db`, `document`
  sind lowercase (Decision 0003).
- `app::escape()` für jeden User-sichtbaren String. `app::error_log()`
  statt eigenem Logger.
- `cursor: pointer` und child-counts im Tree sind schon da (M002/0001).
- AJAX-Layer wird in M002/0005 dazukommen; konservatives PHP ist seit
  Decision 0004 explizit für UX gelockert.
