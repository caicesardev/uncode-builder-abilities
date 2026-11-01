<?php
require __DIR__ . '/test-page.php';

add_filter(
	'vc_load_default_templates',
	static function ( $templates ) {
		$templates[] = array(
			'name'         => 'UBA raw code test',
			'custom_class' => 'uba-test',
			'content'      => '[vc_row][vc_column width="1/1"][vc_raw_html]' . base64_encode( rawurlencode( '<script>alert(1)</script>' ) ) . '[/vc_raw_html][/vc_column][/vc_row]',
		);
		return $templates;
	}
);

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 200 ), "\n";
}

function uba_try( array $elements ) {
	$tree = wp_get_ability( 'uncode-builder/get-page-tree' )->execute( array( 'post_id' => PAGE_ID, 'decode' => false ) );

	return wp_get_ability( 'uncode-builder-edit/insert-elements' )->execute(
		array(
			'post_id'  => PAGE_ID,
			'version'  => $tree['version'],
			'dry_run'  => true,
			'elements' => $elements,
		)
	);
}

function uba_row( array $children ): array {
	return array( 'tag' => 'vc_row', 'children' => array( array( 'tag' => 'vc_column', 'children' => $children ) ) );
}

function uba_button( $link ): array {
	return uba_row( array( array( 'tag' => 'vc_button', 'attributes' => array( 'link' => $link ), 'content' => 'Botón' ) ) );
}

function uba_message( $result ): string {
	if ( ! is_wp_error( $result ) ) {
		return 'aceptado';
	}
	$data = $result->get_error_data();

	return implode( ' | ', array_column( is_array( $data ) ? ( $data['errors'] ?? array() ) : array(), 'message' ) ) ?: $result->get_error_message();
}

echo "-- Enlaces (todos los usuarios)\n";
uba_check( 'javascript: como objeto rechazado', is_wp_error( uba_try( array( uba_button( array( 'url' => 'javascript:alert(1)' ) ) ) ) ), uba_message( uba_try( array( uba_button( array( 'url' => 'javascript:alert(1)' ) ) ) ) ) );
uba_check( 'javascript: en formato url:… rechazado', is_wp_error( uba_try( array( uba_button( 'url:javascript%3Aalert(1)|title:x' ) ) ) ) );
uba_check( 'data: rechazado', is_wp_error( uba_try( array( uba_button( array( 'url' => 'data:text/html,<script>alert(1)</script>' ) ) ) ) ) );
foreach ( array( '/contacto/', 'https://caicesardev.com/', 'mailto:hola@example.com', 'tel:+34600000000', '#servicios' ) as $url ) {
	uba_check( "enlace válido $url", ! is_wp_error( uba_try( array( uba_button( array( 'url' => $url ) ) ) ) ) );
}

echo "-- CSS de diseño (todos los usuarios)\n";
$css_row = static fn( $css ) => uba_row( array( array( 'tag' => 'vc_column_text', 'attributes' => array( 'css' => $css ), 'content' => '<p>x</p>' ) ) );
foreach ( array( '</style><script>alert(1)</script>', 'expression(alert(1))', 'url(javascript:alert(1))', '1px @import x' ) as $value ) {
	uba_check( "CSS peligroso rechazado: $value", is_wp_error( uba_try( array( $css_row( array( 'margin-top' => $value ) ) ) ) ) );
}
uba_check( 'CSS en texto con </style> rechazado', is_wp_error( uba_try( array( $css_row( '.vc_custom_1{margin-top: 1px;}</style>' ) ) ) ) );
uba_check( 'CSS normal aceptado', ! is_wp_error( uba_try( array( $css_row( array( 'margin-top' => '10px', 'padding-left' => '2em' ) ) ) ) ) );

echo "-- Límites\n";
$many = uba_row( array_fill( 0, 500, array( 'tag' => 'vc_empty_space' ) ) );
uba_check( 'más de 500 elementos rechazado', false !== strpos( uba_message( uba_try( array( $many ) ) ), 'at most 500' ) );
$deep = array( 'tag' => 'vc_column_text' );
for ( $level = 0; $level < 35; $level++ ) {
	$deep = array( 'tag' => 'vc_row', 'children' => array( $deep ) );
}
uba_check( 'más de 30 niveles rechazado', false !== strpos( uba_message( uba_try( array( $deep ) ) ), 'nested more than 30' ) );

