<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Device_Visibility {

	const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	public static function annotate( array $nodes, array $visible = self::DEVICES ): array {
		return array_map(
			static function ( array $node ) use ( $visible ): array {
				if ( ! isset( $node['tag'] ) ) {
					return $node;
				}

				$here = self::without( $visible, self::hidden_devices( $node ) );
				if ( self::DEVICES !== $here ) {
					$node['visible_on'] = $here;
				}
				if ( isset( $node['children'] ) ) {
					$node['children'] = self::annotate( $node['children'], $here );
				}

				return $node;
			},
			$nodes
		);
	}

	public static function without( array $devices, array $hidden ): array {
		return array_values( array_diff( $devices, $hidden ) );
	}

	public static function sorted( array $devices ): array {
		return array_values( array_intersect( self::DEVICES, $devices ) );
	}

	private static function hidden_devices( array $node ): array {
		return (array) apply_filters( 'uncode_builder_abilities_hidden_devices', array(), $node['tag'], (array) ( $node['attributes'] ?? array() ) );
	}
}
