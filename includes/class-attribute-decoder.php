<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Attribute_Decoder {

	const LINK_PARTS = array( 'url', 'title', 'target', 'rel' );

	public function decode( ?string $type, string $value ) {
		if ( '' === $value ) {
			return null;
		}

		switch ( $type ) {
			case 'textarea_raw_html':
			case 'textarea_ace':
				return self::decode_base64_text( $value );
			case 'vc_link':
				return self::decode_link( $value );
			case 'param_group':
				return self::decode_json( rawurldecode( $value ) );
			case 'css_editor':
				return self::decode_design_css( $value );
		}

		if ( ! Builder_Escaping::is_escaped( $value ) ) {
			return null;
		}

		$unescaped = Builder_Escaping::unescape( $value );
		$json      = self::decode_json( $unescaped );

		return null === $json ? $unescaped : $json;
	}

	private static function decode_base64_text( string $value ) {
		$binary = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- WPBakery stores raw HTML attributes as base64.

		return false === $binary ? null : rawurldecode( $binary );
	}

	private static function decode_link( string $value ): ?array {
		$link = array();

		foreach ( explode( '|', $value ) as $pair ) {
			$separator = strpos( $pair, ':' );
			if ( false === $separator ) {
				continue;
			}
			$key = substr( $pair, 0, $separator );
			if ( in_array( $key, self::LINK_PARTS, true ) ) {
				$link[ $key ] = trim( rawurldecode( substr( $pair, $separator + 1 ) ) );
			}
		}

		return array() === $link ? null : $link;
	}

	private static function decode_json( string $json ) {
		$decoded = json_decode( $json, true );

		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ? $decoded : null;
	}

	private static function decode_design_css( string $value ): ?array {
		if ( ! preg_match( '/^\s*\.([\w-]+)\s*\{(.*)\}\s*$/s', $value, $match ) ) {
			return null;
		}

		$declarations = array();
		foreach ( array_filter( array_map( 'trim', explode( ';', $match[2] ) ) ) as $declaration ) {
			$parts = array_map( 'trim', explode( ':', $declaration, 2 ) );
			if ( 2 === count( $parts ) ) {
				$declarations[ $parts[0] ] = $parts[1];
			}
		}

		return array(
			'class'        => $match[1],
			'declarations' => $declarations,
		);
	}
}
