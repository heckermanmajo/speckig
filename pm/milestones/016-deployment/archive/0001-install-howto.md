# 0001 — Install-How-to schreiben

## Goal
Ein Leser kann anhand `pm/how-to/install.md` Speckig auf einem leeren
Mac oder Linux-Rechner aufsetzen und starten, ohne den Code zu lesen.

## Notes
- Voraussetzungen explizit nennen: PHP 8.5+, git. Kein Composer, kein
  npm, kein Docker — Speckig hat absichtlich keinen Build.
- Pfad-Konvention `~/Desktop/speckig` ist Default, aber nicht hart
  vorgeschrieben — How-to muss klarstellen, wo es flexibel ist und wo
  nicht.
- Mac- und Linux-Hinweise getrennt halten, wo sie sich unterscheiden
  (Browser-Open, PHP-Installation).
- Verlinkung zu Memory-Eintrag "PHP -S braucht -t app" muss
  inhaltlich rein, auch wenn die Memory selbst nicht im Repo liegt —
  also als Erklaerung im How-to.
- Soll kein Ersatz fuer den Installer-Script aus 0003 werden, aber als
  Referenz funktionieren, falls jemand alles manuell macht.

## Done when
- Datei `pm/how-to/install.md` existiert und enthaelt:
  Voraussetzungen, git-clone-Schritt, Bashrc-Snippet-Hinweis,
  Mac- und Linux-Abschnitte, Verweis auf Setup/Repair-Tab.
- Ein neuer Leser kann die Schritte 1:1 abarbeiten und endet mit einem
  laufenden Speckig auf `localhost:8083`.
- Datei verlinkt auf `0002-bashrc-snippet.md` und
  `0003-installer-script.md` (oder die zugehoerigen Artefakte, sobald
  sie da sind).

## Out of scope
- Bashrc-Snippet selbst (0002).
- Installer-Script (0003).
- Setup/Repair-Checks fuer Deployment (0004).
- Update-Pfad (`speckig update`).

See: CLAUDE.md, pm/how-to/process.md

## Plan
- **Neue Datei**: `pm/how-to/install.md`.
- **Struktur**:
  1. **Voraussetzungen**: PHP 8.5+ (Hinweis: kein Composer / kein npm
     / kein Docker — Speckig hat keinen Build). git. Linux oder Mac.
  2. **Schritt 1: Clone**: `git clone <repo-url> ~/Desktop/speckig`.
     Hinweis dass `~/Desktop/speckig` der **Default**-Pfad ist; ein
     anderer Pfad funktioniert genauso, aber der Setup/Repair-Tab
     warnt dann auf `warn` (siehe M016/0004).
  3. **Schritt 2: Bashrc-Snippet**: kurzer Hinweis dass die
     `speckig`-Shellfunktion via `scripts/install.sh` oder per Hand
     aus `scripts/bashrc-snippet.sh` (M016/0002) eingerichtet wird.
     Zwei Wege beschreiben:
     - Komfort: `~/Desktop/speckig/scripts/install.sh` ausfuehren.
     - Manuell: Inhalt von `scripts/bashrc-snippet.sh` in
       `~/.bashrc` (bash) bzw. `~/.zshrc` (zsh) einfuegen.
  4. **Schritt 3: Shell neu laden**: `source ~/.bashrc` o.ae.
  5. **Schritt 4: Server starten**: `speckig` aufrufen, das oeffnet
     den Browser auf `http://localhost:8083`.
  6. **Verifikation**: Setup/Repair-Tab im UI → alle Checks gruen.
  7. **Mac-Hinweise**: `xdg-open` durch `open` ersetzen (das macht
     das Bashrc-Snippet automatisch, hier nur als Hinweis warum es
     wichtig ist). PHP-Installation via `brew install php` empfohlen.
  8. **Linux-Hinweise**: PHP-Installation via Paketmanager
     (`apt install php8.5-cli` oder Aequivalent). `xdg-open` ist
     ueblicherweise vorhanden.
  9. **Warum -t app**: kurzer Absatz, warum `php -S` mit `-t app`
     laufen muss — `app/` ist der Web-Root, nicht das Repo-Root.
     SPECKIG_ROOT zeigt aufs Repo-Root, das wird via Env-Variable
     uebergeben.
- **Verlinkung**: zu `scripts/install.sh` (0003) und
  `scripts/bashrc-snippet.sh` (0002).
- **Files touched**: `pm/how-to/install.md` (neu).

## Verifikation
- Datei existiert.
- `wc -l pm/how-to/install.md` > 30 (genug Substanz, aber keine
  Romane).
- Markdown rendert ohne Syntax-Fehler via Parsedown (auf info.php
  testen, falls der Pfad in der UI eingebunden ist; sonst nur
  visueller Check).
- Inhaltlicher Check: ein Reviewer kann die Schritte 1:1 abarbeiten.
- `git status` clean.

## Out of scope (Plan)
- Tatsaechlicher Installer-Script (0003).
- Bashrc-Snippet selbst (0002).
- Update-Pfad.

## Done
- `pm/how-to/install.md` neu angelegt, 114 Zeilen. Struktur exakt nach
  Plan: Voraussetzungen, vier nummerierte Schritte, Verifikation,
  Mac- und Linux-Hinweise, Abschnitt "Warum -t app", See-also.
- Default-Pfad `~/Desktop/speckig` als Default beschrieben, mit
  Hinweis dass abweichender Pfad funktioniert aber Setup/Repair-Tab
  einen `warn` ausgibt (Forward-Ref auf M016/0004).
- Bashrc-Snippet-Schritt beschreibt beide Wege: Komfort via
  `scripts/install.sh` (M016/0003) und manuelles Einfuegen aus
  `scripts/bashrc-snippet.sh` (M016/0002). Beide Files sind noch nicht
  da, werden im jeweiligen Ticket angelegt — Verlinkung steht.
- Mac-/Linux-Hinweise sauber getrennt: brew install php vs. Paket-
  manager-Befehle. `xdg-open`/`open`-Unterschied erwaehnt mit dem
  Hinweis dass das Snippet das automatisch macht.
- "Warum -t app"-Absatz erklaert Web-Root vs. Repo-Root und die Rolle
  von `SPECKIG_ROOT` als Bruecke zurueck zum Repo-Wurzelverzeichnis
  (Memory-Eintrag inhaltlich uebernommen).

Files touched:
- `pm/how-to/install.md` (neu).
- `pm/milestones/016-deployment/milestone.md` (Haekchen + Pfad).
- Ticket-Move open/ → archive/.

Verifikation:
- `ls -la pm/how-to/install.md` → existiert, 3447 Bytes.
- `wc -l pm/how-to/install.md` → 114 (> 30 gefordert).
- Markdown-Syntax visuell geprueft: Headings konsistent (H1, H2),
  Code-Bloecke mit Sprache-Hint, Listen sauber.
- `git status` clean nach Close-Commit.

Plan-Abweichungen: keine.
