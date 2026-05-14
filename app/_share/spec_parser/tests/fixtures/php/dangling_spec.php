<?php

namespace _share\spec_parser\tests;

class Foo
{
}

// @spec
// This spec block has no following symbol; the parser should mark it as dangling.
// @end-spec
