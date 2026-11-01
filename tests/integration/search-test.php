<?php
require __DIR__ . '/test-page.php';

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 220 ), "\n";
}

$before = md5( (string) get_post_field( 'post_content', PAGE_ID, 'raw' ) );

foreach ( array( 'hero' => 'headers', 'cta' => 'call_to_action', 'testimonials' => 'quotes', 'team' => 'team_members', 'faq' => 'tabs-accordions', 'pricing' => 'pricing_tables' ) as $word => $category ) {
	$found      = wp_get_ability( 'uncode-builder/list-templates' )->execute( array( 'search' => $word, 'limit' => 100 ) );
	$categories = array_count_values( array_column( $found['templates'] ?? array(), 'category' ) );
	uba_check( "plantillas para \"$word\"", ( $found['matches'] ?? 0 ) > 0 && isset( $categories[ $category ] ), array( 'resultados' => $found['matches'] ?? 0, 'categorías' => $categories ) );
}

foreach ( array( 'menu transparency' => '_uncode_specific_menu_opaque', 'width' => '_uncode_specific_main_width', 'footer block' => '_uncode_specific_footer_block' ) as $search => $expected ) {
	$found = wp_get_ability( 'uncode-builder/get-page-options' )->execute( array( 'post_id' => PAGE_ID, 'search' => $search ) );
	$names = array_column( $found['fields'] ?? array(), 'name' );
	uba_check( "opciones para \"$search\"", in_array( $expected, $names, true ), $names );
}

uba_check( 'búsquedas sin efectos en el borrador', md5( (string) get_post_field( 'post_content', PAGE_ID, 'raw' ) ) === $before );
