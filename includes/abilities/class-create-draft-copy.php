<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Plugin;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Create_Draft_Copy extends Ability {

	private Draft_Copies $copies;

	public function __construct( Draft_Copies $copies ) {
		$this->copies = $copies;
	}

	public function name(): string {
		return 'create-draft-copy';
	}

	public function has_permission( $input = null ): bool {
		$post_id = (int) ( self::input_array( $input )['post_id'] ?? 0 );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function execute( $input = null ) {
		$post = get_post( (int) ( self::input_array( $input )['post_id'] ?? 0 ) );

		if ( ! $post instanceof WP_Post || 'revision' === $post->post_type ) {
			return new WP_Error( 'uncode_builder_post_not_found', 'The post does not exist.', array( 'status' => 404 ) );
		}

		return $this->copies->create( $post );
	}

	protected function ability_namespace(): string {
		return Plugin::WRITE_NAMESPACE;
	}

	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	protected function label(): string {
		return __( 'Create a draft copy of a published page', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Creates a draft copy of a published page, post or content block so its layout can be changed without touching the live version. With the Yoast Duplicate Post plugin it is a "Rewrite & Republish" copy: a person reviews it and clicks Republish to merge it into the original, which keeps its URL. If the item already has such a copy, that copy is returned instead of creating another. Without that plugin it is an independent draft. Returns `copy_id` (use it as `post_id` with get-page-tree and the edit abilities), `preview_url` and `how_to_apply`. The original is never modified.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'ID of the published item to copy.',
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}
}
