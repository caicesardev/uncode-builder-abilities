=== Uncode Builder Abilities ===
Contributors: caicesardev
Tags: wpbakery, uncode, page builder, ai, mcp
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.12.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets AI agents read and compose WPBakery / Uncode layouts with the real page builder elements, through the WordPress Abilities API and any MCP transport.

== Description ==

Uncode Builder Abilities exposes the WPBakery Page Builder, and the Uncode theme on top of it, as WordPress Abilities. An MCP transport that publishes abilities (such as Easy MCP AI or the WordPress MCP Adapter) hands them to an AI agent, which can then build and change pages with the same elements, design tokens and templates a person uses in the editor, instead of pasting generic HTML.

**Read**

* The element catalogue, with nesting rules and each element's parameters (compact, one group at a time).
* The site design tokens: colour palette, font families, sizes, weights, line heights and letter spacings.
* Page layouts as trees with stable paths and ids.
* The template library (with Uncode, its 900+ wireframes, adapted to the site) and content blocks.
* Per-page theme settings (with Uncode: header, menu, width, content and footer blocks, sidebar, scroll).
* The global theme options, read-only (with Uncode: the Theme Options panel), with secrets redacted.
* A rendered preview of a page or section: a readable outline plus issues such as heading jumps, empty links or images without alt text.

**Edit**

* Insert, update, move and remove elements; insert templates; change per-page settings.
* Every change is validated against the live page builder (parameter names, options, bounds, nesting) and splices the original content, so everything else stays byte for byte.
* Every change sends the version it read, can be previewed with `dry_run` and can be undone with `restore-snapshot`.

**Safety**

* By default agents only change drafts, pending items and draft copies. Published pages are changed through a draft copy (a native WordPress autosave, or a Yoast Duplicate Post "Rewrite & Republish" copy) that a person reviews and applies.
* Links only accept relative paths and the protocols WordPress allows; design CSS refuses script constructs; users without `unfiltered_html` cannot add raw HTML or code.
* Reads and edits use separate ability namespaces, so transports with per-namespace permissions can grant read-only access.

== Installation ==

1. Upload the plugin and activate it. WordPress 6.9 or later (Abilities API) and WPBakery Page Builder are required; the Uncode layer switches on by itself when Uncode is active.
2. Review Settings → Uncode Builder Abilities.
3. In your MCP transport, enable the abilities listed on that page. Abilities added by later versions must be enabled there too.
4. Connect your AI client to the transport, ideally with OAuth and only the scopes it needs.

== Frequently Asked Questions ==

= Does it change the global theme options? =

No. It reads the global Uncode options to resolve design tokens, and only writes per-page settings of the item being edited.

= Can an agent publish changes on its own? =

Not in the default drafts-only mode. A person applies draft copies by restoring the autosave or clicking Republish.

= Does it need WordPress revisions? =

No. It keeps its own snapshots (one per item by default, configurable) for `restore-snapshot`.

== Changelog ==

= 0.12.1 =
* get-theme-options names what IDs point to: the title of content blocks, pages and media, and the name of palette colours (also inside backgrounds), in `value_label`.
* Theme options without a label are named after their id.

= 0.12.0 =
* New get-theme-options ability: reads the global theme options (with Uncode, the Theme Options panel) by section, search or changes from the defaults, with each value's label, default and whether it applies. Read-only, for users who can edit theme options, with secret-looking values redacted.

= 0.11.0 =
* New find-elements ability: search a layout by tag, attributes and text without reading the whole tree.
* get-page-tree and render-preview report `visible_on` for elements hidden on some devices, and heading checks run per device.
* get-page-tree can expand embedded content blocks (`expand_blocks`), read-only, with their own `post_id` and `version`.
* render-preview can return the rendered HTML (`include_html`), so a draft copy can be checked before it is republished.
* `copy_of` is now set on Rewrite & Republish copies too.
* The write abilities say in their description whether the site lets them change published items.

= 0.10.3 =
* Edits return `advice` about the elements they touched. With Uncode, buttons whose text cannot be read (white text on a light colour) or whose outline is ignored by the theme's button hover are reported.
* render-preview ignores scripts and styles, and no longer joins words across line breaks, blocks or Uncode heading lines.

= 0.10.2 =
* Inner rows (vc_row_inner) can be inserted inside columns again.
* The plugin description no longer repeats the author credit.

= 0.10.1 =
* Inserting at the top of a page (path "0") returns the new element's path again, and render-preview keeps a path "0".
* Integration suites take the test page from UBA_TEST_PAGE_ID; more unit tests for layout operations and previews.

= 0.10.0 =
* Settings page (Settings → Uncode Builder Abilities) with write policy, snapshots kept per item, draft copy strategy and a status overview.
* Spanish translation of the interface; agent-facing descriptions stay in English.
* readme.txt, unit tests and continuous integration.

= 0.9.1 =
* Template search understands common words (hero, cta, testimonials, team, faq, pricing…); page options can be searched.

= 0.9.0 =
* Per-page settings (read, update, undo) and rendered previews.

= 0.8.0 =
* Security hardening of links, design CSS and encoded values; limits per call; elements retired by the theme are hidden; clean uninstall.

= 0.7.0 =
* Draft copies of published items use native WordPress autosaves, or Yoast Duplicate Post when it is active.

= 0.5.0 =
* Template library abilities.

= 0.4.0 =
* Drafts-only write policy.

= 0.2.0 =
* Edit abilities with snapshots.

= 0.1.0 =
* Read-only abilities.
