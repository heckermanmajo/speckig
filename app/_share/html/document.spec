file: document.php
purpose: Page chrome — emits the doctype/head/nav at the top and the global dialog slot plus closing tags at the bottom.
functions:
  - name: head
    does: Render <!doctype>, <head> with CSRF-meta and shared assets, open <body>, and emit the global login-aware nav.
    conditions:
      - "reads csrf token from $_SESSION['csrf_token'] (set in _share/init.php) and exposes it as <meta name='csrf-token'>"
      - "nav shows Dashboard + username when app::somebody_is_logged_in(), Login link otherwise — no throws on missing session"
      - "nav shows an extra Admin link when the logged-in User has is_admin === 1"
      - "username is run through app::escape before output"
  - name: footer
    does: Emit the single global <dialog id='global-dialog'> element and close </body></html>.
    conditions:
      - "dialog lives once per page regardless of how many call sites open dialogs"
      - "content is filled at runtime by /sharejs/snippet.js (open_dialog_snippet)"
