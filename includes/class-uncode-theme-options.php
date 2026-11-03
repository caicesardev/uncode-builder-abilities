<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Uncode_Theme_Options {

	const SETTINGS_ID_FUNCTION = 'ot_settings_id';

	const OPTIONS_ID_FUNCTION = 'ot_options_id';

	const DEFAULT_SETTINGS_ID = 'uncode_settings';

	const DEFAULT_OPTIONS_ID = 'uncode';

	const OPTION_PREFIX = '_uncode_';

	const PALETTE_OPTION = '_uncode_custom_colors_list';

	const PALETTE_KINDS = array( 'color', 'background' );

	const EXTRA_KINDS = array(
		'upload'          => 'media',
		'list-item'       => 'list',
		'textarea-simple' => 'text_multiline',
		'js'              => 'code',
		'css'             => 'code',
		'radio-image'     => 'select',
		'number'          => 'number',
		'page_select'     => 'post',
	);

	public static function register(): void {
		add_filter( 'uncode_builder_abilities_theme_options_available', array( __CLASS__, 'is_available' ) );
		add_filter( 'uncode_builder_abilities_theme_options', array( __CLASS__, 'definition' ) );
		add_filter( 'uncode_builder_abilities_theme_option_values', array( __CLASS__, 'values' ) );
		add_filter( 'uncode_builder_abilities_theme_option_value_label', array( __CLASS__, 'value_label' ), 10, 3 );
	}

	public static function is_available( bool $available ): bool {
		return $available || function_exists( self::SETTINGS_ID_FUNCTION );
	}

	public static function definition( array $fields ): array {
		$settings = (array) get_option( self::option_id( self::SETTINGS_ID_FUNCTION, self::DEFAULT_SETTINGS_ID ), array() );
		$sections = self::sections( (array) ( $settings['sections'] ?? array() ) );
		$kinds    = array_merge( Uncode_Page_Options::KINDS, self::EXTRA_KINDS );

		foreach ( (array) ( $settings['settings'] ?? array() ) as $raw ) {
			$type = (string) ( $raw['type'] ?? '' );
			if ( empty( $raw['id'] ) || ! isset( $kinds[ $type ] ) ) {
				continue;
			}

			$section  = $sections[ (string) ( $raw['section'] ?? '' ) ] ?? array( 'name' => Uncode_Page_Options::DEFAULT_SECTION );
			$field          = Uncode_Page_Options::field( $raw, $section['name'], $kinds[ $type ] );
			$field['label'] = Param_Normalizer::plain_text_or( $field['label'], self::label_from_name( $field['name'] ) );
			$fields[]       = array_merge( $field, array_diff_key( $section, array( 'name' => true ) ) );
		}

		return $fields;
	}

	public static function values( array $values ): array {
		return array_merge( $values, (array) get_option( self::option_id( self::OPTIONS_ID_FUNCTION, self::DEFAULT_OPTIONS_ID ), array() ) );
	}

	public static function value_label( ?string $label, array $field, $value ): ?string {
		if ( null !== $label || ! in_array( $field['kind'], self::PALETTE_KINDS, true ) ) {
			return $label;
		}

		$color = is_array( $value ) ? ( $value['background-color'] ?? '' ) : $value;

		return is_scalar( $color ) ? self::palette()[ (string) $color ] ?? null : null;
	}

	private static function palette(): array {
		$palette = array();
		foreach ( (array) ( self::values( array() )[ self::PALETTE_OPTION ] ?? array() ) as $color ) {
			$id    = (string) ( $color['_uncode_custom_color_unique_id'] ?? '' );
			$title = Param_Normalizer::plain_text_or( $color['title'] ?? '', $id );
			$hex   = (string) ( $color['_uncode_custom_color'] ?? '' );

			$palette[ $id ] = '' === $hex || false !== stripos( $title, $hex ) ? $title : sprintf( '%s (%s)', $title, $hex );
		}

		return $palette;
	}

	private static function label_from_name( string $name ): string {
		return ucfirst( trim( str_replace( '_', ' ', 0 === strpos( $name, self::OPTION_PREFIX ) ? substr( $name, strlen( self::OPTION_PREFIX ) ) : $name ) ) );
	}

	private static function sections( array $raw_sections ): array {
		$sections = array();
		$group    = '';

		foreach ( $raw_sections as $raw ) {
			$id    = (string) ( $raw['id'] ?? '' );
			$group = Param_Normalizer::plain_text_or( $raw['group'] ?? '', $group );

			$sections[ $id ] = array_filter(
				array(
					'name'  => Param_Normalizer::plain_text_or( $raw['title'] ?? '', $id ),
					'group' => $group,
				),
				'strlen'
			);
		}

		return $sections;
	}

	private static function option_id( string $function_name, string $fallback ): string {
		return function_exists( $function_name ) ? (string) call_user_func( $function_name ) : $fallback;
	}
}
