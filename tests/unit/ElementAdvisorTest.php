<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;
use Uncode_Builder_Abilities\Element_Advisor;

final class ElementAdvisorTest extends Test_Case {

	public function test_prefixes_advice_with_the_element_path_and_walks_children(): void {
		Filters\expectApplied( 'uncode_builder_abilities_element_advice' )
			->twice()
			->andReturnUsing( static fn( array $advice, string $tag, array $attributes ) => 'vc_button' === $tag ? array( 'size is ' . $attributes['size'] ) : array() );

		$tree = $this->parser()->parse( '[vc_column][vc_button size="btn-lg"]Go[/vc_button][/vc_column]', false )['tree'];

		$this->assertSame( array( '0.0 vc_button: size is btn-lg' ), Element_Advisor::advice_for( $tree ) );
	}
}
