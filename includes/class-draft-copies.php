<?php

namespace Uncode_Builder_Abilities;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Draft_Copies {

	const REWRITE_COPY_OF_META = '_dp_is_rewrite_republish_copy';

	const REWRITE_COPY_META = '_dp_has_rewrite_republish_copy';

	const COPY_OF_META = '_uncode_builder_abilities_copy_of';

	const COPY_STATUS = 'draft';

	const MODE_REWRITE = 'rewrite_and_republish';

	const MODE_COPY = 'draft_copy';

	const SKIPPED_META = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', Snapshot_Store::META_KEY );

	public static function register(): void {
		add_filter( 'duplicate_post_excludelist_filter', array( __CLASS__, 'exclude_snapshots_from_copies' ) );
	}

	public static function exclude_snapshots_from_copies( $excluded ): array {
		return array_merge( (array) $excluded, array( Snapshot_Store::META_KEY ) );
	}

	public static function rewrite_copy_id( WP_Post $post ): int {
		$copy_id = (int) get_post_meta( $post->ID, self::REWRITE_COPY_META, true );

		return $copy_id > 0 && get_post( $copy_id ) instanceof WP_Post ? $copy_id : 0;
	}

	public function create( WP_Post $original ) {
		if ( Write_Policy::is_draft( $original ) ) {
			return new WP_Error(
				'uncode_builder_already_a_draft',
				sprintf( '"%s" is already a %s and can be edited directly.', $original->post_title, $original->post_status ),
				array( 'status' => 400 )
			);
		}

		$existing = self::rewrite_copy_id( $original );
		if ( $existing > 0 ) {
			return $this->reply( $original, get_post( $existing ), self::MODE_REWRITE, true );
		}

		$copy = $this->can_rewrite_and_republish( $original ) ? $this->rewrite_copy( $original ) : $this->plain_copy( $original );
		if ( is_wp_error( $copy ) ) {
			return $copy;
		}

		if ( ! Write_Policy::is_draft( $copy ) ) {
			wp_update_post( array( 'ID' => $copy->ID, 'post_status' => self::COPY_STATUS ) );
			$copy = get_post( $copy->ID );
		}

		$mode = (int) get_post_meta( $copy->ID, self::REWRITE_COPY_OF_META, true ) ? self::MODE_REWRITE : self::MODE_COPY;

		return $this->reply( $original, $copy, $mode, false );
	}

	private function can_rewrite_and_republish( WP_Post $original ): bool {
		if ( ! class_exists( '\Yoast\WP\Duplicate_Post\Post_Duplicator' ) || ! class_exists( '\Yoast\WP\Duplicate_Post\Permissions_Helper' ) ) {
			return false;
		}

		$permissions = new \Yoast\WP\Duplicate_Post\Permissions_Helper();

		return $permissions->is_current_user_allowed_to_copy()
			&& $permissions->is_post_type_enabled( $original->post_type )
			&& $permissions->should_rewrite_and_republish_be_allowed( $original );
	}

	private function rewrite_copy( WP_Post $original ) {
		$copy_id = ( new \Yoast\WP\Duplicate_Post\Post_Duplicator() )->create_duplicate_for_rewrite_and_republish( $original );

		return is_wp_error( $copy_id ) ? $copy_id : get_post( $copy_id );
	}

	private function plain_copy( WP_Post $original ) {
		$copy_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $original->post_type,
					'post_status'  => self::COPY_STATUS,
					'post_title'   => sprintf( '%s (draft copy)', $original->post_title ),
					'post_content' => $original->post_content,
					'post_excerpt' => $original->post_excerpt,
					'post_parent'  => $original->post_parent,
					'menu_order'   => $original->menu_order,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $copy_id ) ) {
			return $copy_id;
		}

		foreach ( get_post_meta( $original->ID ) as $key => $values ) {
			if ( in_array( $key, self::SKIPPED_META, true ) || 0 === strpos( $key, '_dp_' ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $copy_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
		update_post_meta( $copy_id, self::COPY_OF_META, $original->ID );

		return get_post( $copy_id );
	}

	private function reply( WP_Post $original, WP_Post $copy, string $mode, bool $reused ): array {
		$how_to_apply = self::MODE_REWRITE === $mode
			? 'Edit the copy with the uncode-builder-edit abilities. When it is ready, a person opens it in the editor and clicks "Republish": its changes replace the original, which keeps its URL.'
			: 'Edit the copy with the uncode-builder-edit abilities. It is an independent draft: a person reviews it and copies its layout to the original, or publishes it in its place.';

		return array(
			'original_id'  => $original->ID,
			'copy_id'      => $copy->ID,
			'status'       => $copy->post_status,
			'mode'         => $mode,
			'reused'       => $reused,
			'preview_url'  => (string) get_preview_post_link( $copy ),
			'edit_url'     => (string) get_edit_post_link( $copy, 'raw' ),
			'how_to_apply' => $how_to_apply,
		);
	}
}
