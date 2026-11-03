<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Uncode_Builder_Abilities\Abilities\Get_Theme_Options;
use Uncode_Builder_Abilities\Theme_Options;
use Uncode_Builder_Abilities\Uncode_Theme_Options;
use WP_Error;

final class ThemeOptionsTest extends Test_Case {

	const SETTINGS = array(
		'sections' => array(
			array(
				'id'    => 'header_section',
				'title' => '<i class="fa fa-heart3"></i> Navbar',
				'group' => 'General',
			),
			array(
				'id'    => 'main_section',
				'title' => 'Layout',
			),
			array(
				'id'    => 'customize_section',
				'title' => 'Customize',
				'group' => 'Visual',
			),
		),
		'settings' => array(
			array(
				'id'      => '_header_title',
				'type'    => 'textblock-titled',
				'section' => 'header_section',
			),
			array(
				'id'      => '_uncode_headers',
				'label'   => 'Header type',
				'type'    => 'radio-image',
				'section' => 'header_section',
				'std'     => 'hmenu-right',
				'choices' => array(
					array(
						'value' => 'hmenu-right',
						'label' => 'Right',
						'src'   => 'right.png',
					),
					array(
						'value' => 'vmenu',
						'label' => 'Vertical',
						'src'   => 'vertical.png',
					),
				),
			),
			array(
				'id'        => '_uncode_menu_sticky',
				'label'     => 'Sticky menu',
				'type'      => 'on-off',
				'section'   => 'header_section',
				'std'       => 'off',
				'condition' => '_uncode_headers:not(vmenu)',
			),
			array(
				'id'        => '_uncode_menu_shrink',
				'label'     => 'Shrink menu',
				'type'      => 'on-off',
				'section'   => 'header_section',
				'std'       => 'off',
				'condition' => '_uncode_menu_sticky:is(on)',
			),
			array(
				'id'      => '_uncode_main_width',
				'label'   => 'Layout width',
				'type'    => 'measurement',
				'section' => 'main_section',
			),
			array(
				'id'      => '_uncode_button_hover',
				'label'   => 'Button hover effect',
				'type'    => 'select',
				'section' => 'customize_section',
				'std'     => '',
				'choices' => array(
					array(
						'value' => '',
						'label' => 'Outlined',
					),
					array(
						'value' => 'full-colored',
						'label' => 'Flat',
					),
				),
			),
			array(
				'id'      => '_uncode_gmaps_api',
				'label'   => 'Google Maps API KEY',
				'type'    => 'text',
				'section' => 'customize_section',
			),
			array(
				'id'      => '_uncode_custom_css',
				'label'   => 'CSS',
				'type'    => 'css',
				'section' => 'customize_section',
			),
		),
	);

	private array $values;

	protected function setUp(): void {
		parent::setUp();

		$this->values = array(
			'_uncode_headers'      => 'vmenu',
			'_uncode_menu_sticky'  => 'on',
			'_uncode_main_width'   => array( '1200', 'px' ),
			'_uncode_button_hover' => 'full-colored',
			'_uncode_gmaps_api'    => 'AIza-not-for-agents',
			'_uncode_custom_css'   => str_repeat( 'a', Theme_Options::MAX_TEXT_LENGTH + 10 ),
		);

		Functions\when( 'get_option' )->alias(
			fn( $name ) => array(
				Uncode_Theme_Options::DEFAULT_SETTINGS_ID => self::SETTINGS,
				Uncode_Theme_Options::DEFAULT_OPTIONS_ID  => $this->values,
			)[ $name ] ?? false
		);
		Filters\expectApplied( 'uncode_builder_abilities_theme_options' )->zeroOrMoreTimes()->andReturnUsing( array( Uncode_Theme_Options::class, 'definition' ) );
		Filters\expectApplied( 'uncode_builder_abilities_theme_option_values' )->zeroOrMoreTimes()->andReturnUsing( array( Uncode_Theme_Options::class, 'values' ) );
	}

	private function described( Theme_Options $options, string $name ): array {
		return $options->described( $options->field( $name ) );
	}

	public function test_uncode_settings_become_fields_grouped_by_section(): void {
		$options = new Theme_Options();

		$this->assertSame(
			array(
				array(
					'name'   => 'Navbar',
					'group'  => 'General',
					'fields' => 3,
				),
				array(
					'name'   => 'Layout',
					'group'  => 'General',
					'fields' => 1,
				),
				array(
					'name'   => 'Customize',
					'group'  => 'Visual',
					'fields' => 3,
				),
			),
			$options->sections()
		);
		$this->assertNull( $options->field( '_header_title' ) );
		$this->assertSame( 'select', $options->field( '_uncode_headers' )['kind'] );
		$this->assertSame( 'code', $options->field( '_uncode_custom_css' )['kind'] );
	}

	public function test_values_explain_choices_conditions_and_secrets(): void {
		$options = new Theme_Options();

		$hover = $this->described( $options, '_uncode_button_hover' );
		$this->assertSame( 'full-colored', $hover['value'] );
		$this->assertSame( 'Flat', $hover['value_label'] );
		$this->assertFalse( $hover['is_default'] );

		$this->assertFalse( $this->described( $options, '_uncode_menu_sticky' )['applies'] );
		$this->assertFalse( $this->described( $options, '_uncode_menu_shrink' )['applies'], 'Its controller does not apply either.' );
		$this->values['_uncode_headers'] = 'hmenu-right';
		$this->assertTrue( $this->described( new Theme_Options(), '_uncode_menu_shrink' )['applies'] );

		$secret = $this->described( $options, '_uncode_gmaps_api' );
		$this->assertSame( Theme_Options::REDACTED, $secret['value'] );
		$this->assertTrue( $secret['is_set'] );

		$css = $this->described( $options, '_uncode_custom_css' );
		$this->assertSame( Theme_Options::MAX_TEXT_LENGTH, strlen( $css['value'] ) );
		$this->assertTrue( $css['value_truncated'] );
	}

	public function test_ability_filters_by_section_search_and_changes(): void {
		$ability = new Get_Theme_Options( new Theme_Options() );

		$this->assertSame( array( 'sections' ), array_keys( $ability->execute() ) );

		$search = $ability->execute( array( 'search' => 'button hover' ) );
		$this->assertSame( array( '_uncode_button_hover' ), array_column( $search['fields'], 'name' ) );

		$changed = $ability->execute(
			array(
				'section'      => 'customize',
				'changed_only' => true,
				'limit'        => 2,
			)
		);
		$this->assertSame( array( '_uncode_button_hover', '_uncode_gmaps_api' ), array_column( $changed['fields'], 'name' ) );
		$this->assertSame( 3, $changed['total'] );
		$this->assertTrue( $changed['truncated'] );

		$named = $ability->execute( array( 'fields' => array( '_uncode_main_width', '_nope' ) ) );
		$this->assertSame( array( '1200', 'px' ), $named['fields'][0]['value'] );
		$this->assertSame( array( '_nope' ), $named['unknown_fields'] );

		$this->assertInstanceOf( WP_Error::class, $ability->execute( array( 'section' => 'Missing' ) ) );
	}
}
