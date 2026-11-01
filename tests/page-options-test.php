<?php
const PAGE_ID = 179791;

wp_set_current_user( 1 );
if ( 'draft' !== get_post_status( PAGE_ID ) ) {
	exit( "ABORT: la página de pruebas no es un borrador\n" );
}

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 260 ), "\n";
}

function uba_run( string $name, array $input ) {
	return wp_get_ability( $name )->execute( $input );
}

function uba_version(): string {
	return md5( (string) get_post_field( 'post_content', PAGE_ID, 'raw' ) );
}

function uba_meta( string $key ) {
	return metadata_exists( 'post', PAGE_ID, $key ) ? get_metadata( 'post', PAGE_ID, $key, true ) : null;
}

function uba_errors( $result ): array {
	$data = is_wp_error( $result ) ? $result->get_error_data() : array();

	return array_column( is_array( $data ) ? ( $data['errors'] ?? array() ) : array(), 'message', 'at' );
}

function uba_fingerprints(): array {
	global $wpdb;
	$others = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND post_id <> %d ORDER BY meta_id", $wpdb->esc_like( '_uncode' ) . '%', PAGE_ID ), ARRAY_A );

	return array(
		'theme_options' => md5( maybe_serialize( get_option( 'uncode' ) ) ),
		'other_pages'   => md5( serialize( $others ) ) . ' (' . count( $others ) . ' metas)',
	);
}

global $wpdb;
$before_fingerprints = uba_fingerprints();
$draft_meta_before   = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", PAGE_ID, $wpdb->esc_like( '_uncode' ) . '%' ) );
$tested_keys         = array( '_uncode_header_type', '_uncode_specific_menu_opaque', '_uncode_specific_main_width', '_uncode_header_background', '_uncode_specific_main_width_inherit' );

