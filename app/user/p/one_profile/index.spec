file: index.php
purpose: One-profile page — login-gated desktop template that renders a placeholder for viewing another user's profile.
conditions:
  - "redirects to /index_mobile.php when app::is_mobile() is true"
  - "calls app::enforce_login() — anonymous visitors are bounced before any markup is emitted"
  - "wraps body in document::head() / document::footer() for shared chrome"
  - "TODO in source: permission check for viewing another user is not yet implemented"
