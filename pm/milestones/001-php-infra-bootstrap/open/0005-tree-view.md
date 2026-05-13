# 0005 — Tree-view von pm/

See: pm/decisions/0002-php-infra.md
Blocked by: 0004

## Done when
- `app/index.php` zeigt einen rekursiven Tree von `pm/` als HTML5.
- Verzeichnisse sind aufklappbar via `<details><summary>`, kein JS.
- Dateien sind Links auf `/view.php?path=…`.
- Pfad-Traversal (`..`, absolute Pfade) wird hart abgewiesen.
- Kein eigenes CSS-File; höchstens `<style>` inline mit ein paar Zeilen.
