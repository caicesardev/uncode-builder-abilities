<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;

final class ElementComposerTest extends Test_Case {

	private static function row( array $children ): array {
		return array(
			'tag'      => 'vc_row',
			'children' => array(
				array(
					'tag'      => 'vc_column',
					'children' => $children,
				),
			),
		);
	}

	private static function messages( array $composed ): string {
		return implode( ' | ', array_column( $composed['errors'], 'message' ) );
	}

	public function test_composes_valid_elements(): void {
		$composed = $this->composer()->compose(
			array(
				self::row(
					array(
						array(
							'tag'        => 'vc_custom_heading',
							'attributes' => array( 'heading_semantic' => 'h1' ),
							'content'    => 'Hola',
						),
						array( 'tag' => 'vc_empty_space' ),
					)
				),
			),
			null,
			array()
		);

		$this->assertSame( array(), $composed['errors'] );
		$this->assertSame( '[vc_row][vc_column][vc_custom_heading heading_semantic="h1"]Hola[/vc_custom_heading][vc_empty_space][/vc_column][/vc_row]', $composed['shortcodes'][0] );
	}

	public function test_reports_every_problem_with_its_location(): void {
		$composed = $this->composer()->compose(
			array(
				array( 'tag' => 'vc_column_text', 'content' => 'loose' ),
				array(
					'tag'        => 'vc_row',
					'attributes' => array( 'unknown' => 'x' ),
					'children'   => array(
						array(
							'tag'      => 'vc_column',
							'children' => array(
								array( 'tag' => 'vc_column' ),
								array(
									'tag'        => 'vc_custom_heading',
									'attributes' => array( 'heading_semantic' => 'h9' ),
								),
							),
						),
					),
				),
			),
			null,
			array()
		);

		$this->assertSame(
			array(
				'elements[0]',
				'elements[1].attributes.unknown',
				'elements[1].children[0].children[0]',
				'elements[1].children[0].children[1].attributes.heading_semantic',
			),
			array_column( $composed['errors'], 'at' )
		);
	}

	public function test_generates_missing_ids_and_avoids_used_ones(): void {
		$composed = $this->composer()->compose(
			array(
				self::row(
					array(
						array(
							'tag'      => 'vc_tta_tabs',
							'children' => array(
								array(
									'tag'        => 'vc_tta_section',
									'attributes' => array( 'tab_id' => 'taken' ),
								),
							),
						),
					)
				),
			),
			null,
			array( 'taken' )
		);

		$this->assertStringContainsString( 'The ID "taken" is already used', self::messages( $composed ) );

		$generated = $this->composer()->compose( array( self::row( array( array( 'tag' => 'vc_tta_tabs', 'children' => array( array( 'tag' => 'vc_tta_section' ) ) ) ) ) ), null, array() );
		$this->assertMatchesRegularExpression( '/tab_id="\d+-aaaaaa"/', $generated['shortcodes'][0] );
		$this->assertCount( 1, $generated['generated_ids'] );
	}

	public function test_refuses_retired_elements_with_their_replacement(): void {
		Filters\expectApplied( 'uncode_builder_abilities_legacy_elements' )->andReturn( array( 'vc_btn' => 'vc_button' ) );

		$composed = $this->composer()->compose( array( self::row( array( array( 'tag' => 'vc_btn' ) ) ) ), null, array() );

		$this->assertStringContainsString( 'use "vc_button" instead', self::messages( $composed ) );
	}

	public function test_limits_the_size_of_one_call(): void {
		$many     = self::row( array_fill( 0, 500, array( 'tag' => 'vc_empty_space' ) ) );
		$composed = $this->composer()->compose( array( $many ), null, array() );

		$this->assertStringContainsString( 'at most 500 elements', self::messages( $composed ) );

		$deep = array( 'tag' => 'vc_empty_space' );
		for ( $level = 0; $level < 35; $level++ ) {
			$deep = array(
				'tag'      => 'vc_row',
				'children' => array( $deep ),
			);
		}
		$this->assertStringContainsString( 'nested more than 30 levels', self::messages( $this->composer()->compose( array( $deep ), null, array() ) ) );
	}

	public function test_assign_ids_only_touches_opening_tags_that_need_it(): void {
		$source = '[vc_tta_tabs][vc_tta_section title="Uno" tab_id="taken"][vc_column_text]<p>A</p>[/vc_column_text][/vc_tta_section][/vc_tta_tabs]';
		$tree   = $this->parser()->parse( $source, false )['tree'];

		$assigned = $this->composer()->assign_ids( $source, $tree, array( 'taken' ) );

		$this->assertMatchesRegularExpression( '/^\[vc_tta_tabs\]\[vc_tta_section title="Uno" tab_id="\d+-aaaaaa"\]\[vc_column_text\]<p>A<\/p>\[\/vc_column_text\]/', $assigned['content'] );
		$this->assertCount( 1, $assigned['generated_ids'] );
	}
}
