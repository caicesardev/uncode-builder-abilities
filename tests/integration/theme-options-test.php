<?php
// Read-only: needs no test page. Run with `wp eval-file tests/integration/theme-options-test.php`.

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 240 ), "\n";
}

function uba_fingerprint(): string {
	return md5( serialize( array( get_option( 'uncode' ), get_option( 'uncode_settings' ) ) ) );
}

$before  = uba_fingerprint();
$ability = wp_get_ability( 'uncode-builder/get-theme-options' );
uba_check( 'ability registered', null !== $ability );
if ( null === $ability ) {
	exit( 1 );
}

$index = $ability->execute( array() );
uba_check( 'index lists sections only', is_array( $index ) && array( 'sections' ) === array_keys( $index ) && count( $index['sections'] ) > 10, array_column( (array) ( $index['sections'] ?? array() ), 'name' ) );

$hover = $ability->execute( array( 'fields' => array( '_uncode_button_hover' ) ) );
$field = $hover['fields'][0] ?? array();
uba_check( 'button hover value and label', isset( $field['value'], $field['value_label'] ) && 'select' === $field['kind'], array_intersect_key( $field, array_flip( array( 'value', 'value_label', 'is_default', 'applies' ) ) ) );

$labels = array_column( (array) ( $ability->execute( array( 'fields' => array( '_uncode_footer_block', '_uncode_accent_color', '_uncode_headers' ) ) )['fields'] ?? array() ), null, 'name' );
uba_check( 'content block and palette colour have readable labels', ! is_numeric( $labels['_uncode_footer_block']['value_label'] ?? '1' ) && '' !== ( $labels['_uncode_accent_color']['value_label'] ?? '' ), array_column( $labels, 'value_label', 'name' ) );
uba_check( 'options without a label get one', '' !== ( $labels['_uncode_headers']['label'] ?? '' ), $labels['_uncode_headers']['label'] ?? null );

$search = $ability->execute( array( 'search' => 'layout width' ) );
uba_check( 'search finds the layout width', in_array( '_uncode_main_width', array_column( (array) ( $search['fields'] ?? array() ), 'name' ), true ), array_column( (array) ( $search['fields'] ?? array() ), 'name' ) );

$section = $ability->execute( array( 'section' => 'navbar', 'limit' => 200 ) );
$applies = array_count_values( array_map( static fn( $f ) => $f['applies'] ? 'applies' : 'hidden', (array) ( $section['fields'] ?? array() ) ) );
uba_check( 'section is matched without case and conditions are evaluated', ! empty( $applies['applies'] ) && ! empty( $applies['hidden'] ), $applies );

$changed = $ability->execute( array( 'changed_only' => true, 'limit' => 200 ) );
uba_check( 'changed_only returns only saved non-default values', ! empty( $changed['fields'] ) && array() === array_filter( $changed['fields'], static fn( $f ) => $f['is_default'] ), array( 'total' => $changed['total'] ?? null ) );

$secret = $ability->execute( array( 'fields' => array( '_uncode_gmaps_api' ) ) )['fields'][0] ?? array();
uba_check( 'API key is redacted', '[redacted]' === ( $secret['value'] ?? null ) && isset( $secret['is_set'] ), $secret );

$unknown = $ability->execute( array( 'section' => 'nope' ) );
uba_check( 'unknown section is an error', is_wp_error( $unknown ) );

$json = wp_json_encode( $changed );
uba_check( 'reply size is reasonable', strlen( (string) $json ) < 200000, strlen( (string) $json ) . ' bytes' );

wp_set_current_user( 0 );
uba_check( 'anonymous users cannot read', true !== $ability->check_permissions( array() ) );

uba_check( 'theme options untouched', uba_fingerprint() === $before );
