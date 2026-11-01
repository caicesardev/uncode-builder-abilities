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
		return __( 'Lists the WPBakery / Uncode elements (shortcodes) registered on this site: tag, name, categories, whether it is a container, which children or parents it accepts, and whether it can be inserted on its own. Filter with `search` (tag, name or description) or `category`. Workflow for building or changing a page: 1) get-design-tokens once, to use the site colours, fonts and sizes instead of raw values; 2) list-elements to choose elements; 3) get-element-schema per element, one group at a time; 4) get-page-tree for the `version` and paths; 5) the uncode-builder-edit abilities with `dry_run: true`, then for real.', 'uncode-builder-abilities' );
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
