<?php

namespace _share\spec_parser\tests;

$single = '// @spec this is inside a single-quoted string and must not be parsed // @end-spec';

$double = "// @spec this is inside a double-quoted string and must not be parsed // @end-spec";

$heredoc = <<<TXT
// @spec
// fake spec inside heredoc, must be ignored
// @end-spec
TXT;

$nowdoc = <<<'TXT'
// @spec
// fake spec inside nowdoc, must be ignored
// @end-spec
TXT;
