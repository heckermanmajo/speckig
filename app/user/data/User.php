<?php

namespace user\data;

use _share\DataClass;

class User extends DataClass
{
    public string   $username = "";
    public string   $password = "";
    public string   $email = "";
    public int      $email_verified = 0;
    public string   $language = "";
    public int      $is_admin = 0;
}