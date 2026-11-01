<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Layout_Document {

	const ROOT_PATH = '';

	private string $content;

	private array $tree;

	public function __construct( string $content, array $tree ) {
		$this->content = $content;
		$this->tree    = $tree;
	}

	public static function version_of( string $content ): string {
		return md5( $content );
	}

	public static function parent_path_of( string $path ): string {
		$separator = strrpos( $path, '.' );

		return false === $separator ? self::ROOT_PATH : substr( $path, 0, $separator );
	}

	public function content(): string {
		return $this->content;
	}

	public function version(): string {
		return self::version_of( $this->content );
	}

	public function node( string $path ): ?array {
		if ( self::ROOT_PATH === $path ) {
			return null;
		}

		$nodes = $this->tree;
		$node  = null;
		foreach ( explode( '.', $path ) as $index ) {
			if ( ! ctype_digit( $index ) || ! isset( $nodes[ (int) $index ] ) ) {
				return null;
			}
			$node  = $nodes[ (int) $index ];
			$nodes = $node['children'] ?? array();
		}

		return $node;
	}

	public function nodes_with_id( string $id ): array {
		return $this->collect( static fn( array $node ) => ( $node['id'] ?? null ) === $id );
	}

	public function element_starting_at( int $offset ): ?array {
		$found = $this->collect( static fn( array $node ) => isset( $node['tag'] ) && $node[ Shortcode_Parser::SOURCE_KEY ]['start'] === $offset );

		return $found[0] ?? null;
	}

	public function used_ids(): array {
		return array_values( array_filter( array_map( static fn( array $node ) => $node['id'] ?? null, $this->collect( static fn() => true ) ) ) );
	}

	public function children( string $parent_path ): ?array {
		if ( self::ROOT_PATH === $parent_path ) {
			return $this->tree;
		}

		$parent = $this->node( $parent_path );

		return null === $parent || ! isset( $parent['children'] ) ? null : $parent['children'];
	}

	public function element_children_count( string $parent_path ): int {
		return count( array_filter( (array) $this->children( $parent_path ), static fn( array $node ) => isset( $node['tag'] ) ) );
	}

	public function insertion_offset( string $parent_path, ?int $index ): ?int {
		$children = $this->children( $parent_path );
		if ( null === $children ) {
			return null;
		}

		if ( null !== $index && isset( $children[ $index ] ) ) {
			return $children[ $index ][ Shortcode_Parser::SOURCE_KEY ]['start'];
		}

		if ( self::ROOT_PATH === $parent_path ) {
			return strlen( $this->content );
		}

		return $this->node( $parent_path )[ Shortcode_Parser::SOURCE_KEY ]['inner_end'];
	}

	public function source_of( array $node ): string {
		$source = $node[ Shortcode_Parser::SOURCE_KEY ];

		return substr( $this->content, $source['start'], $source['end'] - $source['start'] );
	}

	public function inner_source_of( array $node ): ?string {
		$source = $node[ Shortcode_Parser::SOURCE_KEY ];
		if ( null === ( $source['inner_start'] ?? null ) ) {
			return null;
		}

		return substr( $this->content, $source['inner_start'], $source['inner_end'] - $source['inner_start'] );
	}

	public function splice( int $start, int $length, string $replacement ): string {
		return substr_replace( $this->content, $replacement, $start, $length );
	}

	private function collect( callable $matches ): array {
		$found = array();
		$stack = array_reverse( $this->tree );

		while ( array() !== $stack ) {
			$node = array_pop( $stack );
			if ( $matches( $node ) ) {
				$found[] = $node;
			}
			foreach ( array_reverse( $node['children'] ?? array() ) as $child ) {
				$stack[] = $child;
			}
		}

		return $found;
	}
}
