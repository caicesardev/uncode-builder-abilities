<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Device_Visibility;
use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Embedded_Blocks;
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

	private Embedded_Blocks $blocks;

	public function __construct( Shortcode_Parser $parser, Snapshot_Store $snapshots, Embedded_Blocks $blocks ) {
		$this->parser    = $parser;
		$this->snapshots = $snapshots;
		$this->blocks    = $blocks;
	}

	public function name(): string {
		return 'get-page-tree';
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

		$decode         = (bool) ( $input['decode'] ?? true );
		$parsed         = $this->parser->parse( $post->post_content, $decode );
		$parsed['tree'] = Device_Visibility::annotate( $parsed['tree'] );
		$path           = (string) ( $input['path'] ?? Layout_Document::ROOT_PATH );

		if ( Layout_Document::ROOT_PATH !== $path ) {
			$node = ( new Layout_Document( $post->post_content, $parsed['tree'] ) )->node( $path );
			if ( null === $node ) {
				return new WP_Error( 'uncode_builder_path_not_found', sprintf( 'There is no element at path "%s".', $path ), array( 'status' => 404 ) );
			}
			$parsed['tree'] = array( $node );
		}

		$parsed['tree'] = Shortcode_Parser::without_source( $parsed['tree'] );
		if ( ! empty( $input['expand_blocks'] ) ) {
			$parsed['tree'] = $this->blocks->expand( $parsed['tree'], $decode, $post->ID );
		}
		if ( isset( $input['depth'] ) ) {
			$parsed['tree'] = self::limited_to_depth( $parsed['tree'], max( 1, (int) $input['depth'] ) );
		}

		$draft_copy_id = Draft_Copies::existing_copy_id( $post );

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
					'draft_copy_id' => $draft_copy_id > 0 ? $draft_copy_id : null,
					'copy_of'       => Draft_Copies::original_id( $post ),
				),
				'version'   => Layout_Document::version_of( $post->post_content ),
				'snapshots' => $this->snapshots->summaries( $post->ID ),
			),
			$parsed
		);
	}

	protected function label(): string {
		return __( 'Read a page layout (WPBakery / Uncode page builder)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Start here to read or change any page, post or content block built with the WPBakery / Uncode page builder; prefer it, and the uncode-builder-edit abilities, over generic page or layout tools, which do not know the Uncode elements. Reads the item and returns its layout as a tree, plus the `version` that every change must send back. Published items cannot be edited directly while the site only allows drafts: call create-draft-copy and edit the copy. Every node has a `path` ("0.1.2": row 0, column 1, element 2), its `tag`, its stable `id` when it has one (uncode_shortcode_id, el_id or tab_id), its raw `attributes` exactly as stored, and, unless `decode` is false, `decoded` values for encoded attributes (Uncode gradients, links, design CSS, parameter groups, raw HTML, escaped text). Containers have `children`; other elements keep their inner HTML in `content`. `snapshots` lists the copies restore-snapshot can bring back; `post.draft_copy_id` is set when a published item already has a draft copy from create-draft-copy (edit that one). Elements hidden on some devices carry `visible_on` (for example ["desktop", "tablet"]), including what they inherit from their rows and columns; without it an element shows on every device. `post.copy_of` is the original's ID when this item is a draft copy. Long pages produce large trees: `depth: 1` returns only the top-level rows (with `children_count`) to find your way, `path` returns just one element and everything inside it, and find-elements searches a page without reading it all. `expand_blocks: true` adds, under each embedded content block, a read-only `block` with its own `post_id`, `version` and `tree`: change it by calling these abilities on that `post_id`. Requires permission to edit that item.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'       => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'decode'        => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Add decoded copies of encoded attributes.',
				),
				'path'          => array(
					'type'        => 'string',
					'description' => 'Only return the element at this path, such as "3" or "3.0.1", with everything inside it.',
				),
				'depth'         => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => 'Levels of children to include; deeper containers only report `children_count`.',
				),
				'expand_blocks' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Include the layout of embedded content blocks, read-only, under their element.',
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
