// @spec
// File-level spec for the JS fixture: a single top-level function with a multi-line spec block.
// @end-spec

// @spec
// Compute a normalised page title for the speckig file viewer.
// trims surrounding whitespace
// returns the fallback string when the input is empty
// @end-spec
function build_page_title(raw_path, fallback)
{
    let trimmed = String(raw_path || "").trim();
    if (trimmed === "") { return fallback; }
    return trimmed;
}
