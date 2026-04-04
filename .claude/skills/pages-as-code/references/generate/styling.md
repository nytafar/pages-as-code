# Page Styling

How to write CSS for Pages as Code pages. This reference covers scoping, the cascade, and working with core block CSS rather than against it.

## Core principle: don't reinvent the wheel

WordPress core blocks already handle:
- **Flow layout** — vertical stacking with consistent gap
- **Alignment** — `alignwide`, `alignfull`, constrained content width
- **Spacing** — block gap, padding via theme.json presets
- **Typography** — font sizes, line heights via theme presets

Never rewrite these behaviors. Build on them.

## How page CSS gets loaded

The `css` front matter field enqueues a stylesheet only on that specific page:

```yaml
---
title: About
css: themes/myrvann/css/about.css
---
```

Because the file is page-scoped by the enqueue, you do **not** need an outer page-level class to avoid collisions. The stylesheet only runs on that page.

## Scoping: use the block's className

Every section-level `wp:group` or `wp:cover` gets a `className` attribute that becomes a real class on the `<div>`. This is your scope.

```html
<!-- wp:group {"align":"full","className":"feature-grid"} -->
<div class="wp-block-group alignfull feature-grid">
  <!-- wp:html -->
  <div class="fg-cards">...</div>
  <!-- /wp:html -->
</div>
<!-- /wp:group -->
```

In your CSS:

```css
/* Scope to the block's className — not a page wrapper */
.feature-grid .fg-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: var(--wp--preset--spacing--40, 2rem);
}
```

### When `wp:html` is inside a block

The `wp:html` content sits directly inside the parent block's `<div>`. Your selectors can target:

1. **The block class** — `.feature-grid` (from `className`)
2. **Scoped classes inside wp:html** — `.fg-cards`, `.fg-card`
3. **Structural selectors** — `.feature-grid > div`, `.fg-card:first-child`, `.fg-card + .fg-card`

### When using only core blocks

Core blocks have their own classes (`wp-block-heading`, `wp-block-paragraph`, etc.). Style them contextually:

```css
/* Paragraphs specifically inside .intro-section */
.intro-section .wp-block-paragraph {
  font-size: var(--wp--preset--font-size--large);
}

/* First heading inside .hero */
.hero .wp-block-heading:first-child {
  font-size: clamp(2.5rem, 5vw, 4.5rem);
}
```

## Selector craft

### Respect the cascade

CSS has a natural priority system. Use it instead of fighting it:

1. **Element selectors** for base typography within a scoped section
2. **Class selectors** for component-level styling
3. **Structural pseudo-classes** (`:first-child`, `:nth-child`, `+ `, `> `) for positional variation
4. **Custom properties** for values that vary — never hard-code a color or spacing value that the theme provides as a token

Avoid:
- Stacking multiple classes (`.section.dark.wide.featured`) — one meaningful class is enough
- `!important` — if you need it, your selector strategy is wrong
- IDs in stylesheets — too specific, can't be overridden
- Deep descendant chains (`.a .b .c .d`) — fragile, breaks on structure changes

### Separate concerns

- **Layout** (grid, flex, alignment) on container elements
- **Skin** (color, background, border) on the element itself
- **Typography** (font, size, weight, spacing) inherited or on text elements
- **State** (hover, focus, active) as modifier selectors

### Prefer fewer, smarter selectors

```css
/* BAD: class on every element */
.card-title { ... }
.card-body { ... }
.card-footer { ... }

/* GOOD: structural selectors within the scoped class */
.fg-card > h3 { ... }
.fg-card > p { ... }
.fg-card > footer { ... }

/* GOOD: sibling combinators for spacing */
.fg-card + .fg-card {
  margin-top: var(--wp--preset--spacing--30);
}
```

## Working with theme custom properties

If `theme.md` defines custom properties, use them. Common patterns:

```css
.my-section {
  /* Colors */
  color: var(--color-text);
  background: var(--color-surface);

  /* Spacing — prefer WP preset tokens when available */
  padding: var(--wp--preset--spacing--50);
  gap: var(--wp--preset--spacing--40);

  /* Typography */
  font-family: var(--wp--preset--font-family--body);
}
```

Always check `theme.md` for the exact token names. The examples above are illustrative — every theme defines its own.

## What to override carefully

| Safe to style | Approach |
|---|---|
| Colors, backgrounds | Custom properties or direct values |
| Typography within your sections | Scoped to your block's className |
| Layout of `wp:html` content | Full control — your DOM, your rules |
| Spacing between your elements | Gap, margin on your scoped selectors |

| Override with care | Why |
|---|---|
| `.wp-block-group` layout | Breaks flow for all groups — scope narrowly |
| `.alignfull` / `.alignwide` | Theme controls these widths |
| `.wp-block-cover` positioning | Complex internal structure |
| Core block gap/padding | Usually set by theme.json — override on your class only |

## File organization

One CSS file per page (or shared across related pages). Place in the theme's CSS directory:

```
wp-content/themes/<theme>/css/
  about.css
  landing.css
  shared/
    cards.css       # shared component styles
```

Reference in front matter:

```yaml
css: themes/<theme>/css/about.css
```

Keep files focused. A page CSS file should only contain styles for the blocks and custom HTML on that page — never global overrides.
