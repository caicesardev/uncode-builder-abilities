# Uncode Builder Abilities

WordPress plugin that exposes the WPBakery Page Builder (and the Uncode theme on top of it) as [WordPress Abilities](https://developer.wordpress.org/apis/abilities/). Any MCP transport that serves abilities can hand them to an AI agent, so it composes pages with the real builder elements instead of pasting generic HTML.

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
| `uncode-builder/get-element-schema` | read | Index of every parameter by group, plus the details of one group (default "General") or of the parameters named in `params`. Compact: long option lists point to design tokens. |
| `uncode-builder/get-page-tree` | read | Layout of a post as a tree, with paths, stable ids, decoded values and the `version` that edits must send back. |
| `uncode-builder/list-content-blocks` | read | Uncode content blocks (only when the theme registers them). |
| `uncode-builder/list-templates` | read | Template library: WPBakery default templates (with Uncode, its 900 wireframes) and user-saved templates. Categories only unless `category` or `search` is sent. |
| `uncode-builder/get-template` | read | One template as a layout tree, to inspect it before inserting. |
| `uncode-builder/render-preview` | read | Renders an item or one element the way the site does and returns a readable outline (headings, texts, links, images), issues (heading jumps, empty links, images without alt), unrendered shortcodes and a preview URL. |
| `uncode-builder/get-page-options` | read | Per-page theme settings outside the layout (with Uncode: header, menu, width, content and footer blocks, sidebar, scroll): index by section, stored values and definitions. Never the global theme options. Only when the theme provides them. |
| `uncode-builder-edit/insert-elements` | edit | Insert elements with their children. |
| `uncode-builder-edit/insert-template` | edit | Insert a template as designed, with fresh unique ids; then adapt it with `update-element`. |
| `uncode-builder-edit/update-page-options` | edit | Change per-page settings, validated by kind (choices, toggles, measurements, palette colours, backgrounds); `null` inherits the theme default again. Kept in the snapshot, so `restore-snapshot` undoes it. Not available on autosave copies, which cannot hold settings. |
| `uncode-builder-edit/update-element` | edit | Change parameters or content of one element. |
| `uncode-builder-edit/move-element` | edit | Move an element to another container. |
| `uncode-builder-edit/remove-element` | edit | Remove an element (`expect_tag` required). |
| `uncode-builder-edit/restore-snapshot` | edit | Undo the last change. |
| `uncode-builder-edit/create-draft-copy` | edit | Draft copy of a published item to work on safely; the original is never modified. By default it is a native WordPress autosave (a person previews it and restores it from the editor). With Yoast Duplicate Post active it is a Rewrite & Republish copy (a person clicks Republish). The `uncode_builder_abilities_draft_copy_strategy` filter picks one. |

Reads and edits use separate namespaces, so a transport with per-namespace scopes can grant read-only access.

### How edits stay safe

- Edits splice the original content by source range: everything that is not touched stays byte for byte.
- Every edit sends the `version` it read and is refused if the layout changed since.
- Values are validated against the live `vc_map` (parameter names, options, bounds, nesting) before anything is saved; `dry_run` previews the result.
- Unique ids (`uncode_shortcode_id`, `tab_id`) are generated, WPBakery's custom CSS is rebuilt and LiteSpeed is purged after saving.
- One snapshot per post is kept for `restore-snapshot`, without relying on WordPress revisions.
- Links only accept relative paths and the protocols WordPress allows (no `javascript:` or `data:`); design CSS refuses `</style>`, `expression()`, `@import` and script URLs.
- Users without `unfiltered_html` cannot add raw HTML or code (also inside templates), their design CSS goes through `safecss_filter_attr` and encoded values such as parameter groups through `wp_kses_post`.
- One call creates at most 500 elements, nested up to 30 levels.
- Elements the theme retires from its editor (for Uncode, read from its own list) are hidden from `list-elements` and cannot be inserted; existing ones can still be edited.
- Uninstalling the plugin removes its setting and its post meta (snapshots), on every site of a network.
- **Settings → Uncode Builder Abilities** decides which items can be edited: only drafts and pending items (default) or anything the user can edit. In drafts-only mode, published pages are changed through `create-draft-copy`.

## Setup with Easy MCP AI

1. Install and activate this plugin.
2. In Easy MCP AI, enable the abilities you want to publish. Abilities added by a new version of this plugin must be enabled there too, or the transport does not publish them.
3. Disable Easy MCP AI's own page-builder tools (`wp_get_page_layout`, `wp_update_page_layout`, `wp_list_layout_elements`) so agents do not pick them over these abilities.
4. Connect the MCP client with OAuth and grant the `mcp:abilities:uncode_builder` scope (read) and, to edit, `mcp:abilities:uncode_builder_edit`.

## Extending

| Filter | Purpose |
|---|---|
| `uncode_builder_abilities_param_kinds` | Map custom parameter types to normalized kinds. |
| `uncode_builder_abilities_normalized_param` | Enrich a normalized parameter. |
| `uncode_builder_abilities_implicit_params` | Parameters a theme stores but does not declare in `vc_map`. |
| `uncode_builder_abilities_nesting_rules`, `uncode_builder_abilities_root_tags` | Nesting rules (`only`, `except`, and `structural` children the builder places itself). |
| `uncode_builder_abilities_element_advice` | Advice returned with every edit for the elements it touched, such as a button whose text cannot be read. |
| `uncode_builder_abilities_preview_line_classes` | Classes that render as separate lines, so render-preview keeps their words apart. |
| `uncode_builder_abilities_generate_id` | Generate ids for auto-id parameter types. |
| `uncode_builder_abilities_token_sources`, `uncode_builder_abilities_design_token` | Declare and resolve design tokens. |
| `uncode_builder_abilities_abilities` | Add or remove abilities. |
| `uncode_builder_abilities_snapshot_limit` | Snapshots kept per post (default 1). |
| `uncode_builder_abilities_write_allowed`, `uncode_builder_abilities_can_write_post` | Restrict which items can be edited. |
| `uncode_builder_abilities_catalog_capability` | Capability for the catalogue abilities (default `edit_posts`). |
| `uncode_builder_abilities_show_in_rest` | Expose an ability in the core REST API. |
| `uncode_builder_abilities_page_options_available`, `uncode_builder_abilities_page_options` | Provide per-page settings for another theme (normalized fields: name, label, section, kind, options, default, visible_when). |
| `uncode_builder_abilities_draft_copy_strategy` | `autosave` (native, default) or `rewrite_and_republish` (Yoast Duplicate Post) for create-draft-copy. |

## Development

```bash
composer install
composer test   # PHPUnit unit tests (no WordPress or WPBakery needed)
composer lint   # WordPress coding standards and PHP 7.4+ compatibility
```

- `tests/unit/` covers the logic that does not need a site: escaping and decoding, value validation and security rules, the shortcode parser and the element composer, against a small WPBakery map fixture.
- `tests/integration/` holds WP-CLI suites (`wp eval-file tests/integration/write-test.php`, and `schema-test.php`, `policy-test.php`, `template-test.php`, `draft-copy-test.php`, `security-test.php`, `page-options-test.php`, `search-test.php`, `settings-test.php`) and Python MCP probes, run against a real WPBakery / Uncode site. They work on the empty draft page given in `UBA_TEST_PAGE_ID` (`UBA_TEST_PAGE_ID=123 wp eval-file …`) and refuse to run on anything else; the MCP probes also read the endpoint from `UBA_MCP_ENDPOINT`. Never point them at published content.
- GitHub Actions lints every file on PHP 7.4–8.4, runs PHPCS and the unit tests, and builds the release ZIP (without development files) when a `v*` tag is pushed.
- Translations live in `languages/`. Agent-facing ability descriptions stay in English; the interface is translated (Spanish included).

## License

GPL-2.0-or-later. The full text is in [LICENSE](LICENSE).
