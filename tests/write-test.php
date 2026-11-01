<?php
const PAGE_ID = 179791;

wp_set_current_user( 1 );
if ( 'draft' !== get_post_status( PAGE_ID ) ) {
	exit( "ABORT: la página de pruebas no es un borrador\n" );
}

function uba_run( string $name, array $input ) {
	return wp_get_ability( $name )->execute( $input );
}

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . $text, "\n";
}

function uba_tree( bool $decode = false ) {
	return wp_get_ability( 'uncode-builder/get-page-tree' )->execute( array( 'post_id' => PAGE_ID, 'decode' => $decode ) );
}

function uba_error( $result ) {
	if ( ! is_wp_error( $result ) ) {
		return $result;
	}
	$data = $result->get_error_data();
	return $result->get_error_message() . ' ' . wp_json_encode( is_array( $data ) ? ( $data['errors'] ?? array() ) : array(), JSON_UNESCAPED_UNICODE );
}

function uba_content(): string {
	return (string) get_post_field( 'post_content', PAGE_ID, 'raw' );
}

$before       = uba_tree();
$original     = uba_content();
$original_ids = array_values( array_filter( array_map( static fn( $n ) => $n['id'] ?? null, $before['tree'] ) ) );
uba_check( 'lectura inicial', isset( $before['version'] ), array( 'filas' => count( $before['tree'] ), 'snapshots' => count( $before['snapshots'] ) ) );

$invalid = uba_run( 'uncode-builder-edit/insert-elements', array(
	'post_id'  => PAGE_ID,
	'version'  => $before['version'],
	'dry_run'  => true,
	'elements' => array(
		array( 'tag' => 'vc_column_text', 'content' => '<p>suelto</p>' ),
		array(
			'tag'        => 'vc_row',
			'attributes' => array( 'no_existe' => 'x' ),
			'children'   => array(
				array(
					'tag'      => 'vc_column',
					'children' => array(
						array( 'tag' => 'vc_column', 'children' => array() ),
						array( 'tag' => 'vc_custom_heading', 'attributes' => array( 'heading_semantic' => 'h9' ), 'content' => 'x' ),
					),
				),
			),
		),
	),
) );
$errors = is_wp_error( $invalid ) ? $invalid->get_error_data()['errors'] : array();
uba_check( 'validación rechaza 4 errores (raíz, parámetro, anidado, opción)', 4 === count( $errors ), array_map( static fn( $e ) => $e['at'] . ': ' . $e['message'], $errors ) );

$section = array(
	'tag'        => 'vc_row',
	'attributes' => array( 'back_color_type' => 'uncode-palette', 'back_color' => 'color-lxmt' ),
	'children'   => array(
		array(
			'tag'        => 'vc_column',
			'attributes' => array( 'width' => '1/2' ),
			'children'   => array(
				array( 'tag' => 'vc_custom_heading', 'attributes' => array( 'heading_semantic' => 'h2', 'text_color_type' => 'uncode-palette', 'text_color' => 'accent' ), 'content' => 'Sección creada por MCP' ),
				array( 'tag' => 'vc_column_text', 'attributes' => array( 'css' => array( 'margin-top' => '10px', 'padding-left' => '6px' ) ), 'content' => '<p>Texto con "comillas" y [corchetes] escapados.</p>' ),
			),
		),
		array(
			'tag'        => 'vc_column',
			'attributes' => array( 'width' => '1/2' ),
			'children'   => array(
				array( 'tag' => 'vc_button', 'attributes' => array( 'link' => array( 'url' => '/contacto/', 'title' => 'Hablemos "ya"', 'target' => '_blank' ) ), 'content' => 'Hablemos' ),
				array( 'tag' => 'vc_empty_space', 'attributes' => array( 'empty_h' => '2' ) ),
			),
		),
	),
);

$preview = uba_run( 'uncode-builder-edit/insert-elements', array( 'post_id' => PAGE_ID, 'version' => $before['version'], 'dry_run' => true, 'elements' => array( $section ) ) );
uba_check( 'dry_run inserta sin guardar', ! is_wp_error( $preview ) && empty( $preview['saved'] ) && uba_content() === $original, is_wp_error( $preview ) ? uba_error( $preview ) : array( 'elements' => $preview['elements'], 'ids' => count( $preview['generated_ids'] ) ) );
if ( is_wp_error( $preview ) ) {
	exit;
}
echo '      snippet: ', $preview['snippet'], "\n";

$inserted = uba_run( 'uncode-builder-edit/insert-elements', array( 'post_id' => PAGE_ID, 'version' => $before['version'], 'elements' => array( $section ) ) );
$saved    = uba_content();
uba_check( 'insert guardado', ! is_wp_error( $inserted ) && ! empty( $inserted['saved'] ), is_wp_error( $inserted ) ? uba_error( $inserted ) : array( 'path' => $inserted['elements'][0]['path'] ?? null, 'snapshots' => count( $inserted['snapshots'] ?? array() ) ) );
if ( is_wp_error( $inserted ) ) {
	exit;
}
uba_check( 'contenido previo intacto byte a byte', 0 === strpos( $saved, $original ) );
uba_check( 'versión nueva = md5 guardado', ( $inserted['version'] ?? '' ) === md5( $saved ) );
$custom_css = (string) get_post_meta( PAGE_ID, '_wpb_shortcodes_custom_css', true );
uba_check( 'CSS de WPBakery regenerado', false !== strpos( $custom_css, 'margin-top: 10px !important' ), substr( $custom_css, 0, 160 ) );

$stale = uba_run( 'uncode-builder-edit/update-element', array( 'post_id' => PAGE_ID, 'version' => $before['version'], 'path' => '0', 'attributes' => array( 'top_padding' => '2' ) ) );
uba_check( 'versión antigua rechazada (409)', is_wp_error( $stale ) && 'uncode_builder_version_conflict' === $stale->get_error_code() );

