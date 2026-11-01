<?php
wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . $text, "\n";
}

function uba_size( $value ): string {
	return round( strlen( wp_json_encode( $value ) ) / 1024, 1 ) . ' KB';
}

$plugin = get_plugin_data( WP_PLUGIN_DIR . '/uncode-builder-abilities/uncode-builder-abilities.php', false, false );
uba_check( 'cabecera del plugin', 'caicesardev' === $plugin['Author'] && 'https://caicesardev.com' === $plugin['AuthorURI'], array( $plugin['Version'], $plugin['Author'], $plugin['AuthorURI'], $plugin['PluginURI'] ) );

$names = array_values( array_filter( array_keys( wp_get_abilities() ), static fn( $name ) => 0 === strpos( $name, 'uncode-builder' ) ) );
uba_check( '10 abilities registradas', 10 === count( $names ), $names );

$tokens = wp_get_ability( 'uncode-builder/get-design-tokens' )->execute( array() );
uba_check( 'get-design-tokens', ! empty( $tokens['sets']['colors'] ), array( 'tamaño' => uba_size( $tokens ), 'counts' => $tokens['counts'] ) );
foreach ( $tokens['sets'] as $set => $items ) {
	echo '      ', $set, ': ', wp_json_encode( array_slice( $items, 0, 3 ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
}
$resolved = array_filter( $tokens['sets']['colors'], static fn( $token ) => isset( $token['resolves_to'] ) );
uba_check( 'colores con hex resuelto', count( $resolved ) === count( $tokens['sets']['colors'] ), count( $resolved ) . ' de ' . count( $tokens['sets']['colors'] ) );

foreach ( array( 'vc_custom_heading', 'vc_row', 'vc_column', 'uncode_button' ) as $tag ) {
	$full    = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => $tag, 'group' => '*', 'compact' => false ) );
	$compact = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => $tag, 'group' => '*' ) );
	$default = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => $tag ) );
	$expected_group = isset( $default['groups']['General'] ) ? 'General' : array_key_first( $default['groups'] );
	uba_check( "por defecto $tag devuelve un solo grupo", $expected_group === ( $default['returned_group'] ?? '' ) && count( $default['groups'] ) > 1, $expected_group . ': ' . uba_size( $default ) . ' de ' . count( $default['groups'] ) . ' grupos' );
	$refs    = array_count_values( array_map( static fn( $param ) => preg_replace( '/^params\..*/', 'params.*', $param['options_ref'] ), array_filter( $compact['params'], static fn( $param ) => isset( $param['options_ref'] ) ) ) );
	$general = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => $tag, 'group' => 'General' ) );
	uba_check( "compacto $tag", strlen( wp_json_encode( $compact ) ) < strlen( wp_json_encode( $full ) ), uba_size( $full ) . ' -> ' . uba_size( $compact ) . ' | solo General: ' . uba_size( $general ) . ' | refs ' . wp_json_encode( $refs ) );
}

$heading = wp_get_ability( 'uncode-builder/get-element-schema' )->execute( array( 'tag' => 'vc_custom_heading', 'group' => 'General' ) );
foreach ( $heading['params'] as $param ) {
	if ( in_array( $param['name'], array( 'text_color', 'text_size', 'text_font', 'heading_semantic' ), true ) ) {
		echo '      ', wp_json_encode( $param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "\n";
	}
}

$blocks = wp_get_ability( 'uncode-builder/list-content-blocks' )->execute( array() );
uba_check( 'list-content-blocks', 14 === $blocks['count'], array( 'tamaño' => uba_size( $blocks ), 'primeros' => array_slice( $blocks['blocks'], 0, 3 ) ) );
$footer = wp_get_ability( 'uncode-builder/list-content-blocks' )->execute( array( 'search' => 'Footer' ) );
uba_check( 'búsqueda de bloques', $footer['count'] >= 1, array_column( $footer['blocks'], 'title' ) );

wp_set_current_user( 0 );
$denied = wp_get_ability( 'uncode-builder/get-design-tokens' )->execute( array() );
uba_check( 'tokens sin usuario rechazado', is_wp_error( $denied ), is_wp_error( $denied ) ? $denied->get_error_code() : 'permitido' );
