<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Builder_Escaping {

	const ESCAPES = array(
		'"' => '``',
		'[' => '`{`',
		']' => '`}`',
	);

	const UNESCAPES = array(
		'`{`' => '[',
		'`}`' => ']',
		'``'  => '"',
	);

	public static function escape( string $value ): string {
		return strtr( $value, self::ESCAPES );
	}

	public static function unescape( string $value ): string {
		return strtr( $value, self::UNESCAPES );
	}

	public static function is_escaped( string $value ): bool {
		foreach ( array_keys( self::UNESCAPES ) as $token ) {
			if ( false !== strpos( $value, $token ) ) {
				return true;
			}
		}

		return false;
	}
}
