// @spec
// File-level spec: tokenizer must ignore @spec markers inside template literals.
// @end-spec

const fake_template = `// @spec
// fake spec inside a template literal
// @end-spec`;

// @spec
// Real spec for the only declared function in this fixture.
// @end-spec
function detect(input: string): boolean
{
    return input.length > 0;
}
