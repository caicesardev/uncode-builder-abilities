<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Nesting_Rules {

	const ROOT_TAGS = array( 'vc_row', 'vc_section' );

	const CORE_CHILD_RULES = array(
		'vc_section'      => array( 'only' => array( 'vc_row' ) ),
		'vc_row'          => array( 'only' => array( 'vc_column' ) ),
		'vc_row_inner'    => array( 'only' => array( 'vc_column_inner' ) ),
		'vc_column'       => array( 'except' => array( 'vc_section', 'vc_row', 'vc_column', 'vc_column_inner' ) ),
		'vc_column_inner' => array( 'except' => array( 'vc_section', 'vc_row', 'vc_row_inner', 'vc_column', 'vc_column_inner' ) ),
	);

	private Element_Catalog $catalog;

	private ?array $root_tags = null;

	private ?array $child_rules = null;

	public function __construct( Element_Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	public function violation( ?string $parent_tag, string $child_tag ): ?string {
		if ( null === $parent_tag ) {
			return in_array( $child_tag, $this->root_tags(), true )
				? null
				: sprintf( '"%s" cannot sit at the top level of the page; only %s can.', $child_tag, implode( ', ', $this->root_tags() ) );
		}

		if ( ! $this->catalog->is_container( $parent_tag ) ) {
			return sprintf( '"%s" is not a container, so it cannot hold "%s".', $parent_tag, $child_tag );
		}

		$children_rule = $this->children_rule( $parent_tag );
		if ( ! self::allows( $children_rule, $child_tag ) ) {
			return sprintf( '"%s" cannot hold "%s"%s.', $parent_tag, $child_tag, self::describe( $children_rule ) );
		}

		$parents_rule = $this->catalog->relations( $child_tag )['parents'];
		if ( ! self::allows( $parents_rule, $parent_tag ) ) {
			return sprintf( '"%s" cannot go inside "%s"%s.', $child_tag, $parent_tag, self::describe( $parents_rule ) );
		}

		if ( ! $this->catalog->is_insertable( $child_tag ) && ! in_array( $child_tag, $children_rule['only'] ?? array(), true ) ) {
			return sprintf( '"%s" cannot be inserted inside "%s".', $child_tag, $parent_tag );
		}

		return null;
	}

	private function root_tags(): array {
		$this->root_tags ??= (array) apply_filters( 'uncode_builder_abilities_root_tags', self::ROOT_TAGS );

		return $this->root_tags;
	}

	private function children_rule( string $parent_tag ): array {
		$this->child_rules ??= (array) apply_filters( 'uncode_builder_abilities_nesting_rules', self::CORE_CHILD_RULES );

		return $this->child_rules[ $parent_tag ] ?? $this->catalog->relations( $parent_tag )['children'];
	}

	private static function allows( array $rule, string $tag ): bool {
		if ( ! empty( $rule['only'] ) && ! in_array( $tag, $rule['only'], true ) ) {
			return false;
		}

		return empty( $rule['except'] ) || ! in_array( $tag, $rule['except'], true );
	}

	private static function describe( array $rule ): string {
		if ( ! empty( $rule['only'] ) ) {
			return ' (allowed: ' . implode( ', ', $rule['only'] ) . ')';
		}

		return empty( $rule['except'] ) ? '' : ' (not allowed: ' . implode( ', ', $rule['except'] ) . ')';
	}
}
