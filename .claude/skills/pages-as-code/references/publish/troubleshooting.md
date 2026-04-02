# Troubleshooting

## Common errors

| Error | Cause | Fix |
|---|---|---|
| "You do not have permission to edit pages" | Missing `--user` or user lacks `edit_pages` | Add `--user=<admin_id>` |
| "Invalid page template" | `template` references non-existent template | Remove `template` from front matter |
| "Parent page not found" | Parent doesn't exist yet | Push parent page first |
| "Path outside managed root" | Path traversal (`../`) | Use paths relative to `wp-content/pages/` |
| "missing title field" | No `title` in front matter | Add `title: Your Title` |
| "File not found" | Wrong filename or path | Check spelling and that file exists in `wp-content/pages/` |
| "command not found: wp" | WP-CLI not in PATH or managed hosting | Try `gp wp` (GridPane) or check hosting docs |

## GridPane hosting

GridPane wraps WP-CLI behind `gp wp`. Standard `wp` commands will fail.

### Detection

```bash
# GridPane is present if gp binary exists
command -v gp &>/dev/null || [ -x /usr/local/bin/gp ]
```

### Determine site name

The site name is the domain. Find it from the document root:

```bash
# From the WordPress path
basename "$(dirname "$(dirname "$(wp eval 'echo ABSPATH;')")")"

# Or check the directory structure
ls /var/www/ | grep -v '^22222$'
```

### Commands

```bash
# Find admin users
gp wp staging.myrvann.no user list --role=administrator --fields=ID,user_login

# Push a page
gp wp staging.myrvann.no pac push about.html --user=1

# Verify
gp wp staging.myrvann.no post list --post_type=page --meta_key=_pac_managed --fields=ID,post_title,post_name,post_status --user=1
```

### GridPane gotchas

- `gp wp <site>` requires the full domain as the first argument
- The `cli` subcommand is NOT used: `gp wp <site> pac push`, not `gp wp cli pac push`
- The `--user` flag goes at the end, after the pac arguments

## Template discovery

To check which templates exist in the active theme:

```bash
wp eval 'foreach(wp_get_theme()->get_page_templates() as $k=>$v) echo "$k => $v\n";'
```

If this returns nothing, the theme uses only the default template. Omit the `template` field.

## Debugging a failed push

1. Check the file exists: `ls wp-content/pages/<file>`
2. Validate front matter: `head -10 wp-content/pages/<file>` — must start with `---`
3. Check for title: `grep "^title:" wp-content/pages/<file>`
4. Check parent exists: `wp post list --post_type=page --name=<parent-slug>`
5. Check debug log: `tail -20 /var/www/<site>/logs/debug.log`
