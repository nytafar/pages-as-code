# WordPress Block Editor: Native Block HTML Syntax

## Table of Contents

- [Markup rules](#markup-rules)
- [Core block reference table](#core-block-reference-table)
- [Custom/plugin blocks](#customplugin-blocks)

## Markup rules

Every Gutenberg block is stored as HTML wrapped in `<!-- wp:name -->` comments.

- **JS block name**: `core/paragraph`, `core/image`, `core/group`, etc.
- **Stored comment name**: `wp:paragraph`, `wp:image`, `wp:group` (no `core/` prefix)
- **Attributes**: valid JSON inside the opening comment
- **Static blocks** have saved HTML and a closing tag
- **Dynamic blocks** are self-closing, rendered server-side

### Static block syntax

```html
<!-- wp:name {"attr":"value"} -->
<valid saved HTML>
<!-- /wp:name -->
```

### Self-closing (dynamic) block syntax

```html
<!-- wp:name {"attr":"value"} /-->
```

### Nesting

Inner blocks go inside the parent's saved HTML. Always match open/close order.

```html
<!-- wp:columns -->
<div class="wp-block-columns">
  <!-- wp:column -->
  <div class="wp-block-column">
    <!-- wp:paragraph -->
    <p>Content.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:column -->
</div>
<!-- /wp:columns -->
```

### Rules for AI agents

**CRITICAL**: Bare HTML outside block comments is silently discarded by WordPress. Every HTML element you generate must be inside a block comment pair.

1. **No bare HTML** — every `<p>`, `<h2>`, `<div>`, `<img>`, `<ul>`, `<hr>`, etc. must be wrapped in its corresponding `<!-- wp:blockname -->…<!-- /wp:blockname -->` pair. If no core block fits, use `<!-- wp:html -->…<!-- /wp:html -->`.
2. **Never invent block names** — use only canonical names from the table below.
3. **Use self-closing form for dynamic blocks** — if the Type column says "dynamic", use `<!-- wp:blockname /-->` with no HTML body.
4. **Omit `core/` in comments** — `core/paragraph` → `wp:paragraph`.
5. **Match every opening comment with a closing comment** — mismatched pairs corrupt the block structure.
6. **Nest inner blocks inside the parent's HTML element** — e.g., `<!-- wp:paragraph -->` goes inside the `<div>` of a `<!-- wp:group -->`, not outside it.
7. **Always output valid HTML inside blocks** — the HTML between block comments must be well-formed and match what WordPress expects for that block type (see examples in the table).

### When to use `wp:html` for custom layouts

Core blocks come with their own wrapper HTML (`wp-block-group`, `wp-block-columns`, etc.). For layouts where this extra markup gets in the way — custom grids, card decks, decorative elements, animated sections — use `<!-- wp:html -->` instead and scope everything with a class prefix.

**Always wrap `wp:html` inside a parent layout block** (`wp:group`, `wp:column`, `wp:cover`) with the correct `align` and `className` attributes. This ensures the custom HTML inherits the theme's layout flow — max-width, alignment, spacing — from the parent block's standard CSS.

```html
<!-- wp:group {"align":"full","className":"testimonial-strip"} -->
<div class="wp-block-group alignfull testimonial-strip">

<!-- wp:html -->
<div class="ts-track">
  <blockquote class="ts-card">
    <p>Quote text.</p>
    <cite>— Author</cite>
  </blockquote>
  <blockquote class="ts-card">
    <p>Quote text.</p>
    <cite>— Author</cite>
  </blockquote>
</div>
<!-- /wp:html -->

</div>
<!-- /wp:group -->
```

Use this pattern when:
- A core block would force unwanted wrapper elements or class names
- You need a specific DOM structure for CSS grid/flexbox layouts
- The design requires decorative or interactive HTML (animations, data attributes)
- Multiple related elements should stay together as one unit

Do **not** use `wp:html` as a lazy shortcut — prefer core blocks when they produce the right structure. Reserve `wp:html` for cases where core blocks genuinely cannot express the layout.

## Core block reference table

| JS block name (`core/...`) | Comment (`wp:...`) | Type | Minimal example |
|---|---|---|---|
| `core/paragraph` | `wp:paragraph` | static | `<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Text</p><!-- /wp:paragraph -->` |
| `core/heading` | `wp:heading` | static | `<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Heading</h2><!-- /wp:heading -->` |
| `core/image` | `wp:image` | static | `<!-- wp:image {"id":42} --><figure class="wp-block-image"><img src="..."/></figure><!-- /wp:image -->` |
| `core/gallery` | `wp:gallery` | static | `<!-- wp:gallery {"ids":[10,20]} --><div class="wp-block-gallery">...</div><!-- /wp:gallery -->` |
| `core/columns` | `wp:columns` | static | `<!-- wp:columns --><div class="wp-block-columns">...</div><!-- /wp:columns -->` |
| `core/column` | `wp:column` | static | `<!-- wp:column --><div class="wp-block-column">...</div><!-- /wp:column -->` |
| `core/group` | `wp:group` | static | `<!-- wp:group --><div class="wp-block-group">...</div><!-- /wp:group -->` |
| `core/cover` | `wp:cover` | static | `<!-- wp:cover {"dimRatio":50} --><div class="wp-block-cover">...</div><!-- /wp:cover -->` |
| `core/list` | `wp:list` | static | `<!-- wp:list --><ul class="wp-block-list">...</ul><!-- /wp:list -->` |
| `core/list-item` | `wp:list-item` | static | `<!-- wp:list-item --><li>Item</li><!-- /wp:list-item -->` |
| `core/quote` | `wp:quote` | static | `<!-- wp:quote --><blockquote class="wp-block-quote">...</blockquote><!-- /wp:quote -->` |
| `core/pullquote` | `wp:pullquote` | static | `<!-- wp:pullquote --><figure class="wp-block-pullquote">...</figure><!-- /wp:pullquote -->` |
| `core/table` | `wp:table` | static | `<!-- wp:table --><figure class="wp-block-table"><table>...</table></figure><!-- /wp:table -->` |
| `core/separator` | `wp:separator` | static | `<!-- wp:separator --><hr class="wp-block-separator"/><!-- /wp:separator -->` |
| `core/spacer` | `wp:spacer` | dynamic | `<!-- wp:spacer {"height":"20px"} /-->` |
| `core/buttons` | `wp:buttons` | static | `<!-- wp:buttons --><div class="wp-block-buttons">...</div><!-- /wp:buttons -->` |
| `core/button` | `wp:button` | static | `<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="...">Text</a></div><!-- /wp:button -->` |
| `core/html` | `wp:html` | static | `<!-- wp:html --><div class="custom">...</div><!-- /wp:html -->` |
| `core/code` | `wp:code` | static | `<!-- wp:code --><pre class="wp-block-code"><code>...</code></pre><!-- /wp:code -->` |
| `core/preformatted` | `wp:preformatted` | static | `<!-- wp:preformatted --><pre class="wp-block-preformatted">...</pre><!-- /wp:preformatted -->` |
| `core/embed` | `wp:embed` | static | `<!-- wp:embed {"url":"https://..."} --><figure class="wp-block-embed">...</figure><!-- /wp:embed -->` |
| `core/audio` | `wp:audio` | static | `<!-- wp:audio {"src":"..."} --><figure><audio src="..."/></figure><!-- /wp:audio -->` |
| `core/file` | `wp:file` | static | `<!-- wp:file {"href":"..."} --><div class="wp-block-file">...</div><!-- /wp:file -->` |
| `core/more` | `wp:more` | dynamic | `<!-- wp:more {"customText":"Read more"} /-->` |
| `core/nextpage` | `wp:nextpage` | dynamic | `<!-- wp:nextpage /-->` |
| `core/freeform` | `wp:freeform` | static | `<!-- wp:freeform --><div>Any HTML</div><!-- /wp:freeform -->` |
| `core/latest-posts` | `wp:latest-posts` | dynamic | `<!-- wp:latest-posts {"postsToShow":3} /-->` |
| `core/latest-comments` | `wp:latest-comments` | dynamic | `<!-- wp:latest-comments {"commentsToShow":5} /-->` |
| `core/archives` | `wp:archives` | dynamic | `<!-- wp:archives {"type":"monthly"} /-->` |
| `core/search` | `wp:search` | static | `<!-- wp:search --><div class="wp-block-search">...</div><!-- /wp:search -->` |
| `core/rss` | `wp:rss` | dynamic | `<!-- wp:rss {"feedURL":"https://example.com/feed"} /-->` |
| `core/shortcode` | `wp:shortcode` | static | `<!-- wp:shortcode -->[gallery ids="1,2"]<!-- /wp:shortcode -->` |
| `core/table-of-contents` | `wp:table-of-contents` | dynamic | `<!-- wp:table-of-contents {"maxLevel":3} /-->` |
| `core/footnotes` | `wp:footnotes` | static | `<!-- wp:footnotes --><div class="wp-block-footnotes">...</div><!-- /wp:footnotes -->` |
| `core/navigation` | `wp:navigation` | static | `<!-- wp:navigation --><nav class="wp-block-navigation">...</nav><!-- /wp:navigation -->` |
| `core/navigation-link` | `wp:navigation-link` | static | `<!-- wp:navigation-link {"label":"Home","url":"/"} /-->` |
| `core/social-links` | `wp:social-links` | static | `<!-- wp:social-links --><div class="wp-block-social-links">...</div><!-- /wp:social-links -->` |
| `core/social-link` | `wp:social-link` | static | `<!-- wp:social-link {"service":"twitter","url":"..."} /-->` |
| `core/site-title` | `wp:site-title` | dynamic | `<!-- wp:site-title /-->` |
| `core/site-logo` | `wp:site-logo` | dynamic | `<!-- wp:site-logo /-->` |
| `core/site-tagline` | `wp:site-tagline` | dynamic | `<!-- wp:site-tagline /-->` |
| `core/post-content` | `wp:post-content` | dynamic | `<!-- wp:post-content /-->` |
| `core/post-title` | `wp:post-title` | dynamic | `<!-- wp:post-title /-->` |
| `core/post-date` | `wp:post-date` | dynamic | `<!-- wp:post-date {"format":"F j, Y"} /-->` |
| `core/post-excerpt` | `wp:post-excerpt` | dynamic | `<!-- wp:post-excerpt /-->` |
| `core/post-featured-image` | `wp:post-featured-image` | dynamic | `<!-- wp:post-featured-image /-->` |
| `core/comments` | `wp:comments` | static | `<!-- wp:comments --><div class="wp-block-comments">...</div><!-- /wp:comments -->` |
| `core/query` | `wp:query` | static | `<!-- wp:query --><div class="wp-block-query">...</div><!-- /wp:query -->` |
| `core/post-template` | `wp:post-template` | static | Child of `core/query`. |
| `core/query-pagination` | `wp:query-pagination` | static | `<!-- wp:query-pagination --><div>...</div><!-- /wp:query-pagination -->` |
| `core/query-pagination-next` | `wp:query-pagination-next` | dynamic | `<!-- wp:query-pagination-next /-->` |
| `core/query-pagination-previous` | `wp:query-pagination-previous` | dynamic | `<!-- wp:query-pagination-previous /-->` |
| `core/form` | `wp:form` | experimental | `<!-- wp:form {"method":"post"} --><form class="wp-block-form">...</form><!-- /wp:form -->` |
| `core/accordion` | `wp:accordion` | experimental | `<!-- wp:accordion --><div class="wp-block-accordion">...</div><!-- /wp:accordion -->` |
| `core/accordion-item` | `wp:accordion-item` | nested | Child of `core/accordion`. |
| `core/accordion-heading` | `wp:accordion-heading` | nested | Child of `core/accordion-item`. |
| `core/accordion-panel` | `wp:accordion-panel` | nested | Child of `core/accordion-item`. |

## Custom/plugin blocks

Custom blocks follow the same pattern but keep their namespace:

```html
<!-- wp:my-plugin/testimonial {"author":"Jane"} -->
<div class="wp-block-my-plugin-testimonial">...</div>
<!-- /wp:my-plugin/testimonial -->
```

Treat plugin blocks as static unless documentation explicitly marks them as dynamic.
