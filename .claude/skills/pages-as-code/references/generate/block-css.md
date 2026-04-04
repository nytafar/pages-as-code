# Core Block CSS — Functional Cheat Sheet

The CSS rules you need to predict the cascade when writing page styles. Sourced from WordPress 6.8 (April 2025). Not exhaustive — only the patterns that affect layout decisions.

## Layout fundamentals

### Flow layout (default for groups, covers, columns)

WordPress uses CSS `gap` for vertical spacing between blocks:

```css
/* Theme.json controls the gap value */
.wp-block-group {
  display: flex;
  flex-direction: column;
  gap: var(--wp--style--block-gap, 1.5em);
}
```

**Implication**: Don't add `margin-top` or `margin-bottom` between blocks inside a group — the gap handles it. If you need different spacing within your section, override `gap` on your scoped class.

### Alignment: full, wide, constrained

```css
/* Constrained layout — content centered with max-width */
.is-layout-constrained > * {
  max-width: var(--wp--style--global--content-size);
  margin-left: auto;
  margin-right: auto;
}

.is-layout-constrained > .alignwide {
  max-width: var(--wp--style--global--wide-size);
}

.is-layout-constrained > .alignfull {
  max-width: none;
}
```

**Implication**: An `alignfull` block breaks out of the content column to full viewport width. An `alignwide` sits between content and full. Don't set `width: 100%` manually — use the alignment attributes in the block comment JSON.

### How alignment attributes map to classes

| Block attribute | HTML class | Effect |
|---|---|---|
| `"align":"full"` | `alignfull` | Full viewport width |
| `"align":"wide"` | `alignwide` | Wide width (theme-defined) |
| `"align":"center"` | `has-text-align-center` (paragraphs) | Text centering |
| `"align":"left"` / `"right"` | `alignleft` / `alignright` | Float (images, blocks) |

## Group block

```css
.wp-block-group {
  box-sizing: border-box;
}

/* With padding (set via block attributes or theme.json) */
.wp-block-group.has-background {
  padding: var(--wp--preset--spacing--40, 1.5em);
}
```

The `className` you set in block attributes is added directly to the same `<div>`:

```html
<div class="wp-block-group alignfull my-section">
```

Your `.my-section` selector is on the same element as `.wp-block-group` — no nesting needed to reach the group itself.

## Columns block

```css
.wp-block-columns {
  display: flex;
  flex-wrap: wrap;
  gap: var(--wp--style--block-gap, 2em);
}

.wp-block-column {
  flex-grow: 1;
  flex-basis: 0;
  min-width: 0; /* prevents overflow */
}

/* Stacks vertically on small screens */
@media (max-width: 781px) {
  .wp-block-columns {
    flex-wrap: wrap;
  }
  .wp-block-column {
    flex-basis: 100% !important;
  }
}
```

**Implication**: Columns use flexbox with equal widths by default. Set `{"width":"33.33%"}` on a column to override. On mobile (<782px), all columns stack. If you need a grid that doesn't stack, use `wp:html` with CSS grid instead.

## Cover block

```css
.wp-block-cover {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  min-height: 430px; /* overridden by block attribute */
}

.wp-block-cover__background {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.wp-block-cover__image-background {
  position: absolute;
  inset: 0;
  object-fit: cover;
  z-index: 0;
}

.wp-block-cover__inner-container {
  position: relative;
  z-index: 2;
  width: 100%;
}
```

**Implication**: The inner container is z-index 2, overlay is z-index 1, image is z-index 0. Content inside `.wp-block-cover__inner-container` follows normal flow. Use `min-height` in block attributes (not CSS) to set the cover height.

## Image block

```css
.wp-block-image {
  margin: 0;
}

.wp-block-image img {
  max-width: 100%;
  height: auto;
  vertical-align: bottom;
}

.wp-block-image.alignfull img {
  width: 100%;
}
```

**Implication**: Images are responsive by default. `alignfull` images stretch to viewport. The `<figure>` wrapper carries the alignment class.

## Buttons block

```css
.wp-block-buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5em;
}

.wp-block-button__link {
  display: inline-block;
  padding: calc(0.667em + 2px) calc(1.333em + 2px);
  text-decoration: none;
  border-radius: 0; /* theme often overrides */
}
```

**Implication**: Multiple buttons flex-wrap with gap. Style `.wp-block-button__link` for the button itself, `.wp-block-button` for the wrapper.

## List block

```css
.wp-block-list {
  padding-left: 1.5em; /* varies by theme */
}
```

Standard `<ul>` / `<ol>` behavior. Each `<li>` is a `wp:list-item` block, but the CSS is plain list styling.

## Separator block

```css
.wp-block-separator {
  border: none;
  border-top: 2px solid currentColor;
  opacity: 0.4;
}

.wp-block-separator.is-style-wide {
  border-top-width: 1px;
}

.wp-block-separator.is-style-dots {
  border: none;
  text-align: center;
}
```

## Key takeaways for page CSS

1. **Gap, not margin** — blocks use `gap` for spacing; don't fight it with margins
2. **Alignment is class-based** — use block attributes, not CSS, to control `alignfull`/`alignwide`
3. **Columns are flexbox** — they stack on mobile by default; use `wp:html` + grid if you need different behavior
4. **Cover is positioned layers** — content is z-index 2; don't change the stacking without understanding the overlay
5. **Custom properties are your friends** — `--wp--preset--spacing--*`, `--wp--preset--font-size--*`, `--wp--preset--color--*` are the tokens; use them to stay consistent with theme.json values
6. **Your className lives on the same element as wp-block-*` classes** — you can target `.my-section` or `.my-section.wp-block-group`, no wrapper div between you and the block
