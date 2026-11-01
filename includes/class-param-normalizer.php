<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Param_Normalizer {

	const UNKNOWN_KIND = 'unknown';

	const SELECT_KINDS = array( 'select', 'checkbox' );

	const CORE_KINDS = array(
		'textfield'              => 'text',
		'textarea'               => 'text',
		'textarea_safe'          => 'text',
		'el_id'                  => 'html_id',
		'textarea_html'          => 'html',
		'gutenberg'              => 'html',
		'textarea_raw_html'      => 'encoded_html',
		'textarea_ace'           => 'encoded_code',
		'exploded_textarea'      => 'lines',
		'exploded_textarea_safe' => 'lines',
		'dropdown'               => 'select',
		'button_group'           => 'select',
		'animation_style'        => 'select',
		'checkbox'               => 'checkbox',
		'number'                 => 'number',
		'colorpicker'            => 'color',
		'iconpicker'             => 'icon',
		'attach_image'           => 'attachment_id',
		'attach_images'          => 'attachment_ids',
		'vc_link'                => 'link',
		'href'                   => 'url',
		'css_editor'             => 'design_css',
		'param_group'            => 'group',
		'font_container'         => 'font',
		'google_fonts'           => 'font',
		'loop'                   => 'query',
		'autocomplete'           => 'autocomplete',
		'posttypes'              => 'post_types',
		'sorted_list'            => 'list',
		'tab_id'                 => 'auto_id',
		'vc_grid_id'             => 'auto_id',
		'hidden'                 => 'hidden',
		'custom_markup'          => 'ui',
	);

	private ?array $kinds = null;

	public function normalize_all( array $params, bool $include_descriptions ): array {
		$normalized = array();

		foreach ( array_values( $params ) as $index => $param ) {
			if ( empty( $param['param_name'] ) ) {
				continue;
			}
			$normalized[] = $this->normalize( $param, $index, $params, $include_descriptions );
		}

		return $normalized;
	}

	public function kind_of( string $type ): string {
		$this->kinds ??= (array) apply_filters( 'uncode_builder_abilities_param_kinds', self::CORE_KINDS );

		return $this->kinds[ $type ] ?? self::UNKNOWN_KIND;
	}

	public static function plain_text( $value ): string {
		return trim( html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' ) );
	}

	public static function options_from_value( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$options = array();
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) && array_key_exists( 0, $item ) ) {
				$options[] = self::option( $item[0], $item[1] ?? $item[0] );
			} elseif ( is_string( $key ) && ! is_array( $item ) ) {
				$options[] = self::option( $item, $key );
			} elseif ( ! is_array( $item ) ) {
				$options[] = self::option( $item, $item );
			}
		}

		return $options;
	}

	public static function option( $value, $label ): array {
		return array(
			'value' => (string) $value,
			'label' => self::plain_text( $label ),
		);
	}

	private function normalize( array $param, int $index, array $params, bool $include_descriptions ): array {
		$type = (string) ( $param['type'] ?? '' );
		$kind = $this->kind_of( $type );

		$normalized = array(
			'name'  => (string) $param['param_name'],
			'type'  => $type,
			'kind'  => $kind,
			'label' => self::plain_text( $param['heading'] ?? '' ),
			'group' => self::plain_text( $param['group'] ?? '' ) ?: 'General',
		);

		$default = $this->default_of( $param, $kind );
		if ( null !== $default ) {
			$normalized['default'] = $default;
		}

		if ( in_array( $kind, self::SELECT_KINDS, true ) ) {
			$normalized['options'] = self::options_from_value( $param['value'] ?? array() );
		}

		foreach ( array( 'min', 'max', 'step' ) as $bound ) {
			if ( isset( $param[ $bound ] ) && is_numeric( $param[ $bound ] ) ) {
				$normalized[ $bound ] = $param[ $bound ] + 0;
			}
		}

		$dependency = self::dependency_of( $param );
		if ( null !== $dependency ) {
			$normalized['visible_when'] = $dependency;
		}

		if ( $include_descriptions && ! empty( $param['description'] ) ) {
			$normalized['description'] = self::plain_text( $param['description'] );
		}

		return (array) apply_filters( 'uncode_builder_abilities_normalized_param', $normalized, $param, $index, $params );
	}

	private function default_of( array $param, string $kind ) {
		if ( isset( $param['std'] ) && ! is_array( $param['std'] ) ) {
			return (string) $param['std'];
		}

		$value = $param['value'] ?? null;
		if ( in_array( $kind, self::SELECT_KINDS, true ) || is_array( $value ) || null === $value || '' === $value ) {
			return null;
		}

		return (string) $value;
	}

	private static function dependency_of( array $param ): ?array {
		$dependency = $param['dependency'] ?? null;
		if ( ! is_array( $dependency ) || empty( $dependency['element'] ) ) {
			return null;
		}

		$rule = array( 'param' => (string) $dependency['element'] );

		if ( isset( $dependency['value'] ) ) {
			$rule['in'] = self::string_list( $dependency['value'] );
		}
		if ( isset( $dependency['value_not_equal_to'] ) ) {
			$rule['not_in'] = self::string_list( $dependency['value_not_equal_to'] );
		}
		if ( ! empty( $dependency['not_empty'] ) ) {
			$rule['not_empty'] = true;
		}
		if ( ! empty( $dependency['is_empty'] ) ) {
			$rule['empty'] = true;
		}

		return $rule;
	}

	private static function string_list( $values ): array {
		return array_values(
			array_map(
				static fn( $value ) => is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value,
				(array) $values
			)
		);
	}
}
