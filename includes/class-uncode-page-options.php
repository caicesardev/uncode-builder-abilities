<?php

namespace Uncode_Builder_Abilities;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class Uncode_Page_Options {

	const BUILDER_FUNCTION = 'uncode_page_options';

	const DEFINITION_FILTER = 'uncode_core_page_options_fields';

	const META_BOX_ID = '_uncode_page_options';

	const SECTION_TYPE = 'tab';

	const DEFAULT_SECTION = 'General';

	const KINDS = array(
		'select'                  => 'select',
		'on-off'                  => 'toggle',
		'numeric-slider'          => 'number',
		'measurement'             => 'measurement',
		'background'              => 'background',
		'uncode_color'            => 'color',
		'uncode_colors_w_transp'  => 'color',
		'text'                    => 'text',
		'textarea'                => 'text_multiline',
		'custom-post-type-select' => 'post',
		'page-select'             => 'post',
		'menu-select'             => 'menu',
		'sidebar-select'          => 'sidebar',
	);

	const CONDITION_PATTERN = '/^\s*([\w-]+)\s*:\s*(is|not|contains|less_than|less_than_or_equal_to|greater_than|greater_than_or_equal_to)\s*\(\s*(.*?)\s*\)\s*$/';

	public static function register(): void {
		add_filter( 'uncode_builder_abilities_page_options_available', array( __CLASS__, 'is_available' ) );
		add_filter( 'uncode_builder_abilities_page_options', array( __CLASS__, 'definition' ) );
	}

	public static function is_available( bool $available ): bool {
		return $available || function_exists( self::BUILDER_FUNCTION );
	}

	public static function definition( array $fields ): array {
		$section = self::DEFAULT_SECTION;
		foreach ( self::raw_fields() as $raw ) {
			$type = (string) ( $raw['type'] ?? '' );
			if ( self::SECTION_TYPE === $type ) {
				$section = Param_Normalizer::plain_text( $raw['label'] ?? '' ) ?: $section;
				continue;
			}
			if ( empty( $raw['id'] ) || ! isset( self::KINDS[ $type ] ) ) {
				continue;
			}
			$fields[] = self::normalized( $raw, $section );
		}

		return $fields;
	}

	private static function raw_fields(): array {
		global $ot_meta_boxes;

		foreach ( (array) $ot_meta_boxes as $meta_box ) {
			if ( self::META_BOX_ID === ( $meta_box['id'] ?? '' ) ) {
				return (array) $meta_box['fields'];
			}
		}

		if ( ! function_exists( self::BUILDER_FUNCTION ) ) {
			return array();
		}

		return self::captured_definition();
	}

	private static function captured_definition(): array {
		$captured = array();
		// Stops uncode_page_options() right after its definition filter: outside wp-admin the next step, ot_register_meta_box(), does not exist.
		$capture = static function ( $meta_box ) use ( &$captured ) {
			$captured = (array) ( $meta_box['fields'] ?? array() );
			throw new RuntimeException( self::DEFINITION_FILTER );
		};

		add_filter( self::DEFINITION_FILTER, $capture, PHP_INT_MAX );
		try {
			call_user_func( self::BUILDER_FUNCTION );
		} catch ( RuntimeException $stop ) {
			if ( self::DEFINITION_FILTER !== $stop->getMessage() ) {
				throw $stop;
			}
		} finally {
			remove_filter( self::DEFINITION_FILTER, $capture, PHP_INT_MAX );
		}

		return $captured;
	}

	private static function normalized( array $raw, string $section ): array {
		$type  = (string) $raw['type'];
		$field = array(
			'name'    => (string) $raw['id'],
			'label'   => Param_Normalizer::plain_text( $raw['label'] ?? '' ),
			'section' => $section,
			'kind'    => self::KINDS[ $type ],
		);

		$options = self::options( $type, $raw );
		if ( null !== $options ) {
			$field['options'] = $options;
		}
		if ( 'uncode_colors_w_transp' === $type ) {
			$field['allows_transparent'] = true;
		}
		if ( isset( $raw['std'] ) && '' !== $raw['std'] ) {
			$field['default'] = $raw['std'];
		}
		if ( ! empty( $raw['post_type'] ) ) {
			$field['post_type'] = (string) $raw['post_type'];
		}
		if ( 'numeric-slider' === $type && ! empty( $raw['min_max_step'] ) ) {
			list( $field['min'], $field['max'], $field['step'] ) = array_map( 'floatval', array_pad( explode( ',', (string) $raw['min_max_step'] ), 3, 1 ) );
		}

		$visible_when = self::visibility( (string) ( $raw['condition'] ?? '' ), (string) ( $raw['operator'] ?? 'and' ) );
		if ( null !== $visible_when ) {
			$field['visible_when'] = $visible_when;
		}

		return $field;
	}

	private static function options( string $type, array $raw ): ?array {
		switch ( $type ) {
			case 'on-off':
				return array( Param_Normalizer::option( 'on', 'On' ), Param_Normalizer::option( 'off', 'Off' ) );
			case 'menu-select':
				return array_map( static fn( $menu ) => Param_Normalizer::option( $menu->term_id, $menu->name ), wp_get_nav_menus() );
			case 'sidebar-select':
				return array_values( array_map( static fn( $sidebar ) => Param_Normalizer::option( $sidebar['id'], $sidebar['name'] ), (array) ( $GLOBALS['wp_registered_sidebars'] ?? array() ) ) );
			case 'select':
			case 'custom-post-type-select':
			case 'page-select':
				return array_map( static fn( $choice ) => Param_Normalizer::option( $choice['value'] ?? '', $choice['label'] ?? '' ), (array) ( $raw['choices'] ?? array() ) );
			default:
				return null;
		}
	}

	private static function visibility( string $condition, string $operator ): ?array {
		$rules = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $condition ) ) ) as $rule ) {
			if ( preg_match( self::CONDITION_PATTERN, $rule, $match ) ) {
				$rules[] = array(
					'param' => $match[1],
					'op'    => $match[2],
					'value' => $match[3],
				);
			}
		}

		if ( array() === $rules ) {
			return null;
		}

		return array(
			'match' => 'or' === strtolower( $operator ) ? 'any' : 'all',
			'rules' => $rules,
		);
	}
}
