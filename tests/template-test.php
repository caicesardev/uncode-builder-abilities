<?php
const PAGE_ID = 179791;

wp_set_current_user( 1 );
if ( 'draft' !== get_post_status( PAGE_ID ) ) {
	exit( "ABORT: la página de pruebas no es un borrador\n" );
}

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . $text, "\n";
}

function uba_size( $value ): string {
	return round( strlen( wp_json_encode( $value ) ) / 1024, 1 ) . ' KB';
}

function uba_run( string $name, array $input ) {
	return wp_get_ability( $name )->execute( $input );
}

function uba_content(): string {
	return (string) get_post_field( 'post_content', PAGE_ID, 'raw' );
}

function uba_version(): string {
	return uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => PAGE_ID, 'decode' => false ) )['version'];
}

function uba_duplicate_ids(): array {
	preg_match_all( '/uncode_shortcode_id="(\d+)"/', uba_content(), $matches );

	return array_keys( array_filter( array_count_values( $matches[1] ), static fn( $count ) => $count > 1 ) );
}

$overview = uba_run( 'uncode-builder/list-templates', array() );
uba_check( 'list-templates sin filtros: solo categorías', ! isset( $overview['templates'] ) && $overview['total'] >= 900, array( 'total' => $overview['total'], 'categorías' => count( $overview['categories'] ), 'tamaño' => uba_size( $overview ) ) );

$icons = uba_run( 'uncode-builder/list-templates', array( 'category' => 'icons' ) );
uba_check( 'categoría icons', 28 === $icons['matches'], array( 'tamaño' => uba_size( $icons ), 'primero' => $icons['templates'][0] ) );

$pricing = uba_run( 'uncode-builder/list-templates', array( 'search' => 'pricing', 'limit' => 5 ) );
uba_check( 'búsqueda pricing', $pricing['matches'] > 0 && count( $pricing['templates'] ) <= 5, array_column( $pricing['templates'], 'name' ) );

$template_id = '';
foreach ( $icons['templates'] as $candidate ) {
	if ( 'Icon Left' === $candidate['name'] ) {
		$template_id = $candidate['id'];
	}
}
$template = uba_run( 'uncode-builder/get-template', array( 'id' => $template_id ) );
uba_check( 'get-template Icon Left', ! is_wp_error( $template ) && $template['element_count'] > 5, is_wp_error( $template ) ? $template->get_error_message() : array( 'id' => $template_id, 'elementos' => $template['element_count'], 'tamaño' => uba_size( $template ), 'raíz' => array_column( $template['tree'], 'tag' ) ) );

$missing = uba_run( 'uncode-builder/get-template', array( 'id' => 'default:no-existe' ) );
uba_check( 'plantilla inexistente', is_wp_error( $missing ) );

$original = uba_content();
$preview  = uba_run( 'uncode-builder-edit/insert-template', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'template' => $template_id, 'dry_run' => true ) );
uba_check( 'insert-template dry_run', ! is_wp_error( $preview ) && empty( $preview['saved'] ) && uba_content() === $original, is_wp_error( $preview ) ? $preview->get_error_message() : array( 'elements' => $preview['elements'], 'ids generados' => count( $preview['generated_ids'] ?? array() ) ) );

$into_column = uba_run( 'uncode-builder-edit/insert-template', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'template' => $template_id, 'parent_path' => '0.0', 'dry_run' => true ) );
uba_check( 'plantilla dentro de una columna rechazada', is_wp_error( $into_column ), is_wp_error( $into_column ) ? $into_column->get_error_message() : '' );

$inserted = uba_run( 'uncode-builder-edit/insert-template', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'template' => $template_id ) );
uba_check( 'insert-template guardado', ! is_wp_error( $inserted ) && ! empty( $inserted['saved'] ), is_wp_error( $inserted ) ? $inserted->get_error_message() : array( 'elements' => $inserted['elements'], 'ids generados' => count( $inserted['generated_ids'] ?? array() ) ) );
uba_check( 'contenido previo intacto byte a byte', 0 === strpos( uba_content(), $original ) );

$again = uba_run( 'uncode-builder-edit/insert-template', array( 'post_id' => PAGE_ID, 'version' => uba_version(), 'template' => $template_id ) );
uba_check( 'segunda inserción de la misma plantilla', ! is_wp_error( $again ) && ! empty( $again['saved'] ), is_wp_error( $again ) ? $again->get_error_message() : $again['elements'] );
uba_check( 'todos los uncode_shortcode_id de la página son únicos', array() === uba_duplicate_ids(), uba_duplicate_ids() );

$tree         = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => PAGE_ID, 'decode' => false ) );
$inserted_row = $tree['tree'][ (int) $again['elements'][0]['path'] ];
$first_icon   = $inserted_row['children'][0]['children'][0]['children'][0]['children'][0] ?? null;
uba_check( 'icono de la plantilla localizable para editarlo', 'vc_icon' === ( $first_icon['tag'] ?? '' ) && isset( $first_icon['id'] ), array( 'path' => $first_icon['path'] ?? null, 'id' => $first_icon['id'] ?? null, 'title' => ( (array) ( $first_icon['attributes'] ?? array() ) )['title'] ?? null ) );

$edited = uba_run( 'uncode-builder-edit/update-element', array( 'post_id' => PAGE_ID, 'version' => $tree['version'], 'id' => $first_icon['id'], 'expect_tag' => 'vc_icon', 'attributes' => array( 'title' => 'Diseño web' ), 'content' => 'Webs rápidas, accesibles y fáciles de mantener.' ) );
uba_check( 'adaptar el texto del icono', ! is_wp_error( $edited ) && ! empty( $edited['saved'] ), is_wp_error( $edited ) ? $edited->get_error_message() : $edited['elements'] );

$GLOBALS['post'] = get_post( PAGE_ID );
setup_postdata( $GLOBALS['post'] );
$html = apply_filters( 'the_content', $GLOBALS['post']->post_content );
uba_check( 'render sin shortcodes sin procesar', false === strpos( $html, '[vc_' ) && false !== strpos( $html, 'Diseño web' ), strlen( $html ) . ' bytes de HTML' );

echo "\nestado final del borrador: ", get_post_status( PAGE_ID ), ' | filas ', count( uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => PAGE_ID, 'decode' => false ) )['tree'] ), "\n";
