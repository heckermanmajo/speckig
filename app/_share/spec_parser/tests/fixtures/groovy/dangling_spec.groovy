// @spec
// File-level spec covering the dangling-spec warning case.
// @end-spec

// @spec
// Real spec for the only declared symbol in this fixture.
// @end-spec
class Foo
{
}

// @spec
// This spec block has no following symbol; the parser should mark it dangling.
// @end-spec
