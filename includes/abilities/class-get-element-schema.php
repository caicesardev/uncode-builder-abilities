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
		$input   = self::input_array( $input );
		$tag     = (string) ( $input['tag'] ?? '' );
		$compact = (bool) ( $input['compact'] ?? true );
		$schema  = $this->catalog->schema( $tag, '', ! empty( $input['include_descriptions'] ) );

		if ( null === $schema ) {
			return new WP_Error(
				'uncode_builder_unknown_element',
				sprintf( 'There is no element with the tag "%s". Use list-elements to see the available tags.', $tag ),
				array( 'status' => 404 )
			);
		}

		$index     = self::group_index( $schema['params'], $compact );
		$selection = self::select( $schema['params'], $index, $input );
		if ( is_wp_error( $selection ) ) {
			return $selection;
		}

		$schema['groups'] = $index;
		$schema['params'] = $selection['params'];
		$schema           = array_merge( $schema, $selection['meta'] );

		return $compact ? $this->compactor->compact( $schema, $selection['single_group'] ) : $schema;
	}

	private static function group_index( array $params, bool $compact ): array {
		$index = array();
		foreach ( $params as $param ) {
			if ( $compact && in_array( $param['kind'], Schema_Compactor::OMITTED_KINDS, true ) ) {
				continue;
			}
			$index[ $param['group'] ][] = $param['name'];
		}

		return $index;
	}

	private static function select( array $params, array $index, array $input ) {
		$names = array_values( array_filter( array_map( 'strval', (array) ( $input['params'] ?? array() ) ) ) );
		if ( array() !== $names ) {
			$found = array_values( array_filter( $params, static fn( array $param ) => in_array( $param['name'], $names, true ) ) );

			return array(
				'params'       => $found,
				'single_group' => false,
				'meta'         => array_filter( array( 'unknown_params' => array_values( array_diff( $names, array_column( $found, 'name' ) ) ) ) ),
			);
		}

		$requested = (string) ( $input['group'] ?? '' );
		if ( self::ALL_GROUPS === $requested ) {
			return array(
				'params'       => $params,
				'single_group' => false,
				'meta'         => array(),
			);
		}

		$group = '' === $requested ? self::default_group( $index ) : self::matching_group( $index, $requested );
		if ( null === $group ) {
			return new WP_Error(
				'uncode_builder_unknown_group',
				sprintf( 'There is no group "%s". Groups: %s.', $requested, implode( ', ', array_keys( $index ) ) ),
				array( 'status' => 404 )
			);
		}

		return array(
			'params'       => array_values( array_filter( $params, static fn( array $param ) => $param['group'] === $group ) ),
			'single_group' => true,
			'meta'         => array( 'returned_group' => $group ),
		);
	}

	private static function default_group( array $index ): string {
		return isset( $index[ self::DEFAULT_GROUP ] ) ? self::DEFAULT_GROUP : (string) array_key_first( $index );
	}

	private static function matching_group( array $index, string $requested ): ?string {
		foreach ( array_keys( $index ) as $group ) {
			if ( 0 === strcasecmp( (string) $group, $requested ) ) {
				return (string) $group;
			}
		}

		return null;
	}

	protected function label(): string {
		return __( 'Get a page builder element schema', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Returns the parameters of one WPBakery / Uncode element: name, normalized `kind`, label, default, allowed `options`, numeric bounds, `visible_when` (the parameter only applies when another one has those values) and `active_when` (Uncode colour parameters that only apply in one colour mode). Elements can have 200+ parameters, so the reply always includes `groups`, an index of every parameter name by group, and details only for part of them: the "General" group by default, another group with `group` (for example "Style", "Responsive" or "Animation"), or exactly the parameters you name in `params` (any group) — use the index to pick them instead of asking for everything. The reply is compact: long option lists that match a design token set come as `options_ref: "design_tokens.<set>"` (read them once with get-design-tokens), lists repeated inside the reply come as `options_ref: "params.<name>"` (same options as that parameter), the raw builder `type` is only kept when `kind` is unknown, and editor-only parameters are left out. `include_descriptions` adds the help text.', 'uncode-builder-abilities' );
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
					'description' => 'Group whose parameters to return, as listed in `groups`. Default "General".',
				),
				'params'               => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Exact parameter names to return, from any group (takes precedence over `group`).',
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
