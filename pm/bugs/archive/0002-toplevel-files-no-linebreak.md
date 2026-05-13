# 0002 — Top-level files in tree render without line breaks

Symptom: Im Tree-Nav kleben die Top-Level-Files aneinander zu
`CLAUDE.mdREADME.mdValue.specapp.sqlite`. Ordner mit `<details>` rendern
korrekt, nur die direkten `<a>`-Kinder von `<nav>` (Root-Files) laufen
inline.

Ursache: Die CSS-Regel
`nav details > details, nav details > a { margin-left: 1.25rem; display: block; }`
trifft nur `<a>` **innerhalb** von `<details>`. Root-Files sind direkte
Kinder von `<nav>` und bekommen damit kein `display: block`.

See: pm/decisions/0004-ux-policy.md

## Done when
- Top-Level-Files (`CLAUDE.md`, `README.md`, `Value.spec`, `app.sqlite`)
  rendern jeweils auf eigener Zeile.
- Verschachtelte Files behalten ihre `margin-left: 1.25rem`-Einrückung.
- CSS bleibt inline im `<style>` von `app/index.php` (Decision 0004).

## Done
- CSS-Regel in `app/index.php` (Zeile 211) gesplittet:
  - vorher: `nav details > details, nav details > a { margin-left: 1.25rem; display: block; }`
  - nachher:
    `nav > a, nav details > a { display: block; }`
    `nav details > details, nav details > a { margin-left: 1.25rem; }`
- `display: block` greift jetzt auch für direkte `<nav> > a`-Kinder
  (Top-Level-Files), die `margin-left` bleibt auf verschachtelte
  Eintraege beschränkt — Root-Files haben keine Einrückung.
- `<details>` ist von sich aus block-level, daher entfällt das
  redundante `display: block` für `details > details` ohne Regression.
- Verifikation:
  - `php -l app/index.php` → "No syntax errors detected".
  - `curl -s http://127.0.0.1:8080/ | grep "nav > a"` trifft die neue
    Regel.
  - `curl -s http://127.0.0.1:8080/ | grep "margin-left: 1.25"` trifft
    die unveränderte Einrück-Regel für verschachtelte Files.
  - Die vier Root-Files erscheinen weiter als separate `<a>`-Elemente
    im HTML — der Bug war rein visuell, nicht strukturell.
- Visuelle Wirkung im Browser ist manuell zu prüfen (`<a>`-Tags sind
  jetzt block-displayed, brechen also auf neue Zeilen um).
