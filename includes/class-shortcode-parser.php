<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Shortcode_Parser {

	const ID_ATTRIBUTES = array( 'uncode_shortcode_id', 'el_id', 'tab_id' );

	const SOURCE_KEY = '_source';

	const RAW_ATTRIBUTE_PATTERN = '/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'\]]+))/';

	private Element_Catalog $catalog;

	private Attribute_Decoder $decoder;

	private array $unknown_tags = array();

	private int $element_count = 0;

	public function __construct( Element_Catalog $catalog, Attribute_Decoder $decoder ) {
		$this->catalog = $catalog;
		$this->decoder = $decoder;
	}

	public function parse( string $content, bool $decode ): array {
		$this->unknown_tags  = array();
		$this->element_count = 0;

		$tags = self::tags_in( $content );
		$tree = array() === $tags ? self::text_nodes( $content, '0', 0 ) : $this->parse_segment( $content, 0, $tags, '', $decode );

		return array(
			'element_count' => $this->element_count,
			'unknown_tags'  => array_keys( $this->unknown_tags ),
			'tree'          => $tree,
		);
	}

	public static function without_source( array $nodes ): array {
		return array_map(
			static function ( array $node ): array {
				unset( $node[ self::SOURCE_KEY ] );
				if ( isset( $node['children'] ) ) {
					$node['children'] = self::without_source( $node['children'] );
				}
				return $node;
			},
			$nodes
		);
	}

	public static function raw_attributes( string $attributes_text ): array {
		preg_match_all( self::RAW_ATTRIBUTE_PATTERN, $attributes_text, $matches, PREG_SET_ORDER );

		$attributes = array();
		foreach ( $matches as $match ) {
			$attributes[ strtolower( $match[1] ) ] = self::first_filled_group( $match, array( 4, 3, 2 ) );
		}

		return $attributes;
	}

	private function parse_segment( string $content, int $base, array $tags, string $parent_path, bool $decode ): array {
		preg_match_all( '/' . get_shortcode_regex( $tags ) . '/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

		$nodes  = array();
		$cursor = 0;

		foreach ( $matches as $match ) {
			$offset = $match[0][1];
			$nodes  = array_merge( $nodes, self::text_nodes( substr( $content, $cursor, $offset - $cursor ), self::child_path( $parent_path, count( $nodes ) ), $base + $cursor ) );
			$cursor = $offset + strlen( $match[0][0] );

			$path = self::child_path( $parent_path, count( $nodes ) );
			if ( '[' === $match[1][0] && ']' === $match[6][0] ) {
				$nodes[] = self::text_node( $match[0][0], $path, $base + $offset );
				continue;
			}

			$nodes[] = $this->element_node( $match, $base, $tags, $path, $decode );
		}

		return array_merge( $nodes, self::text_nodes( substr( $content, $cursor ), self::child_path( $parent_path, count( $nodes ) ), $base + $cursor ) );
	}

	private function element_node( array $shortcode_match, int $base, array $tags, string $path, bool $decode ): array {
		++$this->element_count;

		$tag        = $shortcode_match[2][0];
		$start      = $base + $shortcode_match[0][1];
		$has_inner  = isset( $shortcode_match[5] ) && -1 !== $shortcode_match[5][1];
		$inner      = $has_inner ? $shortcode_match[5][0] : null;
		$attributes = shortcode_parse_atts( $shortcode_match[3][0] );
		$attributes = is_array( $attributes ) ? array_map( 'strval', $attributes ) : array();
		$known      = $this->catalog->has( $tag );

		if ( ! $known ) {
			$this->unknown_tags[ $tag ] = true;
		}

		$node = array(
			'path' => $path,
			'tag'  => $tag,
		);

		$id = self::id_of( $attributes );
		if ( null !== $id ) {
			$node['id'] = $id;
		}

		$node['attributes'] = (object) $attributes;

		if ( $decode ) {
			$decoded = $this->decoded_attributes( $tag, $attributes );
			if ( array() !== $decoded ) {
				$node['decoded'] = $decoded;
			}
		}

		$inner_start = $has_inner ? $base + $shortcode_match[5][1] : null;
		$end         = $start + strlen( $shortcode_match[0][0] );

		$node[ self::SOURCE_KEY ] = array(
			'start'           => $start,
			'end'             => $end,
			'inner_start'     => $inner_start,
			'inner_end'       => $has_inner ? $inner_start + strlen( $inner ) : null,
			'attributes_text' => $shortcode_match[3][0],
		);

		if ( null === $inner ) {
			return $node;
		}

		if ( $known && $this->catalog->is_container( $tag ) ) {
			$node['children'] = $this->parse_segment( $inner, $inner_start, $tags, $path, $decode );
			return $node;
		}

		$node['content'] = $inner;
		if ( $decode ) {
			$decoded_content = $this->decoder->decode( $this->catalog->param_type( $tag, 'content' ), $inner );
			if ( null !== $decoded_content ) {
				$node['decoded_content'] = $decoded_content;
			}
		}

		return $node;
	}

	private function decoded_attributes( string $tag, array $attributes ): array {
		$decoded = array();

		foreach ( $attributes as $name => $value ) {
			$result = $this->decoder->decode( $this->catalog->param_type( $tag, (string) $name ), $value );
			if ( null !== $result ) {
				$decoded[ $name ] = $result;
			}
		}

		return $decoded;
	}

	private static function tags_in( string $content ): array {
		preg_match_all( '/\[\/?([A-Za-z0-9_-]+)/', $content, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	private static function text_nodes( string $text, string $path, int $start ): array {
		return '' === trim( $text ) ? array() : array( self::text_node( $text, $path, $start ) );
	}

	private static function text_node( string $text, string $path, int $start ): array {
		return array(
			'path'           => $path,
			'text'           => $text,
			self::SOURCE_KEY => array(
				'start' => $start,
				'end'   => $start + strlen( $text ),
			),
		);
	}

	private static function child_path( string $parent_path, int $index ): string {
		return '' === $parent_path ? (string) $index : $parent_path . '.' . $index;
	}

	private static function id_of( array $attributes ): ?string {
		foreach ( self::ID_ATTRIBUTES as $attribute ) {
			if ( isset( $attributes[ $attribute ] ) && '' !== $attributes[ $attribute ] ) {
				return $attributes[ $attribute ];
			}
		}

		return null;
	}

	private static function first_filled_group( array $captures, array $groups ): string {
		foreach ( $groups as $group ) {
			if ( isset( $captures[ $group ] ) && '' !== $captures[ $group ] ) {
				return $captures[ $group ];
			}
		}

		return '';
	}
}
