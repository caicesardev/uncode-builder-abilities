<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Layout_Writer {

	const BUILDER_STATUS_META = '_wpb_vc_js_status';

	const BUILDER_CSS_TYPES = array( 'default', 'custom' );

	const EXCERPT_CONTEXT = 50;

	private Snapshot_Store $snapshots;

	public function __construct( Snapshot_Store $snapshots ) {
		$this->snapshots = $snapshots;
	}

	public function commit( WP_Post $post, string $content, string $reason, array $meta = array() ) {
		$this->snapshots->push( $post, $reason, array_keys( $meta ) );

		if ( $content !== $post->post_content ) {
			$saved = self::save_content( $post->ID, $content );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			update_metadata( 'post', $post->ID, self::BUILDER_STATUS_META, 'true' );
			self::rebuild_builder_css( $post->ID );
		}

		self::save_meta( $post->ID, $meta );
		do_action( 'litespeed_purge_post', $post->ID );
		do_action( 'uncode_builder_abilities_layout_saved', $post->ID, $reason );

		$stored = (string) get_post_field( 'post_content', $post->ID, 'raw' );
		$reply  = array(
			'version'   => Layout_Document::version_of( $stored ),
			'snapshots' => $this->snapshots->summaries( $post->ID ),
		);

		if ( $stored === $content ) {
			return $reply;
		}

		if ( self::tag_sequence( $stored ) === self::tag_sequence( $content ) ) {
			$reply['notes'] = array(
				sprintf(
					'The theme normalized some attributes while saving, as its editor does (first change near "%s"). The structure and paths are unchanged and `version` already matches the saved layout.',
					self::excerpt_at_first_difference( $stored, $content )
				),
			);
		} else {
			$reply['warnings'] = array( 'WordPress filters changed the layout structure while saving (for example markup this user may not publish). Read the page again before the next change.' );
		}

		return $reply;
	}

	private static function tag_sequence( string $content ): array {
		preg_match_all( '/\[\/?([A-Za-z0-9_-]+)/', $content, $matches );

		return $matches[1];
	}

	private static function excerpt_at_first_difference( string $stored, string $intended ): string {
		$offset = strspn( $stored ^ $intended, "\0" );

		return substr( $stored, max( 0, $offset - self::EXCERPT_CONTEXT ), 2 * self::EXCERPT_CONTEXT );
	}

	private static function save_content( int $post_id, string $content ) {
		// Uncode's page-options meta box saves on every save_post while $_POST is not empty and does not require its nonce, which would blank the page options.
		$request_fields = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST          = array();

		try {
			return wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					)
				),
				true
			);
		} finally {
			$_POST = $request_fields;
		}
	}

	private static function save_meta( int $post_id, array $meta ): void {
		foreach ( $meta as $key => $value ) {
			if ( null === $value ) {
				delete_metadata( 'post', $post_id, $key );
			} else {
				update_metadata( 'post', $post_id, $key, wp_slash( $value ) );
			}
		}
	}

	private static function rebuild_builder_css( int $post_id ): void {
		if ( ! function_exists( 'wpbakery' ) ) {
			return;
		}

		foreach ( self::BUILDER_CSS_TYPES as $type ) {
			wpbakery()->buildShortcodesCss( $post_id, $type );
		}
	}
}
