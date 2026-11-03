<?php

namespace Uncode_Builder_Abilities;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Draft_Copies {

	const STRATEGY_REWRITE = 'rewrite_and_republish';

	const STRATEGY_AUTOSAVE = 'autosave';

	const REWRITE_COPY_OF_META = '_dp_is_rewrite_republish_copy';

	const REWRITE_COPY_META = '_dp_has_rewrite_republish_copy';

	const COPY_ORIGINAL_META = '_dp_original';

	const REWRITE_COPY_STATUS = 'draft';

	const AUTOSAVE_FIELDS = array( 'post_title', 'post_content', 'post_excerpt' );

	const HOW_TO_APPLY = array(
		self::STRATEGY_REWRITE  => 'Edit the copy with the uncode-builder-edit abilities. When it is ready, a person opens it in the editor and clicks "Republish": its changes replace the original, which keeps its URL.',
		self::STRATEGY_AUTOSAVE => 'The copy is a WordPress autosave of the page: unpublished changes that live next to it. Edit it with the uncode-builder-edit abilities using `copy_id`. To apply it, a person opens the page in the editor: WordPress warns that a more recent autosave exists, "Preview" shows it, and "View the autosave" → "Restore This Autosave" publishes it. Each user has one autosave per page, and editing the page by hand in the editor overwrites it.',
	);

	public static function register(): void {
		add_filter( 'duplicate_post_excludelist_filter', array( __CLASS__, 'exclude_snapshots_from_copies' ) );
	}

	public static function exclude_snapshots_from_copies( $excluded ): array {
		return array_merge( (array) $excluded, array( Snapshot_Store::META_KEY ) );
	}

	public static function is_autosave( WP_Post $post ): bool {
		return 'revision' === $post->post_type && false !== wp_is_post_autosave( $post );
	}

	public static function is_layout_target( WP_Post $post ): bool {
		return 'revision' !== $post->post_type || ( self::is_autosave( $post ) && get_current_user_id() === (int) $post->post_author );
	}

	public static function original_id( WP_Post $post ): ?int {
		if ( self::is_autosave( $post ) ) {
			return (int) $post->post_parent;
		}

		if ( ! get_post_meta( $post->ID, self::REWRITE_COPY_OF_META, true ) ) {
			return null;
		}

		$original = (int) get_post_meta( $post->ID, self::COPY_ORIGINAL_META, true );

		return $original > 0 ? $original : null;
	}

	public static function permission_target_id( WP_Post $post ): int {
		return self::is_autosave( $post ) ? (int) $post->post_parent : $post->ID;
	}

	public static function existing_copy_id( WP_Post $original ): int {
		$rewrite_copy = (int) get_post_meta( $original->ID, self::REWRITE_COPY_META, true );
		if ( $rewrite_copy > 0 && get_post( $rewrite_copy ) instanceof WP_Post ) {
			return $rewrite_copy;
		}

		$autosave = wp_get_post_autosave( $original->ID, get_current_user_id() );

		return $autosave && self::is_current( $autosave, $original ) ? $autosave->ID : 0;
	}

	public function create( WP_Post $original ) {
		if ( Write_Policy::is_draft( $original ) ) {
			return new WP_Error(
				'uncode_builder_already_a_draft',
				sprintf( '"%s" is already a %s and can be edited directly.', $original->post_title, self::is_autosave( $original ) ? 'draft copy' : $original->post_status ),
				array( 'status' => 400 )
			);
		}

		$rewrite_available = $this->can_rewrite_and_republish( $original ) || $this->has_rewrite_copy( $original );
		$configured        = Settings_Page::draft_copy_strategy();
		$preferred         = Settings_Page::AUTOMATIC_STRATEGY === $configured ? self::STRATEGY_REWRITE : $configured;
		$strategy          = (string) apply_filters( 'uncode_builder_abilities_draft_copy_strategy', $rewrite_available && self::STRATEGY_REWRITE === $preferred ? self::STRATEGY_REWRITE : self::STRATEGY_AUTOSAVE, $original );

		return self::STRATEGY_REWRITE === $strategy && $rewrite_available ? $this->rewrite_copy( $original ) : $this->autosave_copy( $original );
	}

	private function rewrite_copy( WP_Post $original ) {
		if ( $this->has_rewrite_copy( $original ) ) {
			return $this->reply( $original, get_post( self::existing_copy_id( $original ) ), self::STRATEGY_REWRITE, true );
		}

		$copy_id = ( new \Yoast\WP\Duplicate_Post\Post_Duplicator() )->create_duplicate_for_rewrite_and_republish( $original );
		if ( is_wp_error( $copy_id ) ) {
			return $copy_id;
		}

		if ( self::REWRITE_COPY_STATUS !== get_post_status( $copy_id ) ) {
			wp_update_post(
				array(
					'ID'          => $copy_id,
					'post_status' => self::REWRITE_COPY_STATUS,
				)
			);
		}

		return $this->reply( $original, get_post( $copy_id ), self::STRATEGY_REWRITE, false );
	}

	private function autosave_copy( WP_Post $original ) {
		$autosave = wp_get_post_autosave( $original->ID, get_current_user_id() );

		if ( $autosave && self::is_current( $autosave, $original ) ) {
			return $this->reply( $original, $autosave, self::STRATEGY_AUTOSAVE, true );
		}

		$fields = array_intersect_key( get_post( $original->ID, ARRAY_A ), array_flip( array_merge( array( 'ID' ), self::AUTOSAVE_FIELDS ) ) );

		if ( $autosave ) {
			$saved = wp_update_post( wp_slash( array_merge( $fields, array( 'ID' => $autosave->ID ) ) ), true );
		} else {
			$saved = _wp_put_post_revision( array_merge( $fields, array( 'post_author' => get_current_user_id() ) ), true );
		}

		if ( is_wp_error( $saved ) || ! $saved ) {
			return is_wp_error( $saved ) ? $saved : new WP_Error( 'uncode_builder_copy_failed', 'WordPress could not create the autosave.', array( 'status' => 500 ) );
		}

		return $this->reply( $original, get_post( $saved ), self::STRATEGY_AUTOSAVE, false );
	}

	private static function is_current( WP_Post $autosave, WP_Post $original ): bool {
		return strtotime( $autosave->post_modified_gmt ) >= strtotime( $original->post_modified_gmt );
	}

	private function has_rewrite_copy( WP_Post $original ): bool {
		$copy_id = (int) get_post_meta( $original->ID, self::REWRITE_COPY_META, true );

		return $copy_id > 0 && get_post( $copy_id ) instanceof WP_Post;
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

	private function reply( WP_Post $original, WP_Post $copy, string $strategy, bool $reused ): array {
		$edited_in_editor_of = self::is_autosave( $copy ) ? $original : $copy;

		return array(
			'original_id'  => $original->ID,
			'copy_id'      => $copy->ID,
			'strategy'     => $strategy,
			'reused'       => $reused,
			'edit_url'     => (string) get_edit_post_link( $edited_in_editor_of, 'raw' ),
			'how_to_apply' => self::HOW_TO_APPLY[ $strategy ],
		);
	}
}
