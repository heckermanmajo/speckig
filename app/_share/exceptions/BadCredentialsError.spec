file: BadCredentialsError.php
purpose: UserError subclass — login failed because the password did not match the stored hash.
conditions:
  - "thrown by user\\actions\\LoginAction::execute when password_verify fails for the given user"
