<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Element_Catalog;
use Uncode_Builder_Abilities\Schema_Compactor;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Get_Element_Schema extends Ability {

	const ALL_GROUPS = '*';

	const DEFAULT_GROUP = 'General';

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
		$input     = self::input_array( $input );
		$tag       = (string) ( $input['tag'] ?? '' );
		$requested = (string) ( $input['group'] ?? '' );
		$schema    = $this->catalog->schema( $tag, self::ALL_GROUPS === $requested ? '' : $requested, ! empty( $input['include_descriptions'] ) );

		if ( null === $schema ) {
			return new WP_Error(
				'uncode_builder_unknown_element',
				sprintf( 'There is no element with the tag "%s". Use list-elements to see the available tags.', $tag ),
				array( 'status' => 404 )
			);
		}

		if ( '' === $requested ) {
			$schema = self::only_default_group( $schema );
		}

		return ( $input['compact'] ?? true ) ? $this->compactor->compact( $schema, self::ALL_GROUPS !== $requested ) : $schema;
	}

	private static function only_default_group( array $schema ): array {
		$group = isset( $schema['groups'][ self::DEFAULT_GROUP ] ) ? self::DEFAULT_GROUP : (string) array_key_first( $schema['groups'] );

		$schema['returned_group'] = $group;
		$schema['params']         = array_values( array_filter( $schema['params'], static fn( array $param ) => $param['group'] === $group ) );

		return $schema;
	}

	protected function label(): string {
		return __( 'Get a page builder element schema', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Returns the parameters of one WPBakery / Uncode element: name, normalized `kind`, label, default, allowed `options`, numeric bounds, `visible_when` (the parameter only applies when another one has those values) and `active_when` (Uncode colour parameters that only apply in one colour mode). Elements can have 200+ parameters, so the reply lists every group in `groups` (with its parameter count) but only returns the parameters of one: "General" unless you send `group` (for example "Style", "Responsive" or "Animation"); `group: "*"` returns all of them and can be very large. The reply is compact: long option lists that match a design token set come as `options_ref: "design_tokens.<set>"` (read them once with get-design-tokens), lists repeated inside the reply come as `options_ref: "params.<name>"` (same options as that parameter), the raw builder `type` is only kept when `kind` is unknown, and editor-only parameters are left out. `compact: false` returns everything; `include_descriptions` adds the help text.', 'uncode-builder-abilities' );
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
					'description' => 'Group whose parameters to return, as listed in `groups`. Default "General"; "*" for every group.',
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
