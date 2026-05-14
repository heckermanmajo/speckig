// @spec
// File-level spec: tokenizer must ignore @spec markers inside regex, string and template literals.
// @end-spec

let pattern = /\/\/ @spec[\s\S]*?\/\/ @end-spec/;

let single_string = '// @spec fake inside single-quoted string // @end-spec';

let double_string = "// @spec fake inside double-quoted string // @end-spec";

let template = `// @spec
// fake spec inside template literal
// @end-spec`;

// @spec
// Real spec for the only declared function in this fixture.
// @end-spec
function detect_marker(input)
{
    return pattern.test(input);
}
