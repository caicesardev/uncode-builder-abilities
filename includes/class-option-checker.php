<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Option_Checker {

	const MAX_REPORTED = 15;

	private Element_Catalog $catalog;

	private Shortcode_Parser $parser;

	public function __construct( Element_Catalog $catalog, Shortcode_Parser $parser ) {
		$this->catalog = $catalog;
		$this->parser  = $parser;
	}

	public function mismatches( string $source ): array {
		$mismatches = array();
		$this->collect( $this->parser->parse( $source, false )['tree'], $mismatches );

		$extra = count( $mismatches ) - self::MAX_REPORTED;
		$shown = array_slice( $mismatches, 0, self::MAX_REPORTED );
		if ( $extra > 0 ) {
			$shown[] = sprintf( 'and %d more.', $extra );
		}

		return $shown;
	}

	private function collect( array $nodes, array &$mismatches ): void {
		foreach ( $nodes as $node ) {
			if ( isset( $node['tag'] ) && $this->catalog->has( $node['tag'] ) ) {
				$this->check_element( $node, $mismatches );
			}
			$this->collect( $node['children'] ?? array(), $mismatches );
		}
	}

	private function check_element( array $node, array &$mismatches ): void {
		$params     = $this->catalog->params_by_name( $node['tag'] );
		$attributes = Shortcode_Parser::raw_attributes( $node[ Shortcode_Parser::SOURCE_KEY ]['attributes_text'] );

		foreach ( $attributes as $name => $value ) {
			$param   = $params[ $name ] ?? null;
			$allowed = array_column( $param['options'] ?? array(), 'value' );
			if ( null === $param || 'select' !== $param['kind'] || '' === $value || array() === $allowed || in_array( $value, $allowed, true ) ) {
				continue;
			}

			$mismatches[] = sprintf( '%s %s: %s="%s" is not one of this site\'s options', $node['path'], $node['tag'], $name, $value );
		}
	}
}
