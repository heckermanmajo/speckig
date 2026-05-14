--[[
-- @spec
-- fake spec — this lives inside a Lua block comment and must be ignored
-- @end-spec
]]

--[=[
-- @spec
-- also fake — this is a level-1 block comment with [[ ]] inside the body
-- @end-spec
]=]

function real_function()
    return 42
end
