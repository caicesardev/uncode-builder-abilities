<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Write_Policy {

	const OPTION = 'uncode_builder_abilities_write_policy';

	const DRAFTS_ONLY = 'drafts';

	const ANY_CONTENT = 'any';

	const DRAFT_STATUSES = array( 'draft', 'pending', 'auto-draft' );

	const SETTINGS_PAGE = 'writing';

	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
	}

	public static function register_setting(): void {
		register_setting(
			self::SETTINGS_PAGE,
			self::OPTION,
			array(
				'type'              => 'string',
				'default'           => self::DRAFTS_ONLY,
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);

		add_settings_field(
			self::OPTION,
			__( 'AI page builder edits', 'uncode-builder-abilities' ),
			array( __CLASS__, 'render_field' ),
			self::SETTINGS_PAGE
		);
	}

	public static function sanitize( $value ): string {
		return self::ANY_CONTENT === $value ? self::ANY_CONTENT : self::DRAFTS_ONLY;
	}

	public static function render_field(): void {
		$current = self::current();
		$choices = array(
			self::DRAFTS_ONLY => __( 'Only drafts and pending items (recommended)', 'uncode-builder-abilities' ),
			self::ANY_CONTENT => __( 'Any item the user can edit, including published pages', 'uncode-builder-abilities' ),
		);

		printf( '<select name="%s" id="%s">', esc_attr( self::OPTION ), esc_attr( self::OPTION ) );
		foreach ( $choices as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		printf( '<p class="description">%s</p>', esc_html__( 'Which items the Uncode Builder Abilities may change when an AI agent edits a layout.', 'uncode-builder-abilities' ) );
	}

	public static function current(): string {
		return self::sanitize( get_option( self::OPTION, self::DRAFTS_ONLY ) );
	}

	public static function allows( WP_Post $post ): bool {
		$allowed = self::ANY_CONTENT === self::current() || in_array( $post->post_status, self::DRAFT_STATUSES, true );

		return (bool) apply_filters( 'uncode_builder_abilities_write_allowed', $allowed, $post );
	}
}
