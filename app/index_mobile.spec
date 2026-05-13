file: index_mobile.php
purpose: Mobile variant of the original login page; not part of the speckig UI but kept as a legacy skeleton.
conditions:
  - "redirects to /index.php when the request is not from a mobile user-agent"
  - "redirects to /dash/ when a user is already logged in"
  - "renders only the shared document::head() and document::footer() chrome — no body content"
