# 016 — Deployment: Clone, Bashrc, PHP-Server-Start

Goal: Speckig laesst sich auf einem neuen Rechner (Mac oder Linux)
sauber installieren:
1. `git clone <repo> ~/Desktop/speckig`
2. Eintrag in `~/.bashrc` (bzw. `~/.zshrc`) der einen `speckig`-Alias
   oder eine Funktion bereitstellt, die den PHP-Server mit korrektem
   docroot (`-t app`, siehe Memory) und Port startet.
3. Der gestartete Server bedient die UI und findet das Repo via
   SPECKIG_ROOT bzw. Default-Resolver.

Liefer-Artefakte: ein dokumentierter Install-Pfad (README oder
how-to-Datei), ein wiederholbares Shell-Snippet, und Setup/Repair-
Checks (M015) die zeigen, dass die Bashrc-Integration funktioniert.

Status: planned

## Tickets
- [ ] open/0001-install-howto.md — `pm/how-to/install.md` schreiben:
      Voraussetzungen (PHP 8.5), git clone-Befehl,
      Desktop-Pfad-Konvention (~/Desktop/speckig), Mac- und Linux-
      Hinweise.
- [ ] open/0002-bashrc-snippet.md — Shell-Snippet liefern (POSIX-sh
      kompatibel) das in `~/.bashrc` / `~/.zshrc` eingetragen wird;
      definiert Funktion `speckig` die `php -S 127.0.0.1:8083 -t
      <repo>/app` startet. Idempotent (Marker-Kommentar).
- [ ] open/0003-installer-script.md — `bin/install.sh` (oder
      `scripts/install.sh`): prueft Pfad, fragt nach Repo-URL,
      schreibt Bashrc-Snippet idempotent rein, druckt naechste
      Schritte.
- [ ] open/0004-setup-checks-deployment.md — Setup/Repair-Tab (M015)
      erweitert um Checks: ist die `speckig`-Funktion in der aktiven
      Shell verfuegbar? Liegt das Repo unter ~/Desktop/speckig? Wird
      Port 8083 schon belegt? Repair-Actions wo sinnvoll.

## Out of scope
- Systemd / launchd-Service (Server laeuft per Hand, kein Daemon).
- Windows-Support.
- Docker / Container.
- Auto-Update via `speckig update`.
- Mehrere Speckig-Instanzen parallel.

See: pm/how-to/install.md (anzulegen in 0001), Memory: PHP -S braucht
-t app; Port 8083 ist Users eigener Server.
