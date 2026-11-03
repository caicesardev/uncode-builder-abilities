<?php

namespace Uncode_Builder_Abilities;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Embedded_Blocks {

	const MAX_NESTING = 3;

	private Shortcode_Parser $parser;

	public function __construct( Shortcode_Parser $parser ) {
		$this->parser = $parser;
	}

	public function expand( array $nodes, bool $decode, int $host_id ): array {
		$sources = (array) apply_filters( 'uncode_builder_abilities_embedded_blocks', array() );

		return array() === $sources ? $nodes : $this->expand_nodes( $nodes, $decode, $sources, array( $host_id ) );
	}

	private function expand_nodes( array $nodes, bool $decode, array $sources, array $seen ): array {
		return array_map(
			function ( array $node ) use ( $decode, $sources, $seen ): array {
				if ( isset( $node['children'] ) ) {
					$node['children'] = $this->expand_nodes( $node['children'], $decode, $sources, $seen );
				}

				$attribute = $sources[ $node['tag'] ?? '' ] ?? null;
				if ( null !== $attribute ) {
					$id            = (int) ( ( (array) $node['attributes'] )[ $attribute ] ?? 0 );
					$node['block'] = $this->block( $id, $node['visible_on'] ?? Device_Visibility::DEVICES, $decode, $sources, $seen );
				}

				return $node;
			},
			$nodes
		);
	}

	private function block( int $id, array $visible, bool $decode, array $sources, array $seen ): array {
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post instanceof WP_Post ) {
			return array(
				'post_id' => $id,
				'missing' => true,
			);
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return array(
				'post_id'  => $id,
				'readable' => false,
			);
		}

		if ( in_array( $id, $seen, true ) || count( $seen ) > self::MAX_NESTING ) {
			return array(
				'post_id'  => $id,
				'expanded' => false,
				'reason'   => 'It is already expanded above it (it includes itself) or nested too deeply.',
			);
		}

		$tree = Device_Visibility::annotate( $this->parser->parse( $post->post_content, $decode )['tree'], $visible );

		return array(
			'post_id'   => $id,
			'title'     => $post->post_title,
			'version'   => Layout_Document::version_of( $post->post_content ),
			'read_only' => true,
			'tree'      => $this->expand_nodes( Shortcode_Parser::without_source( $tree ), $decode, $sources, array_merge( $seen, array( $id ) ) ),
		);
	}
}
