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
