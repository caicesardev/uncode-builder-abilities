<?php

namespace Uncode_Builder_Abilities;

use Uncode_Builder_Abilities\Abilities\Ability;
use Uncode_Builder_Abilities\Abilities\Create_Draft_Copy;
use Uncode_Builder_Abilities\Abilities\Get_Design_Tokens;
use Uncode_Builder_Abilities\Abilities\Get_Element_Schema;
use Uncode_Builder_Abilities\Abilities\Get_Page_Options;
use Uncode_Builder_Abilities\Abilities\Render_Preview;
use Uncode_Builder_Abilities\Abilities\Update_Page_Options;
use Uncode_Builder_Abilities\Abilities\Get_Page_Tree;
use Uncode_Builder_Abilities\Abilities\Get_Template;
use Uncode_Builder_Abilities\Abilities\Insert_Elements;
use Uncode_Builder_Abilities\Abilities\Insert_Template;
use Uncode_Builder_Abilities\Abilities\List_Elements;
use Uncode_Builder_Abilities\Abilities\List_Templates;
use Uncode_Builder_Abilities\Abilities\Move_Element;
use Uncode_Builder_Abilities\Abilities\Remove_Element;
use Uncode_Builder_Abilities\Abilities\Restore_Snapshot;
use Uncode_Builder_Abilities\Abilities\Update_Element;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	const READ_NAMESPACE = 'uncode-builder';

	const WRITE_NAMESPACE = 'uncode-builder-edit';

	const CATEGORY = 'uncode-builder';

	public static function boot(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_missing_abilities_api_notice' ) );
			return;
		}

		Uncode_Integration::register();
		Write_Policy::register();
		Draft_Copies::register();

		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Page builder (WPBakery / Uncode)', 'uncode-builder-abilities' ),
				'description' => __( 'Read and compose page layouts built with WPBakery Page Builder and the Uncode theme.', 'uncode-builder-abilities' ),
			)
		);
	}

	public static function register_abilities(): void {
		if ( ! Element_Catalog::is_builder_active() ) {
			return;
		}

		$catalog    = new Element_Catalog( new Param_Normalizer() );
		$parser     = new Shortcode_Parser( $catalog, new Attribute_Decoder() );
		$nesting    = new Nesting_Rules( $catalog );
		$operations = new Layout_Operations( $catalog, $nesting, new Element_Composer( $catalog, $nesting, new Attribute_Encoder() ), $parser );
		$snapshots  = new Snapshot_Store();
		$writer     = new Layout_Writer( $snapshots );
		$tokens     = new Design_Tokens( $catalog );
		$templates  = new Template_Library();
		$checker    = new Option_Checker( $catalog, $parser );

		$abilities = array(
			new List_Elements( $catalog ),
			new Get_Element_Schema( $catalog, new Schema_Compactor( $tokens ) ),
			new Get_Design_Tokens( $tokens ),
			new Get_Page_Tree( $parser, $snapshots ),
			new List_Templates( $templates ),
			new Get_Template( $templates, $parser, $checker ),
			new Insert_Elements( $parser, $operations, $writer ),
			new Insert_Template( $parser, $operations, $writer, $templates, $checker ),
			new Update_Element( $parser, $operations, $writer ),
			new Move_Element( $parser, $operations, $writer ),
			new Remove_Element( $parser, $operations, $writer ),
			new Restore_Snapshot( $parser, $operations, $writer, $snapshots ),
			new Create_Draft_Copy( new Draft_Copies() ),
			new Render_Preview( $parser, new Layout_Preview() ),
		);

		if ( Page_Options::available() ) {
			$page_options = new Page_Options( $tokens );
			$abilities[]  = new Get_Page_Options( $page_options );
			$abilities[]  = new Update_Page_Options( $parser, $operations, $writer, $page_options );
		}

		foreach ( apply_filters( 'uncode_builder_abilities_abilities', $abilities, $catalog, $parser ) as $ability ) {
			if ( $ability instanceof Ability ) {
				wp_register_ability( $ability->qualified_name(), $ability->definition() );
			}
		}
	}

	public static function render_missing_abilities_api_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Uncode Builder Abilities needs the WordPress Abilities API (WordPress 6.9 or later). Its abilities are not registered.', 'uncode-builder-abilities' )
		);
	}
}
