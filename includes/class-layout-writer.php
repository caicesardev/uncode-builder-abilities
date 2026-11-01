<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Layout_Writer {

	const BUILDER_STATUS_META = '_wpb_vc_js_status';

	const BUILDER_CSS_TYPES = array( 'default', 'custom' );

	private Snapshot_Store $snapshots;

	public function __construct( Snapshot_Store $snapshots ) {
		$this->snapshots = $snapshots;
	}

	public function commit( WP_Post $post, string $content, string $reason ) {
		$this->snapshots->push( $post, $reason );

		$saved = self::save_content( $post->ID, $content );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		update_post_meta( $post->ID, self::BUILDER_STATUS_META, 'true' );
		self::rebuild_builder_css( $post->ID );
		do_action( 'litespeed_purge_post', $post->ID );
		do_action( 'uncode_builder_abilities_layout_saved', $post->ID, $reason );

		$stored = (string) get_post_field( 'post_content', $post->ID, 'raw' );
		$reply  = array(
			'version'   => Layout_Document::version_of( $stored ),
			'snapshots' => $this->snapshots->summaries( $post->ID ),
		);

		if ( $stored !== $content ) {
			$reply['warnings'] = array( 'WordPress filters changed the content while saving it (for example markup this user may not publish). Read the page again before the next change.' );
		}

		return $reply;
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

	private static function rebuild_builder_css( int $post_id ): void {
		if ( ! function_exists( 'wpbakery' ) ) {
			return;
		}

		foreach ( self::BUILDER_CSS_TYPES as $type ) {
			wpbakery()->buildShortcodesCss( $post_id, $type );
		}
	}
}
