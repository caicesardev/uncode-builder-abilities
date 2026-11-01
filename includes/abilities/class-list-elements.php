<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Element_Catalog;

defined( 'ABSPATH' ) || exit;

final class List_Elements extends Ability {

	private Element_Catalog $catalog;

	public function __construct( Element_Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	public function name(): string {
		return 'list-elements';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$input    = self::input_array( $input );
		$elements = $this->catalog->summaries(
			(string) ( $input['search'] ?? '' ),
			(string) ( $input['category'] ?? '' ),
			! empty( $input['include_deprecated'] )
		);

		return array(
			'count'      => count( $elements ),
			'categories' => $this->catalog->categories(),
			'elements'   => $elements,
		);
	}

	protected function label(): string {
		return __( 'List page builder elements', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Lists the WPBakery / Uncode elements (shortcodes) registered on this site: tag, name, categories, whether it is a container, which children or parents it accepts, and whether it can be inserted on its own. Use it first to choose elements, then read one with get-element-schema before composing it. Filter with `search` (tag, name or description) or `category`.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'search'             => array(
					'type'        => 'string',
					'description' => 'Text to look for in the tag, name or description.',
				),
				'category'           => array(
					'type'        => 'string',
					'description' => 'Exact category name, as listed in `categories`.',
				),
				'include_deprecated' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'additionalProperties' => false,
		);
	}
}
