<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Snapshot_Store {

	const META_KEY = '_uncode_builder_abilities_snapshots';

	const DEFAULT_LIMIT = 1;

	public function push( WP_Post $post, string $reason ): void {
		$limit = max( 1, (int) apply_filters( 'uncode_builder_abilities_snapshot_limit', self::DEFAULT_LIMIT, $post ) );

		$snapshots = $this->all( $post->ID );
		array_unshift(
			$snapshots,
			array(
				'content'  => $post->post_content,
				'version'  => Layout_Document::version_of( $post->post_content ),
				'saved_at' => gmdate( 'c' ),
				'user_id'  => get_current_user_id(),
				'reason'   => $reason,
			)
		);

		update_metadata( 'post', $post->ID, self::META_KEY, wp_slash( array_slice( $snapshots, 0, $limit ) ) );
	}

	public function latest( int $post_id ): ?array {
		return $this->all( $post_id )[0] ?? null;
	}

	public function summaries( int $post_id ): array {
		return array_map(
			static function ( array $snapshot ): array {
				unset( $snapshot['content'] );
				return $snapshot;
			},
			$this->all( $post_id )
		);
	}

	private function all( int $post_id ): array {
		$snapshots = get_metadata( 'post', $post_id, self::META_KEY, true );

		return is_array( $snapshots ) ? array_values( $snapshots ) : array();
	}
}
