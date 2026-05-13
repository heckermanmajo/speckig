# 0002 — Repo-Root via Server-Argument

Aktuell ist `pm/` als Pfad hartcodiert relativ zur `app/`-Position. Speckig soll später auf beliebige Repos zeigen können.

See: pm/decisions/0004-ux-policy.md

## Done when
- `scripts/run.sh` akzeptiert ein optionales zweites Argument: den Repo-Root, der gebrowsed werden soll. `./scripts/run.sh` → eigenes Repo (wie bisher). `./scripts/run.sh /pfad/zu/anderem/repo` → dieses Repo.
- `run.sh` exportiert `SPECKIG_ROOT` als Env-Var an den PHP-Prozess.
- `app/index.php` liest `getenv("SPECKIG_ROOT")`; falls leer, Fallback auf parent von `__DIR__`.
- Der Tree, der Pfad-Traversal-Check und der Content-Loader nutzen alle den gleichen `$speckig_root_abs`.
- Header zeigt zusätzlich den aktiven Root-Namen (Basename des Pfads).
- Smoketest: `./scripts/run.sh /tmp/anderes-repo` (vorher mit `mkdir /tmp/anderes-repo && touch /tmp/anderes-repo/test.md`), Tree zeigt `test.md` statt `pm/`.

## Done
- `scripts/run.sh`: optionales erstes Argument als zu browsender Repo-Root. Wenn gegeben: `[ -d "$arg_root" ]`-Check (sonst `exit 1` mit klarer Fehlermeldung an stderr), dann via `cd … && pwd` absolut machen. Sonst Fallback auf parent des Speckig-`app/`. In beiden Faellen `export SPECKIG_ROOT="$speckig_root"` vor dem `exec php -S`. `cd "$speckig_app_dir"` (Speckig-Code) bleibt unabhaengig vom gebrowsten Root — DocRoot ist immer das Speckig-eigene `app/`.
- `app/index.php`: `$pm_root_abs` entfernt, ersetzt durch `$speckig_root_abs = getenv("SPECKIG_ROOT") ? realpath(...) : realpath(__DIR__ . "/..")`. Tree-Render, Traversal-Check und Content-Loader nutzen alle die gleiche Variable. `?path=`-Werte werden relativ zu `$speckig_root_abs` aufgeloest — frueheres `pm/decisions/0002-php-infra.md` funktioniert weiter, weil `pm/` unter dem Default-Root liegt.
- Verhaltensaenderung: Tree zeigt jetzt ALLE Top-Level-Files/-Dirs des Roots (nicht mehr nur `pm/`). Beim Default-Aufruf erscheinen also auch `app/`, `scripts/`, `README.md`, `Value.spec` etc. neben `pm/`. Bei fremdem Root nur dessen Inhalt. Konsistent zur "browse beliebige Repos"-Anforderung. Versteckte Eintraege (`.git`, `.gitignore`, `.gitkeep`) sind durch den existierenden `$entry_name[0] === "."`-Filter weiter raus.
- `?path=`-String-Check vereinfacht: der `str_starts_with($raw_path, "pm/")`-Zwang ist weg (weil `pm/` jetzt kein Special-Case mehr ist). `..`-Verbot, kein fuehrender `/`, und der Realpath-Containment-Check gegen `$speckig_root_abs . DIRECTORY_SEPARATOR` bleiben — Traversal weiter dicht.
- Header: nach `<strong>speckig</strong>` jetzt ` · <code>basename($speckig_root_abs)</code>` (also `speckig` beim Default, `speckig-test-repo` bei fremdem Root), gefolgt vom validierten `?path` falls vorhanden.
- Verifikation: `php -l app/index.php` und `bash -n scripts/run.sh` → keine Fehler.
- Test 1 (Default, `SPECKIG_ROOT=""`): `curl http://127.0.0.1:8080/` zeigt im Tree u.a. `<summary>app/`, `<summary>pm/`, `<summary>scripts/` — die Speckig-eigenen Top-Level-Dirs. Header `<code>speckig</code>`.
- Test 2 (`SPECKIG_ROOT=/tmp/speckig-test-repo`): Tree zeigt `subfolder/` (mit `inner.md`) und `test.md`, keine Spur von `app/`, `pm/`, `scripts/`. Header `<code>speckig-test-repo</code>`.
- Test 3 (`./scripts/run.sh /tmp/nonexistent`): exit 1 mit stderr-Zeile `run.sh: '/tmp/nonexistent' ist kein Verzeichnis`.
- Regression-Test gegen 0006-Traversal: `?path=pm/decisions/0002-php-infra.md` → 200 mit `<h1>0002 — PHP infra</h1>`; `?path=../etc/passwd`, `?path=pm/../README.md`, `?path=/etc/passwd`, `?path=pm/decisions/nonexistent.md` → alle `Ungültiger Pfad`. Neue Surface: `?path=README.md` rendert jetzt korrekt das Top-Level-README (war vorher abgewiesen, weil ausserhalb `pm/`) — gewollt durch die Root-Verallgemeinerung.
- Streu-File-Check: nur kanonische `app.sqlite`. Eigene 8080-Test-Server gestoppt, fremde 8083-Session unangetastet. `/tmp/speckig-test-repo` nach den Tests entfernt.
