<?php
require __DIR__ . '/test-page.php';

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 240 ), "\n";
}

function uba_run( string $name, array $input ) {
	return wp_get_ability( $name )->execute( $input );
}

function uba_version(): string {
	return md5( (string) get_post_field( 'post_content', PAGE_ID, 'raw' ) );
}

function uba_h1( string $text, array $visibility = array() ): array {
	return array(
		'tag'        => 'vc_custom_heading',
		'attributes' => array_merge( array( 'heading_semantic' => 'h1' ), $visibility ),
		'content'    => $text,
	);
}

if ( '' !== get_post_field( 'post_content', PAGE_ID, 'raw' ) ) {
	exit( "ABORT: the test page must start empty\n" );
}

$blocks   = uba_run( 'uncode-builder/list-content-blocks', array() );
$block_id = is_array( $blocks ) && ! empty( $blocks['blocks'] ) ? (int) $blocks['blocks'][0]['id'] : 0;

$row = array(
	'tag'      => 'vc_row',
	'children' => array(
		array(
			'tag'        => 'vc_column',
			'attributes' => array(
				'width'             => '1/2',
				'mobile_visibility' => 'yes',
			),
			'children'   => array( uba_h1( 'Inicio escritorio' ) ),
		),
		array(
			'tag'        => 'vc_column',
			'attributes' => array( 'width' => '1/2' ),
			'children'   => array(
				uba_h1(
					'Inicio móvil',
					array(
						'desktop_visibility' => 'yes',
						'medium_visibility'  => 'yes',
					)
				),
			),
		),
	),
);

$block_row = array(
	'tag'      => 'vc_row',
	'children' => array(
		array(
			'tag'      => 'vc_column',
			'children' => array(
				array(
					'tag'        => 'uncode_block',
					'attributes' => array( 'id' => (string) $block_id ),
				),
			),
		),
	),
);

$inserted = uba_run(
	'uncode-builder-edit/insert-elements',
	array(
		'post_id'  => PAGE_ID,
		'version'  => uba_version(),
		'elements' => $block_id > 0 ? array( $row, $block_row ) : array( $row ),
	)
);
uba_check( 'filas de prueba guardadas sin consejos', ! is_wp_error( $inserted ) && ! empty( $inserted['saved'] ) && empty( $inserted['advice'] ), is_wp_error( $inserted ) ? $inserted->get_error_message() : ( $inserted['advice'] ?? '' ) );

$misplaced = uba_run(
	'uncode-builder-edit/insert-elements',
	array(
		'post_id'     => PAGE_ID,
		'version'     => uba_version(),
		'parent_path' => '0.1',
		'dry_run'     => true,
		'elements'    => array(
			array(
				'tag'        => 'uncode_block',
				'attributes' => array( 'id' => (string) max( 1, $block_id ) ),
			),
		),
	)
);
uba_check( 'un content block junto a otros elementos recibe un consejo', ! is_wp_error( $misplaced ) && 1 === count( $misplaced['advice'] ?? array() ), $misplaced['advice'] ?? $misplaced );

$tree = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => PAGE_ID, 'decode' => false ) );
$desktop_h1 = $tree['tree'][0]['children'][0]['children'][0] ?? array();
$mobile_h1  = $tree['tree'][0]['children'][1]['children'][0] ?? array();
uba_check( 'visible_on heredado de la columna', array( 'desktop', 'tablet' ) === ( $desktop_h1['visible_on'] ?? null ), $desktop_h1['visible_on'] ?? null );
uba_check( 'visible_on propio', array( 'mobile' ) === ( $mobile_h1['visible_on'] ?? null ), $mobile_h1['visible_on'] ?? null );
uba_check( 'sin visible_on en lo visible en todas partes', ! isset( $tree['tree'][0]['visible_on'] ) );

