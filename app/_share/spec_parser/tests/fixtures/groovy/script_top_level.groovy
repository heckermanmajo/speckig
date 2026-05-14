// @spec
// File-level spec: bare Groovy script with top-level vars and methods.
// no enclosing class wrapper here
// @end-spec

// @spec
// Greeting prefix used by the script methods below.
// @end-spec
def greeting = "hello"

// @spec
// Counter incremented by repeat() across runs.
// @end-spec
int counter = 0

// @spec
// Greets the named subject by combining the greeting prefix and the name.
// @end-spec
def greet(String name)
{
    return greeting + " " + name
}

// @spec
// Increments the script-level counter by the given delta and returns the new value.
// delta may be negative
// @end-spec
def repeat(int delta)
{
    counter = counter + delta
    return counter
}
