// @spec
// File-level spec exercising local-spec capture inside a method body.
// @end-spec

// @spec
// Compute holds a single method with a local spec note inside the body.
// @end-spec
class Compute
{

    // @spec
    // Sums two integers, clamping negatives to zero before adding.
    // @end-spec
    int add(int a, int b)
    {
        // @spec
        // local guard: refuse negative inputs by clamping them to zero
        // @end-spec
        if (a < 0)
        {
            a = 0
        }
        if (b < 0)
        {
            b = 0
        }
        return a + b
    }
}
