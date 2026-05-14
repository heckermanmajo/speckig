<?php

namespace _share\spec_parser\tests;

class Bare
{
    public string $name = "";
}

function bare_helper(int $count): int
{
    return $count;
}
