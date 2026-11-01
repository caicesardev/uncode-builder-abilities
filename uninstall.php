<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

const UNCODE_BUILDER_ABILITIES_OPTIONS = array( 'uncode_builder_abilities_write_policy', 'uncode_builder_abilities_snapshot_limit', 'uncode_builder_abilities_draft_copy_strategy' );

const UNCODE_BUILDER_ABILITIES_POST_META = array( '_uncode_builder_abilities_snapshots', '_uncode_builder_abilities_copy_of' );

function uncode_builder_abilities_remove_site_data(): void {
	foreach ( UNCODE_BUILDER_ABILITIES_OPTIONS as $option ) {
		delete_option( $option );
	}

	foreach ( UNCODE_BUILDER_ABILITIES_POST_META as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}
}

if ( ! is_multisite() ) {
	uncode_builder_abilities_remove_site_data();
	return;
}

foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
	switch_to_blog( $site_id );
	uncode_builder_abilities_remove_site_data();
	restore_current_blog();
}
