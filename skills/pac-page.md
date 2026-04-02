# Pages as Code — Page Creation Skill

Create and publish WordPress pages using the Pages as Code plugin. This skill has two submodules: **Markup** (creating compliant .html files) and **CLI** (pushing them to WordPress).

---

## Submodule 1: Markup — Creating Page Files

### File location

All page files live under `wp-content/pages/`. Subdirectories are organizational only — they do not affect WordPress page hierarchy or URLs.

### File format

Every `.html` file has two parts:

1. **YAML front matter** between `---` delimiters
2. **Body**: raw Gutenberg block markup

```html
---
title: Page Title
slug: page-slug
status: draft
template: template-slug
parent: parent-page-slug
meta:
  seo_title: SEO override title
  custom_key: custom value
---
<!-- wp:paragraph -->
<p>Block content goes here.</p>
<!-- /wp:paragraph -->
```

### Front matter fields

| Field      | Required | Default | Maps to         | Notes |
|------------|----------|---------|-----------------|-------|
| `title`    | yes      | —       | `post_title`    | Always required. |
| `slug`     | no       | filename| `post_name`     | Primary key. Falls back to filename without `.html`. |
| `status`   | no       | `draft` | `post_status`   | Use `publish` to make live immediately. |
| `template` | no       | default | `page_template` | Must exist in the active theme. Omit if unsure. |
| `parent`   | no       | —       | `post_parent`   | Slug of parent page. Parent must exist before push. |
| `meta`     | no       | —       | post meta       | One level of key-value pairs. Written as-is. |

### Rules for creating files

1. `title` is always required. The push will fail without it.
2. `slug` should be unique across all page files. If two files share a slug, the second push overwrites the first.
3. `template` must be a valid template slug in the active theme. If the template doesn't exist, WordPress rejects the post. When in doubt, omit it.
4. `parent` is resolved by slug at push time. Push parent pages before children.
5. `status` accepts any valid WordPress post status: `draft`, `publish`, `pending`, `private`, `future`.
6. `meta` supports simple key-value pairs only (one level deep). Values are sanitized on write.

### Creating vs publishing

Creating the file and publishing it to WordPress are **separate actions**:

1. **Create**: Write the `.html` file to `wp-content/pages/`
2. **Publish**: Run `wp pac push <file>` to insert/update the page in WordPress

The file on disk has no effect on WordPress until explicitly pushed.

---

## Submodule 2: CLI — Pushing Pages to WordPress

### Command

```bash
wp pac push <file> [--format=<format>] [--user=<id>]
```

- `<file>` — path relative to `wp-content/pages/` (e.g., `about.html`, `landing/product-a.html`)
- `--format` — `human` (default) or `json`
- `--user` — WordPress user ID with `edit_pages` capability (required in most hosting environments)

### Behavior

1. Reads and parses the file (front matter + body)
2. Computes SHA-256 hash of file contents
3. Looks up existing page by slug
4. If page exists and hash matches: **skip** (no-op)
5. If page exists and hash differs: **update** (creates revision)
6. If page doesn't exist: **create**
7. Writes tracking meta: `_pac_managed`, `_pac_source`, `_pac_hash`, `_pac_last_push_gmt`
8. Writes user-defined meta from front matter

### GridPane hosting

On GridPane servers, WP-CLI runs through `gp wp`:

```bash
# Find admin users
gp wp <site> user list --role=administrator --fields=ID,user_login

# Push a page (must specify --user for capability check)
gp wp <site> pac push about.html --user=1

# Verify the page
gp wp <site> post list --post_type=page --fields=ID,post_title,post_name,post_status
```

Replace `<site>` with the site domain (e.g., `staging.myrvann.no`).

### Standard WP-CLI hosting

```bash
wp pac push about.html
wp pac push landing/product-a.html --format=json
```

### Output examples

```
Success: Created page "About" (ID 42, slug: about).
Success: Updated page "About" (ID 42, slug: about).
Success: Page "About" unchanged, skipping.
Error: File not found: landing/missing.html
Error: Front matter parse error in about.html: missing title field.
Error: Parent page "company" not found.
```

### JSON output

```json
{"status":"created","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"unchanged","id":42,"slug":"about","title":"About","file":"about.html"}
{"status":"error","message":"File not found: landing/missing.html","file":"landing/missing.html"}
```

### Workflow for pushing multiple pages

Push parent pages first, then children:

```bash
wp pac push company.html --user=1
wp pac push company/about.html --user=1
wp pac push company/team.html --user=1
```

Re-pushing an unchanged file is safe — the hash check makes it a no-op.

---

## Submodule 3: Block Editor Markup Reference

### Core markup rules

Every Gutenberg block is stored as HTML wrapped in `<!-- wp:name -->` comments. There are two forms:

**Static blocks** (have saved HTML):
```html
<!-- wp:name {"attr":"value"} -->
<valid saved HTML>
<!-- /wp:name -->
```

**Dynamic/self-closing blocks** (rendered server-side):
```html
<!-- wp:name {"attr":"value"} /-->
```

### Key rules

- Block names in HTML comments use `wp:` prefix, not `core/`. So `core/paragraph` becomes `wp:paragraph`.
- Attributes must be valid JSON inside the opening comment.
- Inner blocks nest inside the saved HTML of their parent block.
- Never invent block names — use only canonical names from the reference table below.
- Always output valid HTML fragments inside the saved HTML.
- Use consistent indentation for readability but WordPress doesn't require it.

### Nesting example

```html
<!-- wp:columns -->
<div class="wp-block-columns">
  <!-- wp:column -->
  <div class="wp-block-column">
    <!-- wp:paragraph -->
    <p>Left column content.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:column -->

  <!-- wp:column -->
  <div class="wp-block-column">
    <!-- wp:paragraph -->
    <p>Right column content.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:column -->
</div>
<!-- /wp:columns -->
```

### Core block reference table

| JS block name (`core/...`) | Comment (`wp:...`) | Type | Minimal example |
|---|---|---|---|
| `core/paragraph` | `wp:paragraph` | static | `<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Text</p><!-- /wp:paragraph -->` |
| `core/heading` | `wp:heading` | static | `<!-- wp:heading {"level":2} --><h2>Heading</h2><!-- /wp:heading -->` |
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

### Custom/plugin blocks

Custom blocks follow the same pattern but keep their namespace:

```html
<!-- wp:my-plugin/testimonial {"author":"Jane"} -->
<div class="wp-block-my-plugin-testimonial">...</div>
<!-- /wp:my-plugin/testimonial -->
```

When in doubt, treat a plugin block as static unless its documentation says otherwise.
