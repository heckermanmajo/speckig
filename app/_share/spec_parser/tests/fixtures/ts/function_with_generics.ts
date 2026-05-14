// @spec
// File-level spec: a generic mapping helper, generics carried as source string in the signature.
// @end-spec

// @spec
// Map every element of xs through fn and return the resulting array.
// @end-spec
function map<T, U>(xs: T[], fn: (t: T) => U): U[]
{
    const out: U[] = [];
    for (const x of xs)
    {
        out.push(fn(x));
    }
    return out;
}
