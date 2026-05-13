file: NeedsLoginError.php
purpose: UserError subclass — the endpoint requires an authenticated session and none is present.
conditions:
  - "thrown by _share\\app::enforce_plattform_admin when nobody is logged in"
  - "thrown by _share\\app::get_current_user_id when nobody is logged in"
