<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Attribute_Decoder;

final class AttributeDecoderTest extends Test_Case {

	private Attribute_Decoder $decoder;

	protected function setUp(): void {
		parent::setUp();
		$this->decoder = new Attribute_Decoder();
	}

	public function test_decodes_uncode_gradients(): void {
		$stored  = '{``css``:``background: linear-gradient(90deg, red 0%);``,``sliders``:`{`{``position``:0}`}`}';
		$decoded = $this->decoder->decode( 'uncode_gradientpicker', $stored );

		$this->assertSame( 'background: linear-gradient(90deg, red 0%);', $decoded['css'] );
		$this->assertSame( array( array( 'position' => 0 ) ), $decoded['sliders'] );
	}

	public function test_unescapes_plain_text(): void {
		$this->assertSame( 'Hablemos "ya"', $this->decoder->decode( 'textfield', 'Hablemos ``ya``' ) );
	}

	public function test_leaves_values_without_encoding_alone(): void {
		$this->assertNull( $this->decoder->decode( 'textfield', 'plain' ) );
		$this->assertNull( $this->decoder->decode( 'textfield', '' ) );
	}

	public function test_decodes_links(): void {
		$this->assertSame(
			array(
				'url'    => '/contacto/',
				'title'  => 'Hablemos "ya"',
				'target' => '_blank',
			),
			$this->decoder->decode( 'vc_link', 'url:%2Fcontacto%2F|title:Hablemos%20%22ya%22|target:_blank' )
		);
	}

	public function test_decodes_raw_html(): void {
		$this->assertSame( '<p>x</p>', $this->decoder->decode( 'textarea_raw_html', base64_encode( rawurlencode( '<p>x</p>' ) ) ) );
	}

	public function test_decodes_parameter_groups(): void {
		$stored = rawurlencode( (string) json_encode( array( array( 'label' => 'Design', 'value' => '90' ) ) ) );

		$this->assertSame( array( array( 'label' => 'Design', 'value' => '90' ) ), $this->decoder->decode( 'param_group', $stored ) );
	}

	public function test_decodes_design_css(): void {
		$this->assertSame(
			array(
				'class'        => 'vc_custom_1',
				'declarations' => array(
					'margin-top'   => '10px !important',
					'padding-left' => '2em',
				),
			),
			$this->decoder->decode( 'css_editor', '.vc_custom_1{margin-top: 10px !important;padding-left: 2em;}' )
		);
	}
}
