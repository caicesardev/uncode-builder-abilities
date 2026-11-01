<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Design_Tokens;

defined( 'ABSPATH' ) || exit;

final class Get_Design_Tokens extends Ability {

	private Design_Tokens $tokens;

	public function __construct( Design_Tokens $tokens ) {
		$this->tokens = $tokens;
	}

	public function name(): string {
		return 'get-design-tokens';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$sets = $this->tokens->sets();

		return array(
			'counts' => array_map( 'count', $sets ),
			'sets'   => $sets,
		);
	}

	protected function label(): string {
		return __( 'Get the site design tokens', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Returns the design tokens of the site as the page builder knows them: colour palette, font families, font sizes, weights, line heights and letter spacings, each with the `value` to put in element parameters, its `label` and, when the theme exposes it, what it `resolves_to` (hex colour, pixel size, font stack). Read it once per session: get-element-schema refers to these sets with `options_ref: "design_tokens.<set>"` instead of repeating them. Prefer these tokens over raw colours or sizes so pages follow the site style.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
		);
	}
}
