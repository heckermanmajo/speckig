file: init.php
purpose: Bootstrap for every web entry-point — autoloader, output buffer, session, csrf, JSON-body merge, admin seed.
conditions:
  - "registers spl_autoload that maps namespace to a path under DOCUMENT_ROOT"
  - "starts output buffering and forces display_errors plus E_ALL"
  - "sets default timezone to Europe/Berlin"
  - "starts session and generates a csrf_token if absent"
  - "merges a JSON request body into $_REQUEST when the body is a JSON object"
  - "seeds a default admin user (username=admin, password=admin, is_admin=1) when no row with id=1 exists"
