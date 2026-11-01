<?php

namespace Uncode_Builder_Abilities;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class Attribute_Encoder {

	const ENCODED_CONTENT_TYPES = array( 'textarea_raw_html', 'textarea_ace' );

	const DESIGN_CSS_CLASS_PREFIX = 'vc_custom_';

	const MAX_LISTED_OPTIONS = 20;

	private static int $design_css_sequence = 0;

	public function encode( array $param, $value ): string {
		$plain = $this->plain_value( $param, $value );

		if ( false !== strpos( $plain, '\\' ) ) {
			throw new InvalidArgumentException( 'WordPress removes backslashes from shortcode attributes when it reads the page; send the value without them.' );
		}

		return Builder_Escaping::escape( $plain );
	}

	public function encode_content( ?string $type, $content ): string {
		if ( ! is_string( $content ) ) {
			throw new InvalidArgumentException( '`content` must be a string.' );
		}

		return in_array( $type, self::ENCODED_CONTENT_TYPES, true ) ? base64_encode( rawurlencode( $content ) ) : $content;
	}

	private function plain_value( array $param, $value ): string {
		switch ( $param['kind'] ) {
			case 'select':
				return self::select_value( $param, $value );
			case 'checkbox':
				return self::checkbox_value( $param, $value );
			case 'number':
				return self::number_value( $param, $value );
			case 'fraction':
				return self::fraction_value( $value );
			case 'link':
				return is_array( $value ) ? self::link_value( $value ) : self::text_value( $value );
			case 'gradient':
				return is_array( $value ) ? self::json( $value ) : self::text_value( $value );
			case 'group':
				return is_array( $value ) ? rawurlencode( self::json( $value ) ) : self::text_value( $value );
			case 'encoded_html':
			case 'encoded_code':
				return base64_encode( rawurlencode( self::text_value( $value ) ) );
			case 'design_css':
				return is_array( $value ) ? self::design_css_value( $value ) : self::design_css_text( self::text_value( $value ) );
			case 'attachment_id':
				return self::attachment_ids( array( $value ), 1 );
			case 'attachment_ids':
				return self::attachment_ids( is_array( $value ) ? $value : explode( ',', self::text_value( $value ) ), PHP_INT_MAX );
			default:
				return self::text_value( $value );
		}
	}

	private static function select_value( array $param, $value ): string {
		$value   = self::text_value( $value );
		$allowed = array_column( $param['options'] ?? array(), 'value' );

		if ( '' === $value || array() === $allowed || in_array( $value, $allowed, true ) ) {
			return $value;
		}

		throw new InvalidArgumentException( sprintf( '"%s" is not one of the options. Allowed: %s', $value, self::list_options( $allowed ) ) );
	}

	private static function checkbox_value( array $param, $value ): string {
		$allowed = array_column( $param['options'] ?? array(), 'value' );

		if ( true === $value ) {
			return (string) ( $allowed[0] ?? 'yes' );
		}
		if ( false === $value ) {
			return '';
		}

		$values = array_filter( array_map( 'trim', explode( ',', self::text_value( $value ) ) ), 'strlen' );
		foreach ( $values as $item ) {
			if ( array() !== $allowed && ! in_array( $item, $allowed, true ) ) {
				throw new InvalidArgumentException( sprintf( '"%s" is not one of the options. Allowed: %s (or true / false)', $item, self::list_options( $allowed ) ) );
			}
		}

		return implode( ',', $values );
	}

	private static function number_value( array $param, $value ): string {
		if ( '' === $value ) {
			return '';
		}
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			throw new InvalidArgumentException( 'Expected a number.' );
		}

		$number = $value + 0;
		if ( isset( $param['min'] ) && $number < $param['min'] ) {
			throw new InvalidArgumentException( sprintf( 'The minimum is %s.', $param['min'] ) );
		}
		if ( isset( $param['max'] ) && $number > $param['max'] ) {
			throw new InvalidArgumentException( sprintf( 'The maximum is %s.', $param['max'] ) );
		}

		return (string) $value;
	}

	private static function fraction_value( $value ): string {
		$value = self::text_value( $value );
		if ( ! preg_match( '/^([1-9]\d?)\/([1-9]\d?)$/', $value, $match ) || (int) $match[1] > (int) $match[2] ) {
			throw new InvalidArgumentException( 'Expected a fraction of the row such as "1/1", "1/2", "2/3" or "5/12".' );
		}

		return $value;
	}

	private static function link_value( array $link ): string {
		$parts = array();
		foreach ( Attribute_Decoder::LINK_PARTS as $key ) {
			if ( isset( $link[ $key ] ) && '' !== (string) $link[ $key ] ) {
				$parts[] = $key . ':' . rawurlencode( self::text_value( $link[ $key ] ) );
			}
		}

		return implode( '|', $parts );
	}

	private static function design_css_value( array $value ): string {
		$declarations = isset( $value['declarations'] ) && is_array( $value['declarations'] ) ? $value['declarations'] : $value;

		$rules = '';
		foreach ( $declarations as $property => $declaration ) {
			$property    = strtolower( trim( (string) $property ) );
			$declaration = trim( self::text_value( $declaration ) );
			if ( ! preg_match( '/^[a-z-]+$/', $property ) || preg_match( '/[{};]/', $declaration ) ) {
				throw new InvalidArgumentException( sprintf( 'Invalid CSS declaration "%s: %s".', $property, $declaration ) );
			}
			$important = false === stripos( $declaration, '!important' ) ? ' !important' : '';
			$rules    .= sprintf( '%s: %s%s;', $property, $declaration, $important );
		}

		return sprintf( '.%s%d{%s}', self::DESIGN_CSS_CLASS_PREFIX, (int) ( microtime( true ) * 1000 ) + self::$design_css_sequence++, $rules );
	}

	private static function design_css_text( string $value ): string {
		if ( '' !== $value && ! preg_match( '/^\.[\w-]+\{[^{}]*\}$/', $value ) ) {
			throw new InvalidArgumentException( 'Design CSS must look like ".vc_custom_123{margin-top: 10px !important;}", or be sent as an object of CSS declarations.' );
		}

		return $value;
	}

	private static function attachment_ids( array $values, int $limit ): string {
		$ids = array_values( array_filter( array_map( 'trim', array_map( array( __CLASS__, 'text_value' ), $values ) ), 'strlen' ) );
		if ( count( $ids ) > $limit ) {
			throw new InvalidArgumentException( 'Expected a single attachment ID.' );
		}

		foreach ( $ids as $id ) {
			if ( ! ctype_digit( $id ) || 'attachment' !== get_post_type( (int) $id ) ) {
				throw new InvalidArgumentException( sprintf( '"%s" is not an attachment ID from the media library.', $id ) );
			}
		}

		return implode( ',', $ids );
	}

	private static function text_value( $value ): string {
		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		throw new InvalidArgumentException( 'Expected text.' );
	}

	private static function json( array $value ): string {
		return (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	private static function list_options( array $allowed ): string {
		$listed = array_map( static fn( $option ) => '"' . $option . '"', array_slice( $allowed, 0, self::MAX_LISTED_OPTIONS ) );
		$more   = count( $allowed ) - count( $listed );

		return implode( ', ', $listed ) . ( $more > 0 ? sprintf( ' and %d more (see get-element-schema)', $more ) : '' );
	}
}
