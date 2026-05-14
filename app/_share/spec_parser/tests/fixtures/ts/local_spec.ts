// @spec
// Fixture exercising local-spec capture inside a class method body.
// @end-spec

// @spec
// Container class with a single method holding a local spec note.
// @end-spec
class Compute
{
    // @spec
    // Sums two integers, clamping negatives to zero before adding.
    // @end-spec
    add(a: number, b: number): number
    {
        // @spec
        // local guard: refuse negative inputs by clamping them to zero
        // @end-spec
        if (a < 0)
        {
            a = 0;
        }
        if (b < 0)
        {
            b = 0;
        }
        return a + b;
    }
}
