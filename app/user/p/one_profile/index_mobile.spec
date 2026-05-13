file: index_mobile.php
purpose: Mobile variant of user/p/one_profile/index.php — empty document body wrapped in the shared chrome.
conditions:
  - "redirects to /index.php when app::is_mobile() is false"
  - "calls app::enforce_login() — anonymous visitors are bounced before any markup is emitted"
  - "renders only document::head() and document::footer(); body content is intentionally empty for now"
