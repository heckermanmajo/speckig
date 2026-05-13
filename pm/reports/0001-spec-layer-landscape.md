# 0001 — Spec-Layer Landscape

Date: 2026-05-13
Type: research
Status: final

## TL;DR
- Direkter Konkurrent: **SpecDD** (`.sdd` neben Code) — gleiche Philosophie, keine Drift-Detection.
- Niemand kombiniert *adjacent specs + Git-basierte Drift + MCP*. Klare Nische für speckig.
- **OpenLore** macht Drift-Detection, aber zentral statt adjacent.
- **SKILL.md** (YAML-Frontmatter + Body) wird zum Format-Standard quer durch alle Agents — wir sollten kompatibel bleiben.
- MCP-Server für Specs/ADRs als first-class Resource: **existiert nicht** — Marktlücke.

## Findings

### A — Spec-Driven Dev Tools (direkte Konkurrenz)

- **SpecDD** — `specdd.ai` — `.sdd` neben Code, Inheritance über Dirs, Sektionen Purpose/Owns/Must/Forbids. *Keine Drift-Detection.* Stärkster direkter Konkurrent.
- **GitHub Spec Kit** — `github.com/github/spec-kit` — 7-Phasen-Workflow, zentrale Specs in `.specify/specs/`. ~93k Stars. Workflow-orientiert, nicht file-adjacent.
- **OpenSpec (Fission-AI)** — `github.com/Fission-AI/OpenSpec` — ~47k Stars, Change-Proposals mit Delta-Specs (ADDED/MODIFIED/REMOVED).
- **spec-kitty (Priivacy)** — `github.com/Priivacy-ai/spec-kitty` — 1.2k Stars, git-worktree-Isolation + Kanban-Dashboard. Mission-zentriert.
- **Kiro (Amazon)** — `kiro.dev` — Closed-Source IDE, generiert `requirements.md`/`design.md`/`tasks.md`. Eingebaute Drift-Detection. IDE-gebunden.
- **Tessl** — `tessl.io` — Registry-Modell für Library-Skill-Pakete, 10k+ Specs. Anderer Use-Case.
- **OpenLore (ex-spec-gen)** — `github.com/clay-good/spec-gen` — *Echte Drift-Detection* via Git-Diff vs Spec-Mapping, kategorisiert Gap/Stale/Uncovered/Orphaned. Closest match — aber zentrale Specs.
- **Intent (Augment)** — closed-source — "Living Specs", Spec aktualisiert sich automatisch wenn Code geändert wird. Macht das, was speckig *nicht* macht (auto-sync statt drift-show).
- **BMAD-METHOD** — 46.7k Stars — Agent-Orchestrierung, weniger Spec-Tool.

### B — Drift Detection & MCP für Code

Drift-Approaches:
- **Semcheck** — Go-CLI mit LLM-Backend, semantische Spec↔Code-Validierung, läuft pre-commit nur auf geänderten Rules. `github.com/rejot-dev/semcheck` — **Pattern für uns relevant.**
- **OpenSpec Profile Sync** — vergleicht installierte Files gegen User-Global-Config, meldet Mismatches.
- **Drift Detection Claude Skill** — Spec-Alignment-Spezialist, monitort PRDs vs Code+Tests. `mcpmarket.com/tools/skills/drift-detection`
- **CodeScene Hotspots** — Git-Churn × Code-Health als Risk-Score. Pattern übertragbar auf Spec-Files.
- **Sourcegraph Code Graph** — Semantic Index (Definitions/References/Symbols). Graph-Basis, kein Drift.
- **Copilot Workspace** — Bidirektionale Task↔Spec↔Plan↔Code-Pipeline.

MCP-Server (Code/Repo):
- **Official Reference Servers** — Filesystem, Git, Memory, Sequential Thinking. Baseline. `github.com/modelcontextprotocol/servers`
- **Context7 (Upstash)** — On-demand Library-Docs als MCP-Tool. Pattern: indexed registry + snippet retrieval.
- **Claude Context (zilliztech)** — Repo→Embeddings→NL-Querying. Production-grade Repo-Kontext.
- **CocoIndex Code** — Tree-sitter AST-aware Embedding, 70% Token-Reduktion, 80-90% Cache-Hit.
- **Tree-sitter MCP (wrale)** — Reines AST über MCP, ohne Embedding-Layer.
- **MCP-Spec 2025-11-25** — Server Cards (`.well-known`), MCP Apps, Audit-Logs. An Linux Foundation gespendet.
- **Lücke:** Kein MCP-Server für ADRs/Specs/Requirements als first-class typed Resource.

### C — Tickets-in-Repo, ADR, lokale UIs

