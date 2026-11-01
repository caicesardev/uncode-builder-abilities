<?php
require __DIR__ . '/test-page.php';

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . $text, "\n";
}

function uba_try_insert(): array {
	$tree   = wp_get_ability( 'uncode-builder/get-page-tree' )->execute( array( 'post_id' => PAGE_ID, 'decode' => false ) );
	$result = wp_get_ability( 'uncode-builder-edit/insert-elements' )->execute(
		array(
			'post_id'  => PAGE_ID,
			'version'  => $tree['version'],
			'dry_run'  => true,
			'elements' => array( array( 'tag' => 'vc_row', 'children' => array( array( 'tag' => 'vc_column' ) ) ) ),
		)
	);

	return array( is_wp_error( $result ) ? $result->get_error_code() : 'ok', is_wp_error( $result ) ? $result->get_error_message() : '' );
}

uba_check( 'política por defecto', 'drafts' === Uncode_Builder_Abilities\Write_Policy::current(), Uncode_Builder_Abilities\Write_Policy::current() );

list( $code ) = uba_try_insert();
uba_check( 'borrador: escritura permitida', 'ok' === $code, $code );

wp_update_post( array( 'ID' => PAGE_ID, 'post_status' => 'private' ) );
try {
	list( $code, $message ) = uba_try_insert();
	uba_check( 'privado + solo borradores: rechazado', 'uncode_builder_drafts_only' === $code, $message );

	$allow_any = static fn() => 'any';
	add_filter( 'pre_option_' . Uncode_Builder_Abilities\Write_Policy::OPTION, $allow_any );
	list( $code ) = uba_try_insert();
	remove_filter( 'pre_option_' . Uncode_Builder_Abilities\Write_Policy::OPTION, $allow_any );
	uba_check( 'privado + cualquier contenido: permitido', 'ok' === $code, $code );
} finally {
	wp_update_post( array( 'ID' => PAGE_ID, 'post_status' => 'draft' ) );
}

uba_check( 'borrador restaurado', 'draft' === get_post_status( PAGE_ID ), get_post_status( PAGE_ID ) );
uba_check( 'la opción no se ha guardado en la base de datos', false === get_option( Uncode_Builder_Abilities\Write_Policy::OPTION, false ) );
