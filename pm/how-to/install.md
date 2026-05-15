# install

Von leerem Mac/Linux-Rechner zu laufendem Speckig auf
`http://localhost:8083`. Kein Composer, kein npm, kein Docker — Speckig
hat absichtlich keinen Build.

## Voraussetzungen

- **PHP 8.5+** (CLI). Pruefen: `php -v`.
- **git**. Pruefen: `git --version`.
- Linux oder Mac. Windows wird nicht unterstuetzt (siehe Milestone-016
  Out-of-scope).

## Schritt 1 — Clone

```sh
git clone <repo-url> ~/Desktop/speckig
```

`~/Desktop/speckig` ist der **Default-Pfad**. Ein anderer Pfad
funktioniert genauso (Bashrc-Snippet und Installer akzeptieren das),
aber der Setup/Repair-Tab im UI gibt eine `warn`-Meldung aus, weil die
Konvention nicht gehalten ist (siehe M016/0004). Wer den Default haelt,
hat einen Check weniger gelb.

## Schritt 2 — Bashrc-Snippet

Die `speckig`-Shellfunktion startet den PHP-Server mit korrektem
docroot und Port. Zwei Wege, eines reicht:

### Komfort: Installer-Script

```sh
~/Desktop/speckig/scripts/install.sh
```

Schreibt das Snippet idempotent in `~/.bashrc` (bash) bzw. `~/.zshrc`
(zsh) — siehe `scripts/install.sh` (M016/0003).

### Manuell: Snippet kopieren

Inhalt von `scripts/bashrc-snippet.sh` (M016/0002) ans Ende von
`~/.bashrc` (bash) oder `~/.zshrc` (zsh) anhaengen. Das Snippet
definiert die `speckig`-Funktion und setzt `SPECKIG_ROOT`.

## Schritt 3 — Shell neu laden

```sh
source ~/.bashrc      # bash
# oder
source ~/.zshrc       # zsh
```

Pruefen, dass die Funktion da ist:

```sh
type speckig
```

## Schritt 4 — Server starten

```sh
speckig
```

Startet `php -S 127.0.0.1:8083 -t <repo>/app` und oeffnet den Browser
auf `http://localhost:8083`.

## Verifikation

Setup/Repair-Tab im UI oeffnen. Alle Checks sollten gruen sein. Gelbe
oder rote Eintraege haben einen Repair-Button bzw. einen Hinweis, was
zu tun ist. Siehe M015 (`pm/milestones/archive/015-setup-repair-tab/`)
fuer Hintergrund, M016/0004 fuer Deployment-spezifische Checks.

## Mac-Hinweise

- PHP installieren: `brew install php`. Pruefen, dass `php -v` 8.5+
  meldet — gelegentlich liefert Homebrew aeltere Versionen, dann
  `brew upgrade php` oder explizit `brew install php@8.5`.
- `xdg-open` existiert auf Mac nicht. Das Bashrc-Snippet erkennt das
  Betriebssystem und ruft `open` statt `xdg-open` auf — manuell nichts
  zu tun.

## Linux-Hinweise

- PHP installieren via Paketmanager:
  - Debian/Ubuntu: `sudo apt install php8.5-cli` (ggf. PPA noetig,
    z. B. `ondrej/php`, wenn 8.5 in der Distri-Paketquelle fehlt).
  - Arch: `sudo pacman -S php`.
  - Fedora: `sudo dnf install php-cli`.
- `xdg-open` ist auf den meisten Desktop-Distributionen vorhanden
  (`xdg-utils`). Headless-Server: Browser-Open faellt aus, Server
  laeuft trotzdem.

## Warum `-t app`

`app/` ist der Web-Root, **nicht** das Repo-Root. PHPs eingebauter
Server (`php -S`) routet jede Anfrage durch das per `-t` angegebene
Verzeichnis. Wuerde man `-t .` (Repo-Root) verwenden, waeren
`pm/`, `scripts/` und `.git/` ueber HTTP erreichbar — das ist weder
gewollt noch sicher.

Damit Code unter `app/` trotzdem ans Repo-Root kommt (z. B. um
`pm/how-to/*.md` zu lesen), setzt das Bashrc-Snippet die
Umgebungsvariable `SPECKIG_ROOT` auf das Repo-Wurzelverzeichnis. PHP
liest sie via `getenv("SPECKIG_ROOT")`.

## See also

- `scripts/install.sh` (M016/0003) — Installer.
- `scripts/bashrc-snippet.sh` (M016/0002) — das Shell-Snippet.
- M016/0004 — Setup/Repair-Checks fuer Deployment.
- `pm/how-to/process.md` — Dev-Loop.
