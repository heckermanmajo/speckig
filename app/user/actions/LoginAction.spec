file: LoginAction.php
purpose: Action that authenticates a username/password pair and starts a session for the matched user.
functions:
  - name: execute
    does: Look up the user by username, verify the password, and perform login on success.
    conditions:
      - "throws UserInputError if username is empty"
      - "throws UserInputError if password is empty"
      - "throws IdNotFoundError if no User row matches the given username"
      - "throws BadCredentialsError if password_verify fails for the matched user"
      - "calls app::perform_login on success, which sets the session user_id"
