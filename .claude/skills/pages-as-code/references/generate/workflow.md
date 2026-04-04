# Generate Page Workflow

Step-by-step process for creating a Pages as Code `.html` file.

## Steps

### 1. Determine page identity

Decide title, slug, status, and parent (if any). Check existing pages to avoid slug collisions:

```bash
wp post list --post_type=page --fields=ID,post_name,post_status
```

### 2. Read project context

Check for context files in `.claude/` that steer content and styling:

```bash
# Brand identity and writing voice
cat .claude/brand.md 2>/dev/null

# Theme design system — custom properties, class conventions, layout patterns
cat .claude/theme.md 2>/dev/null
```

- **`brand.md`** — brand identity, tone, vocabulary, example passages. Read this to write content that sounds like the brand, not like a generic AI.
- **`theme.md`** — design tokens, class naming, spacing scale, templates. Read this to write CSS that harmonizes with the theme instead of fighting it.

If neither exists, study existing page files in `wp-content/pages/` for established patterns.

For CSS principles and core block behavior, also read [styling.md](styling.md) and [block-css.md](block-css.md).

### 3. Write front matter

Start with the required `title`. Add optional fields only when needed:

```yaml
---
title: About Us
slug: about
status: publish
---
```

Only add `template` if you know the exact slug exists in the active theme. Only add `parent` if the parent page is already in WordPress.

### 4. Write block markup body

**Every HTML element must be wrapped in WordPress block comments.** Bare HTML outside block comments is silently discarded by WordPress. This is the most common mistake — never output a `<p>`, `<h2>`, `<div>`, `<img>`, or any other element without its block comment wrapper.

For the full block reference table (50+ blocks with examples), read [block-editor.md](block-editor.md).

Core pattern — every block follows this structure:
```html
<!-- wp:blockname {"attr":"value"} -->
<valid HTML>
<!-- /wp:blockname -->
```

Build from the outside in:
1. Start with the outermost container (`wp:group`, `wp:cover`, `wp:columns`)
2. Add inner blocks nested **inside** the parent's HTML element
3. Match every opening `<!-- wp:name -->` with a closing `<!-- /wp:name -->`
4. Use `<!-- wp:html -->…<!-- /wp:html -->` for any custom HTML that doesn't map to a core block
5. Never output bare HTML — if you're unsure which block to use, wrap it in `wp:html`

### 5. Write page CSS (if needed)

If the page uses custom layouts via `wp:html` or needs visual styling beyond what core blocks provide, create a CSS file and reference it in front matter:

```yaml
css: themes/<theme>/css/my-page.css
```

Follow the principles in [styling.md](styling.md):
- Don't wrap selectors in an outer page class — the file is already page-scoped by the enqueue
- Scope to block `className` values and structural selectors within `wp:html` content
- Use theme custom properties from `theme.md` — never hard-code colors, spacing, or fonts the theme provides as tokens
- Respect core block CSS — read [block-css.md](block-css.md) to predict the cascade

### 6. Validate

Run the validation script before pushing:

```bash
bash .claude/skills/pages-as-code/scripts/validate-page.sh wp-content/pages/about.html
```

Or check manually:
- Front matter has `---` delimiters and `title` field
- Every `<!-- wp:name -->` has a matching `<!-- /wp:name -->`
- No invented block names
- HTML fragments are valid

### 7. Save

Save to `wp-content/pages/<slug>.html` (or in a subdirectory for organization).

The file has no effect on WordPress until pushed. Proceed to publish workflow.

## Tips

- **Custom layouts**: When core blocks introduce too much wrapper HTML or can't express the design, use `<!-- wp:html -->` with scoped classes — but always wrap it inside a parent layout block (`wp:group`, `wp:column`, `wp:cover`) with appropriate `align`/`className` so the theme's layout flow (max-width, spacing, alignment) still applies
- Keep block nesting shallow when possible — deep nesting is harder to debug
- The `wp:group` block with `{"align":"full","className":"your-class"}` is the workhorse for section-level layout
- Use the page shell template as a starting point: `templates/page-shell.html`
- Always check `.claude/theme.md` and `.claude/brand.md` before generating — they prevent generic output
