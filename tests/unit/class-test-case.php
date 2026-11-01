<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnit_Test_Case;
use Uncode_Builder_Abilities\Attribute_Decoder;
use Uncode_Builder_Abilities\Attribute_Encoder;
use Uncode_Builder_Abilities\Element_Catalog;
use Uncode_Builder_Abilities\Element_Composer;
use Uncode_Builder_Abilities\Nesting_Rules;
use Uncode_Builder_Abilities\Param_Normalizer;
use Uncode_Builder_Abilities\Shortcode_Parser;

abstract class Test_Case extends PHPUnit_Test_Case {

	const ATTACHMENT_IDS = array( 5, 6 );

	const SAFE_CSS_PROPERTIES = '/^(margin|padding|border|background|color)/';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		\WPBMap::use_fixture();

		Functions\stubs(
			array(
				'__'                   => static fn( $text ) => $text,
				'wp_strip_all_tags'    => static fn( $text ) => trim( strip_tags( (string) $text ) ),
				'wp_json_encode'       => static fn( $value, $flags = 0 ) => json_encode( $value, $flags ),
				'current_user_can'     => true,
				'esc_url_raw'          => static fn( $url ) => preg_match( '/^\s*(javascript|data|vbscript):/i', (string) $url ) ? '' : (string) $url,
				'wp_kses_post'         => static fn( $html ) => strip_tags( preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html ), '<p><a><strong><em><br>' ),
				'safecss_filter_attr'  => static fn( $css ) => preg_match( self::SAFE_CSS_PROPERTIES, (string) $css ) ? (string) $css : '',
				'get_post_type'        => static fn( $id ) => in_array( (int) $id, self::ATTACHMENT_IDS, true ) ? 'attachment' : false,
				'wp_generate_password' => static fn( $length ) => str_repeat( 'a', (int) $length ),
				'is_wp_error'          => static fn( $thing ) => $thing instanceof \WP_Error,
				'wp_parse_url'         => static fn( $url, $component = -1 ) => parse_url( $url, $component ),
				'sanitize_html_class'  => static fn( $class ) => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class ),
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	protected function catalog(): Element_Catalog {
		return new Element_Catalog( new Param_Normalizer() );
	}

	protected function parser( ?Element_Catalog $catalog = null ): Shortcode_Parser {
		return new Shortcode_Parser( $catalog ?? $this->catalog(), new Attribute_Decoder() );
	}

	protected function composer( ?Element_Catalog $catalog = null ): Element_Composer {
		$catalog = $catalog ?? $this->catalog();

		return new Element_Composer( $catalog, new Nesting_Rules( $catalog ), new Attribute_Encoder() );
	}

	protected function without_unfiltered_html(): void {
		Functions\when( 'current_user_can' )->alias( static fn( $capability ) => 'unfiltered_html' !== $capability );
	}
}
