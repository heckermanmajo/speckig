<?php

namespace user\actions;

use _share\Action;
use _share\db;
use _share\app;
use _share\exceptions\BadCredentialsError;
use _share\exceptions\IdNotFoundError;
use _share\exceptions\UserInputError;
use user\data\User;
use Exception;


final class LoginAction extends Action
{

    function __construct(){}

    static function execute(
        string $username,
        string $password
    ): self {
        
        if ($username == "") throw new UserInputError("mssing username:is empty");
        if ($password == "") throw new UserInputError("mssing password:is empty");
        
        $user = db::select_one(
            User::class, 
            "SELECT * FROM User WHERE username = :username", 
            ["username" => $username]
        );

        if ( !$user )
        {
            throw new IdNotFoundError(
                "User for username not found"
            );
        }

        $password_correct = password_verify( $password, $user->password ); 
        if ( !$password_correct )
        {
            throw new BadCredentialsError(
                "Bad password or username"
            );
        }    

        app::perform_login($user);

        return new self();
    }   
}