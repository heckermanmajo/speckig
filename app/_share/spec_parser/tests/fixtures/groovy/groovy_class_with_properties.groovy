// @spec
// File-level spec: bag with auto-getter/setter property fields.
// @end-spec

// @spec
// Person carries identity-style attributes as Groovy properties.
// each property gets an auto-generated getter/setter pair
// @end-spec
class Person
{

    // @spec
    // Display name shown in the UI.
    // @end-spec
    String name

    // @spec
    // Age in completed years; never negative.
    // @end-spec
    int age

    String email
}
