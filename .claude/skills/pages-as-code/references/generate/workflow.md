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

### 5. Generate CSS (optional)

Create a matching `.css` file when the page needs styling beyond what the active theme provides.

**When to create CSS:**
- Page-specific section treatments, animations, or layout refinements
- Custom wrappers or visual identity elements
- Styles that only apply to this page, not globally

**When NOT to create CSS:**
- The theme already provides the typography, spacing, colors, and button styles you need
- You're resetting or fighting global theme defaults — prefer additive styling
- Generic block styling that `theme.json` or the active theme handles

**CSS philosophy:**
- Start from the theme's existing baseline — analyze it first if needed
- Use page-scoped selectors (e.g., `.page-about .hero-section`)
- Focus on inside-block content, not editor chrome or global resets
- Prefer additive styling over replacement styling
- Don't reinvent typography, spacing, buttons, forms, or block defaults

**How to create:**
```bash
# Sibling pattern — same directory, same basename
wp-content/pages/about.html
wp-content/pages/about.css
```

The CSS will be auto-resolved during push and loaded on both the frontend and block editor.

### 6. Generate JS (only when needed)

Create a matching `.js` file only when the page requires client-side interaction.

**When JS is justified:**
- Interactive UI elements (sliders, tabs, accordions beyond what blocks provide)
- Form validation or dynamic form behavior
- Animation triggered by scroll or user interaction
- Third-party widget initialization

**When NOT to create JS:**
- Block editor already provides the interaction (e.g., accordion block, tabs block)
- The theme or a plugin already handles it globally

JS is loaded on the **frontend only** (not in the block editor). Keep scripts standalone and page-scoped.

### 7. Save

Save to `wp-content/pages/<slug>.html` (and optional `<slug>.css` / `<slug>.js`).

The files have no effect on WordPress until pushed. Proceed to publish workflow.

## Tips

- Use `<!-- wp:html -->` blocks for complex custom markup rather than trying to force it into core blocks
- Keep block nesting shallow when possible — deep nesting is harder to debug
- The `wp:group` block with `{"align":"full","className":"your-class"}` is the workhorse for section-level layout
- Use the page shell template as a starting point: `templates/page-shell.html`
- Prefer a sibling `.css` file over inline `<style>` tags — it's better for caching, diffs, and editor parity
- Analyze the active theme before generating CSS to avoid duplicating existing styles
