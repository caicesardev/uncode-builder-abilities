<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Element_Catalog;
use Uncode_Builder_Abilities\Schema_Compactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Get_Element_Schema extends Ability {

	private Element_Catalog $catalog;

	private Schema_Compactor $compactor;

	public function __construct( Element_Catalog $catalog, Schema_Compactor $compactor ) {
		$this->catalog   = $catalog;
		$this->compactor = $compactor;
	}

	public function name(): string {
		return 'get-element-schema';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$input  = self::input_array( $input );
		$tag    = (string) ( $input['tag'] ?? '' );
		$group  = (string) ( $input['group'] ?? '' );
		$schema = $this->catalog->schema( $tag, $group, ! empty( $input['include_descriptions'] ) );

		if ( null === $schema ) {
			return new WP_Error(
				'uncode_builder_unknown_element',
				sprintf( 'There is no element with the tag "%s". Use list-elements to see the available tags.', $tag ),
				array( 'status' => 404 )
			);
		}

		return ( $input['compact'] ?? true ) ? $this->compactor->compact( $schema, '' !== $group ) : $schema;
	}

	protected function label(): string {
		return __( 'Get a page builder element schema', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Returns the parameters of one WPBakery / Uncode element: name, type, normalized `kind`, label, group, default, allowed `options`, numeric bounds, `visible_when` (the parameter only applies when another one has those values) and `active_when` (Uncode colour parameters that only apply in one colour mode). Elements can have 200+ parameters, so `groups` gives the count per group and `group` limits the reply to one of them. By default the reply is compact: long option lists that match a design token set come as `options_ref: "design_tokens.<set>"` (read them once with get-design-tokens), lists repeated inside the reply come as `options_ref: "params.<name>"` (same options as that parameter), the raw builder `type` is only kept when `kind` is unknown, and editor-only parameters are left out. `compact: false` returns everything; `include_descriptions` adds the help text.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'tag'                  => array(
					'type'        => 'string',
					'description' => 'Element tag, such as vc_row or vc_custom_heading.',
				),
				'group'                => array(
					'type'        => 'string',
					'description' => 'Only return the parameters of this group (for example General, Style or Responsive).',
				),
				'compact'              => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'include_descriptions' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'required'             => array( 'tag' ),
			'additionalProperties' => false,
		);
	}
}
