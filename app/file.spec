file: file.php
purpose: JSON endpoint for the AJAX content loader — renders one file from SPECKIG_ROOT as HTML and wraps it in a JSON envelope.
conditions:
  - "always responds with Content-Type: application/json"
  - "resolves $speckig_root_abs from SPECKIG_ROOT env var, falling back to realpath(__DIR__/..)"
  - "three-layer ?path validation: string-shape (no .., no leading /), realpath inside root, is_file"
  - "responds 400 with {ok:false, message:'Ungueltiger Pfad.'} on any validation failure"
  - ".md files are rendered through Parsedown"
  - "non-.md files are wrapped in <pre>...</pre> with content HTML-escaped via app::escape"
  - "success envelope is {ok:true, path, html}"
