// @spec
// File-level spec: REST controller surface for the foo resource.
// @end-spec

package com.example.foo

import org.springframework.web.bind.annotation.GetMapping
import org.springframework.web.bind.annotation.RequestMapping
import org.springframework.web.bind.annotation.RestController

// @spec
// FooController exposes /api/foo endpoints; mounted under /api.
// @end-spec
@RestController
@RequestMapping("/api")
class FooController
{

    // @spec
    // Lists all known foos for the current tenant.
    // returns an empty list when no foo exists
    // @end-spec
    @GetMapping("/foo")
    def listFoos()
    {
        return []
    }
}
