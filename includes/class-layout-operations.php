<?php

namespace Uncode_Builder_Abilities;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Layout_Operations {

	private Element_Catalog $catalog;

	private Nesting_Rules $nesting;

	private Element_Composer $composer;

	private Shortcode_Parser $parser;

	public function __construct( Element_Catalog $catalog, Nesting_Rules $nesting, Element_Composer $composer, Shortcode_Parser $parser ) {
		$this->catalog  = $catalog;
		$this->nesting  = $nesting;
		$this->composer = $composer;
		$this->parser   = $parser;
	}

	public function insert_source( Layout_Document $document, string $parent_path, ?int $index, string $source ) {
		$parent_tag = $this->container_tag( $document, $parent_path );
		if ( is_wp_error( $parent_tag ) ) {
			return $parent_tag;
		}

		$tree   = $this->parser->parse( $source, false )['tree'];
		$errors = array();
		foreach ( $tree as $node ) {
			$violation = isset( $node['tag'] ) ? $this->nesting->violation( $parent_tag, $node['tag'] ) : 'The template has loose text outside its elements.';
			if ( null !== $violation ) {
				$errors[] = array(
					'at'      => 'template.' . $node['path'],
					'message' => $violation,
				);
			}
		}
		if ( array() !== $errors ) {
			return self::invalid( $errors );
		}

		$prepared = $this->composer->assign_ids( $source, $tree, $document->used_ids() );
		$offset   = $document->insertion_offset( $parent_path, $index );
		$focus    = array();
		foreach ( $this->parser->parse( $prepared['content'], false )['tree'] as $node ) {
			$focus[] = $offset + $node[ Shortcode_Parser::SOURCE_KEY ]['start'];
		}

		return array(
			'content'       => $document->splice( $offset, 0, $prepared['content'] ),
			'focus'         => $focus,
			'generated_ids' => $prepared['generated_ids'],
		);
	}

	public function insert( Layout_Document $document, string $parent_path, ?int $index, array $specs ) {
		$parent_tag = $this->container_tag( $document, $parent_path );
		if ( is_wp_error( $parent_tag ) ) {
			return $parent_tag;
		}

		$composed = $this->composer->compose( $specs, $parent_tag, $document->used_ids() );
		if ( array() !== $composed['errors'] ) {
			return self::invalid( $composed['errors'] );
		}

		$offset  = $document->insertion_offset( $parent_path, $index );
		$focus   = array();
		$cursor  = $offset;
		foreach ( $composed['shortcodes'] as $shortcode ) {
			$focus[] = $cursor;
			$cursor += strlen( $shortcode );
		}
		$snippet = implode( '', $composed['shortcodes'] );

		return array(
			'content'       => $document->splice( $offset, 0, $snippet ),
			'focus'         => $focus,
			'snippet'       => $snippet,
			'generated_ids' => $composed['generated_ids'],
		);
	}

	public function update( Layout_Document $document, array $node, array $attribute_changes, $content ) {
		if ( ! isset( $node['tag'] ) ) {
			return new WP_Error( 'uncode_builder_not_an_element', 'Text between elements cannot be updated; remove it instead.', array( 'status' => 400 ) );
		}

		$tag     = $node['tag'];
		$source  = $node[ Shortcode_Parser::SOURCE_KEY ];
		$current = Shortcode_Parser::raw_attributes( $source['attributes_text'] );
		$own_id  = $node['id'] ?? null;
		$others  = array_values( array_filter( $document->used_ids(), static fn( $id ) => $id !== $own_id ) );

		$changed = $this->composer->change_attributes( $tag, $current, $attribute_changes, $others );
		$errors  = $changed['errors'];

		$inner = $document->inner_source_of( $node );
		if ( null !== $content ) {
			if ( isset( $node['children'] ) ) {
				$errors[] = array(
					'at'      => 'content',
					'message' => sprintf( '"%s" is a container; change its children with insert-elements, move-element and remove-element.', $tag ),
				);
			} else {
				$encoded = $this->composer->content_for( $tag, $content );
				$errors  = array_merge( $errors, $encoded['errors'] );
				$inner   = $encoded['content'];
			}
		}

		if ( array() !== $errors ) {
			return self::invalid( $errors );
		}

		$element = Shortcode_Serializer::element( $tag, $changed['attributes'], $inner );

		return array(
			'content' => $document->splice( $source['start'], $source['end'] - $source['start'], $element ),
			'focus'   => array( $source['start'] ),
			'snippet' => Shortcode_Serializer::open_tag( $tag, $changed['attributes'] ),
		);
	}

