file: NotAllowedError.php
purpose: UserError subclass — the caller is authenticated but lacks the required permission.
conditions:
  - "thrown by _share\\app::enforce_plattform_admin when the session user_id has no DB row"
  - "thrown by _share\\app::enforce_plattform_admin when the logged-in user is not a platform admin"
