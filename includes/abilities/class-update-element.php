<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Update_Element extends Write_Ability {

	public function name(): string {
		return 'update-element';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$node = $this->target( $document, $input );
		if ( is_wp_error( $node ) ) {
			return $node;
		}

		$attributes = $input['attributes'] ?? array();
		$content    = $input['content'] ?? null;
		if ( ! is_array( $attributes ) || ( array() === $attributes && null === $content ) ) {
			return new WP_Error( 'uncode_builder_nothing_to_update', 'Send `attributes` to change, `content`, or both.', array( 'status' => 400 ) );
		}

		return $this->operations->update( $document, $node, $attributes, $content );
	}

	protected function is_destructive(): bool {
		return true;
	}

	protected function label(): string {
		return __( 'Update a page builder element', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Changes the parameters and/or the text content of one element, chosen by `path` or `id` from get-page-tree. `attributes` sets parameters (a null value removes one, going back to its default); the rest of the element and of the page stay byte for byte as they were. `content` replaces the inner HTML of non-container elements. Values are validated against get-element-schema. Requires the `version` from get-page-tree; use `dry_run` to preview and `expect_tag` to make sure the right element is changed.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema(
			array_merge(
				$this->common_properties(),
				self::target_properties(),
				array(
					'attributes' => array(
						'type'        => 'object',
						'description' => 'Parameters to set; null removes a parameter.',
					),
					'content'    => array(
						'type'        => 'string',
						'description' => 'New inner HTML or text for elements that have content.',
					),
				)
			),
			array( 'post_id', 'version' )
		);
	}
}