	public function move( Layout_Document $document, array $node, string $to_parent_path, ?int $index ) {
		if ( ! isset( $node['tag'] ) ) {
			return new WP_Error( 'uncode_builder_not_an_element', 'Only elements can be moved.', array( 'status' => 400 ) );
		}

		if ( $to_parent_path === $node['path'] || 0 === strpos( $to_parent_path, $node['path'] . '.' ) ) {
			return new WP_Error( 'uncode_builder_move_into_itself', 'An element cannot be moved inside itself.', array( 'status' => 400 ) );
		}

		$parent_tag = $this->container_tag( $document, $to_parent_path );
		if ( is_wp_error( $parent_tag ) ) {
			return $parent_tag;
		}

		$violation = $this->nesting->violation( $parent_tag, $node['tag'] );
		if ( null !== $violation ) {
			return self::invalid( array( array( 'at' => 'to_parent_path', 'message' => $violation ) ) );
		}

		$source  = $node[ Shortcode_Parser::SOURCE_KEY ];
		$length  = $source['end'] - $source['start'];
		$target  = $document->insertion_offset( $to_parent_path, $index );
		$target  = $target > $source['start'] ? $target - $length : $target;
		$without = $document->splice( $source['start'], $length, '' );

		return array(
			'content'  => substr_replace( $without, $document->source_of( $node ), $target, 0 ),
			'focus'    => array( $target ),
			'warnings' => $this->emptied_parent_warnings( $document, $node ),
		);
	}

	public function remove( Layout_Document $document, array $node ) {
		$source = $node[ Shortcode_Parser::SOURCE_KEY ];

		return array(
			'content'  => $document->splice( $source['start'], $source['end'] - $source['start'], '' ),
			'focus'    => array(),
			'warnings' => $this->emptied_parent_warnings( $document, $node ),
		);
	}

	private function container_tag( Layout_Document $document, string $parent_path ) {
		if ( Layout_Document::ROOT_PATH === $parent_path ) {
			return null;
		}

		$parent = $document->node( $parent_path );
		if ( null === $parent ) {
			return new WP_Error( 'uncode_builder_path_not_found', sprintf( 'There is no element at path "%s".', $parent_path ), array( 'status' => 404 ) );
		}

		if ( ! isset( $parent['tag'] ) || ! isset( $parent['children'] ) || ! $this->catalog->is_container( $parent['tag'] ) ) {
			return new WP_Error( 'uncode_builder_not_a_container', sprintf( 'The element at path "%s" cannot hold other elements.', $parent_path ), array( 'status' => 400 ) );
		}

		return $parent['tag'];
	}

	private function emptied_parent_warnings( Layout_Document $document, array $node ): array {
		$parent_path = Layout_Document::parent_path_of( $node['path'] );
		if ( Layout_Document::ROOT_PATH === $parent_path || ! isset( $node['tag'] ) || 1 !== $document->element_children_count( $parent_path ) ) {
			return array();
		}

		return array( sprintf( 'The element at path "%s" is now empty.', $parent_path ) );
	}

	private static function invalid( array $errors ): WP_Error {
		$first = $errors[0];

		return new WP_Error(
			'uncode_builder_invalid_layout',
			sprintf( '%d problem(s) found; nothing was changed. First: %s: %s', count( $errors ), $first['at'], $first['message'] ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}
}
