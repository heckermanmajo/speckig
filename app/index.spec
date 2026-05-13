file: index.php
purpose: Speckig layout entry-point — header, left tree of the repo, right article placeholder; AJAX hands off content rendering to file.php.
functions:
  - name: count_visible_children
    does: Count non-hidden entries in a directory for the tree-summary badge.
    conditions:
      - "returns 0 when scandir fails"
      - "skips entries whose name starts with a dot"
  - name: render_tree
    does: Recursively build nested details/summary/anchor markup for a directory listing.
    conditions:
      - "directories rendered before files, each group alphabetically sorted"
      - "hidden entries (dot-prefix) are skipped"
      - "all names HTML-escaped via _share\\app::escape — no raw filename ever reaches the DOM"
      - "anchors point at /?path=<rel> so a hard reload still works"
conditions:
  - "resolves $speckig_root_abs from SPECKIG_ROOT env var, falling back to realpath(__DIR__/..)"
  - "three-layer ?path validation: string-shape (no .., no leading /), realpath inside root, is_file"
  - "logs rejected paths via app::error_log but renders the page anyway"
  - "header path label only shows the validated $raw_path — never the unvalidated input (reflected-XSS guard)"
  - "right article is a static placeholder; content_loader.js fills it on DOMContentLoaded"
