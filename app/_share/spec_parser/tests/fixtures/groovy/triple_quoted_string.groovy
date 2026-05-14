// @spec
// File-level spec: spec markers inside triple-quoted strings must NOT be parsed.
// @end-spec

def triple_double = """first
// @spec
// fake spec inside a triple double-quoted string
// @end-spec
last"""

def triple_single = '''first
// @spec
// fake spec inside a triple single-quoted string
// @end-spec
last'''

// @spec
// Real spec for the only declared function in this fixture.
// @end-spec
def render(String body)
{
    return body
}
