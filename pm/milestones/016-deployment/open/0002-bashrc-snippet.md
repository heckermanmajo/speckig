# 0002 — Bashrc/Zshrc-Snippet

## Goal
Es gibt ein klar abgegrenztes Shell-Snippet, das in `~/.bashrc` bzw.
`~/.zshrc` eingetragen wird und eine `speckig`-Funktion bereitstellt,
die den Server mit korrektem docroot und Port startet.

## Notes
- POSIX-sh-kompatibel halten, damit bash und zsh beide laufen — keine
  bash-only-Konstrukte wie `[[ ]]`.
- Snippet muss durch einen Marker-Kommentar eingerahmt sein
  (`# >>> speckig` / `# <<< speckig`), damit der Installer (0003)
  idempotent re-installieren und sauber wieder entfernen kann.
- Funktion `speckig` muss den Default-Port 8083 nutzen und `-t app`
  setzen (siehe Memory). Optionales erstes Argument als alternativer
  Repo-Pfad, analog zu `scripts/run.sh`.
- Snippet darf den Server nicht beim Shell-Start anwerfen, nur eine
  Funktion definieren.
- SPECKIG_ROOT korrekt exportieren, sonst stimmt der Tree-Pfad nicht.

## Done when
- Snippet existiert als versionierte Datei im Repo, lesbar fuer den
  Installer (z.B. `scripts/bashrc-snippet.sh`).
- Eintrag in eine leere `.bashrc` per `eval "$(cat ...)"` macht
  `speckig` als Shell-Funktion verfuegbar.
- `speckig` ohne Argument startet den Server gegen das Repo, in dem
  das Snippet ausgepackt wurde.
- `speckig /pfad/zu/anderem/repo` startet gegen jenes Repo.
- Marker-Kommentare oben und unten klar erkennbar.

## Out of scope
- Installer-Script selbst (0003).
- Self-Update (`speckig update`).
- Windows-Support.

See: CLAUDE.md
