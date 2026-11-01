<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Move_Element extends Write_Ability {

	public function name(): string {
		return 'move-element';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$node = $this->target( $document, $input );
		if ( is_wp_error( $node ) ) {
			return $node;
		}

		return $this->operations->move(
			$document,
			$node,
			(string) ( $input['to_parent_path'] ?? Layout_Document::ROOT_PATH ),
			isset( $input['index'] ) ? (int) $input['index'] : null
		);
	}

	protected function label(): string {
		return __( 'Move a page builder element', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Moves one element (with everything inside it), chosen by `path` or `id`, into the container at `to_parent_path` ("" for the top level), before the child at `index` as the page is now (default: at the end). Nesting rules are checked. Requires the `version` from get-page-tree; use `dry_run` to preview.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema(
			array_merge(
				$this->common_properties(),
				self::target_properties(),
				array(
					'to_parent_path' => array(
						'type'        => 'string',
						'description' => 'Path of the destination container; "" for the top level.',
					),
					'index'          => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Insert before the child at this position of the destination, as the page is now. Leave out to append.',
					),
				)
			),
			array( 'post_id', 'version', 'to_parent_path' )
		);
	}
}
