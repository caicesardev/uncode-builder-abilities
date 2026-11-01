<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Attribute_Encoder;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Operations;
use Uncode_Builder_Abilities\Nesting_Rules;
use WP_Error;

final class LayoutOperationsTest extends Test_Case {

	const PAGE = '[vc_row][vc_column][vc_custom_heading]A[/vc_custom_heading][vc_custom_heading]B[/vc_custom_heading][/vc_column]'
		. '[vc_column][vc_column_text]C[/vc_column_text][/vc_column][/vc_row]';

	private function operations(): Layout_Operations {
		$catalog = $this->catalog();

		return new Layout_Operations( $catalog, new Nesting_Rules( $catalog ), $this->composer( $catalog ), $this->parser( $catalog ) );
	}

	private function document( string $content = self::PAGE ): Layout_Document {
		return new Layout_Document( $content, $this->parser()->parse( $content, false )['tree'] );
	}

	private static function error_code( $result ): string {
		return $result instanceof WP_Error ? $result->get_error_code() : 'no error';
	}

	public function test_inserts_at_the_end_of_a_container(): void {
		$result = $this->operations()->insert(
			$this->document(),
			'0.1',
			null,
			array(
				array(
					'tag'     => 'vc_custom_heading',
					'content' => 'D',
				),
			)
		);

		$inserted = $this->document( $result['content'] )->node( '0.1.1' );
		$this->assertSame( 'vc_custom_heading', $inserted['tag'] );
		$this->assertSame( array( strpos( self::PAGE, '[/vc_column][/vc_row]' ) ), $result['focus'] );
		$this->assertSame( $result['snippet'], $this->document( $result['content'] )->source_of( $inserted ) );
	}

	public function test_inserts_before_the_element_at_an_index(): void {
		$result = $this->operations()->insert(
			$this->document(),
			'',
			0,
			array(
				array(
					'tag'      => 'vc_row',
					'children' => array( array( 'tag' => 'vc_column' ) ),
				),
			)
		);

		$this->assertSame( array( 0 ), $result['focus'] );
		$this->assertStringEndsWith( self::PAGE, $result['content'] );
		$this->assertSame( 2, $this->document( $result['content'] )->element_children_count( '' ) );
	}

	public function test_refuses_invalid_targets_without_changing_anything(): void {
		$operations = $this->operations();
		$heading    = array( array( 'tag' => 'vc_custom_heading' ) );

		$this->assertSame( 'uncode_builder_invalid_layout', self::error_code( $operations->insert( $this->document(), '', null, $heading ) ) );
		$this->assertSame( 'uncode_builder_not_a_container', self::error_code( $operations->insert( $this->document(), '0.0.0', null, $heading ) ) );
		$this->assertSame( 'uncode_builder_path_not_found', self::error_code( $operations->insert( $this->document(), '0.7', null, $heading ) ) );
	}

	public function test_updates_attributes_and_content_in_place(): void {
		$document = $this->document();
		$result   = $this->operations()->update( $document, $document->node( '0.0.1' ), array( 'heading_semantic' => 'h3' ), 'B2' );

		$updated = $this->document( $result['content'] )->node( '0.0.1' );
		$this->assertSame( array( 'heading_semantic' => 'h3' ), (array) $updated['attributes'] );
		$this->assertSame( 'B2', $this->document( $result['content'] )->inner_source_of( $updated ) );
		$this->assertSame( '[vc_custom_heading heading_semantic="h3"]', $result['snippet'] );
		$this->assertStringStartsWith( '[vc_row][vc_column][vc_custom_heading]A[/vc_custom_heading]', $result['content'] );
	}

	public function test_refuses_text_content_on_a_container(): void {
		$document = $this->document();

		$this->assertSame( 'uncode_builder_invalid_layout', self::error_code( $this->operations()->update( $document, $document->node( '0.0' ), array(), 'Text' ) ) );
	}

	public function test_moves_an_element_forward_past_its_own_source(): void {
		$document = $this->document();
		$result   = $this->operations()->move( $document, $document->node( '0.0.0' ), '0.1', null );

		$this->assertSame(
			'[vc_row][vc_column][vc_custom_heading]B[/vc_custom_heading][/vc_column]'
			. '[vc_column][vc_column_text]C[/vc_column_text][vc_custom_heading]A[/vc_custom_heading][/vc_column][/vc_row]',
			$result['content']
		);
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_moves_an_element_backwards_and_warns_about_the_emptied_parent(): void {
		$document = $this->document();
		$result   = $this->operations()->move( $document, $document->node( '0.1.0' ), '0.0', 0 );

		$this->assertSame(
			'[vc_row][vc_column][vc_column_text]C[/vc_column_text][vc_custom_heading]A[/vc_custom_heading][vc_custom_heading]B[/vc_custom_heading][/vc_column]'
			. '[vc_column][/vc_column][/vc_row]',
			$result['content']
		);
		$this->assertSame( array( 'The element at path "0.1" is now empty.' ), $result['warnings'] );
	}

	public function test_refuses_moving_an_element_inside_itself(): void {
		$document = $this->document();

		$this->assertSame( 'uncode_builder_move_into_itself', self::error_code( $this->operations()->move( $document, $document->node( '0.0' ), '0.0', null ) ) );
	}

	public function test_removes_an_element(): void {
		$document = $this->document();
		$result   = $this->operations()->remove( $document, $document->node( '0.0.0' ) );

		$this->assertSame( str_replace( '[vc_custom_heading]A[/vc_custom_heading]', '', self::PAGE ), $result['content'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_template_source_with_raw_code_needs_unfiltered_html(): void {
		$source = '[vc_raw_html]PHA+SG9sYTwvcD4=[/vc_raw_html]';

		$allowed = $this->operations()->insert_source( $this->document(), '0.1', null, $source );
		$this->assertStringContainsString( $source, $allowed['content'] );

		$this->without_unfiltered_html();
		$refused = $this->operations()->insert_source( $this->document(), '0.1', null, $source );
		$this->assertSame( Attribute_Encoder::RAW_CODE_REFUSAL, $refused->get_error_data()['errors'][0]['message'] );
	}
}
