# Generate Page Workflow

Step-by-step process for creating a Pages as Code `.html` file.

## Steps

### 1. Determine page identity

Decide title, slug, status, and parent (if any). Check existing pages to avoid slug collisions:

```bash
wp post list --post_type=page --fields=ID,post_name,post_status
```

### 2. Write front matter

Start with the required `title`. Add optional fields only when needed:

```yaml
---
title: About Us
slug: about
status: publish
---
```

Only add `template` if you know the exact slug exists in the active theme. Only add `parent` if the parent page is already in WordPress.

### 3. Write block markup body

Use Gutenberg block comment syntax. For the full block reference table, read [block-editor.md](block-editor.md).

Core pattern:
```html
<!-- wp:blockname {"attr":"value"} -->
<valid HTML>
<!-- /wp:blockname -->
```

Build from the outside in:
1. Start with the outermost container (group, cover, columns)
2. Add inner blocks nested inside the parent's HTML
3. Match every opening comment with a closing comment
4. Use `<!-- wp:html -->` for any custom HTML that doesn't fit a core block

### 4. Validate

Run the validation script before pushing:

```bash
bash .claude/skills/pages-as-code/scripts/validate-page.sh wp-content/pages/about.html
```

Or check manually:
- Front matter has `---` delimiters and `title` field
- Every `<!-- wp:name -->` has a matching `<!-- /wp:name -->`
- No invented block names
- HTML fragments are valid

### 5. Save

Save to `wp-content/pages/<slug>.html` (or in a subdirectory for organization).

The file has no effect on WordPress until pushed. Proceed to publish workflow.

## Tips

- Use `<!-- wp:html -->` blocks for complex custom markup rather than trying to force it into core blocks
- Keep block nesting shallow when possible — deep nesting is harder to debug
- The `wp:group` block with `{"align":"full","className":"your-class"}` is the workhorse for section-level layout
- Use the page shell template as a starting point: `templates/page-shell.html`
