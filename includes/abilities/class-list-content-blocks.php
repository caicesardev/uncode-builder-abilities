<?php

namespace Uncode_Builder_Abilities\Abilities;

defined( 'ABSPATH' ) || exit;

final class List_Content_Blocks extends Ability {

	const POST_TYPE = 'uncodeblock';

	const MAX_RESULTS = 100;

	public function name(): string {
		return 'list-content-blocks';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$input  = self::input_array( $input );
		$blocks = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'perm'           => 'readable',
				's'              => (string) ( $input['search'] ?? '' ),
				'posts_per_page' => self::MAX_RESULTS,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return array(
			'count'       => count( $blocks ),
			'insert_with' => array(
				'tag'        => 'uncode_block',
				'attributes' => array( 'id' => '<block id>' ),
			),
			'blocks'      => array_map(
				static fn( $block ) => array(
					'id'       => $block->ID,
					'title'    => $block->post_title,
					'status'   => $block->post_status,
					'modified' => $block->post_modified,
				),
				$blocks
			),
		);
	}

	protected function label(): string {
		return __( 'List Uncode content blocks', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Lists the Uncode content blocks: reusable sections (headers, footers, call to actions…) built with the page builder. Reuse one on a page by inserting { tag: "uncode_block", attributes: { id: "<block id>" } } with insert-elements, or read and edit the block itself with get-page-tree and the edit abilities using its id as `post_id` (every page that shows it changes too). Filter with `search`.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Text to look for in the block titles and content.',
				),
			),
			'additionalProperties' => false,
		);
	}
}
