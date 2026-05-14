// @spec
// File-level spec: a single class extending Widget with two fields and a render method.
// @end-spec

// @spec
// Renders a tree node label with optional child counts.
// @end-spec
class TreeNode extends Widget
{
    // @spec display label, never empty after trim
    // @end-spec
    label = "";

    // @spec count of direct children, always >= 0
    // @end-spec
    child_count = 0;

    // @spec
    // Build the DOM string for this node, including the child-count badge.
    // @end-spec
    render(prefix)
    {
        return prefix + this.label;
    }
}
