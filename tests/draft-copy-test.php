<?php
wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . $text, "\n";
}

function uba_run( string $name, array $input ) {
	return wp_get_ability( $name )->execute( $input );
}

function uba_error( $result ): string {
	return is_wp_error( $result ) ? $result->get_error_code() . ': ' . $result->get_error_message() : 'sin error';
}

function uba_heading_row( string $text ): array {
	return array( 'tag' => 'vc_row', 'children' => array( array( 'tag' => 'vc_column', 'children' => array( array( 'tag' => 'vc_custom_heading', 'content' => $text ) ) ) ) );
}

function uba_temporary_page(): int {
	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => '[TEST] MCP copia temporal - no publicar',
			'post_content' => '[vc_row][vc_column width="1/1"][vc_column_text]<p>Original</p>[/vc_column_text][/vc_column][/vc_row]',
			'meta_input'   => array( '_yoast_wpseo_meta-robots-noindex' => '1' ),
		),
		true
	);
	wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish', 'post_password' => wp_generate_password( 20, false ) ) );

	return $page_id;
}

$created = array();

try {
	$page_id   = uba_temporary_page();
	$created[] = $page_id;
	$original  = (string) get_post_field( 'post_content', $page_id, 'raw' );

	echo "-- Estrategia por defecto con Duplicate Post activo\n";
	$rewrite = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	if ( ! is_wp_error( $rewrite ) ) {
		$created[] = $rewrite['copy_id'];
	}
	uba_check( 'copia Reescribir y republicar', ! is_wp_error( $rewrite ) && 'rewrite_and_republish' === $rewrite['strategy'] && 'draft' === get_post_status( $rewrite['copy_id'] ), is_wp_error( $rewrite ) ? uba_error( $rewrite ) : array_intersect_key( $rewrite, array_flip( array( 'copy_id', 'strategy', 'reused' ) ) ) );
	$again = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'se reutiliza', ! is_wp_error( $again ) && $again['copy_id'] === $rewrite['copy_id'] && $again['reused'] );
	delete_post_meta( $page_id, '_dp_has_rewrite_republish_copy' );

	echo "-- Sin Duplicate Post: autoguardado nativo\n";
	add_filter( 'duplicate_post_enabled_post_types', '__return_empty_array' );

	$autosave = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'copia = autoguardado de WordPress', ! is_wp_error( $autosave ) && 'autosave' === $autosave['strategy'] && (int) wp_is_post_autosave( $autosave['copy_id'] ) === $page_id, is_wp_error( $autosave ) ? uba_error( $autosave ) : array_intersect_key( $autosave, array_flip( array( 'copy_id', 'strategy', 'reused', 'edit_url' ) ) ) );
	uba_check( 'el autoguardado parte del layout publicado', get_post_field( 'post_content', $autosave['copy_id'], 'raw' ) === $original );

	$reused = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'se reutiliza el autoguardado', ! is_wp_error( $reused ) && $reused['copy_id'] === $autosave['copy_id'] && $reused['reused'] );

	$copy_tree = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => $autosave['copy_id'], 'decode' => false ) );
	uba_check( 'get-page-tree lee el autoguardado', ! is_wp_error( $copy_tree ) && $page_id === $copy_tree['post']['copy_of'], is_wp_error( $copy_tree ) ? uba_error( $copy_tree ) : $copy_tree['post'] );

	$original_tree = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => $page_id, 'depth' => 1 ) );
	uba_check( 'el original indica su copia', (int) $original_tree['post']['draft_copy_id'] === $autosave['copy_id'] );

	$edited = uba_run( 'uncode-builder-edit/insert-elements', array( 'post_id' => $autosave['copy_id'], 'version' => $copy_tree['version'], 'elements' => array( uba_heading_row( 'Cambio en el autoguardado' ) ) ) );
	uba_check( 'editar el autoguardado', ! is_wp_error( $edited ) && ! empty( $edited['saved'] ), uba_error( $edited ) );
	uba_check( 'sigue siendo un autoguardado de la página', 'revision' === get_post_type( $autosave['copy_id'] ) && (int) wp_is_post_autosave( $autosave['copy_id'] ) === $page_id );
	uba_check( 'el original publicado sigue intacto', get_post_field( 'post_content', $page_id, 'raw' ) === $original && 'publish' === get_post_status( $page_id ) );
	uba_check( 'la copia guardada se queda en el autoguardado, no en la página', empty( get_metadata( 'post', $page_id, '_uncode_builder_abilities_snapshots', true ) ) && 1 === count( (array) get_metadata( 'post', $autosave['copy_id'], '_uncode_builder_abilities_snapshots', true ) ) );

	wp_restore_post_revision( $autosave['copy_id'] );
	uba_check( 'restaurar el autoguardado (lo que hace la persona) aplica el cambio', false !== strpos( get_post_field( 'post_content', $page_id, 'raw' ), 'Cambio en el autoguardado' ) );

	wp_update_post( array( 'ID' => $page_id, 'post_content' => $original ) );
	$stale = get_post( $autosave['copy_id'] );
	$wpdb_time = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
	$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_modified_gmt' => $wpdb_time, 'post_modified' => $wpdb_time ), array( 'ID' => $stale->ID ) );
	clean_post_cache( $stale->ID );
	$refreshed = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'un autoguardado antiguo se actualiza con la versión publicada', ! is_wp_error( $refreshed ) && false === $refreshed['reused'] && get_post_field( 'post_content', $refreshed['copy_id'], 'raw' ) === $original, is_wp_error( $refreshed ) ? uba_error( $refreshed ) : array_intersect_key( $refreshed, array_flip( array( 'copy_id', 'reused' ) ) ) );

	wp_set_current_user( 0 );
	$anonymous = uba_run( 'uncode-builder-edit/insert-elements', array( 'post_id' => $refreshed['copy_id'], 'version' => md5( $original ), 'dry_run' => true, 'elements' => array( uba_heading_row( 'x' ) ) ) );
	wp_set_current_user( 1 );
	uba_check( 'sin usuario no se puede editar el autoguardado', is_wp_error( $anonymous ), uba_error( $anonymous ) );

	remove_filter( 'duplicate_post_enabled_post_types', '__return_empty_array' );

	echo "-- Estrategia forzada por filtro\n";
	add_filter( 'uncode_builder_abilities_draft_copy_strategy', static fn() => 'autosave' );
	$forced = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'el filtro elige autoguardado aunque haya Duplicate Post', ! is_wp_error( $forced ) && 'autosave' === $forced['strategy'], is_wp_error( $forced ) ? uba_error( $forced ) : $forced['strategy'] );
} finally {
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		delete_post_meta( $id, '_dp_has_rewrite_republish_copy' );
		wp_trash_post( $id );
	}
	$left = array_filter( $created, static fn( $id ) => 'trash' !== get_post_status( $id ) );
	uba_check( 'limpieza: página temporal y copias en la papelera', array() === $left, array( 'en papelera' => $created ) );
}
