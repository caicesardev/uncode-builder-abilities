<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Operations;
use Uncode_Builder_Abilities\Layout_Writer;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Snapshot_Store;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Restore_Snapshot extends Write_Ability {

	private Snapshot_Store $snapshots;

	public function __construct( Shortcode_Parser $parser, Layout_Operations $operations, Layout_Writer $writer, Snapshot_Store $snapshots ) {
		parent::__construct( $parser, $operations, $writer );
		$this->snapshots = $snapshots;
	}

	public function name(): string {
		return 'restore-snapshot';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$snapshot = $this->snapshots->latest( $post->ID );
		if ( null === $snapshot ) {
			return new WP_Error( 'uncode_builder_no_snapshot', 'There is no snapshot of this item to restore.', array( 'status' => 404 ) );
		}

		return array(
			'content'  => (string) $snapshot['content'],
			'meta'     => (array) ( $snapshot['meta'] ?? array() ),
			'focus'    => array(),
			'warnings' => array( sprintf( 'Restores the layout saved at %s, before "%s".', $snapshot['saved_at'], $snapshot['reason'] ) ),
		);
	}

	protected function is_destructive(): bool {
		return true;
	}

	protected function label(): string {
		return __( 'Restore the last layout snapshot', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Puts back the layout, and the page options changed with update-page-options, as they were before the last change made with these abilities. The layout being replaced becomes the new snapshot, so calling it again undoes the restore. get-page-tree lists the snapshots kept for an item (one by default). Requires the `version` from get-page-tree; use `dry_run` to preview.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema( $this->common_properties(), array( 'post_id', 'version' ) );
	}
}
