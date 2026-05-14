<?php
// @spec
// Authenticated platform user; admin flag controls privileged endpoints.
// @end-spec

namespace user\data;

use _share\DataClass;

// @spec
// User row: login handle, password hash, email + verification flag, language, admin flag.
// @end-spec
class User extends DataClass
{
    // @spec login handle, unique, 2..64 chars
    // @end-spec
    public string   $username = "";

    // @spec password_hash(PASSWORD_DEFAULT) bcrypt output, never plaintext
    // @end-spec
    public string   $password = "";

    // @spec optional contact address; validated via FILTER_VALIDATE_EMAIL when non-empty
    // @end-spec
    public string   $email = "";

    // @spec 1 if email ownership has been confirmed, 0 otherwise
    // @end-spec
    public int      $email_verified = 0;

    // @spec BCP-47-ish locale tag, empty string means "use default"
    // @end-spec
    public string   $language = "";

    // @spec 1 = platform admin (privileged endpoints), 0 = normal user
    // @end-spec
    public int      $is_admin = 0;
}