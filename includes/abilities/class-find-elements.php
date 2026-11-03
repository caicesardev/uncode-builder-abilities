<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Device_Visibility;
use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Shortcode_Parser;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Find_Elements extends Ability {

	const DEFAULT_LIMIT = 50;

	const MAX_LIMIT = 200;

	const VALUE_EXCERPT = 80;

	const TEXT_EXCERPT = 120;

	private Shortcode_Parser $parser;

	public function __construct( Shortcode_Parser $parser ) {
		$this->parser = $parser;
	}

	public function name(): string {
		return 'find-elements';
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

		$tags       = array_filter( (array) ( $input['tag'] ?? array() ) );
		$attributes = (array) ( $input['attributes'] ?? array() );
		$text       = (string) ( $input['text'] ?? '' );
		$limit      = min( self::MAX_LIMIT, max( 1, (int) ( $input['limit'] ?? self::DEFAULT_LIMIT ) ) );

		$tree    = Device_Visibility::annotate( $this->parser->parse( $post->post_content, false )['tree'] );
		$matches = array();
		self::collect( Shortcode_Parser::without_source( $tree ), $tags, $attributes, $text, $matches );

		return array(
			'post_id'   => $post->ID,
			'version'   => Layout_Document::version_of( $post->post_content ),
			'count'     => count( $matches ),
			'matches'   => array_slice( $matches, 0, $limit ),
			'truncated' => count( $matches ) > $limit,
		);
	}

	private static function collect( array $nodes, array $tags, array $attributes, string $text, array &$matches ): void {
		foreach ( $nodes as $node ) {
			if ( isset( $node['tag'] ) && self::matches( $node, $tags, $attributes, $text ) ) {
				$matches[] = self::summary( $node );
			}
			self::collect( $node['children'] ?? array(), $tags, $attributes, $text, $matches );
		}
	}

	private static function matches( array $node, array $tags, array $attributes, string $text ): bool {
		if ( array() !== $tags && ! in_array( $node['tag'], $tags, true ) ) {
			return false;
		}

		$stored = (array) $node['attributes'];
		foreach ( $attributes as $name => $expected ) {
			if ( ! self::attribute_matches( $stored[ $name ] ?? null, $expected ) ) {
				return false;
			}
		}

		return '' === $text || false !== mb_stripos( self::plain( $node['content'] ?? '' ), $text );
	}

	private static function attribute_matches( ?string $value, $expected ): bool {
		if ( is_array( $expected ) && isset( $expected['contains'] ) ) {
			return null !== $value && false !== mb_stripos( $value, (string) $expected['contains'] );
		}

		return null !== $value && (string) $expected === $value;
	}

	private static function summary( array $node ): array {
		$summary = array(
			'path'       => $node['path'],
			'tag'        => $node['tag'],
			'id'         => $node['id'] ?? null,
			'attributes' => (object) array_map( array( __CLASS__, 'excerpt_value' ), (array) $node['attributes'] ),
			'text'       => self::excerpt( self::plain( $node['content'] ?? '' ), self::TEXT_EXCERPT ),
			'visible_on' => $node['visible_on'] ?? null,
		);

		return array_filter( $summary, static fn( $value ) => null !== $value && '' !== $value );
	}

	private static function excerpt_value( string $value ): string {
		return self::excerpt( $value, self::VALUE_EXCERPT );
	}

	private static function plain( string $html ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $html ) ) );
	}

	private static function excerpt( string $text, int $length ): string {
		return mb_strlen( $text ) > $length ? rtrim( mb_substr( $text, 0, $length - 1 ) ) . '…' : $text;
	}

	protected function label(): string {
		return __( 'Find elements in a page layout (WPBakery / Uncode page builder)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Searches a page, post or content block for elements without reading the whole tree, which is large on real pages. Filter by `tag` (one or several), `attributes` (each value must be equal, or {"contains": "..."} to match part of it) and `text` (case-insensitive, in the element\'s content); filters combine. Each match has its `path`, `tag`, stable `id`, raw `attributes` (long values shortened), a `text` excerpt and, when it is hidden on some devices, `visible_on`. Matches inside rows, inner rows, columns and other containers are all found. Also returns the `version` the edit abilities need, so you can go straight to update-element. For example, `tag: "vc_custom_heading"` lists every heading with its `heading_semantic` and `text_size`. Requires permission to edit that item.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id'    => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'tag'        => array(
					'description' => 'Element tag, or several tags, to find.',
					'oneOf'       => array(
						array( 'type' => 'string' ),
						array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'attributes' => array(
					'type'                 => 'object',
					'description'          => 'Attribute values the element must have: "name": "value" for an exact match, "name": {"contains": "part"} for a partial one.',
					'additionalProperties' => array(
						'oneOf' => array(
							array( 'type' => 'string' ),
							array(
								'type'                 => 'object',
								'properties'           => array( 'contains' => array( 'type' => 'string' ) ),
								'required'             => array( 'contains' ),
								'additionalProperties' => false,
							),
						),
					),
				),
				'text'       => array(
					'type'        => 'string',
					'description' => 'Text the element\'s content must include (case-insensitive).',
				),
				'limit'      => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_LIMIT,
					'default' => self::DEFAULT_LIMIT,
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}
}
