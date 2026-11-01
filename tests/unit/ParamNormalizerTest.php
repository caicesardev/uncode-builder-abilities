<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Param_Normalizer;

final class ParamNormalizerTest extends Test_Case {

	public function test_plain_text_falls_back_only_when_empty(): void {
		$this->assertSame( 'Hero', Param_Normalizer::plain_text_or( '<b>Hero</b>', 'General' ) );
		$this->assertSame( 'General', Param_Normalizer::plain_text_or( '<b> </b>', 'General' ) );
	}

	public function test_reads_every_wpbakery_option_format(): void {
		$this->assertSame(
			array(
				array( 'value' => 'accent', 'label' => 'Accent' ),
				array( 'value' => 'h1', 'label' => 'Heading 1' ),
				array( 'value' => 'plain', 'label' => 'plain' ),
			),
			Param_Normalizer::options_from_value(
				array(
					array( 'accent', 'Accent' ),
					'Heading 1' => 'h1',
					'plain',
				)
			)
		);
	}

	public function test_normalizes_the_heading_schema(): void {
		$params = ( new Param_Normalizer() )->normalize_all( \WPBMap::$map['vc_custom_heading']['params'], false );
		$byname = array_column( $params, null, 'name' );

		$this->assertSame( 'html', $byname['content']['kind'] );
		$this->assertSame( 'select', $byname['heading_semantic']['kind'] );
		$this->assertSame( 'h2', $byname['heading_semantic']['default'] );
		$this->assertSame( array( 'h1', 'h2', 'h3' ), array_column( $byname['heading_semantic']['options'], 'value' ) );
		$this->assertSame(
			array(
				'param' => 'heading_semantic',
				'in'    => array( 'h1', 'h2' ),
			),
			$byname['text_size']['visible_when']
		);
		$this->assertSame( 'checkbox', $byname['uppercase']['kind'] );
		$this->assertSame( 'General', $byname['uppercase']['group'] );
	}

	public function test_unknown_types_are_reported_as_unknown(): void {
		$params = ( new Param_Normalizer() )->normalize_all( \WPBMap::$map['vc_row']['params'], false );

		$this->assertSame( 'unknown', array_column( $params, 'kind', 'name' )['top_padding'] );
	}
}
