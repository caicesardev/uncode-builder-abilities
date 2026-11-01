<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Settings_Page {

	const SLUG = 'uncode-builder-abilities';

	const GROUP = 'uncode_builder_abilities';

	const CAPABILITY = 'manage_options';

	const SNAPSHOT_LIMIT_OPTION = 'uncode_builder_abilities_snapshot_limit';

	const DRAFT_COPY_STRATEGY_OPTION = 'uncode_builder_abilities_draft_copy_strategy';

	const AUTOMATIC_STRATEGY = 'auto';

	const MAX_SNAPSHOTS = 20;

	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UNCODE_BUILDER_ABILITIES_FILE ), array( __CLASS__, 'add_settings_link' ) );
	}

	public static function snapshot_limit(): int {
		return self::sanitize_snapshot_limit( get_option( self::SNAPSHOT_LIMIT_OPTION, Snapshot_Store::DEFAULT_LIMIT ) );
	}

	public static function draft_copy_strategy(): string {
		return self::sanitize_strategy( get_option( self::DRAFT_COPY_STRATEGY_OPTION, self::AUTOMATIC_STRATEGY ) );
	}

	public static function sanitize_snapshot_limit( $value ): int {
		return min( self::MAX_SNAPSHOTS, max( 1, (int) $value ) );
	}

	public static function sanitize_strategy( $value ): string {
		return array_key_exists( (string) $value, self::strategy_choices() ) ? (string) $value : self::AUTOMATIC_STRATEGY;
	}

	public static function add_page(): void {
		add_options_page(
			__( 'Uncode Builder Abilities', 'uncode-builder-abilities' ),
			__( 'Uncode Builder Abilities', 'uncode-builder-abilities' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function add_settings_link( array $links ): array {
		$url = admin_url( 'options-general.php?page=' . self::SLUG );
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'uncode-builder-abilities' ) ) );

		return $links;
	}

	public static function register_settings(): void {
		register_setting(
			self::GROUP,
			Write_Policy::OPTION,
			array(
				'type'              => 'string',
				'default'           => Write_Policy::DRAFTS_ONLY,
				'sanitize_callback' => array( Write_Policy::class, 'sanitize' ),
			)
		);
		register_setting(
			self::GROUP,
			self::SNAPSHOT_LIMIT_OPTION,
			array(
				'type'              => 'integer',
				'default'           => Snapshot_Store::DEFAULT_LIMIT,
				'sanitize_callback' => array( __CLASS__, 'sanitize_snapshot_limit' ),
			)
		);
		register_setting(
			self::GROUP,
			self::DRAFT_COPY_STRATEGY_OPTION,
			array(
				'type'              => 'string',
				'default'           => self::AUTOMATIC_STRATEGY,
				'sanitize_callback' => array( __CLASS__, 'sanitize_strategy' ),
			)
		);

		add_settings_section( 'safety', __( 'Safety', 'uncode-builder-abilities' ), '__return_false', self::SLUG );
		add_settings_field( Write_Policy::OPTION, __( 'Items AI agents may change', 'uncode-builder-abilities' ), array( __CLASS__, 'render_write_policy' ), self::SLUG, 'safety' );
		add_settings_field( self::SNAPSHOT_LIMIT_OPTION, __( 'Snapshots kept per item', 'uncode-builder-abilities' ), array( __CLASS__, 'render_snapshot_limit' ), self::SLUG, 'safety' );

		add_settings_section( 'draft_copies', __( 'Draft copies of published items', 'uncode-builder-abilities' ), '__return_false', self::SLUG );
		add_settings_field( self::DRAFT_COPY_STRATEGY_OPTION, __( 'How copies are made', 'uncode-builder-abilities' ), array( __CLASS__, 'render_strategy' ), self::SLUG, 'draft_copies' );
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'These abilities let AI agents read and compose WPBakery / Uncode layouts through any MCP transport that publishes WordPress Abilities. Every change is validated against the page builder and can be undone with restore-snapshot.', 'uncode-builder-abilities' )
		);

		echo '<form action="options.php" method="post">';
		settings_fields( self::GROUP );
		do_settings_sections( self::SLUG );
		submit_button();
		echo '</form>';

		self::render_status();
		echo '</div>';
	}

	public static function render_write_policy(): void {
		self::render_select( Write_Policy::OPTION, Write_Policy::current(), Write_Policy::choices() );
		printf( '<p class="description">%s</p>', esc_html__( 'In drafts-only mode agents change published pages by creating a draft copy that a person reviews and applies.', 'uncode-builder-abilities' ) );
	}

	public static function render_snapshot_limit(): void {
		printf(
			'<input type="number" min="1" max="%d" step="1" name="%s" id="%s" value="%d" class="small-text">',
			(int) self::MAX_SNAPSHOTS,
			esc_attr( self::SNAPSHOT_LIMIT_OPTION ),
			esc_attr( self::SNAPSHOT_LIMIT_OPTION ),
			(int) self::snapshot_limit()
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Previous versions stored per item for restore-snapshot, independent of WordPress revisions.', 'uncode-builder-abilities' ) );
	}

	public static function render_strategy(): void {
		self::render_select( self::DRAFT_COPY_STRATEGY_OPTION, self::draft_copy_strategy(), self::strategy_choices() );
		printf(
			'<p class="description">%s</p>',
			esc_html(
				self::rewrite_and_republish_available()
					? __( 'Yoast Duplicate Post is active, so the automatic choice uses its Rewrite & Republish copies.', 'uncode-builder-abilities' )
					: __( 'Yoast Duplicate Post is not active, so copies are native WordPress autosaves.', 'uncode-builder-abilities' )
			)
		);
	}

	private static function render_status(): void {
		$abilities = function_exists( 'wp_get_abilities' )
			? array_values( array_filter( array_keys( wp_get_abilities() ), static fn( $name ) => 0 === strpos( $name, Plugin::READ_NAMESPACE . '/' ) || 0 === strpos( $name, Plugin::WRITE_NAMESPACE . '/' ) ) )
			: array();

		printf( '<h2>%s</h2>', esc_html__( 'Status', 'uncode-builder-abilities' ) );
		echo '<table class="widefat striped" style="max-width:48rem"><tbody>';
		self::render_status_row( __( 'WordPress Abilities API', 'uncode-builder-abilities' ), function_exists( 'wp_register_ability' ) );
		self::render_status_row( __( 'WPBakery Page Builder', 'uncode-builder-abilities' ), Element_Catalog::is_builder_active() );
		self::render_status_row( __( 'Uncode theme layer', 'uncode-builder-abilities' ), function_exists( Uncode_Integration::BACKEND_SETUP_FUNCTION ) );
		self::render_status_row( __( 'Yoast Duplicate Post', 'uncode-builder-abilities' ), self::rewrite_and_republish_available() );
		echo '</tbody></table>';

		printf( '<h2>%s</h2>', esc_html__( 'Registered abilities', 'uncode-builder-abilities' ) );
		printf( '<p>%s</p>', esc_html__( 'Enable them in your MCP transport (for example in Easy MCP AI) so agents can use them. Abilities added by a new version of this plugin must be enabled there too.', 'uncode-builder-abilities' ) );
		echo '<ul style="columns:2;max-width:48rem">';
		foreach ( $abilities as $name ) {
			printf( '<li><code>%s</code></li>', esc_html( $name ) );
		}
		echo '</ul>';
	}

	private static function render_status_row( string $label, bool $available ): void {
		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html( $label ),
			esc_html( $available ? __( 'Active', 'uncode-builder-abilities' ) : __( 'Not detected', 'uncode-builder-abilities' ) )
		);
	}

	private static function render_select( string $name, string $current, array $choices ): void {
		printf( '<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $name ) );
		foreach ( $choices as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	private static function strategy_choices(): array {
		return array(
			self::AUTOMATIC_STRATEGY        => __( 'Automatic: Rewrite & Republish when available, otherwise a native autosave', 'uncode-builder-abilities' ),
			Draft_Copies::STRATEGY_AUTOSAVE => __( 'Always a native WordPress autosave', 'uncode-builder-abilities' ),
			Draft_Copies::STRATEGY_REWRITE  => __( 'Rewrite & Republish (Yoast Duplicate Post) when available', 'uncode-builder-abilities' ),
		);
	}

	private static function rewrite_and_republish_available(): bool {
		return class_exists( '\Yoast\WP\Duplicate_Post\Post_Duplicator' );
	}
}
