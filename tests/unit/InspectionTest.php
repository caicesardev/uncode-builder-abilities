<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Uncode_Builder_Abilities\Abilities\Find_Elements;
use Uncode_Builder_Abilities\Device_Visibility;
use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Embedded_Blocks;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Uncode_Integration;
use WP_Post;

final class InspectionTest extends Test_Case {

	const PAGE = '[vc_row][vc_column mobile_visibility="yes"][vc_custom_heading heading_semantic="h1"]Welcome home[/vc_custom_heading]'
		. '[vc_row_inner][vc_column_inner][vc_custom_heading heading_semantic="h2" desktop_visibility="yes"]Inner title[/vc_custom_heading][/vc_column_inner][/vc_row_inner]'
		. '[/vc_column][/vc_row][vc_row][vc_column][vc_column_text]<p>Plain text</p>[/vc_column_text][/vc_column][/vc_row]';

	protected function setUp(): void {
		parent::setUp();

		Filters\expectApplied( 'uncode_builder_abilities_hidden_devices' )->zeroOrMoreTimes()->andReturnUsing( array( Uncode_Integration::class, 'hidden_devices' ) );
	}

	private static function post( int $id, string $content, array $fields = array() ): WP_Post {
		return new WP_Post(
			(object) array_merge(
				array(
					'ID'           => $id,
					'post_type'    => 'page',
					'post_status'  => 'draft',
					'post_title'   => 'Page ' . $id,
					'post_content' => $content,
					'post_parent'  => 0,
				),
				$fields
			)
		);
	}

	private static function stub_posts( WP_Post ...$posts ): void {
		$by_id = array();
		foreach ( $posts as $post ) {
			$by_id[ $post->ID ] = $post;
		}
		Functions\when( 'get_post' )->alias( static fn( $id ) => $by_id[ (int) $id ] ?? null );
	}

	public function test_visibility_is_inherited_and_only_reported_when_restricted(): void {
		$tree = Device_Visibility::annotate( $this->parser()->parse( self::PAGE, false )['tree'] );

		$column  = $tree[0]['children'][0];
		$heading = $column['children'][0];
		$inner   = $column['children'][1]['children'][0]['children'][0];

		$this->assertArrayNotHasKey( 'visible_on', $tree[0] );
		$this->assertSame( array( 'desktop', 'tablet' ), $column['visible_on'] );
		$this->assertSame( array( 'desktop', 'tablet' ), $heading['visible_on'] );
		$this->assertSame( array( 'tablet' ), $inner['visible_on'] );
		$this->assertArrayNotHasKey( 'visible_on', $tree[1]['children'][0] );
	}

	public function test_copy_of_names_the_original_for_both_copy_strategies(): void {
		Functions\when( 'wp_is_post_autosave' )->alias( static fn( $post ) => 'revision' === $post->post_type ? (int) $post->post_parent : false );
		Functions\when( 'get_post_meta' )->alias(
			static function ( $id, $key ) {
				$meta = array(
					Draft_Copies::REWRITE_COPY_OF_META => '1',
					Draft_Copies::COPY_ORIGINAL_META   => '7',
				);

				return 31 === (int) $id ? ( $meta[ $key ] ?? '' ) : '';
			}
		);

		$autosave = self::post( 30, '', array( 'post_type' => 'revision', 'post_parent' => 7 ) );
		$rewrite  = self::post( 31, '' );
		$plain    = self::post( 32, '' );

		$this->assertSame( 7, Draft_Copies::original_id( $autosave ) );
		$this->assertSame( 7, Draft_Copies::original_id( $rewrite ) );
		$this->assertNull( Draft_Copies::original_id( $plain ) );
	}

	public function test_find_elements_searches_nested_containers(): void {
		self::stub_posts( self::post( 5, self::PAGE ) );
		$find = new Find_Elements( $this->parser() );

		$headings = $find->execute(
			array(
				'post_id' => 5,
				'tag'     => 'vc_custom_heading',
			)
		);
		$this->assertSame( 2, $headings['count'] );
		$this->assertSame( array( '0.0.0', '0.0.1.0.0' ), array_column( $headings['matches'], 'path' ) );
		$this->assertSame( 'h2', $headings['matches'][1]['attributes']->heading_semantic );
		$this->assertSame( array( 'tablet' ), $headings['matches'][1]['visible_on'] );
		$this->assertSame( md5( self::PAGE ), $headings['version'] );

		$by_attribute = $find->execute(
			array(
				'post_id'    => 5,
				'attributes' => array( 'heading_semantic' => array( 'contains' => 'H1' ) ),
			)
		);
		$this->assertSame( array( 'Welcome home' ), array_column( $by_attribute['matches'], 'text' ) );

		$by_text = $find->execute(
			array(
				'post_id' => 5,
				'text'    => 'PLAIN',
				'limit'   => 1,
			)
		);
		$this->assertSame( array( 'vc_column_text' ), array_column( $by_text['matches'], 'tag' ) );
		$this->assertFalse( $by_text['truncated'] );
	}

	public function test_embedded_blocks_expand_once_and_report_missing_ones(): void {
		Filters\expectApplied( 'uncode_builder_abilities_embedded_blocks' )->andReturn( array( 'uncode_block' => 'id' ) );
		self::stub_posts(
			self::post( 10, '[vc_row][vc_column][uncode_block id="20"][uncode_block id="99"][/vc_column][/vc_row]' ),
			self::post( 20, '[vc_row][vc_column][vc_custom_heading]Block title[/vc_custom_heading][uncode_block id="20"][/vc_column][/vc_row]', array( 'post_type' => 'uncodeblock' ) )
		);

		$tree     = Shortcode_Parser::without_source( $this->parser()->parse( (string) get_post( 10 )->post_content, false )['tree'] );
		$expanded = ( new Embedded_Blocks( $this->parser() ) )->expand( $tree, false, 10 );
		$column   = $expanded[0]['children'][0];

		$block = $column['children'][0]['block'];
		$this->assertSame( 20, $block['post_id'] );
		$this->assertTrue( $block['read_only'] );
		$this->assertSame( 'Block title', $block['tree'][0]['children'][0]['children'][0]['content'] );
		$this->assertFalse( $block['tree'][0]['children'][0]['children'][1]['block']['expanded'] );
		$this->assertTrue( $column['children'][1]['block']['missing'] );
	}
}
