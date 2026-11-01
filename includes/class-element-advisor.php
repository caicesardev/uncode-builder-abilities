<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Element_Advisor {

	const MAX_REPORTED = 15;

	public static function advice_for( array $nodes ): array {
		$advice = array();
		foreach ( $nodes as $node ) {
			self::collect( $node, $advice );
		}

		return array_slice( array_values( array_unique( $advice ) ), 0, self::MAX_REPORTED );
	}

	private static function collect( array $node, array &$advice ): void {
		if ( isset( $node['tag'] ) ) {
			$attributes = Shortcode_Parser::raw_attributes( $node[ Shortcode_Parser::SOURCE_KEY ]['attributes_text'] );
			foreach ( (array) apply_filters( 'uncode_builder_abilities_element_advice', array(), $node['tag'], $attributes ) as $message ) {
				$advice[] = sprintf( '%s %s: %s', $node['path'], $node['tag'], $message );
			}
		}

		foreach ( $node['children'] ?? array() as $child ) {
			self::collect( $child, $advice );
		}
	}
}
