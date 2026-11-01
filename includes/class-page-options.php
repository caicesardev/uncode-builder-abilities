<?php

namespace Uncode_Builder_Abilities;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class Page_Options {

	const MEASUREMENT_UNITS = array( 'px', '%', 'em', 'rem', 'vh', 'vw' );

	const BACKGROUND_CHOICES = array(
		'background-repeat'     => array( '', 'no-repeat', 'repeat', 'repeat-x', 'repeat-y', 'inherit' ),
		'background-attachment' => array( '', 'scroll', 'fixed', 'inherit' ),
		'background-size'       => array( '', 'cover', 'contain', 'auto', 'inherit' ),
	);

	const BACKGROUND_KEYS = array( 'background-color', 'background-repeat', 'background-attachment', 'background-position', 'background-size', 'background-image' );

	const TOGGLE_VALUES = array( 'on', 'off' );

	const TRANSPARENT = 'transparent';

	private Design_Tokens $tokens;

	private ?array $definition = null;

	public function __construct( Design_Tokens $tokens ) {
		$this->tokens = $tokens;
	}

	public static function available(): bool {
		return (bool) apply_filters( 'uncode_builder_abilities_page_options_available', false );
	}

	public function sections(): array {
		$sections = array();
		foreach ( $this->fields() as $field ) {
			$sections[ $field['section'] ][] = $field['name'];
		}

		return $sections;
	}

	public function fields(): array {
		$this->definition ??= array_column( (array) apply_filters( 'uncode_builder_abilities_page_options', array(), $this->tokens ), null, 'name' );

		return $this->definition;
	}

	public function field( string $name ): ?array {
		return $this->fields()[ $name ] ?? null;
	}

	public function stored_values( int $post_id ): array {
		$values = array();
		foreach ( array_keys( $this->fields() ) as $name ) {
			if ( metadata_exists( 'post', $post_id, $name ) ) {
				$values[ $name ] = get_metadata( 'post', $post_id, $name, true );
			}
		}

		return $values;
	}

	public function validate( array $changes ): array {
		$meta   = array();
		$errors = array();

		foreach ( $changes as $name => $value ) {
			$name  = (string) $name;
			$field = $this->field( $name );
			if ( null === $field ) {
				$errors[] = array(
					'at'      => 'options.' . $name,
					'message' => sprintf( 'There is no page option "%s". Use get-page-options to see them.', $name ),
				);
				continue;
			}

			try {
				$meta[ $name ] = null === $value ? null : $this->value_for( $field, $value );
			} catch ( InvalidArgumentException $exception ) {
				$errors[] = array(
					'at'      => 'options.' . $name,
					'message' => $exception->getMessage(),
				);
			}
		}

		return array(
			'meta'   => $meta,
			'errors' => $errors,
		);
	}

	private function value_for( array $field, $value ) {
		switch ( $field['kind'] ) {
			case 'toggle':
				return self::toggle( $value );
			case 'number':
				return self::number( $field, $value );
			case 'measurement':
				return self::measurement( $value );
			case 'color':
				return $this->color( $field, $value );
			case 'background':
				return $this->background( $value );
			case 'text':
				return sanitize_text_field( self::scalar( $value ) );
			case 'text_multiline':
				$text = self::scalar( $value );
				return Attribute_Encoder::may_post_unfiltered() ? $text : wp_kses_post( $text );
			default:
				return self::choice( $field, $value );
		}
	}

	private static function choice( array $field, $value ): string {
		$value   = self::scalar( $value );
		$allowed = array_map( 'strval', array_column( $field['options'] ?? array(), 'value' ) );

		if ( '' === $value || in_array( $value, $allowed, true ) ) {
			return $value;
		}

		throw new InvalidArgumentException( sprintf( '"%s" is not one of the options of %s. Allowed: %s', $value, $field['name'], implode( ', ', array_slice( $allowed, 0, 25 ) ) ) );
	}

	private static function toggle( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'on' : 'off';
		}

		$value = self::scalar( $value );
		if ( ! in_array( $value, self::TOGGLE_VALUES, true ) ) {
			throw new InvalidArgumentException( 'Expected "on", "off", true or false.' );
		}

