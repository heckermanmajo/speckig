<?php
// @spec
// Pilot-style data class fixture: file-spec plus three typed properties, each with its own spec line.
// @end-spec

namespace _share\spec_parser\tests;

// @spec
// Demo product row used for parser fixture tests; structurally mirrors User.php.
// @end-spec
class Product
{
    // @spec product display name, 1..200 chars
    // @end-spec
    public string $title = "";

    // @spec price in cents, non-negative
    // @end-spec
    public int $price_cents = 0;

    // @spec true once the product has been published to the public catalog
    // @end-spec
    public bool $is_published = false;
}