Tickets-in-Repo:
- **git-bug** — Tickets als Git-Objekte, CLI/TUI/Web. `github.com/git-bug/git-bug`
- **wedow/ticket** — Bash-Script, Markdown+YAML-Frontmatter, agent-designed. `github.com/wedow/ticket`
- **Git-issues (HN)** — Go-Binary, generiert `.agent.md` für Claude Code automatisch.
- **claude-task-master** — MCP-Server für Cursor/Claude, parst PRD→Tasks, `tasks.json` + per-Task Markdown.
- **Fossil** — VCS+Wiki+Tickets+Forum, eingebaute Web-UI.

ADR-Tools:
- **adr-tools (npryce)** — Bash, Nygard-Format, Quasi-Standard, wenig gepflegt.
- **log4brains** — Node-Tool, Static-Site, MADR-Default, **slug-based Filenames** (vermeidet Merge-Konflikte). `github.com/thomvaill/log4brains`
- **MADR** — Markdown Any Decision Records, schlankes Template.

Lokale Markdown-UIs:
- **Obsidian** — Closed-Source, riesiges Plugin-Ökosystem, Graph-View.
- **Logseq** — Open-Source, AGPL-3, outline-orientiert, Datalog-Queries.
- **silverbullet.md** — Self-hosted Browser, Live-Preview, **Space Lua als embedded Scripting**.
- **markserv** — Node-CLI, WebSocket-Live-Reload bei Save.
- **Mistralys/markdown-viewer** — PHP 7.4+, Bootstrap-5, Auto-TOC, `{include-file}`. *Relevant für die PHP-UI-Idee.*
- **php5-markdown-wiki (isofarro)** — Minimales PHP-Wiki, gute Vorlage.
- **PHP Live-Reload via SSE** — single-class Pattern (felipperegazio).

## Hooks for us

1. **Drift via Git-Diff pro Spec-File** (à la OpenLore, aber adjacent): `spec status` nutzt `git log` mtime/churn pro `*.spec` und zugehörige `*.code`, zeigt Risk-Score.
2. **Semcheck-Pattern** für optionale LLM-Validierung: nur geänderte Specs werden semantisch gecheckt — billig, pre-commit-tauglich.
3. **MCP-Server für `.spec`-Files** bauen (Lücke!): typed Resources mit Status, Diff-since-Ref, list-orphaned, list-drifted. `.well-known/mcp` für Discovery.
4. **YAML-Frontmatter kompatibel zu SKILL.md** halten — Mindestfelder `name:`, `description:`. Erlaubt späteren Re-Export als Skill.
5. **Slug-basierte ADR-Filenames** (log4brains-Pattern) statt strikt sequentiell — vermeidet Merge-Konflikte. **Anpassung an unser `pm/decisions/` Schema prüfen.**
6. **Auto-generiertes `pm/.agent.md`** als Index für LLMs — bei Speichern regenerieren, vermeidet 100-File-Crawl.
7. **PHP-UI Stack-Empfehlung**: Mistralys/markdown-viewer als Basis + SSE-Hot-Reload-Pattern für die `php-web-ui` Idee (`pm/ideas/php-web-ui.md`).
8. **Tree-sitter AST-Anchors** statt File-Pfaden als stabile Spec↔Code-Links — überlebt Refactorings. *Komplex, später.*

## Sources

Spec-Driven Tools:
- https://specdd.ai/
- https://github.com/github/spec-kit
- https://github.com/Fission-AI/OpenSpec
- https://github.com/Priivacy-ai/spec-kitty
- https://kiro.dev/
- https://tessl.io/
- https://github.com/clay-good/spec-gen
- https://github.com/bmad-code-org/BMAD-METHOD

Drift & MCP:
- https://github.com/rejot-dev/semcheck/
- https://deepwiki.com/Fission-AI/OpenSpec/8.12-profile-sync-and-drift-detection
- https://mcpmarket.com/tools/skills/drift-detection
- https://codescene.com/product/code-health
- https://sourcegraph.com/docs/cody/core-concepts/code-graph
- https://githubnext.com/projects/copilot-workspace/
- https://github.com/modelcontextprotocol/servers
- https://github.com/upstash/context7
- https://github.com/zilliztech/claude-context
- https://github.com/cocoindex-io/cocoindex-code
- https://github.com/wrale/mcp-server-tree-sitter
- https://modelcontextprotocol.io/specification/2025-11-25

Tickets / ADR / UI:
- https://github.com/git-bug/git-bug
- https://github.com/wedow/ticket
- https://github.com/eyaltoledano/claude-task-master
- https://github.com/git-dit/git-dit
- https://fossil-scm.org/
- https://github.com/npryce/adr-tools
- https://github.com/thomvaill/log4brains
- https://adr.github.io/madr/
- https://obsidian.md
- https://logseq.com
- https://silverbullet.md/
- https://github.com/markserv/markserv
- https://github.com/Mistralys/markdown-viewer
- https://github.com/isofarro/php5-markdown-wiki
- https://dev.to/felipperegazio/a-complete-live-reload-feature-for-php-projects-in-a-single-class-380m
