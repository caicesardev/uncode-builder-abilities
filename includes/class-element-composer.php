<?php

namespace Uncode_Builder_Abilities;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class Element_Composer {

	const AUTO_ID_KIND = 'auto_id';

	private Element_Catalog $catalog;

	private Nesting_Rules $nesting;

	private Attribute_Encoder $encoder;

	private array $errors = array();

	private array $used_ids = array();

	private array $generated_ids = array();

	public function __construct( Element_Catalog $catalog, Nesting_Rules $nesting, Attribute_Encoder $encoder ) {
		$this->catalog = $catalog;
		$this->nesting = $nesting;
		$this->encoder = $encoder;
	}

	public function compose( array $specs, ?string $parent_tag, array $used_ids ): array {
		$this->reset( $used_ids );

		$shortcodes = array();
		foreach ( array_values( $specs ) as $index => $spec ) {
			$shortcodes[] = $this->element( $spec, $parent_tag, sprintf( 'elements[%d]', $index ) );
		}

		return array(
			'shortcodes'    => $shortcodes,
			'errors'        => $this->errors,
			'generated_ids' => $this->generated_ids,
		);
	}

	public function change_attributes( string $tag, array $current, array $changes, array $used_ids ): array {
		$this->reset( $used_ids );
		$params = $this->catalog->params_by_name( $tag );

		foreach ( $changes as $name => $value ) {
			$name = (string) $name;
			if ( null === $value ) {
				unset( $current[ $name ] );
				continue;
			}
			$encoded = $this->attribute( $tag, $params, $name, $value, 'attributes.' . $name );
			if ( null !== $encoded ) {
				$current[ $name ] = $encoded;
			}
		}

		return array(
			'attributes' => $current,
			'errors'     => $this->errors,
		);
	}

	public function content_for( string $tag, $content ): array {
		$this->reset( array() );
		$encoded = $this->content( $tag, $content, 'content' );

		return array(
			'content' => $encoded,
			'errors'  => $this->errors,
		);
	}

	private function reset( array $used_ids ): void {
		$this->errors        = array();
		$this->generated_ids = array();
		$this->used_ids      = array_fill_keys( array_map( 'strval', $used_ids ), true );
	}

	private function element( $spec, ?string $parent_tag, string $location ): string {
		if ( ! is_array( $spec ) || empty( $spec['tag'] ) || ! is_string( $spec['tag'] ) ) {
			$this->fail( $location, 'Each element needs a `tag`.' );
			return '';
		}

		$tag = $spec['tag'];
		if ( ! $this->catalog->has( $tag ) ) {
			$this->fail( $location, sprintf( 'There is no element "%s". Use list-elements to see the available tags.', $tag ) );
			return '';
		}

		$violation = $this->nesting->violation( $parent_tag, $tag );
		if ( null !== $violation ) {
			$this->fail( $location, $violation );
		}

		$attributes = $this->attributes( $tag, $spec['attributes'] ?? array(), $location );
		$attributes = $this->with_generated_ids( $tag, $attributes, $location );
		$inner      = $this->inner( $tag, $spec, $location );

		return Shortcode_Serializer::element( $tag, $attributes, $inner );
	}

	private function attributes( string $tag, $values, string $location ): array {
		if ( ! is_array( $values ) || ( array() !== $values && array_is_list( $values ) ) ) {
			$this->fail( $location . '.attributes', '`attributes` must be an object of parameter names and values.' );
			return array();
		}

		$params  = $this->catalog->params_by_name( $tag );
		$encoded = array();
		foreach ( $values as $name => $value ) {
			if ( null === $value ) {
				continue;
			}
			$result = $this->attribute( $tag, $params, (string) $name, $value, $location . '.attributes.' . $name );
			if ( null !== $result ) {
				$encoded[ (string) $name ] = $result;
			}
		}

		return $encoded;
	}

