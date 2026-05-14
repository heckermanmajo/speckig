// @spec
// File-level spec: spec markers inside GStrings must NOT be parsed as specs.
// @end-spec

// @spec
// Holder script for the fake-spec strings used to check the tokenizer.
// the GString below has // @spec inside ${...} interpolation
// @end-spec
def fake_inline = "value=${1 + 1} // @spec\n// fake spec inside a gstring\n// @end-spec"

def fake_var = "name=$user_name // @spec inside a $var GString // @end-spec"

// @spec
// Real spec for the only declared function in this fixture.
// @end-spec
def detect(String input)
{
    return input.length() > 0
}
