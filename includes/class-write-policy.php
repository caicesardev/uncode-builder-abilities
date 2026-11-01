<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Write_Policy {

	const OPTION = 'uncode_builder_abilities_write_policy';

	const DRAFTS_ONLY = 'drafts';

	const ANY_CONTENT = 'any';

	const DRAFT_STATUSES = array( 'draft', 'pending', 'auto-draft' );

	public static function choices(): array {
		return array(
			self::DRAFTS_ONLY => __( 'Only drafts, pending items and draft copies (recommended)', 'uncode-builder-abilities' ),
			self::ANY_CONTENT => __( 'Any item the user can edit, including published pages', 'uncode-builder-abilities' ),
		);
	}

	public static function sanitize( $value ): string {
		return self::ANY_CONTENT === $value ? self::ANY_CONTENT : self::DRAFTS_ONLY;
	}

	public static function current(): string {
		return self::sanitize( get_option( self::OPTION, self::DRAFTS_ONLY ) );
	}

	public static function allows( WP_Post $post ): bool {
		$allowed = self::ANY_CONTENT === self::current() || self::is_draft( $post );

		return (bool) apply_filters( 'uncode_builder_abilities_write_allowed', $allowed, $post );
	}

	public static function is_draft( WP_Post $post ): bool {
		return in_array( $post->post_status, self::DRAFT_STATUSES, true ) || Draft_Copies::is_autosave( $post );
	}
}
