-- @spec
-- Fixture exercising local-spec capture inside a function body.
-- @end-spec

-- @spec
-- Compute the sum of two integers, clamping negatives to zero before adding.
-- @end-spec
function compute(a, b)
    -- @spec
    -- local guard: refuse negative inputs by clamping them to zero
    -- @end-spec
    if a < 0 then
        a = 0
    end
    if b < 0 then
        b = 0
    end
    return a + b
end
