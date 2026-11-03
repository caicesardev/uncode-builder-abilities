<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;
use Uncode_Builder_Abilities\Element_Advisor;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Uncode_Integration;

final class ElementAdvisorTest extends Test_Case {

	public function test_prefixes_advice_with_the_element_path_and_walks_children(): void {
		Filters\expectApplied( 'uncode_builder_abilities_element_advice' )
			->twice()
			->andReturnUsing( static fn( array $advice, string $tag, array $attributes ) => 'vc_button' === $tag ? array( 'size is ' . $attributes['size'] ) : array() );

		$content = '[vc_column][vc_button size="btn-lg"]Go[/vc_button][/vc_column]';
		$tree    = $this->parser()->parse( $content, false )['tree'];

		$this->assertSame( array( '0.0 vc_button: size is btn-lg' ), Element_Advisor::advice_for( new Layout_Document( $content, $tree ), $tree ) );
	}

	public function test_content_blocks_must_have_a_row_of_their_own(): void {
		Filters\expectApplied( 'uncode_builder_abilities_element_advice' )->zeroOrMoreTimes()->andReturnUsing( array( Uncode_Integration::class, 'advise_element' ) );

		$content  = '[vc_row][vc_column][uncode_block id="3"][/vc_column][/vc_row]'
			. '[vc_row][vc_column][vc_column_text]<p>Intro</p>[/vc_column_text][uncode_block id="4"][/vc_column][/vc_row]';
		$tree     = $this->parser()->parse( $content, false )['tree'];
		$document = new Layout_Document( $content, $tree );

		$this->assertSame( array(), Element_Advisor::advice_for( $document, array( $document->node( '0' ) ) ) );
		$this->assertSame( array( '1.0.1 uncode_block: ' . Uncode_Integration::BLOCK_ROW_ADVICE ), Element_Advisor::advice_for( $document, array( $document->node( '1.0.1' ) ) ) );
	}
}
