<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Remove_Element extends Write_Ability {

	public function name(): string {
		return 'remove-element';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$node = $this->target( $document, $input );
		if ( is_wp_error( $node ) ) {
			return $node;
		}

		return $this->operations->remove( $document, $node );
	}

	protected function is_destructive(): bool {
		return true;
	}

	protected function label(): string {
		return __( 'Remove a page builder element', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Removes one element and everything inside it, chosen by `path` or `id` from get-page-tree. `expect_tag` is required so the wrong element is never removed. Requires the `version` from get-page-tree; use `dry_run` to preview. The previous content is kept as a snapshot that restore-snapshot brings back.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema(
			array_merge( $this->common_properties(), self::target_properties() ),
			array( 'post_id', 'version', 'expect_tag' )
		);
	}
}
