<?php

$root = dirname( __DIR__, 2 );

require_once $root . '/vendor/autoload.php';

foreach ( array( $root . '/vendor/wordpress', $root . '/vendor/roots/wordpress-no-content' ) as $wordpress ) {
	if ( is_readable( $wordpress . '/wp-includes/shortcodes.php' ) ) {
		define( 'ABSPATH', $wordpress . '/' );
		break;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "WordPress core was not installed by Composer.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-includes/compat.php';
require_once ABSPATH . 'wp-includes/shortcodes.php';
require_once ABSPATH . 'wp-includes/class-wp-error.php';
require_once ABSPATH . 'wp-includes/class-wp-post.php';

require_once __DIR__ . '/fixtures/class-wpbmap.php';
require_once __DIR__ . '/class-test-case.php';

foreach (
	array(
		'class-builder-escaping.php',
		'class-param-normalizer.php',
		'class-element-catalog.php',
		'class-design-tokens.php',
		'class-attribute-decoder.php',
		'class-attribute-encoder.php',
		'class-shortcode-parser.php',
		'class-shortcode-serializer.php',
		'class-layout-document.php',
		'class-device-visibility.php',
		'class-embedded-blocks.php',
		'class-draft-copies.php',
		'class-nesting-rules.php',
		'class-element-composer.php',
		'class-layout-operations.php',
		'class-layout-preview.php',
		'class-element-advisor.php',
		'abilities/class-ability.php',
		'abilities/class-find-elements.php',
		'class-uncode-integration.php',
	) as $file
) {
	require_once $root . '/includes/' . $file;
}
