// @spec
// Fixture exercising the dangling-spec warning when a spec block trails the last symbol.
// @end-spec

// @spec
// Returns the constant value 1.
// @end-spec
function ok(): number
{
    return 1;
}

// @spec
// This spec block has no following symbol; the parser should mark it as dangling.
// @end-spec
