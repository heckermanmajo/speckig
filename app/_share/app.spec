file: app.php
purpose: Application-wide helpers — escaping, redirects, auth gates, session user access, debug flags and log stubs.
functions:
  - name: escape
    does: HTML-escape a string for element content and attribute values, UTF-8.
  - name: lang
    does: Pick a language string from a map — currently always returns the "de" entry.
  - name: redirect
    does: Send a Location header for the given path (defaults to current REQUEST_URI) and exit.
    conditions:
      - "never returns — always exits after sending header"
      - "discards any buffered output via ob_get_clean before redirecting"
  - name: enforce_login
    does: Redirect to / when no user is logged in.
    conditions:
      - "calls app::redirect, which never returns"
  - name: enforce_plattform_admin
    does: Hard gate for admin-only endpoints — require a logged-in user that exists in DB and has is_admin == 1.
    conditions:
      - "throws NeedsLoginError if no session user"
      - "throws NotAllowedError if session user has no DB row or is_admin != 1"
  - name: get_current_user_id
    does: Return the session user id.
    conditions:
      - "throws NeedsLoginError if no user is logged in"
  - name: get_current_user
    does: Load and return the User row for the current session user.
    conditions:
      - "throws NeedsLoginError if no session user"
      - "throws IdNotFoundError if session user id has no DB row"
  - name: log
    does: No-op placeholder for per-function logging.
  - name: error_log
    does: No-op placeholder for error logging.
  - name: is_mobile
    does: Cheap User-Agent sniff for desktop/mobile routing — not for security decisions.
  - name: is_debug
    does: Return whether debug envelope fields should be exposed — currently hardcoded to true.
  - name: somebody_is_logged_in
    does: Return whether the session holds a positive integer user_id.
  - name: perform_login
    does: Store the given user's id in the session.
  - name: get_logs
    does: Return the collected logs — currently always an empty array.
  - name: function_log
    does: No-op placeholder for structured per-function log entries.
