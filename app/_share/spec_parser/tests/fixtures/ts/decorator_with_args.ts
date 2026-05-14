// @spec
// Fixture exercising the three decorator-args forms: bare (null), empty parens, and parens with content.
// @end-spec

// @spec
// Bare decorator without parens: args_source must be null.
// @end-spec
@Sealed
class A
{
}

// @spec
// Decorator with empty parens: args_source must be the empty string.
// @end-spec
@LogCall()
class B
{
}

// @spec
// Decorator with content: args_source must contain the inner source string.
// @end-spec
@Component({selector: "app-c", standalone: true})
class C
{
}
