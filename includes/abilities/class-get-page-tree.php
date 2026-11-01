<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Snapshot_Store;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Get_Page_Tree extends Ability {

	const BUILDER_STATUS_META = '_wpb_vc_js_status';

	private Shortcode_Parser $parser;

	private Snapshot_Store $snapshots;

	public function __construct( Shortcode_Parser $parser, Snapshot_Store $snapshots ) {
		$this->parser    = $parser;
		$this->snapshots = $snapshots;
	}

	public function name(): string {
		return 'get-page-tree';
	}

	public function has_permission( $input = null ): bool {
		$post_id = (int) ( self::input_array( $input )['post_id'] ?? 0 );

		return $post_id > 0 && current_user_can( 'edit_post', $post_id );
	}

	public function execute( $input = null ) {
		$input = self::input_array( $input );
		$post  = get_post( (int) ( $input['post_id'] ?? 0 ) );

		if ( ! $post instanceof WP_Post || 'revision' === $post->post_type ) {
			return new WP_Error( 'uncode_builder_post_not_found', 'The post does not exist.', array( 'status' => 404 ) );
		}

		$parsed = $this->parser->parse( $post->post_content, (bool) ( $input['decode'] ?? true ) );
		$path   = (string) ( $input['path'] ?? Layout_Document::ROOT_PATH );

		if ( Layout_Document::ROOT_PATH !== $path ) {
			$node = ( new Layout_Document( $post->post_content, $parsed['tree'] ) )->node( $path );
			if ( null === $node ) {
				return new WP_Error( 'uncode_builder_path_not_found', sprintf( 'There is no element at path "%s".', $path ), array( 'status' => 404 ) );
			}
			$parsed['tree'] = array( $node );
		}

		$parsed['tree'] = Shortcode_Parser::without_source( $parsed['tree'] );
		if ( isset( $input['depth'] ) ) {
			$parsed['tree'] = self::limited_to_depth( $parsed['tree'], max( 1, (int) $input['depth'] ) );
		}

		return array_merge(
			array(
				'post'      => array(
					'id'            => $post->ID,
					'type'          => $post->post_type,
					'status'        => $post->post_status,
					'title'         => $post->post_title,
					'modified'      => $post->post_modified,
					'builder'       => self::uses_builder( $post ) ? 'wpbakery' : 'none',
					'content_bytes' => strlen( $post->post_content ),
					'draft_copy_id' => Draft_Copies::rewrite_copy_id( $post ) ?: null,
				),
				'version'   => Layout_Document::version_of( $post->post_content ),
				'snapshots' => $this->snapshots->summaries( $post->ID ),
			),
			$parsed
		);
	}

	protected function label(): string {
		return __( 'Get a page layout tree', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Reads a post, page or any other item built with WPBakery / Uncode and returns its layout as a tree, plus the `version` that every change must send back. Every node has a `path` ("0.1.2": row 0, column 1, element 2), its `tag`, its stable `id` when it has one (uncode_shortcode_id, el_id or tab_id), its raw `attributes` exactly as stored, and, unless `decode` is false, `decoded` values for encoded attributes (Uncode gradients, links, design CSS, parameter groups, raw HTML, escaped text). Containers have `children`; other elements keep their inner HTML in `content`. `snapshots` lists the copies restore-snapshot can bring back; `post.draft_copy_id` is set when a published item already has a Rewrite & Republish copy (edit that one). Long pages produce large trees: `depth: 1` returns only the top-level rows (with `children_count`) to find your way, and `path` returns just one element and everything inside it. Requires permission to edit that item.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'decode'  => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Add decoded copies of encoded attributes.',
				),
				'path'    => array(
					'type'        => 'string',
					'description' => 'Only return the element at this path, such as "3" or "3.0.1", with everything inside it.',
				),
				'depth'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Levels of children to include; deeper containers only report `children_count`.',
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}

	private static function limited_to_depth( array $nodes, int $depth ): array {
		return array_map(
			static function ( array $node ) use ( $depth ): array {
				if ( ! isset( $node['children'] ) ) {
					return $node;
				}
				if ( $depth > 1 ) {
					$node['children'] = self::limited_to_depth( $node['children'], $depth - 1 );
					return $node;
				}
				$node['children_count'] = count( $node['children'] );
				unset( $node['children'] );
				return $node;
			},
			$nodes
		);
	}

	private static function uses_builder( WP_Post $post ): bool {
		return 'true' === get_post_meta( $post->ID, self::BUILDER_STATUS_META, true ) || false !== strpos( $post->post_content, '[vc_row' );
	}
}
