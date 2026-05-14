## @spec
## Fixture exercising local-spec capture inside a proc body.
## @end-spec

## @spec
## Compute the sum of two integers, clamping negatives to zero before adding.
## @end-spec
proc compute(a: int, b: int): int =
  ## @spec
  ## local guard: refuse negative inputs by clamping them to zero
  ## @end-spec
  if a < 0:
    a = 0
  if b < 0:
    b = 0
  result = a + b
