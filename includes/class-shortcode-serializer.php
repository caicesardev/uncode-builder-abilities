<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Shortcode_Serializer {

	public static function element( string $tag, array $attributes, ?string $inner ): string {
		$open = self::open_tag( $tag, $attributes );

		return null === $inner ? $open : $open . $inner . '[/' . $tag . ']';
	}

	public static function open_tag( string $tag, array $attributes ): string {
		$text = '';
		foreach ( $attributes as $name => $value ) {
			$text .= sprintf( ' %s="%s"', $name, $value );
		}

		return '[' . $tag . $text . ']';
	}
}
