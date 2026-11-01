# Uncode Builder Abilities

WordPress plugin by [caicesardev](https://caicesardev.com) that exposes the WPBakery Page Builder (and the Uncode theme on top of it) as [WordPress Abilities](https://developer.wordpress.org/apis/abilities/). Any MCP transport that serves abilities can hand them to an AI agent, so it composes pages with the real builder elements instead of pasting generic HTML.

## Requirements

- WordPress 6.9 or later (Abilities API in core).
- PHP 7.4 or later.
- WPBakery Page Builder. The Uncode layer switches on by itself when Uncode is active; on other WPBakery themes the generic abilities still work.
- An MCP transport that publishes abilities, such as Easy MCP AI or the WordPress MCP Adapter.

## Abilities

| Ability | Access | What it does |
|---|---|---|
| `uncode-builder/get-design-tokens` | read | Colour palette, font families, sizes, weights, line heights and letter spacings, with the value each one resolves to. |
| `uncode-builder/list-elements` | read | Elements registered in the builder, with nesting rules. |
| `uncode-builder/get-element-schema` | read | Parameters of one element, one group at a time (`group: "*"` for all). Compact by default: long option lists point to design tokens. |
| `uncode-builder/get-page-tree` | read | Layout of a post as a tree, with paths, stable ids, decoded values and the `version` that edits must send back. |
| `uncode-builder/list-content-blocks` | read | Uncode content blocks (only when the theme registers them). |
| `uncode-builder-edit/insert-elements` | edit | Insert elements with their children. |
| `uncode-builder-edit/update-element` | edit | Change parameters or content of one element. |
| `uncode-builder-edit/move-element` | edit | Move an element to another container. |
| `uncode-builder-edit/remove-element` | edit | Remove an element (`expect_tag` required). |
| `uncode-builder-edit/restore-snapshot` | edit | Undo the last change. |

Reads and edits use separate namespaces, so a transport with per-namespace scopes can grant read-only access.

### How edits stay safe

- Edits splice the original content by source range: everything that is not touched stays byte for byte.
- Every edit sends the `version` it read and is refused if the layout changed since.
- Values are validated against the live `vc_map` (parameter names, options, bounds, nesting) before anything is saved; `dry_run` previews the result.
- Unique ids (`uncode_shortcode_id`, `tab_id`) are generated, WPBakery's custom CSS is rebuilt and LiteSpeed is purged after saving.
- One snapshot per post is kept for `restore-snapshot`, without relying on WordPress revisions.
- **Settings → Writing → AI page builder edits** decides which items can be edited: only drafts and pending items (default) or anything the user can edit.

## Setup with Easy MCP AI

1. Install and activate this plugin.
2. In Easy MCP AI, enable the abilities you want to publish. Abilities added by a new version of this plugin must be enabled there too, or the transport does not publish them.
3. Connect the MCP client with OAuth and grant the `mcp:abilities:uncode_builder` scope (read) and, to edit, `mcp:abilities:uncode_builder_edit`.

## Extending

| Filter | Purpose |
|---|---|
| `uncode_builder_abilities_param_kinds` | Map custom parameter types to normalized kinds. |
| `uncode_builder_abilities_normalized_param` | Enrich a normalized parameter. |
| `uncode_builder_abilities_implicit_params` | Parameters a theme stores but does not declare in `vc_map`. |
| `uncode_builder_abilities_nesting_rules`, `uncode_builder_abilities_root_tags` | Nesting rules. |
| `uncode_builder_abilities_generate_id` | Generate ids for auto-id parameter types. |
| `uncode_builder_abilities_token_sources`, `uncode_builder_abilities_design_token` | Declare and resolve design tokens. |
| `uncode_builder_abilities_abilities` | Add or remove abilities. |
| `uncode_builder_abilities_snapshot_limit` | Snapshots kept per post (default 1). |
| `uncode_builder_abilities_write_allowed`, `uncode_builder_abilities_can_write_post` | Restrict which items can be edited. |
| `uncode_builder_abilities_catalog_capability` | Capability for the catalogue abilities (default `edit_posts`). |
| `uncode_builder_abilities_show_in_rest` | Expose an ability in the core REST API. |

## Tests

`tests/` holds WP-CLI suites (`wp eval-file tests/write-test.php`, `schema-test.php`, `policy-test.php`) and Python MCP probes. They work on a draft page whose id is set at the top of each file; never point them at published content.

## License

GPL-2.0-or-later.
