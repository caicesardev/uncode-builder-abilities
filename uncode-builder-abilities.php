<?php
/**
 * Plugin Name:       Uncode Builder Abilities
 * Plugin URI:        https://caicesardev.com
 * Description:       Exposes the WPBakery / Uncode element catalogue and page layouts as WordPress Abilities (read and edit), ready to be served to AI agents by any MCP transport. Made by <a href="https://caicesardev.com">caicesardev</a>.
 * Version:           0.9.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            caicesardev
 * Author URI:        https://caicesardev.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uncode-builder-abilities
 */

defined( 'ABSPATH' ) || exit;

define( 'UNCODE_BUILDER_ABILITIES_VERSION', '0.9.1' );

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-builder-escaping.php';
require_once __DIR__ . '/includes/class-param-normalizer.php';
require_once __DIR__ . '/includes/class-element-catalog.php';
require_once __DIR__ . '/includes/class-design-tokens.php';
require_once __DIR__ . '/includes/class-schema-compactor.php';
require_once __DIR__ . '/includes/class-attribute-decoder.php';
require_once __DIR__ . '/includes/class-attribute-encoder.php';
require_once __DIR__ . '/includes/class-shortcode-parser.php';
require_once __DIR__ . '/includes/class-shortcode-serializer.php';
require_once __DIR__ . '/includes/class-layout-document.php';
require_once __DIR__ . '/includes/class-nesting-rules.php';
require_once __DIR__ . '/includes/class-element-composer.php';
require_once __DIR__ . '/includes/class-layout-operations.php';
require_once __DIR__ . '/includes/class-snapshot-store.php';
require_once __DIR__ . '/includes/class-layout-writer.php';
require_once __DIR__ . '/includes/class-write-policy.php';
require_once __DIR__ . '/includes/class-template-library.php';
require_once __DIR__ . '/includes/class-option-checker.php';
require_once __DIR__ . '/includes/class-draft-copies.php';
require_once __DIR__ . '/includes/class-page-options.php';
require_once __DIR__ . '/includes/class-uncode-page-options.php';
require_once __DIR__ . '/includes/class-layout-preview.php';
require_once __DIR__ . '/includes/class-uncode-integration.php';
require_once __DIR__ . '/includes/abilities/class-ability.php';
require_once __DIR__ . '/includes/abilities/class-write-ability.php';
require_once __DIR__ . '/includes/abilities/class-list-elements.php';
require_once __DIR__ . '/includes/abilities/class-get-element-schema.php';
require_once __DIR__ . '/includes/abilities/class-get-design-tokens.php';
require_once __DIR__ . '/includes/abilities/class-list-content-blocks.php';
require_once __DIR__ . '/includes/abilities/class-get-page-tree.php';
require_once __DIR__ . '/includes/abilities/class-list-templates.php';
require_once __DIR__ . '/includes/abilities/class-get-template.php';
require_once __DIR__ . '/includes/abilities/class-insert-elements.php';
require_once __DIR__ . '/includes/abilities/class-insert-template.php';
require_once __DIR__ . '/includes/abilities/class-update-element.php';
require_once __DIR__ . '/includes/abilities/class-move-element.php';
require_once __DIR__ . '/includes/abilities/class-remove-element.php';
require_once __DIR__ . '/includes/abilities/class-restore-snapshot.php';
require_once __DIR__ . '/includes/abilities/class-create-draft-copy.php';
require_once __DIR__ . '/includes/abilities/class-get-page-options.php';
require_once __DIR__ . '/includes/abilities/class-update-page-options.php';
require_once __DIR__ . '/includes/abilities/class-render-preview.php';

Uncode_Builder_Abilities\Plugin::boot();
