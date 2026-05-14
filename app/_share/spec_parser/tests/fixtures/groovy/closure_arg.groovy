// @spec
// File-level spec: ensure the tokenizer survives closure-shaped parameter defaults.
// @end-spec

// @spec
// Runner accepts a closure as parameter; closure body is not analyzed.
// @end-spec
class Runner
{

    // @spec
    // Runs the given closure with no arguments, swallowing any return value.
    // closure body is opaque to the parser
    // @end-spec
    void run(Closure cb = { -> println("default") })
    {
        cb.call()
    }

    // @spec
    // Runs the closure twice in sequence; second call sees the same closure.
    // @end-spec
    void run_twice(Closure cb)
    {
        cb.call()
        cb.call()
    }
}
