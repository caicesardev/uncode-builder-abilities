<?php

namespace Uncode_Builder_Abilities\Tests;

use InvalidArgumentException;
use Uncode_Builder_Abilities\Attribute_Encoder;

final class AttributeEncoderTest extends Test_Case {

	private Attribute_Encoder $encoder;

	protected function setUp(): void {
		parent::setUp();
		$this->encoder = new Attribute_Encoder();
	}

	private static function param( string $kind, array $extra = array() ): array {
		return array_merge(
			array(
				'name' => 'value',
				'type' => $kind,
				'kind' => $kind,
			),
			$extra
		);
	}

	public function test_escapes_text_like_wpbakery(): void {
		$this->assertSame( 'say ``hi`` `{`now`}`', $this->encoder->encode( self::param( 'text' ), 'say "hi" [now]' ) );
	}

	public function test_refuses_backslashes(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'text' ), 'a\\b' );
	}

	public function test_select_accepts_only_its_options(): void {
		$param = self::param( 'select', array( 'options' => array( array( 'value' => 'h1', 'label' => 'h1' ) ) ) );

		$this->assertSame( 'h1', $this->encoder->encode( $param, 'h1' ) );
		$this->assertSame( '', $this->encoder->encode( $param, '' ) );

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( $param, 'h9' );
	}

	public function test_checkbox_accepts_booleans(): void {
		$param = self::param( 'checkbox', array( 'options' => array( array( 'value' => 'yes', 'label' => '' ) ) ) );

		$this->assertSame( 'yes', $this->encoder->encode( $param, true ) );
		$this->assertSame( '', $this->encoder->encode( $param, false ) );
	}

	public function test_number_respects_bounds(): void {
		$param = self::param( 'number', array( 'min' => 0, 'max' => 7 ) );

		$this->assertSame( '4', $this->encoder->encode( $param, 4 ) );

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( $param, 8 );
	}

	public function test_fraction_must_fit_the_row(): void {
		$this->assertSame( '2/3', $this->encoder->encode( self::param( 'fraction' ), '2/3' ) );

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'fraction' ), '3/2' );
	}

	public function test_links_are_encoded_like_wpbakery(): void {
		$this->assertSame(
			'url:%2Fcontacto%2F|title:Hablemos%20%22ya%22|target:_blank',
			$this->encoder->encode(
				self::param( 'link' ),
				array(
					'url'    => '/contacto/',
					'title'  => 'Hablemos "ya"',
					'target' => '_blank',
				)
			)
		);
	}

	/**
	 * @dataProvider unsafe_links
	 */
	public function test_refuses_script_links( $link ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'link' ), $link );
	}

	public function unsafe_links(): array {
		return array(
			'object'        => array( array( 'url' => 'javascript:alert(1)' ) ),
			'packed string' => array( 'url:javascript%3Aalert(1)|title:x' ),
			'data url'      => array( array( 'url' => 'data:text/html,<b>x</b>' ) ),
		);
	}

	public function test_design_css_gets_a_class_and_important(): void {
		$this->assertMatchesRegularExpression(
			'/^\.vc_custom_\d+\{margin-top: 10px !important;\}$/',
			$this->encoder->encode( self::param( 'design_css' ), array( 'margin-top' => '10px' ) )
		);
	}

	/**
	 * @dataProvider unsafe_css
	 */
	public function test_refuses_unsafe_css( $css ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'design_css' ), $css );
	}

	public function unsafe_css(): array {
		return array(
			'closing style tag' => array( array( 'margin-top' => '</style><script>x</script>' ) ),
			'expression'        => array( array( 'width' => 'expression(alert(1))' ) ),
			'script url'        => array( array( 'background' => 'url(javascript:alert(1))' ) ),
			'import'            => array( array( 'margin' => '1px @import x' ) ),
			'text form'         => array( '.vc_custom_1{margin-top: 1px;}</style>' ),
		);
	}

	public function test_without_unfiltered_html_raw_code_is_refused(): void {
		$this->without_unfiltered_html();

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'encoded_html' ), '<b>x</b>' );
	}

	public function test_without_unfiltered_html_raw_content_is_refused(): void {
		$this->without_unfiltered_html();

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode_content( 'textarea_raw_html', '<b>x</b>' );
	}

	public function test_without_unfiltered_html_css_goes_through_safecss(): void {
		$this->without_unfiltered_html();

		$this->assertStringContainsString( 'margin-top: 10px', $this->encoder->encode( self::param( 'design_css' ), array( 'margin-top' => '10px' ) ) );

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'design_css' ), array( 'position' => 'fixed' ) );
	}

	public function test_without_unfiltered_html_group_texts_are_filtered(): void {
		$this->without_unfiltered_html();

		$encoded = $this->encoder->encode( self::param( 'group' ), array( array( 'label' => '<script>alert(1)</script>Design' ) ) );

		$this->assertSame( array( array( 'label' => 'Design' ) ), json_decode( rawurldecode( $encoded ), true ) );
	}

	public function test_attachments_must_exist(): void {
		$this->assertSame( '5,6', $this->encoder->encode( self::param( 'attachment_ids' ), array( 5, 6 ) ) );

		$this->expectException( InvalidArgumentException::class );
		$this->encoder->encode( self::param( 'attachment_id' ), 99 );
	}
}
