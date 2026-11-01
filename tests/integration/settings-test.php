<?php
use Uncode_Builder_Abilities\Settings_Page;
use Uncode_Builder_Abilities\Write_Policy;

wp_set_current_user( 1 );

function uba_check( string $label, bool $ok, $detail = '' ): void {
	$text = is_string( $detail ) ? $detail : wp_json_encode( $detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo ( $ok ? 'OK    ' : 'FALLO ' ), $label, '' === $text ? '' : ' -> ' . mb_substr( $text, 0, 240 ), "\n";
}

$stored_before = array(
	Write_Policy::OPTION                     => get_option( Write_Policy::OPTION, null ),
	Settings_Page::SNAPSHOT_LIMIT_OPTION     => get_option( Settings_Page::SNAPSHOT_LIMIT_OPTION, null ),
	Settings_Page::DRAFT_COPY_STRATEGY_OPTION => get_option( Settings_Page::DRAFT_COPY_STRATEGY_OPTION, null ),
);

uba_check( 'valores por defecto', Write_Policy::DRAFTS_ONLY === Write_Policy::current() && 1 === Settings_Page::snapshot_limit() && 'auto' === Settings_Page::draft_copy_strategy(), array( Write_Policy::current(), Settings_Page::snapshot_limit(), Settings_Page::draft_copy_strategy() ) );
uba_check( 'validación del límite de copias', 1 === Settings_Page::sanitize_snapshot_limit( 0 ) && 20 === Settings_Page::sanitize_snapshot_limit( 99 ) && 5 === Settings_Page::sanitize_snapshot_limit( '5' ) );
uba_check( 'validación de la estrategia', 'auto' === Settings_Page::sanitize_strategy( 'inventada' ) && 'autosave' === Settings_Page::sanitize_strategy( 'autosave' ) );
uba_check( 'validación de la política', 'drafts' === Write_Policy::sanitize( 'todo' ) && 'any' === Write_Policy::sanitize( 'any' ) );

switch_to_locale( 'es_ES' );
Settings_Page::register_settings();
ob_start();
Settings_Page::render_page();
$html = (string) ob_get_clean();
restore_previous_locale();

uba_check( 'la página se pinta', false !== strpos( $html, 'name="uncode_builder_abilities_write_policy"' ) && false !== strpos( $html, 'name="uncode_builder_abilities_snapshot_limit"' ) && false !== strpos( $html, 'name="uncode_builder_abilities_draft_copy_strategy"' ), strlen( $html ) . ' bytes' );
uba_check( 'en español', false !== strpos( $html, 'Seguridad' ) && false !== strpos( $html, 'Abilities registradas' ) && false !== strpos( $html, 'Solo borradores' ) );
preg_match_all( '#<li><code>([^<]+)</code></li>#', $html, $listed );
uba_check( 'lista las abilities registradas', count( $listed[1] ) >= 17, count( $listed[1] ) . ' abilities' );
uba_check( 'detecta WPBakery, Uncode y Duplicate Post', 4 === substr_count( $html, '<td>Activo</td>' ), substr_count( $html, '<td>Activo</td>' ) . ' activos' );

$links = Settings_Page::add_settings_link( array() );
uba_check( 'enlace de ajustes en la lista de plugins', false !== strpos( $links[0], 'options-general.php?page=uncode-builder-abilities' ) );

$stored_after = array(
	Write_Policy::OPTION                     => get_option( Write_Policy::OPTION, null ),
	Settings_Page::SNAPSHOT_LIMIT_OPTION     => get_option( Settings_Page::SNAPSHOT_LIMIT_OPTION, null ),
	Settings_Page::DRAFT_COPY_STRATEGY_OPTION => get_option( Settings_Page::DRAFT_COPY_STRATEGY_OPTION, null ),
);
uba_check( 'no se ha guardado ninguna opción', $stored_before === $stored_after, $stored_after );
