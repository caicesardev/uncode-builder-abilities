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

$created = array();
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
$created[] = $page_id;

try {
	$on_draft = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'copia de un borrador: no hace falta', is_wp_error( $on_draft ) && 'uncode_builder_already_a_draft' === $on_draft->get_error_code(), uba_error( $on_draft ) );

	wp_update_post( array( 'ID' => $page_id, 'post_status' => 'publish', 'post_password' => wp_generate_password( 20, false ) ) );
	$original_content = (string) get_post_field( 'post_content', $page_id, 'raw' );

	$tree    = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => $page_id, 'decode' => false ) );
	$refused = uba_run( 'uncode-builder-edit/insert-elements', array( 'post_id' => $page_id, 'version' => $tree['version'], 'dry_run' => true, 'elements' => array( array( 'tag' => 'vc_row', 'children' => array( array( 'tag' => 'vc_column' ) ) ) ) ) );
	uba_check( 'editar el original publicado: rechazado y sugiere la copia', is_wp_error( $refused ) && false !== strpos( $refused->get_error_message(), 'create-draft-copy' ), uba_error( $refused ) );

	$copy = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	if ( ! is_wp_error( $copy ) ) {
		$created[] = $copy['copy_id'];
	}
	uba_check( 'copia Reescribir y republicar creada', ! is_wp_error( $copy ) && 'rewrite_and_republish' === $copy['mode'] && 'draft' === $copy['status'] && false === $copy['reused'], is_wp_error( $copy ) ? uba_error( $copy ) : array_intersect_key( $copy, array_flip( array( 'copy_id', 'mode', 'status', 'reused' ) ) ) );
	uba_check( 'copia enlazada como Duplicate Post la espera', 1 === (int) get_post_meta( $copy['copy_id'], '_dp_is_rewrite_republish_copy', true ) && (int) $copy['copy_id'] === (int) get_post_meta( $page_id, '_dp_has_rewrite_republish_copy', true ) );
	uba_check( 'la copia tiene el mismo layout', get_post_field( 'post_content', $copy['copy_id'], 'raw' ) === $original_content );

	$again = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	uba_check( 'segunda petición reutiliza la copia', ! is_wp_error( $again ) && $again['copy_id'] === $copy['copy_id'] && true === $again['reused'], is_wp_error( $again ) ? uba_error( $again ) : $again['copy_id'] );

	$original_tree = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => $page_id, 'depth' => 1 ) );
	uba_check( 'get-page-tree del original indica la copia', (int) ( $original_tree['post']['draft_copy_id'] ?? 0 ) === (int) $copy['copy_id'] );

	$copy_tree = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => $copy['copy_id'], 'decode' => false ) );
	$edited    = uba_run(
		'uncode-builder-edit/insert-elements',
		array(
			'post_id'  => $copy['copy_id'],
			'version'  => $copy_tree['version'],
			'elements' => array( array( 'tag' => 'vc_row', 'children' => array( array( 'tag' => 'vc_column', 'children' => array( array( 'tag' => 'vc_custom_heading', 'content' => 'Cambio en la copia' ) ) ) ) ) ),
		)
	);
	uba_check( 'editar la copia: permitido', ! is_wp_error( $edited ) && ! empty( $edited['saved'] ), uba_error( $edited ) );
	uba_check( 'el original publicado sigue intacto', get_post_field( 'post_content', $page_id, 'raw' ) === $original_content && 'publish' === get_post_status( $page_id ) );
	uba_check( 'las copias guardadas no viajan a la copia de Duplicate Post', in_array( '_uncode_builder_abilities_snapshots', apply_filters( 'duplicate_post_excludelist_filter', array() ), true ) );

	delete_post_meta( $page_id, '_dp_has_rewrite_republish_copy' );
	add_filter( 'duplicate_post_enabled_post_types', '__return_empty_array' );
	$plain = uba_run( 'uncode-builder-edit/create-draft-copy', array( 'post_id' => $page_id ) );
	remove_filter( 'duplicate_post_enabled_post_types', '__return_empty_array' );
	if ( ! is_wp_error( $plain ) ) {
		$created[] = $plain['copy_id'];
	}
	uba_check( 'sin Duplicate Post: copia en borrador independiente', ! is_wp_error( $plain ) && 'draft_copy' === $plain['mode'] && 'draft' === $plain['status'] && (int) get_post_meta( $plain['copy_id'], '_uncode_builder_abilities_copy_of', true ) === $page_id, is_wp_error( $plain ) ? uba_error( $plain ) : array_intersect_key( $plain, array_flip( array( 'copy_id', 'mode', 'status' ) ) ) );
	uba_check( 'la copia independiente conserva las metas de la página', '1' === get_post_meta( $plain['copy_id'], '_yoast_wpseo_meta-robots-noindex', true ) );
} finally {
	wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
	delete_post_meta( $page_id, '_dp_has_rewrite_republish_copy' );
	foreach ( $created as $id ) {
		wp_trash_post( $id );
	}
	$left = array_filter( $created, static fn( $id ) => 'trash' !== get_post_status( $id ) );
	uba_check( 'limpieza: página temporal y copias en la papelera', array() === $left, array( 'en papelera' => $created ) );
}