		return $value;
	}

	private static function number( array $field, $value ): string {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			throw new InvalidArgumentException( 'Expected a number.' );
		}

		$number = $value + 0;
		if ( ( isset( $field['min'] ) && $number < $field['min'] ) || ( isset( $field['max'] ) && $number > $field['max'] ) ) {
			throw new InvalidArgumentException( sprintf( 'Expected a number between %s and %s.', $field['min'] ?? '-∞', $field['max'] ?? '∞' ) );
		}

		return (string) $value;
	}

	private static function measurement( $value ): array {
		if ( is_array( $value ) && 2 === count( $value ) ) {
			list( $number, $unit ) = array_values( $value );
		} elseif ( is_string( $value ) && preg_match( '/^\s*(-?\d+(?:\.\d+)?)\s*([a-z%]*)\s*$/i', $value, $match ) ) {
			list( , $number, $unit ) = $match;
		} else {
			throw new InvalidArgumentException( 'Expected a measurement such as "1200px" or [1200, "px"].' );
		}

		if ( ! is_numeric( $number ) || ! in_array( (string) $unit, self::MEASUREMENT_UNITS, true ) ) {
			throw new InvalidArgumentException( sprintf( 'Expected a number and one of these units: %s.', implode( ', ', self::MEASUREMENT_UNITS ) ) );
		}

		return array( (string) $number, (string) $unit );
	}

	private function color( array $field, $value ): string {
		$value = self::scalar( $value );
		if ( '' === $value || ( ! empty( $field['allows_transparent'] ) && self::TRANSPARENT === $value ) || in_array( $value, $this->color_tokens(), true ) ) {
			return $value;
		}

		throw new InvalidArgumentException( sprintf( '"%s" is not a colour of the site palette. Use a value from get-design-tokens (colors).', $value ) );
	}

	private function background( $value ): array {
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'Expected an object with background-color, background-image, background-repeat, background-attachment, background-position and/or background-size.' );
		}

		$unknown = array_diff( array_keys( $value ), self::BACKGROUND_KEYS );
		if ( array() !== $unknown ) {
			throw new InvalidArgumentException( sprintf( 'Unknown background keys: %s.', implode( ', ', $unknown ) ) );
		}

		$background = array_fill_keys( self::BACKGROUND_KEYS, '' );
		foreach ( $value as $key => $part ) {
			$part = self::scalar( $part );
			if ( isset( self::BACKGROUND_CHOICES[ $key ] ) && ! in_array( $part, self::BACKGROUND_CHOICES[ $key ], true ) ) {
				throw new InvalidArgumentException( sprintf( '%s must be one of: %s.', $key, implode( ', ', array_filter( self::BACKGROUND_CHOICES[ $key ] ) ) ) );
			}
			$background[ $key ] = $this->background_part( $key, $part );
		}

		return $background;
	}

	private function background_part( string $key, string $part ): string {
		if ( '' === $part ) {
			return '';
		}

		if ( 'background-color' === $key && ! in_array( $part, $this->color_tokens(), true ) && ! preg_match( '/^#[0-9a-f]{3,8}$/i', $part ) ) {
			throw new InvalidArgumentException( 'background-color must be a palette colour from get-design-tokens or a hex colour.' );
		}

		if ( 'background-image' === $key ) {
			if ( ctype_digit( $part ) ) {
				if ( 'attachment' !== get_post_type( (int) $part ) ) {
					throw new InvalidArgumentException( sprintf( '"%s" is not an attachment ID from the media library.', $part ) );
				}
				return $part;
			}
			if ( '' === esc_url_raw( $part ) ) {
				throw new InvalidArgumentException( 'background-image must be an attachment ID or an http(s) image address.' );
			}
			return esc_url_raw( $part );
		}

		return sanitize_text_field( $part );
	}

	private function color_tokens(): array {
		return array_column( $this->tokens->sets()['colors'] ?? array(), 'value' );
	}

	private static function scalar( $value ): string {
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		throw new InvalidArgumentException( 'Expected a single value.' );
	}
}
