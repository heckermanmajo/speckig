file: index.php
purpose: User landing page — login-gated desktop template that renders a placeholder for profile and one-profile links.
conditions:
  - "redirects to /index_mobile.php when app::is_mobile() is true"
  - "calls app::enforce_login() — anonymous visitors are bounced before any markup is emitted"
  - "wraps body in document::head() / document::footer() for shared chrome"
