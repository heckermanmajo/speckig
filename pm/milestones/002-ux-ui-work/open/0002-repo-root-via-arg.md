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
