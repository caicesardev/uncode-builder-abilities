<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Builder_Escaping;

final class BuilderEscapingTest extends Test_Case {

	public function test_escapes_quotes_and_brackets_like_wpbakery(): void {
		$this->assertSame( 'say ``hi`` `{`now`}`', Builder_Escaping::escape( 'say "hi" [now]' ) );
	}

	/**
	 * @dataProvider values
	 */
	public function test_unescape_reverts_escape( string $value ): void {
		$this->assertSame( $value, Builder_Escaping::unescape( Builder_Escaping::escape( $value ) ) );
	}

	public function values(): array {
		return array(
			'quotes and brackets' => array( 'a "b" [c]' ),
			'quote then bracket'  => array( '"[' ),
			'bracket then quote'  => array( ']"' ),
			'json'                => array( '{"css":"x","sliders":[{"position":0}]}' ),
			'plain'               => array( 'plain text' ),
		);
	}

	public function test_detects_escaped_values(): void {
		$this->assertTrue( Builder_Escaping::is_escaped( '{``css``:1}' ) );
		$this->assertTrue( Builder_Escaping::is_escaped( '`{`1`}`' ) );
		$this->assertFalse( Builder_Escaping::is_escaped( 'plain `code`' ) );
	}
}
