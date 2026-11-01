<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Design_Tokens {

	private Element_Catalog $catalog;

	private ?array $sets = null;

	public function __construct( Element_Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	public function sets(): array {
		if ( null !== $this->sets ) {
			return $this->sets;
		}

		$this->sets = array();
		foreach ( (array) apply_filters( 'uncode_builder_abilities_token_sources', array() ) as $name => $source ) {
			$options = $this->source_options( (array) $source );
			if ( array() === $options ) {
				continue;
			}
			$this->sets[ $name ] = array_map(
				static fn( array $option ) => (array) apply_filters( 'uncode_builder_abilities_design_token', $option, $name ),
				$options
			);
		}

		return $this->sets;
	}

	public function set_matching( array $options ): ?string {
		$signature = self::signature( $options );

		foreach ( $this->sets() as $name => $tokens ) {
			if ( self::signature( $tokens ) === $signature ) {
				return $name;
			}
		}

		return null;
	}

	public static function signature( array $options ): string {
		$values = array_unique( array_filter( array_map( 'strval', array_column( $options, 'value' ) ), 'strlen' ) );
		sort( $values );

		return implode( "\n", $values );
	}

	private function source_options( array $source ): array {
		$param   = $this->catalog->params_by_name( (string) ( $source['tag'] ?? '' ) )[ (string) ( $source['param'] ?? '' ) ] ?? null;
		$options = array();

		foreach ( $param['options'] ?? array() as $option ) {
			if ( '' !== $option['value'] && ! isset( $options[ $option['value'] ] ) ) {
				$options[ $option['value'] ] = $option;
			}
		}

		return array_values( $options );
	}
}
