<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Shortcode_Parser;

final class ShortcodeParserTest extends Test_Case {

	const PAGE = '[vc_row el_class="hero"][vc_column][vc_custom_heading heading_semantic="h1"]Hola [b]mundo[/b][/vc_custom_heading][vc_empty_space empty_h="2"][/vc_column][/vc_row]'
		. '[vc_row][vc_column][vc_tta_tabs][vc_tta_section title="Uno" tab_id="tab-1"][vc_column_text]<p>A</p>[/vc_column_text][/vc_tta_section][/vc_tta_tabs][/vc_column][/vc_row]';

	public function test_builds_the_layout_tree(): void {
		$parsed = $this->parser()->parse( self::PAGE, false );
		$tree   = Shortcode_Parser::without_source( $parsed['tree'] );

		$this->assertSame( 9, $parsed['element_count'] );
		$this->assertSame( array( 'vc_row', 'vc_row' ), array_column( $tree, 'tag' ) );
		$this->assertSame( '0.0.0', $tree[0]['children'][0]['children'][0]['path'] );
		$this->assertSame( 'Hola [b]mundo[/b]', $tree[0]['children'][0]['children'][0]['content'] );
		$this->assertArrayNotHasKey( 'content', $tree[0]['children'][0]['children'][1] );
		$this->assertSame( 'tab-1', $tree[1]['children'][0]['children'][0]['children'][0]['id'] );
		$this->assertSame( array(), $parsed['unknown_tags'] );
	}

	public function test_reports_unknown_tags_inside_containers(): void {
		$parsed = $this->parser()->parse( '[vc_row][vc_column][foo x="1"][/vc_column][/vc_row]', false );

		$this->assertSame( array( 'foo' ), $parsed['unknown_tags'] );
	}

	public function test_source_ranges_point_at_the_exact_text(): void {
		$parsed   = $this->parser()->parse( self::PAGE, false );
		$document = new Layout_Document( self::PAGE, $parsed['tree'] );

		$this->assertSame( '[vc_empty_space empty_h="2"]', $document->source_of( $document->node( '0.0.1' ) ) );
		$this->assertSame( '<p>A</p>', $document->inner_source_of( $document->node( '1.0.0.0.0' ) ) );
		$this->assertSame( strlen( self::PAGE ), $document->insertion_offset( '', null ) );
	}

	public function test_escaped_shortcodes_stay_text(): void {
		$tree = $this->parser()->parse( '[[vc_row]]', false )['tree'];

		$this->assertSame( '[[vc_row]]', $tree[0]['text'] );
		$this->assertArrayNotHasKey( 'tag', $tree[0] );
	}

	public function test_decodes_encoded_attributes(): void {
		$content = '[vc_button link="url:%2Fcontacto%2F|title:Contacto"]Hablemos[/vc_button]';
		$tree    = $this->parser()->parse( $content, true )['tree'];

		$this->assertSame( '/contacto/', $tree[0]['decoded']['link']['url'] );
	}

	public function test_keeps_raw_attribute_values(): void {
		$this->assertSame(
			array(
				'gradient' => '{``css``:``x``}',
				'el_class' => 'a b',
			),
			Shortcode_Parser::raw_attributes( ' gradient="{``css``:``x``}" el_class=\'a b\'' )
		);
	}
}