$found = uba_run( 'uncode-builder/find-elements', array( 'post_id' => PAGE_ID, 'tag' => 'vc_custom_heading' ) );
uba_check( 'find-elements encuentra los dos h1', ! is_wp_error( $found ) && 2 === $found['count'] && $tree['version'] === $found['version'], is_wp_error( $found ) ? $found->get_error_message() : array_column( $found['matches'], 'path' ) );
uba_check( 'find-elements es mucho más pequeño que el árbol', strlen( (string) wp_json_encode( $found ) ) * 2 < strlen( (string) wp_json_encode( $tree ) ), strlen( (string) wp_json_encode( $found ) ) . ' frente a ' . strlen( (string) wp_json_encode( $tree ) ) );

$preview = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID, 'path' => '0' ) );
$h1_issue = array_filter( $preview['issues'], static fn( $issue ) => false !== strpos( $issue, 'More than one h1' ) );
uba_check( 'un h1 por dispositivo no da aviso', array() === $h1_issue, $preview['issues'] );
uba_check( 'el esquema indica los dispositivos', in_array( array( 'mobile' ), array_column( $preview['outline'], 'visible_on' ), true ), array_slice( $preview['outline'], 0, 3 ) );

$markup = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID, 'path' => '0.1', 'include_html' => true ) );
uba_check( 'include_html devuelve las clases renderizadas', false !== strpos( $markup['html'] ?? '', 'desktop-hidden' ) && false !== strpos( $markup['html'] ?? '', 'tablet-hidden' ), mb_substr( $markup['html'] ?? '', 0, 160 ) );
uba_check( 'sin include_html no hay html', ! isset( $preview['html'] ) );

if ( $block_id > 0 ) {
	$expanded = uba_run( 'uncode-builder/get-page-tree', array( 'post_id' => PAGE_ID, 'decode' => false, 'expand_blocks' => true ) );
	$block    = $expanded['tree'][1]['children'][0]['children'][0]['block'] ?? array();
	uba_check( 'expand_blocks incluye el content block', $block_id === ( $block['post_id'] ?? 0 ) && ! empty( $block['read_only'] ) && is_array( $block['tree'] ?? null ), array_diff_key( $block, array( 'tree' => 1 ) ) );
	uba_check( 'sin expand_blocks no se incluye', ! isset( $tree['tree'][1]['children'][0]['children'][0]['block'] ) );
} else {
	uba_check( 'expand_blocks (sin content blocks en el sitio)', true, 'omitido' );
}

$second = uba_run(
	'uncode-builder-edit/insert-elements',
	array(
		'post_id'     => PAGE_ID,
		'version'     => uba_version(),
		'parent_path' => '0.0',
		'elements'    => array( uba_h1( 'Otro h1 de escritorio' ) ),
	)
);
$preview  = uba_run( 'uncode-builder/render-preview', array( 'post_id' => PAGE_ID, 'path' => '0' ) );
$h1_issue = array_values( array_filter( $preview['issues'], static fn( $issue ) => false !== strpos( $issue, 'More than one h1' ) ) );
uba_check( 'dos h1 en el mismo dispositivo sí dan aviso', ! is_wp_error( $second ) && 1 === count( $h1_issue ) && false !== strpos( $h1_issue[0], 'on desktop, tablet.' ), $h1_issue );

$description = wp_get_ability( 'uncode-builder-edit/update-element' )->get_description();
uba_check( 'la descripción de escritura indica la política del sitio', false !== strpos( $description, 'On this site it' ), mb_substr( $description, -170 ) );

do {
	$removed = uba_run(
		'uncode-builder-edit/remove-element',
		array(
			'post_id'    => PAGE_ID,
			'version'    => uba_version(),
			'path'       => '0',
			'expect_tag' => 'vc_row',
		)
	);
} while ( ! is_wp_error( $removed ) && '' !== get_post_field( 'post_content', PAGE_ID, 'raw' ) );
delete_post_meta( PAGE_ID, '_uncode_builder_abilities_snapshots' );
uba_check( 'limpieza: borrador vacío', ! is_wp_error( $removed ) && '' === get_post_field( 'post_content', PAGE_ID, 'raw' ) );