$after_insert = uba_tree();
$new_row      = end( $after_insert['tree'] );
$heading      = $new_row['children'][0]['children'][0];
uba_check( 'ids Uncode generados y únicos', isset( $heading['id'] ) && ! in_array( $heading['id'], $original_ids, true ), $heading['id'] ?? '' );
$decoded_row = end( uba_tree( true )['tree'] );
$link        = $decoded_row['children'][1]['children'][0]['decoded']['link'] ?? null;
uba_check( 'enlace con comillas ida y vuelta', ( $link['title'] ?? '' ) === 'Hablemos "ya"', $link );

$updated = uba_run( 'uncode-builder-edit/update-element', array(
	'post_id'    => PAGE_ID,
	'version'    => $after_insert['version'],
	'id'         => $heading['id'],
	'expect_tag' => 'vc_custom_heading',
	'attributes' => array( 'text_color' => 'color-xsdn', 'text_color_type' => null ),
	'content'    => 'Título actualizado por MCP',
) );
$heading_after = uba_tree();
$heading_node  = end( $heading_after['tree'] )['children'][0]['children'][0];
uba_check( 'update de atributos y contenido', ! is_wp_error( $updated ) && 'Título actualizado por MCP' === $heading_node['content'] && ! isset( ( (array) $heading_node['attributes'] )['text_color_type'] ), is_wp_error( $updated ) ? uba_error( $updated ) : (array) $heading_node['attributes'] );
uba_check( 'filas originales siguen intactas tras update', substr( uba_content(), 0, strlen( $original ) ) === $original );

$row_path = end( $heading_after['tree'] )['path'];
$button   = end( $heading_after['tree'] )['children'][1]['children'][0];
$moved    = uba_run( 'uncode-builder-edit/move-element', array( 'post_id' => PAGE_ID, 'version' => $heading_after['version'], 'id' => $button['id'], 'expect_tag' => 'vc_button', 'to_parent_path' => $row_path . '.0' ) );
uba_check( 'move del botón a la columna izquierda', ! is_wp_error( $moved ) && ( $moved['elements'][0]['path'] ?? '' ) === $row_path . '.0.2', is_wp_error( $moved ) ? uba_error( $moved ) : $moved['elements'] );
if ( is_wp_error( $moved ) ) {
	exit;
}

$bad_move = uba_run( 'uncode-builder-edit/move-element', array( 'post_id' => PAGE_ID, 'version' => $moved['version'], 'path' => $row_path . '.0', 'to_parent_path' => $row_path . '.0.0', 'dry_run' => true ) );
uba_check( 'move dentro de sí mismo rechazado', is_wp_error( $bad_move ), is_wp_error( $bad_move ) ? $bad_move->get_error_message() : '' );

$wrong_tag = uba_run( 'uncode-builder-edit/remove-element', array( 'post_id' => PAGE_ID, 'version' => $moved['version'], 'id' => $button['id'], 'expect_tag' => 'vc_row' ) );
uba_check( 'remove con expect_tag erróneo rechazado', is_wp_error( $wrong_tag ) && 'uncode_builder_unexpected_tag' === $wrong_tag->get_error_code() );

$before_remove = uba_content();
$removed       = uba_run( 'uncode-builder-edit/remove-element', array( 'post_id' => PAGE_ID, 'version' => $moved['version'], 'id' => $button['id'], 'expect_tag' => 'vc_button' ) );
$buttons_left = substr_count( uba_content(), '[vc_button ' );
uba_check( 'remove del botón', ! is_wp_error( $removed ) && substr_count( $before_remove, '[vc_button ' ) - 1 === $buttons_left && false === strpos( uba_content(), $button['id'] ), is_wp_error( $removed ) ? uba_error( $removed ) : ( $removed['warnings'] ?? '' ) );
if ( is_wp_error( $removed ) ) {
	exit;
}

$restored = uba_run( 'uncode-builder-edit/restore-snapshot', array( 'post_id' => PAGE_ID, 'version' => $removed['version'] ) );
uba_check( 'restore-snapshot devuelve el botón', ! is_wp_error( $restored ) && uba_content() === $before_remove, is_wp_error( $restored ) ? uba_error( $restored ) : ( $restored['warnings'] ?? '' ) );
$redo = uba_run( 'uncode-builder-edit/restore-snapshot', array( 'post_id' => PAGE_ID, 'version' => $restored['version'], 'dry_run' => true ) );
uba_check( 'restore otra vez (dry_run) desharía el restore', ! is_wp_error( $redo ) && ! empty( $redo['changed'] ), is_wp_error( $redo ) ? uba_error( $redo ) : ( $redo['warnings'] ?? '' ) );
uba_check( 'solo 1 snapshot guardado', 1 === count( (array) get_post_meta( PAGE_ID, '_uncode_builder_abilities_snapshots', true ) ) );

$GLOBALS['post'] = get_post( PAGE_ID );
setup_postdata( $GLOBALS['post'] );
$html = apply_filters( 'the_content', $GLOBALS['post']->post_content );
uba_check( 'render sin shortcodes sin procesar', false === strpos( $html, '[vc_' ) && false !== strpos( $html, 'Título actualizado por MCP' ), strlen( $html ) . ' bytes de HTML' );
uba_check( 'render muestra comillas y corchetes', false !== strpos( $html, '[corchetes]' ) || false !== strpos( $html, '&#91;corchetes&#93;' ) );

echo "\nestado final del borrador: ", get_post_status( PAGE_ID ), " | versión ", md5( uba_content() ), "\n";
