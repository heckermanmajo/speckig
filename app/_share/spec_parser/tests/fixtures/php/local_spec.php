<?php

namespace _share\spec_parser\tests;

class Calculator
{
    public function compute(int $a, int $b): int
    {
        // @spec
        // local guard: refuse negative inputs by clamping them to zero
        // @end-spec
        if ($a < 0) { $a = 0; }
        if ($b < 0) { $b = 0; }

        return $a + $b;
    }
}
