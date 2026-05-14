-- @spec
-- Fixture verifying that -- @spec markers buried inside long-string literals are not parsed as spec blocks.
-- @end-spec

local long = [[
-- @spec
-- not a real spec — this lives inside a long-string literal
-- @end-spec
]]

local lvl = [==[
-- @spec
-- also not a real spec — this is a level-2 long-string [[ ]] inside
-- @end-spec
]==]
