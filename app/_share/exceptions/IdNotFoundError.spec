file: IdNotFoundError.php
purpose: UserError subclass — a lookup by id (or unique key) returned no row.
conditions:
  - "thrown by _share\\app::get_current_user when the session user_id has no matching User row"
  - "thrown by user\\actions\\LoginAction::execute when no User row exists for the given username"
