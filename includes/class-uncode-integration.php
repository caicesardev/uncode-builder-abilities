<?php

namespace Uncode_Builder_Abilities;

use Uncode_Builder_Abilities\Abilities\List_Content_Blocks;

defined( 'ABSPATH' ) || exit;

final class Uncode_Integration {

	const PARAM_KINDS = array(
		'uncode_shortcode_id'            => 'auto_id',
		'type_numeric_slider'            => 'number',
		'uncode_numeric_textfield'       => 'number',
		'uncode_advanced_color_selector' => 'color_mode',
		'uncode_colorpicker'             => 'color',
		'uncode_colorpicker_alt'         => 'color',
		'uncode_gradientpicker'          => 'gradient',
		'media_element'                  => 'attachment_ids',
		'uncode_radio_image'             => 'select',
		'uncode_inner_tabs'              => 'ui',
	);

	const COLOR_MODE_SELECTOR = 'uncode_advanced_color_selector';

	const DEFAULT_COLOR_MODE = 'uncode-palette';

	const SHORTCODE_ID_TYPE = 'uncode_shortcode_id';

	const SHORTCODE_ID_MIN = 100000;

	const SHORTCODE_ID_MAX = 999999;

	const THEME_OPTIONS = 'uncode';

	const TOKEN_SOURCE_TAG = 'vc_custom_heading';

	const TOKEN_SOURCES = array(
		'colors'          => 'text_color',
		'font_families'   => 'text_font',
		'font_sizes'      => 'text_size',
		'font_weights'    => 'text_weight',
		'line_heights'    => 'text_height',
		'letter_spacings' => 'text_space',
	);

	const TOKEN_LISTS = array(
		'colors'          => array( '_uncode_custom_colors_list', '_uncode_custom_color_unique_id' ),
		'font_families'   => array( '_uncode_font_groups', '_uncode_font_group_unique_id' ),
		'font_sizes'      => array( '_uncode_heading_font_sizes', '_uncode_heading_font_size_unique_id' ),
		'line_heights'    => array( '_uncode_heading_font_heights', '_uncode_heading_font_height_unique_id' ),
		'letter_spacings' => array( '_uncode_heading_font_spacings', '_uncode_heading_font_spacing_unique_id' ),
	);

	const CONTENT_BLOCK_POST_TYPE = 'uncodeblock';

	private static ?array $resolved_tokens = null;

	public static function register(): void {
		add_filter( 'uncode_builder_abilities_param_kinds', array( __CLASS__, 'add_param_kinds' ) );
		add_filter( 'uncode_builder_abilities_normalized_param', array( __CLASS__, 'enrich_param' ), 10, 4 );
		add_filter( 'uncode_builder_abilities_generate_id', array( __CLASS__, 'generate_shortcode_id' ), 10, 3 );
		add_filter( 'uncode_builder_abilities_token_sources', array( __CLASS__, 'add_token_sources' ) );
		add_filter( 'uncode_builder_abilities_design_token', array( __CLASS__, 'resolve_token' ), 10, 2 );
		add_filter( 'uncode_builder_abilities_abilities', array( __CLASS__, 'add_abilities' ) );
	}

	public static function add_token_sources( array $sources ): array {
		foreach ( self::TOKEN_SOURCES as $name => $param ) {
			$sources[ $name ] ??= array(
				'tag'   => self::TOKEN_SOURCE_TAG,
				'param' => $param,
			);
		}

		return $sources;
	}

	public static function resolve_token( array $token, string $set ): array {
		$resolved = self::resolved_tokens()[ $set ][ $token['value'] ] ?? self::hex_in_label( $token['label'] );
		if ( '' !== (string) $resolved ) {
			$token['resolves_to'] = $resolved;
		}

		return $token;
	}

	public static function add_abilities( array $abilities ): array {
		if ( post_type_exists( self::CONTENT_BLOCK_POST_TYPE ) ) {
			$abilities[] = new List_Content_Blocks();
		}

		return $abilities;
	}