	private function attribute( string $tag, array $params, string $name, $value, string $location ): ?string {
		if ( 'content' === $name && isset( $params['content'] ) ) {
			$this->fail( $location, 'Send the element text in `content`, not as an attribute.' );
			return null;
		}

		if ( ! isset( $params[ $name ] ) ) {
			$this->fail( $location, sprintf( '"%s" has no parameter "%s". Use get-element-schema to see its parameters.', $tag, $name ) );
			return null;
		}

		try {
			$encoded = $this->encoder->encode( $params[ $name ], $value );
		} catch ( InvalidArgumentException $exception ) {
			$this->fail( $location, $exception->getMessage() );
			return null;
		}

		if ( self::AUTO_ID_KIND === $params[ $name ]['kind'] && '' !== $encoded ) {
			if ( isset( $this->used_ids[ $encoded ] ) ) {
				$this->fail( $location, sprintf( 'The ID "%s" is already used on this page; leave it out and one will be generated.', $encoded ) );
				return null;
			}
			$this->used_ids[ $encoded ] = true;
		}

		return $encoded;
	}

	private function with_generated_ids( string $tag, array $attributes, string $location ): array {
		foreach ( $this->catalog->params_by_name( $tag ) as $name => $param ) {
			if ( self::AUTO_ID_KIND !== $param['kind'] || isset( $attributes[ $name ] ) ) {
				continue;
			}

			$id = (string) apply_filters( 'uncode_builder_abilities_generate_id', self::default_id( $param['type'] ), $param['type'], array_keys( $this->used_ids ) );
			if ( '' === $id ) {
				continue;
			}

			$attributes[ $name ]   = $id;
			$this->used_ids[ $id ] = true;
			$this->generated_ids[] = array(
				'element'   => $location,
				'tag'       => $tag,
				'parameter' => $name,
				'id'        => $id,
			);
		}

		return $attributes;
	}

	private function inner( string $tag, array $spec, string $location ): ?string {
		$is_container    = $this->catalog->is_container( $tag );
		$accepts_content = $this->catalog->accepts_content( $tag );
		$children        = $spec['children'] ?? array();
		$has_content     = array_key_exists( 'content', $spec ) && null !== $spec['content'];

		if ( ! $is_container && ! empty( $children ) ) {
			$this->fail( $location . '.children', sprintf( '"%s" is not a container; put its text in `content`.', $tag ) );
		}

		$inner = '';
		if ( $has_content ) {
			if ( $is_container ) {
				$this->fail( $location . '.content', sprintf( '"%s" is a container; give it `children` instead of `content`.', $tag ) );
			} else {
				$inner = (string) $this->content( $tag, $spec['content'], $location . '.content' );
			}
		}

		if ( $is_container && is_array( $children ) ) {
			foreach ( array_values( $children ) as $index => $child ) {
				$inner .= $this->element( $child, $tag, sprintf( '%s.children[%d]', $location, $index ) );
			}
		}

		return $is_container || $accepts_content || $has_content ? $inner : null;
	}

	private function content( string $tag, $content, string $location ): ?string {
		if ( ! $this->catalog->accepts_content( $tag ) ) {
			$this->fail( $location, sprintf( '"%s" has no text content; set its parameters in `attributes`.', $tag ) );
			return null;
		}

		try {
			$encoded = $this->encoder->encode_content( $this->catalog->param_type( $tag, 'content' ), $content );
		} catch ( InvalidArgumentException $exception ) {
			$this->fail( $location, $exception->getMessage() );
			return null;
		}

		if ( false !== strpos( $encoded, '[/' . $tag . ']' ) ) {
			$this->fail( $location, sprintf( 'The content cannot contain the closing tag [/%s].', $tag ) );
			return null;
		}

		return $encoded;
	}

	private static function default_id( string $type ): string {
		return 'tab_id' === $type ? sprintf( '%d-%s', (int) ( microtime( true ) * 1000 ), strtolower( wp_generate_password( 6, false ) ) ) : '';
	}

	private function fail( string $location, string $message ): void {
		$this->errors[] = array(
			'at'      => $location,
			'message' => $message,
		);
	}
}