echo "-- Elementos retirados por el tema\n";
$default_list = wp_get_ability( 'uncode-builder/list-elements' )->execute( array( 'search' => 'vc_btn' ) );
uba_check( 'list-elements oculta vc_btn', 0 === $default_list['count'] );
$with_legacy = wp_get_ability( 'uncode-builder/list-elements' )->execute( array( 'search' => 'vc_btn', 'include_deprecated' => true ) );
uba_check( 'con include_deprecated aparece y propone vc_button', 'vc_button' === ( $with_legacy['elements'][0]['use_instead'] ?? '' ), $with_legacy['elements'][0] ?? array() );
uba_check( 'insertar vc_btn rechazado con sustituto', false !== strpos( uba_message( uba_try( array( uba_row( array( array( 'tag' => 'vc_btn' ) ) ) ) ) ), 'use "vc_button" instead' ), uba_message( uba_try( array( uba_row( array( array( 'tag' => 'vc_btn' ) ) ) ) ) ) );
uba_check( 'vc_tta_tabs propone vc_tabs', false !== strpos( uba_message( uba_try( array( uba_row( array( array( 'tag' => 'vc_tta_tabs' ) ) ) ) ) ), 'vc_tabs' ) );
$total_visible = wp_get_ability( 'uncode-builder/list-elements' )->execute( array() );
uba_check( 'elementos visibles por defecto', $total_visible['count'] < 146, $total_visible['count'] . ' (antes 146)' );

echo "-- Usuario sin unfiltered_html (simulado)\n";
$without_unfiltered = static function ( $caps ) {
	$caps['unfiltered_html'] = false;
	return $caps;
};
add_filter( 'user_has_cap', $without_unfiltered );

uba_check( 'la simulación funciona', ! current_user_can( 'unfiltered_html' ) );
uba_check( 'HTML crudo (vc_raw_html) rechazado', false !== strpos( uba_message( uba_try( array( uba_row( array( array( 'tag' => 'vc_raw_html', 'content' => '<b>x</b>' ) ) ) ) ) ), 'unfiltered_html' ) );
uba_check( 'CSS normal sigue aceptado', ! is_wp_error( uba_try( array( $css_row( array( 'margin-top' => '10px' ) ) ) ) ), uba_message( uba_try( array( $css_row( array( 'margin-top' => '10px' ) ) ) ) ) );
uba_check( 'enlace normal sigue aceptado', ! is_wp_error( uba_try( array( uba_button( array( 'url' => '/contacto/', 'title' => 'Contacto' ) ) ) ) ) );

$group_schema = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => 'vc_progress_bar', 'params' => array( 'values' ) ) );
if ( 'group' === ( $group_schema['params'][0]['kind'] ?? '' ) ) {
	$grouped = uba_try( array( uba_row( array( array( 'tag' => 'vc_progress_bar', 'attributes' => array( 'values' => array( array( 'label' => '<script>alert(1)</script>Diseño', 'value' => '90' ) ) ) ) ) ) ) );
	$snippet = is_wp_error( $grouped ) ? '' : rawurldecode( $grouped['snippet'] );
	uba_check( 'grupo de parámetros: se quita <script>', ! is_wp_error( $grouped ) && false === stripos( $snippet, 'script' ) && false !== strpos( $snippet, 'Diseño' ), is_wp_error( $grouped ) ? uba_message( $grouped ) : $snippet );
} else {
	echo "      (vc_progress_bar no tiene grupo de parámetros en este sitio; se omite)\n";
}

$tree     = wp_get_ability( 'uncode-builder/get-page-tree' )->execute( array( 'post_id' => PAGE_ID, 'decode' => false ) );
$template = wp_get_ability( 'uncode-builder-edit/insert-template' )->execute( array( 'post_id' => PAGE_ID, 'version' => $tree['version'], 'template' => 'default:uba-raw-code-test', 'dry_run' => true ) );
uba_check( 'plantilla con HTML crudo rechazada', is_wp_error( $template ) && false !== strpos( uba_message( $template ), 'unfiltered_html' ), uba_message( $template ) );

remove_filter( 'user_has_cap', $without_unfiltered );
$template_admin = wp_get_ability( 'uncode-builder-edit/insert-template' )->execute( array( 'post_id' => PAGE_ID, 'version' => $tree['version'], 'template' => 'default:uba-raw-code-test', 'dry_run' => true ) );
uba_check( 'la misma plantilla sí para un administrador', ! is_wp_error( $template_admin ), uba_message( $template_admin ) );

uba_check( 'el borrador sigue vacío', '' === get_post_field( 'post_content', PAGE_ID, 'raw' ) );
