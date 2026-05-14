## @spec
## Top-level helper used to validate that proc-level specs with multiple condition lines parse correctly.
## @end-spec

## @spec
## Validate a username/email pair, returning the normalised handle.
## throws ValueError if username is empty after strip
## throws ValueError if username length is not in 2..64
## throws ValueError if email is non-empty and does not contain "@"
## returned string is the trimmed username, never empty
## @end-spec
proc validate_user_input(username: string, email: string, max_length: int = 64): string =
  result = username
