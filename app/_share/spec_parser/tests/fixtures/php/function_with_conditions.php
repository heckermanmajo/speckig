<?php
// @spec
// Top-level helper used to validate that function-level specs with multiple condition lines parse correctly.
// @end-spec

namespace _share\spec_parser\tests;

// @spec
// Validate a username/email pair, returning the normalised handle.
// throws InvalidArgumentException if username is empty after trim
// throws InvalidArgumentException if username length is not in 2..64
// throws InvalidArgumentException if email is non-empty and fails FILTER_VALIDATE_EMAIL
// returned string is the trimmed username, never empty
// @end-spec
function validate_user_input(string $username, string $email, int $max_length = 64): string
{
    $username = trim($username);
    return $username;
}
