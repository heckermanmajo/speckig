file: UserInputError.php
purpose: UserError subclass — user-supplied input failed validation (empty, malformed, out of range, or duplicate).
conditions:
  - "thrown by user\\actions\\LoginAction::execute when username or password is empty"
  - "thrown by user\\actions\\CreateUserAction::execute on empty/short/long username, short password, invalid email, or duplicate username"
