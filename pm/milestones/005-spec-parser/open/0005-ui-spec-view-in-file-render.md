# 0005 — UI: Parser-Output ueber Datei-Inhalt anzeigen

See: pm/ideas/spec-as-comment.md
Blocked by: 0002, 0003

## Done when

- `app/file.php` ruft fuer `.php`- und `.js`-Dateien den Parser aus M005/0001 auf, **bevor** der Code-Inhalt ausgeliefert wird.
- Antwort an den Browser enthaelt zusaetzlich zum heutigen `html`-Feld ein `spec`-Feld (`null` falls Datei keine Spec hat oder Sprache nicht unterstuetzt wird).
- `spec`-Feld ist das Parser-JSON aus 0001/0002/0003.
- Im Frontend wird der Spec-Block ueber dem Code als eigene Sektion gerendert:
  - Datei-Spec ganz oben.
  - Pro Symbol: Signatur (aus dem Parser-Output) + Spec-Zeilen darunter.
  - Cursor `pointer` und visuelles Styling konsistent mit bestehender Tree-/Card-Optik.
  - Spec-View ist kollabierbar (Default: aufgeklappt). Status muss nicht persistiert werden.
- Bestehende `.spec`-Datei-Anzeige (aktuelle Plaintext-Render von `.spec` via `file.php`) bleibt unangetastet — Dateien, die noch nicht migriert sind, werden weiter aus der `.spec`-Datei daneben gelesen. Das Spec-View aus dem Parser greift nur, wenn die `.php`/`.js`-Datei tatsaechlich `// @spec`-Bloecke enthaelt.
- Smoketest: `php -S 127.0.0.1:8099 -t app` starten (DocumentRoot ist `app/`, nicht Repo-Root — nicht Port 8083 nutzen, der gehoert dem User), `curl "http://127.0.0.1:8099/file.php?path=app/user/data/User.php"` aufrufen, Spec-View zeigt Datei-Spec + sechs Felder mit Specs. `?path=app/user/actions/CreateUserAction.php` zeigt Klassen-Spec + Methode `execute` mit Intent + Conditions. Server am Ende beenden.
- `php -l` sauber, JS ohne Console-Errors im Browser.

## Aus der Idea wichtig

- Spec-View ersetzt die `.spec`-Datei NICHT generell — sie ist die Anzeige fuer migrierte Dateien. Migration ist ein eigener Milestone (M006).
- Spec-View darf KEINE Typen aus dem Spec-Text rendern, die schon in der Signatur stehen — sonst doppelt. Renderer nimmt Typen aus Parser-Output.
- Performance: Re-Parsen pro Request ist OK fuer den Pilot. Caching ist Out-of-scope (eigene offene Frage in der Idea).

## Done

(append after work)
