<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Theme_Options {

	const POST_KINDS = array( 'post', 'media' );

	const SECRET_PATTERN = '/(^|_)(api|apikey|key|token|secret|password|license|licence|purchase)(_|$)/i';

	const REDACTED = '[redacted]';

	const MAX_TEXT_LENGTH = 2000;

	const MAX_OPTIONS = 40;

	private ?array $definition = null;

	private ?array $stored = null;

	public static function available(): bool {
		return (bool) apply_filters( 'uncode_builder_abilities_theme_options_available', false );
	}

	public function fields(): array {
		$this->definition ??= array_column( (array) apply_filters( 'uncode_builder_abilities_theme_options', array() ), null, 'name' );

		return $this->definition;
	}

	public function field( string $name ): ?array {
		return $this->fields()[ $name ] ?? null;
	}

	public function sections(): array {
		$sections = array();
		foreach ( $this->fields() as $field ) {
			$name = $field['section'];
			if ( ! isset( $sections[ $name ] ) ) {
				$sections[ $name ] = array_filter(
					array(
						'name'   => $name,
						'group'  => $field['group'] ?? null,
						'fields' => 0,
					),
					static fn( $value ) => null !== $value
				);
			}
			++$sections[ $name ]['fields'];
		}

		return array_values( $sections );
	}

	public function described( array $field ): array {
		$value = $this->value_of( $field['name'] );

		$described = array_merge(
			array_diff_key( $field, array( 'options' => true ) ),
			array(
				'value'      => $value,
				'is_default' => $this->is_default( $field ),
				'applies'    => $this->applies( $field ),
			)
		);

		if ( $this->is_secret( $field ) ) {
			return array_merge(
				$described,
				array(
					'value'    => self::REDACTED,
					'redacted' => true,
					'is_set'   => ! self::is_empty( $value ),
				)
			);
		}

		return array_merge( $described, self::value_details( $field, $value ) );
	}

	public function is_default( array $field ): bool {
		$stored = $this->stored();
		if ( ! array_key_exists( $field['name'], $stored ) ) {
			return true;
		}

		$value   = $stored[ $field['name'] ];
		$default = $field['default'] ?? '';
		if ( self::is_empty( $value ) && self::is_empty( $default ) ) {
			return true;
		}

		return is_scalar( $value ) && is_scalar( $default ) ? (string) $value === (string) $default : $value === $default;
	}

	private static function is_empty( $value ): bool {
		return null === $value || '' === $value || array() === $value;
	}

	public function applies( array $field, array $visiting = array() ): bool {
		$when = $field['visible_when'] ?? null;
		if ( null === $when || in_array( $field['name'], $visiting, true ) ) {
			return true;
		}

		$visiting[] = $field['name'];
		$results    = array_map( fn( array $rule ) => $this->rule_met( $rule, $visiting ), $when['rules'] );

		return 'any' === $when['match'] ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	private function rule_met( array $rule, array $visiting ): bool {
		$controller = $this->field( $rule['param'] );
		if ( null !== $controller && ! $this->applies( $controller, $visiting ) ) {
			return false;
		}

		return self::compare( $this->value_of( $rule['param'] ), $rule['op'], (string) $rule['value'] );
	}

	private static function compare( $value, string $operator, string $expected ): bool {
		$value = is_array( $value ) ? implode( ',', array_map( 'strval', $value ) ) : (string) $value;

		switch ( $operator ) {
			case 'is':
				return $value === $expected;
			case 'not':
				return $value !== $expected;
			case 'contains':
				return false !== strpos( $value, $expected );
			case 'less_than':
				return (float) $value < (float) $expected;
			case 'less_than_or_equal_to':
				return (float) $value <= (float) $expected;
			case 'greater_than':
				return (float) $value > (float) $expected;
			case 'greater_than_or_equal_to':
				return (float) $value >= (float) $expected;
			default:
				return true;
		}
	}

	private function value_of( string $name ) {
		return $this->stored()[ $name ] ?? $this->field( $name )['default'] ?? '';
	}

	private function stored(): array {
		$this->stored ??= (array) apply_filters( 'uncode_builder_abilities_theme_option_values', array() );

		return $this->stored;
	}

	private function is_secret( array $field ): bool {
		return (bool) apply_filters( 'uncode_builder_abilities_theme_option_is_secret', 1 === preg_match( self::SECRET_PATTERN, $field['name'] ), $field );
	}

	private static function choice_label( array $options, $value ): ?string {
		return is_scalar( $value ) ? array_column( $options, 'label', 'value' )[ (string) $value ] ?? null : null;
	}

	private static function post_title( array $field, $value ): ?string {
		if ( ! in_array( $field['kind'], self::POST_KINDS, true ) || ! is_numeric( $value ) || (int) $value <= 0 ) {
			return null;
		}

		$post = get_post( (int) $value );

		return $post instanceof WP_Post ? Param_Normalizer::plain_text_or( $post->post_title, sprintf( '#%d', $post->ID ) ) : null;
	}

	private static function value_details( array $field, $value ): array {
		$details = array();
		$options = $field['options'] ?? array();

		$label = apply_filters( 'uncode_builder_abilities_theme_option_value_label', self::choice_label( $options, $value ) ?? self::post_title( $field, $value ), $field, $value );
		if ( null !== $label && '' !== $label ) {
			$details['value_label'] = (string) $label;
		}

		if ( is_string( $value ) && mb_strlen( $value ) > self::MAX_TEXT_LENGTH ) {
			$details['value']           = mb_substr( $value, 0, self::MAX_TEXT_LENGTH );
			$details['value_truncated'] = true;
		}

		if ( array() !== $options ) {
			$details['options'] = array_slice( $options, 0, self::MAX_OPTIONS );
			if ( count( $options ) > self::MAX_OPTIONS ) {
				$details['more_options'] = count( $options ) - self::MAX_OPTIONS;
			}
		}

		return $details;
	}
}
