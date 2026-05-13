<?php

namespace _share;

use Throwable;

class cards
{
    static function get_error_card(Throwable $t): string
    {
        # ...
    }
    static function get_info_card(string $header, string $body): string
    {
        # ...
    }
}