try {
	echo "-- Leer\n";
	$read = uba_run( 'uncode-builder/get-page-options', array( 'post_id' => PAGE_ID ) );
	uba_check( 'get-page-options: secciones e índice', ! is_wp_error( $read ) && count( $read['sections'] ) >= 8 && 'General' === $read['returned_section'], is_wp_error( $read ) ? $read->get_error_message() : array( 'secciones' => array_map( 'count', $read['sections'] ), 'tamaño KB' => round( strlen( wp_json_encode( $read ) ) / 1024, 1 ) ) );
	$header = uba_run( 'uncode-builder/get-page-options', array( 'post_id' => PAGE_ID, 'fields' => array( '_uncode_header_type', '_uncode_header_height', 'no_existe' ) ) );
	uba_check( 'campos concretos y desconocidos', 2 === count( $header['fields'] ) && array( 'no_existe' ) === $header['unknown_fields'], $header['fields'][1] ?? array() );

	echo "-- Validación\n";
	$invalid = uba_run(
		'uncode-builder-edit/update-page-options',
		array(
			'post_id' => PAGE_ID,
			'version' => uba_version(),
			'dry_run' => true,
			'options' => array(
				'no_existe'                    => 'x',
				'_uncode_header_type'          => 'cabecera_inventada',
				'_uncode_specific_menu_opaque' => 'quizás',
				'_uncode_specific_main_width'  => '10 parsecs',
				'_uncode_header_overlay_color' => 'red',
				'_uncode_header_background'    => array( 'background-image' => 'javascript:alert(1)' ),
				'_uncode_specific_bg_color'    => 'fucsia',
			),
		)
	);
	uba_check( 'rechaza 7 valores inválidos', 7 === count( uba_errors( $invalid ) ), uba_errors( $invalid ) );

	echo "-- Cambiar, deshacer y rehacer\n";
	$changes = array(
		'_uncode_header_type'                => 'header_basic',
		'_uncode_specific_menu_opaque'       => true,
		'_uncode_specific_main_width_inherit' => 'custom',
		'_uncode_specific_main_width'        => '1100px',
		'_uncode_header_background'          => array( 'background-color' => 'color-wayh', 'background-size' => 'cover' ),
	);
	$preview = uba_run( 'uncode-builder-edit/update-page-options', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'dry_run' => true, 'options' => $changes ) );
	uba_check( 'dry_run describe los cambios sin guardar', ! is_wp_error( $preview ) && 5 === count( $preview['options'] ) && null === uba_meta( '_uncode_header_type' ), is_wp_error( $preview ) ? $preview->get_error_message() . wp_json_encode( uba_errors( $preview ) ) : array_keys( $preview['options'] ) );

	$saved = uba_run( 'uncode-builder-edit/update-page-options', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'options' => $changes ) );
	uba_check( 'guardado', ! is_wp_error( $saved ) && ! empty( $saved['saved'] ), is_wp_error( $saved ) ? $saved->get_error_message() : '' );
	uba_check( 'valores almacenados en el formato de Uncode', 'header_basic' === uba_meta( '_uncode_header_type' ) && 'on' === uba_meta( '_uncode_specific_menu_opaque' ) && array( '1100', 'px' ) === uba_meta( '_uncode_specific_main_width' ) && 'cover' === ( uba_meta( '_uncode_header_background' )['background-size'] ?? '' ) && 6 === count( (array) uba_meta( '_uncode_header_background' ) ), array( uba_meta( '_uncode_specific_main_width' ), uba_meta( '_uncode_header_background' ) ) );
	uba_check( 'el contenido no cambia', '' === get_post_field( 'post_content', PAGE_ID, 'raw' ) );
	uba_check( 'la copia guardada lista las opciones', array() !== ( $saved['snapshots'][0]['options'] ?? array() ), $saved['snapshots'][0]['options'] ?? array() );

	$read_after = uba_run( 'uncode-builder/get-page-options', array( 'post_id' => PAGE_ID, 'section' => 'header' ) );
	uba_check( 'get-page-options muestra los valores', 'header_basic' === ( (array) $read_after['values'] )['_uncode_header_type'] && 'Header' === $read_after['returned_section'] );

	$undo = uba_run( 'uncode-builder-edit/restore-snapshot', array( 'post_id' => PAGE_ID, 'version' => uba_version() ) );
	uba_check( 'restore-snapshot devuelve las opciones a su estado anterior', ! is_wp_error( $undo ) && ! empty( $undo['saved'] ) && null === uba_meta( '_uncode_header_type' ) && null === uba_meta( '_uncode_header_background' ), is_wp_error( $undo ) ? $undo->get_error_message() : '' );
	$redo = uba_run( 'uncode-builder-edit/restore-snapshot', array( 'post_id' => PAGE_ID, 'version' => uba_version() ) );
	uba_check( 'restaurar otra vez rehace el cambio', ! is_wp_error( $redo ) && 'header_basic' === uba_meta( '_uncode_header_type' ) );

	$reset = uba_run( 'uncode-builder-edit/update-page-options', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'options' => array_fill_keys( array_keys( $changes ), null ) ) );
	uba_check( 'null vuelve a heredar del tema', ! is_wp_error( $reset ) && array() === array_filter( array_map( 'uba_meta', array_keys( $changes ) ) ) );

	echo "-- Política y copias\n";
	wp_update_post( array( 'ID' => PAGE_ID, 'post_status' => 'private' ) );
	$refused = uba_run( 'uncode-builder-edit/update-page-options', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'options' => array( '_uncode_header_type' => 'none' ) ) );
	wp_update_post( array( 'ID' => PAGE_ID, 'post_status' => 'draft' ) );
	uba_check( 'item no borrador: rechazado', is_wp_error( $refused ) && 'uncode_builder_drafts_only' === $refused->get_error_code() );

	$autosave_id = _wp_put_post_revision( array( 'ID' => PAGE_ID, 'post_title' => get_the_title( PAGE_ID ), 'post_content' => '', 'post_author' => 1 ), true );
	$on_autosave = uba_run( 'uncode-builder-edit/update-page-options', array( 'post_id' => $autosave_id, 'version' => md5( '' ), 'options' => array( '_uncode_header_type' => 'none' ) ) );
	uba_check( 'autoguardado: rechazado con explicación', is_wp_error( $on_autosave ) && 'uncode_builder_meta_in_autosave' === $on_autosave->get_error_code(), is_wp_error( $on_autosave ) ? $on_autosave->get_error_message() : 'aceptado' );
	wp_delete_post_revision( $autosave_id );

	echo "-- Vista previa\n";
	$inserted = uba_run( 'uncode-builder-edit/insert-template', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'template' => 'default:pricing-table-wide' ) );
	uba_check( 'plantilla insertada para renderizar', ! is_wp_error( $inserted ) && ! empty( $inserted['saved'] ) );
	$render = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID ) );
	$headings = array_values( array_filter( $render['outline'] ?? array(), static fn( $entry ) => isset( $entry['heading'] ) ) );
	uba_check( 'render-preview: esquema legible', ! is_wp_error( $render ) && array() !== $headings && array() === $render['unrendered_shortcodes'], is_wp_error( $render ) ? $render->get_error_message() : array( 'html KB' => round( $render['html_bytes'] / 1024, 1 ), 'palabras' => $render['words'], 'entradas' => count( $render['outline'] ), 'primeros' => array_slice( $render['outline'], 0, 4 ), 'problemas' => $render['issues'] ) );
	uba_check( 'enlace de vista previa del borrador', false !== strpos( $render['preview_url'] ?? '', 'preview=true' ), $render['preview_url'] ?? '' );
	$section = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID, 'path' => '0.0' ) );
	uba_check( 'render-preview de una sola columna', ! is_wp_error( $section ) && $section['html_bytes'] < $render['html_bytes'], is_wp_error( $section ) ? $section->get_error_message() : $section['html_bytes'] . ' bytes' );
	$broken = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID, 'path' => '99' ) );
	uba_check( 'ruta inexistente', is_wp_error( $broken ) );
} finally {
	wp_update_post( array( 'ID' => PAGE_ID, 'post_status' => 'draft', 'post_content' => '' ) );
	$draft_meta_now = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", PAGE_ID, $wpdb->esc_like( '_uncode' ) . '%' ) );
	foreach ( array_diff( $draft_meta_now, $draft_meta_before ) as $added_key ) {
		delete_post_meta( PAGE_ID, $added_key );
	}
	foreach ( array( '_wpb_shortcodes_custom_css', '_wpb_shortcodes_custom_css_updated', '_wpb_shortcodes_default_css', '_wpb_shortcodes_default_css_updated' ) as $key ) {
		delete_post_meta( PAGE_ID, $key );
	}

	$after_fingerprints = uba_fingerprints();
	uba_check( 'opciones globales del tema intactas', $before_fingerprints['theme_options'] === $after_fingerprints['theme_options'] );
	uba_check( 'opciones de página de las demás entradas intactas', $before_fingerprints['other_pages'] === $after_fingerprints['other_pages'], $after_fingerprints['other_pages'] );
	uba_check( 'borrador devuelto a su estado inicial', '' === get_post_field( 'post_content', PAGE_ID, 'raw' ) && 'draft' === get_post_status( PAGE_ID ) );
}
