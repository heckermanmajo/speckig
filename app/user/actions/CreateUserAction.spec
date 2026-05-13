file: CreateUserAction.php
purpose: Admin-only action that validates inputs and inserts a new User row, exposing the created id.
functions:
  - name: execute
    does: Enforce admin, validate username/password/email, ensure username is unique, then save the new user.
    conditions:
      - "calls app::enforce_plattform_admin, which throws NeedsLoginError or NotAllowedError if the caller is not a platform admin"
      - "throws UserInputError if username is empty after trim"
      - "throws UserInputError if password is empty"
      - "throws UserInputError if username length is not in 2..64"
      - "throws UserInputError if password is shorter than 6 characters"
      - "throws UserInputError if a non-empty email fails FILTER_VALIDATE_EMAIL"
      - "throws UserInputError if a User row already exists with the same username"
      - "throws BadStateError if db::save returns without assigning an id"
      - "password is stored as a password_hash, never plaintext"
      - "is_admin is set to 1 only when the input string equals \"1\""
