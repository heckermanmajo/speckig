// @spec
// File-level spec: tokenizer must ignore @spec markers inside regex literals.
// @end-spec

let pattern = /\/\/ @spec[\s\S]*?\/\/ @end-spec/;

// @spec
// Real spec for the only declared function in this fixture.
// @end-spec
function matches(input: string): boolean
{
    return pattern.test(input);
}
