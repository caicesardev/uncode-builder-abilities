<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Preview;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Write_Policy;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Render_Preview extends Ability {

	private Shortcode_Parser $parser;

	private Layout_Preview $preview;

	public function __construct( Shortcode_Parser $parser, Layout_Preview $preview ) {
		$this->parser  = $parser;
		$this->preview = $preview;
	}

	public function name(): string {
		return 'render-preview';
	}

	public function has_permission( $input = null ): bool {
		$post = get_post( (int) ( self::input_array( $input )['post_id'] ?? 0 ) );

		return $post instanceof WP_Post && Draft_Copies::is_layout_target( $post ) && current_user_can( 'edit_post', Draft_Copies::permission_target_id( $post ) );
	}

	public function execute( $input = null ) {
		$input = self::input_array( $input );
		$post  = get_post( (int) ( $input['post_id'] ?? 0 ) );

		if ( ! $post instanceof WP_Post || ! Draft_Copies::is_layout_target( $post ) ) {
			return new WP_Error( 'uncode_builder_post_not_found', 'The post does not exist.', array( 'status' => 404 ) );
		}

		$path   = (string) ( $input['path'] ?? Layout_Document::ROOT_PATH );
		$source = $post->post_content;
		if ( Layout_Document::ROOT_PATH !== $path ) {
			$document = new Layout_Document( $post->post_content, $this->parser->parse( $post->post_content, false )['tree'] );
			$node     = $document->node( $path );
			if ( null === $node ) {
				return new WP_Error( 'uncode_builder_path_not_found', sprintf( 'There is no element at path "%s".', $path ), array( 'status' => 404 ) );
			}
			$source = $document->source_of( $node );
		}

		$context = Draft_Copies::is_autosave( $post ) ? get_post( $post->post_parent ) : $post;
		$html    = $this->preview->render( $context, $source );

		return array_merge(
			array_filter(
				array(
					'post_id' => $post->ID,
					'path'    => Layout_Document::ROOT_PATH === $path ? null : $path,
				),
				static fn( $value ) => null !== $value
			),
			self::how_to_view( $post, $context ),
			$this->preview->analyze( $html )
		);
	}

	private static function how_to_view( WP_Post $post, WP_Post $context ): array {
		if ( Draft_Copies::is_autosave( $post ) ) {
			return array( 'how_to_view' => sprintf( 'Open %s and click Preview: WordPress shows the autosave.', get_edit_post_link( $context, 'raw' ) ) );
		}

		if ( Write_Policy::is_draft( $post ) || 'private' === $post->post_status ) {
			return array(
				'preview_url' => (string) get_preview_post_link( $post ),
				'how_to_view' => 'A person logged in to WordPress can open `preview_url`.',
			);
		}

		return array( 'preview_url' => (string) get_permalink( $post ) );
	}

	protected function label(): string {
		return __( 'Render a page preview (WPBakery / Uncode page builder)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'You cannot see pages, so use this to check what a layout actually renders after changing it. Renders the item, or only the element at `path` with everything inside it, the way the site does, and returns a readable outline (headings with their level, text excerpts, links with their destination, buttons, images with their alt text), `issues` found (heading levels that jump, empty headings, links without a real destination or text, images without alt), `unrendered_shortcodes` (builder code that did not turn into HTML, usually a wrong tag or nesting) and the `preview_url` a person can open. It does not change anything.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'path'    => array(
					'type'        => 'string',
					'description' => 'Only render the element at this path, such as "3", with everything inside it.',
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}
}
