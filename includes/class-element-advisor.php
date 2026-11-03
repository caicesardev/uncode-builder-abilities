<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Element_Advisor {

	const MAX_REPORTED = 15;

	public static function advice_for( Layout_Document $document, array $nodes ): array {
		$advice = array();
		foreach ( $nodes as $node ) {
			self::collect( $node, self::ancestors_of( $document, $node['path'] ), $advice );
		}

		return array_slice( array_values( array_unique( $advice ) ), 0, self::MAX_REPORTED );
	}

	private static function ancestors_of( Layout_Document $document, string $path ): array {
		$ancestors = array();
		for ( $parent = Layout_Document::parent_path_of( $path ); Layout_Document::ROOT_PATH !== $parent; $parent = Layout_Document::parent_path_of( $parent ) ) {
			array_unshift( $ancestors, $document->node( $parent ) );
		}

		return array_filter( $ancestors );
	}

	private static function collect( array $node, array $ancestors, array &$advice ): void {
		if ( isset( $node['tag'] ) ) {
			$attributes = Shortcode_Parser::raw_attributes( $node[ Shortcode_Parser::SOURCE_KEY ]['attributes_text'] );
			foreach ( (array) apply_filters( 'uncode_builder_abilities_element_advice', array(), $node['tag'], $attributes, $ancestors ) as $message ) {
				$advice[] = sprintf( '%s %s: %s', $node['path'], $node['tag'], $message );
			}
		}

		foreach ( $node['children'] ?? array() as $child ) {
			self::collect( $child, array_merge( $ancestors, array( $node ) ), $advice );
		}
	}
}
