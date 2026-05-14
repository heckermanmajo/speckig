## @spec
## Fixture verifying that ## @spec markers buried inside string literals are not parsed as spec blocks.
## @end-spec

let triple_payload = """
## @spec
## not a real spec — this lives inside a triple-string literal
## @end-spec
"""

let raw_payload = r"## @spec inside a raw-string is just text, not a marker"

let plain_payload = "## @spec also harmless in a plain double-quoted string"
