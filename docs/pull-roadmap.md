# Pull Roadmap — Design Decisions and Future Concerns

## What v1 (`wp pac pull`) does

Extracts a WordPress page into a `.html` file with YAML front matter. Simple CLI building block — the agent or user decides workflow, the command just reads and writes.

**v1 scope:** pull a page by slug, write file, track revision. No conflict resolution, no sync, no diff.

## v1 front matter fields

| Field | Source | Purpose |
|-------|--------|---------|
| `title` | `post_title` | Required field |
| `slug` | `post_name` | Explicit for clarity |
| `status` | `post_status` | Current publish state |
| `template` | `_wp_page_template` | Only if not `default` |
| `parent` | `post_parent` → slug lookup | Only if page has a parent |
| `meta` | User-defined meta keys via `_pac_meta_keys` | Round-trip from push |
| `pulled_revision` | Latest revision post ID | Drift anchor (see below) |
| `pulled_gmt` | ISO 8601 timestamp of pull | When the snapshot was taken |

## Revision tracking strategy

WordPress stores revisions as child posts (`post_type = 'revision'`). Each edit creates a new revision with a monotonically increasing post ID.

**v1 approach:** Store the latest revision post ID as `pulled_revision` in front matter. On the next pull, compare current latest revision ID with the stored one to detect drift. If they match, the page hasn't changed since last pull.

**Why revision ID over `post_modified_gmt`:**
- Integer comparison is simpler and unambiguous
- Revision IDs survive timezone confusion
- Maps directly to `wp_get_post_revisions()` for future diff tooling

**Fallback:** If revisions are disabled (`WP_POST_REVISIONS = false`), fall back to `post_modified_gmt`. Store whichever is available; consumer checks the field name.

**What this does NOT do (v1):** No conflict resolution. No merge. No "refuse push if page changed since pull." The user manages the pull → edit → push cycle. The revision ID is informational — it lets agents or humans decide whether to re-pull before pushing.

## File collision handling

When the target file already exists on disk:

1. **Default:** Refuse with an error. Don't silently overwrite.
2. **`--force`:** Overwrite the existing file.
3. **`--revision-suffix`:** Write to `about.r123.html` where `123` is the revision ID. Provides a versioned snapshot without overwriting.

**Slug resolution on push:** `PAC_File` already resolves slug from filename. Files with `.r123` suffix need the slug resolution to strip it — `about.r123.html` → slug `about`, not `about.r123`. This keeps push compatible with revision-suffixed filenames.

## Subfolder targeting

`--dir=<subfolder>` writes the file into a subdirectory under `wp-content/pages/`:

```bash
wp pac pull about --dir=drafts/       # → pages/drafts/about.html
wp pac pull about --dir=archive/2026/ # → pages/archive/2026/about.html
```

This enables workflow patterns in agent instructions without hardcoding them in the plugin:
- Pull into `drafts/` for review before promoting to root
- Pull into `archive/` for point-in-time snapshots
- Pull into `compare/` for side-by-side diffing

The directory is created if it doesn't exist (like `wp_mkdir_p`).

## Content normalization concern

`post_content` may differ from what was originally pushed:
- Block editor re-serializes blocks on save (attribute order, whitespace)
- `wp_kses_post()` may strip or modify markup
- `wpautop` may affect non-block content

**v1 approach:** Accept it. The pulled file reflects what WordPress currently has, not what was originally pushed. The next push will write it back as-is. Hash comparison will see a diff even if nothing semantically changed — this is acceptable for v1 because the user is explicitly choosing to pull and re-push.

**Future consideration:** A `wp pac status` command could do semantic comparison (parse both, compare block trees) rather than byte comparison. Deferred.

## Meta round-trip

**Problem:** On push, user-defined meta from front matter `meta:` is written to post meta. On pull, we need to know which meta keys to read back. Other plugins also write meta — we can't pull everything.

**v1 approach:** On push, `PAC_Pusher::write_meta()` stores the list of user-defined meta keys in `_pac_meta_keys` (serialized array). On pull, `PAC_Puller` reads `_pac_meta_keys` and extracts only those values. If `_pac_meta_keys` is absent (page was pushed before this feature), pull writes no `meta:` section.

## Parent resolution in reverse

Push takes `parent: company` (slug). Pull needs to resolve `post_parent` (ID) back to a slug.

**v1 approach:** Look up the parent post by ID, use `post_name` as the slug. If parent doesn't exist (deleted), omit the `parent:` field and log a warning. If parent isn't PAC-managed, still write the slug — it's valid even if the parent was created manually.

## Asset handling on pull

CSS/JS paths are stored in `_pac_css` / `_pac_js` meta. On pull:
- If the file exists on disk, write `css:` / `js:` in front matter
- If the file doesn't exist, omit the field (dead path)

Assets are NOT pulled/copied — they're already on disk. Pull only records the reference.

## Modular architecture

```
includes/
  class-pac-puller.php      Pull logic: query page, extract content, build file string
  class-pac-serializer.php   YAML front matter + body → string (shared by pull, future template gen)
```

**Rollback:** Delete these two files, remove `pull()` from `PAC_CLI`, remove require_once lines. Zero impact on push/validate.

## Deferred to future versions

| Feature | Why deferred |
|---------|-------------|
| `wp pac status <file>` | Drift detection (file vs DB) — separate command, needs semantic block comparison |
| `wp pac diff <file>` | Show what changed since last push/pull — needs block-level diffing |
| `--validate` on pull | Run validator on pulled content — trivial to add once pull exists, but not v1 |
| Conflict resolution | "Page changed since pull, refuse push" — requires storing pull state in meta and comparing on push. Intentionally deferred — let the user/agent manage the cycle. |
| Bidirectional sync | Fundamentally different architecture. Not on the roadmap. PAC is one-way-at-a-time by design. |
| Bulk pull | `wp pac pull --all` to pull all PAC-managed pages. Useful but v1 focuses on single-page building blocks. |

## Design philosophy

**CLI building blocks, not workflow engine.** The agent improves over time independently of our code. Keep commands simple, composable, and JSON-friendly. The agent (via CLAUDE.md and skills) decides:
- When to pull vs push
- Where to store files
- Whether to re-pull before pushing
- How to handle drift

This separation means every improvement to the agent's reasoning automatically improves the workflow without plugin changes.
