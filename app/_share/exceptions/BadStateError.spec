file: BadStateError.php
purpose: UserError subclass — an operation reached an unexpected state that should not normally occur.
conditions:
  - "thrown by user\\actions\\CreateUserAction::execute when db::save returns without assigning an id"
