<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Functions;
use Uncode_Builder_Abilities\Uncode_Integration;

final class UncodeIntegrationTest extends Test_Case {

	public function test_links_colour_parameters_to_their_colour_mode(): void {
		$params = array(
			array(
				'type'       => 'uncode_advanced_color_selector',
				'param_name' => 'text_color_type',
				'supported'  => array( 'palette', 'solid' ),
			),
			array(
				'type'                => 'dropdown',
				'param_name'          => 'text_color',
				'advanced-color-type' => 'uncode-palette',
			),
			array(
				'type'                => 'uncode_colorpicker',
				'param_name'          => 'text_color_solid',
				'advanced-color-type' => 'uncode-solid',
			),
		);

		$selector = Uncode_Integration::enrich_param( array( 'name' => 'text_color_type' ), $params[0], 0, $params );
		$palette  = Uncode_Integration::enrich_param( array( 'name' => 'text_color' ), $params[1], 1, $params );
		$solid    = Uncode_Integration::enrich_param( array( 'name' => 'text_color_solid' ), $params[2], 2, $params );

		$this->assertSame( array( 'uncode-palette', 'uncode-solid' ), array_column( $selector['options'], 'value' ) );
		$this->assertSame( array( 'param' => 'text_color_type', 'in' => array( '', 'uncode-palette' ) ), $palette['active_when'] );
		$this->assertSame( array( 'param' => 'text_color_type', 'in' => array( 'uncode-solid' ) ), $solid['active_when'] );
	}

	public function test_shortcode_ids_are_six_digits_and_unique(): void {
		$sequence = array( 123456, 123456, 654321 );
		Functions\when( 'wp_rand' )->alias( static function () use ( &$sequence ) {
			return array_shift( $sequence );
		} );

		$this->assertSame( '654321', Uncode_Integration::generate_shortcode_id( '', 'uncode_shortcode_id', array( '123456' ) ) );
		$this->assertSame( 'kept', Uncode_Integration::generate_shortcode_id( 'kept', 'tab_id', array() ) );
	}

	public function test_without_uncode_no_module_is_retired(): void {
		$this->assertSame( array( 'vc_btn' => 'custom' ), Uncode_Integration::add_retired_modules( array( 'vc_btn' => 'custom' ) ) );
	}
}
