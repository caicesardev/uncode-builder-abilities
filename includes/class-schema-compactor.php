<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Schema_Compactor {

	const OMITTED_KINDS = array( 'ui', 'hidden' );

	const INLINE_OPTIONS_LIMIT = 6;

	const TOKEN_REFERENCE_PREFIX = 'design_tokens.';

	const PARAM_REFERENCE_PREFIX = 'params.';

	private Design_Tokens $tokens;

	public function __construct( Design_Tokens $tokens ) {
		$this->tokens = $tokens;
	}

	public function compact( array $schema, bool $single_group ): array {
		$first_param_with = array();
		$params           = array();

		foreach ( $schema['params'] as $param ) {
			if ( in_array( $param['kind'], self::OMITTED_KINDS, true ) ) {
				continue;
			}

			if ( Param_Normalizer::UNKNOWN_KIND !== $param['kind'] ) {
				unset( $param['type'] );
			}
			if ( $single_group ) {
				unset( $param['group'] );
			}

			if ( count( $param['options'] ?? array() ) > self::INLINE_OPTIONS_LIMIT ) {
				$reference = $this->reference_for( $param, $first_param_with );
				if ( null !== $reference ) {
					unset( $param['options'] );
					$param['options_ref'] = $reference;
				}
			}

			$params[] = $param;
		}

		$schema['params'] = $params;

		return $schema;
	}

	private function reference_for( array $param, array &$first_param_with ): ?string {
		$token_set = $this->tokens->set_matching( $param['options'] );
		if ( null !== $token_set ) {
			return self::TOKEN_REFERENCE_PREFIX . $token_set;
		}

		$signature = Design_Tokens::signature( $param['options'] );
		if ( isset( $first_param_with[ $signature ] ) ) {
			return self::PARAM_REFERENCE_PREFIX . $first_param_with[ $signature ];
		}

		$first_param_with[ $signature ] = $param['name'];

		return null;
	}
}
