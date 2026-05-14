<?php
// @spec
// Admin-only action that validates inputs and inserts a new User row, exposing the created id.
// @end-spec

namespace user\actions;

use _share\Action;
use _share\db;
use _share\app;
use _share\exceptions\BadStateError;
use _share\exceptions\UserInputError;
use user\data\User;


// @spec
// Action: create a new User row after admin gate and input validation; result carries the new id.
// @end-spec
final class CreateUserAction extends Action
{

    // @spec id of the user this action created; 0 before execute() ran
    // @end-spec
    public int $created_user_id = 0;

    function __construct() {}

    // @spec
    // Enforce admin, validate username/password/email, ensure username is unique, then save the new user.
    // calls app::enforce_plattform_admin, which throws NeedsLoginError or NotAllowedError if the caller is not a platform admin
    // throws UserInputError if username is empty after trim
    // throws UserInputError if password is empty
    // throws UserInputError if username length is not in 2..64
    // throws UserInputError if password is shorter than 6 characters
    // throws UserInputError if a non-empty email fails FILTER_VALIDATE_EMAIL
    // throws UserInputError if a User row already exists with the same username
    // throws BadStateError if db::save returns without assigning an id
    // password is stored as a password_hash, never plaintext
    // is_admin is set to 1 only when the input string equals "1"
    // @end-spec
    static function execute(
        string $username,
        string $password,
        string $email,
        string $is_admin = "0"
    ): self {

        # --- guard: only platform admins may create users ---

        app::enforce_plattform_admin();


        # --- validate inputs ---

        $username = trim($username);
        $email    = trim($email);

        if ($username === "")
        {
            throw new UserInputError("missing username: is empty");
        }

        if ($password === "")
        {
            throw new UserInputError("missing password: is empty");
        }

        $username_length_is_sane =
            mb_strlen($username) >= 2
            && mb_strlen($username) <= 64;

        if ( ! $username_length_is_sane )
        {
            throw new UserInputError("username must be 2..64 characters");
        }

        $password_is_long_enough = strlen($password) >= 6;

        if ( ! $password_is_long_enough )
        {
            throw new UserInputError("password must be at least 6 characters");
        }

        $email_provided_but_invalid =
            $email !== ""
            && filter_var($email, FILTER_VALIDATE_EMAIL) === false;

        if ($email_provided_but_invalid)
        {
            throw new UserInputError("email is not a valid address");
        }


        # --- uniqueness: no duplicate username ---

        $existing_user_with_same_username = db::select_one(
            User::class,
            "SELECT * FROM User WHERE username = :username",
            ["username" => $username]
        );

        if ($existing_user_with_same_username)
        {
            throw new UserInputError("username already taken");
        }


        # --- build & save new user ---

        $new_user = new User();
        $new_user->username       = $username;
        $new_user->password       = password_hash($password, PASSWORD_DEFAULT);
        $new_user->email          = $email;
        $new_user->email_verified = 0;
        $new_user->language       = "";
        $new_user->is_admin       = $is_admin === "1" ? 1 : 0;

        db::save($new_user);

        if ($new_user->id <= 0)
        {
            app::error_log("CreateUserAction: db::save did not assign an id for username={$username}");
            throw new BadStateError("user save did not return an id");
        }

        app::log("CreateUserAction created user id={$new_user->id} username={$username}", __FUNCTION__);


        # --- response ---

        $response = new self();
        $response->created_user_id = $new_user->id;
        return $response;
    }
}
