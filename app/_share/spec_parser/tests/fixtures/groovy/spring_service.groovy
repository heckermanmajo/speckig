// @spec
// File-level spec: service layer for foo persistence and lookup.
// @end-spec

package com.example.foo

import org.springframework.beans.factory.annotation.Autowired
import org.springframework.stereotype.Service

// @spec
// FooService brokers foo lookups against the BarRepo.
// holds no mutable state; safe to inject as a singleton bean
// @end-spec
@Service
class FooService
{

    // @spec
    // Repository handle injected by Spring at construction time.
    // never null after Spring DI is complete
    // @end-spec
    @Autowired
    BarRepo bar

    // @spec
    // Looks the foo with the given id up via the repository.
    // returns null when no row matches
    // @end-spec
    def find(Long id)
    {
        return bar.findOne(id)
    }
}