	private static function resolved_tokens(): array {
		if ( null !== self::$resolved_tokens ) {
			return self::$resolved_tokens;
		}

		$options               = (array) get_option( self::THEME_OPTIONS, array() );
		self::$resolved_tokens = array();
		foreach ( self::TOKEN_LISTS as $set => list( $list_key, $id_key ) ) {
			foreach ( (array) ( $options[ $list_key ] ?? array() ) as $item ) {
				if ( is_array( $item ) && ! empty( $item[ $id_key ] ) ) {
					self::$resolved_tokens[ $set ][ $item[ $id_key ] ] = self::token_value( $set, $item );
				}
			}
		}

		return self::$resolved_tokens;
	}

	private static function token_value( string $set, array $item ): string {
		switch ( $set ) {
			case 'colors':
				return 'on' === ( $item['_uncode_custom_color_regular'] ?? 'on' ) ? (string) ( $item['_uncode_custom_color'] ?? '' ) : (string) ( $item['_uncode_custom_color_gradient'] ?? '' );
			case 'font_families':
				return 'manual' === ( $item['_uncode_font_group'] ?? '' ) ? (string) ( $item['_uncode_font_manual'] ?? '' ) : str_replace( '+', ' ', (string) ( $item['_uncode_font_group'] ?? '' ) );
			case 'font_sizes':
				return isset( $item['_uncode_heading_font_size'] ) ? $item['_uncode_heading_font_size'] . 'px' : '';
			case 'line_heights':
				return (string) ( $item['_uncode_heading_font_height'] ?? '' );
			default:
				return (string) ( $item['_uncode_heading_font_spacing'] ?? '' );
		}
	}

	private static function hex_in_label( string $label ): string {
		return preg_match( '/#[0-9a-f]{3,8}\b/i', $label, $match ) ? $match[0] : '';
	}

	public static function generate_shortcode_id( string $id, string $type, array $used_ids ): string {
		if ( self::SHORTCODE_ID_TYPE !== $type ) {
			return $id;
		}

		do {
			$candidate = (string) wp_rand( self::SHORTCODE_ID_MIN, self::SHORTCODE_ID_MAX );
		} while ( in_array( $candidate, $used_ids, true ) );

		return $candidate;
	}

	public static function add_param_kinds( array $kinds ): array {
		return array_merge( $kinds, self::PARAM_KINDS );
	}

	public static function enrich_param( array $normalized, array $param, int $index, array $params ): array {
		$type = $param['type'] ?? '';

		if ( 'uncode_radio_image' === $type ) {
			$normalized['options'] = self::radio_image_options( $param['options'] ?? array() );
		}

		if ( self::COLOR_MODE_SELECTOR === $type ) {
			$normalized['options'] = self::color_mode_options( $param['supported'] ?? array() );
			$normalized['default'] = self::DEFAULT_COLOR_MODE;
		}

		$color_mode = $param['advanced-color-type'] ?? null;
		if ( is_string( $color_mode ) ) {
			$selector = self::preceding_color_selector( $index, $params );
			if ( null !== $selector ) {
				$normalized['active_when'] = array(
					'param' => $selector,
					'in'    => self::DEFAULT_COLOR_MODE === $color_mode ? array( '', $color_mode ) : array( $color_mode ),
				);
			}
		}

		return $normalized;
	}

	private static function radio_image_options( array $options ): array {
		$normalized = array();
		foreach ( $options as $value => $labelled_image ) {
			$label        = is_array( $labelled_image ) ? (string) array_key_first( $labelled_image ) : (string) $value;
			$normalized[] = Param_Normalizer::option( $value, $label );
		}

		return $normalized;
	}

	private static function color_mode_options( array $supported ): array {
		return array_map(
			static fn( $mode ) => Param_Normalizer::option( 'uncode-' . $mode, ucfirst( (string) $mode ) ),
			array_values( $supported )
		);
	}

	private static function preceding_color_selector( int $index, array $params ): ?string {
		$params = array_values( $params );
		for ( $position = $index - 1; $position >= 0; $position-- ) {
			if ( self::COLOR_MODE_SELECTOR === ( $params[ $position ]['type'] ?? '' ) ) {
				return (string) $params[ $position ]['param_name'];
			}
		}

		return null;
	}
}
