<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Element_Catalog {

	const SUMMARY_DESCRIPTION_LENGTH = 140;

	const COLUMN_IMPLICIT_PARAMS = array(
		array(
			'name'     => 'width',
			'type'     => 'column_width',
			'kind'     => 'fraction',
			'label'    => 'Width',
			'group'    => 'General',
			'default'  => '1/1',
			'implicit' => true,
		),
		array(
			'name'     => 'offset',
			'type'     => 'column_offset',
			'kind'     => 'text',
			'label'    => 'Responsive offset classes',
			'group'    => 'Responsive',
			'implicit' => true,
		),
	);

	private Param_Normalizer $normalizer;

	private ?array $map = null;

	private array $params_by_tag = array();

	private ?array $legacy = null;

	public function __construct( Param_Normalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	public static function is_builder_active(): bool {
		return class_exists( 'WPBMap' ) && method_exists( 'WPBMap', 'getAllShortCodes' );
	}

	public function has( string $tag ): bool {
		return isset( $this->map()[ $tag ] );
	}

	public function is_container( string $tag ): bool {
		$element = $this->map()[ $tag ] ?? array();

		return ! empty( $element['is_container'] ) || ! empty( $element['as_parent'] );
	}

	public function is_insertable( string $tag ): bool {
		return false !== ( $this->map()[ $tag ]['content_element'] ?? true );
	}

	public function accepts_content( string $tag ): bool {
		return null !== $this->param_type( $tag, 'content' );
	}

	public function is_legacy( string $tag ): bool {
		return array_key_exists( $tag, $this->legacy_elements() );
	}

	public function replacement_for( string $tag ): ?string {
		$replacement = $this->legacy_elements()[ $tag ] ?? null;

		return is_string( $replacement ) && $this->has( $replacement ) ? $replacement : null;
	}

	public function relations( string $tag ): array {
		$element = $this->map()[ $tag ] ?? array();

		return array(
			'children' => self::relation( $element['as_parent'] ?? null ),
			'parents'  => self::relation( $element['as_child'] ?? null ),
		);
	}

	public function params_by_name( string $tag ): array {
		if ( ! isset( $this->params_by_tag[ $tag ] ) ) {
			$this->params_by_tag[ $tag ] = array_column( $this->params( $tag, false ), null, 'name' );
		}

		return $this->params_by_tag[ $tag ];
	}

	public function param_type( string $tag, string $param_name ): ?string {
		foreach ( $this->map()[ $tag ]['params'] ?? array() as $param ) {
			if ( ( $param['param_name'] ?? null ) === $param_name ) {
				return (string) ( $param['type'] ?? '' );
			}
		}

		return null;
	}

	public function summaries( string $search, string $category, bool $include_deprecated ): array {
		$summaries = array();

		foreach ( $this->map() as $tag => $element ) {
			$summary = $this->summary( (string) $tag, $element );

			if ( $summary['deprecated'] && ! $include_deprecated ) {
				continue;
			}
			if ( '' !== $category && ! self::in_category( $summary, $category ) ) {
				continue;
			}
			if ( '' !== $search && ! self::matches( $summary, $search ) ) {
				continue;
			}

			$summaries[] = self::compact( $summary );
		}

		return $summaries;
	}

	public function categories(): array {
		$categories = array();
		foreach ( $this->map() as $element ) {
			foreach ( self::categories_of( $element ) as $category ) {
				$categories[ $category ] = true;
			}
		}

		$names = array_keys( $categories );
		sort( $names );

		return $names;
	}

	public function schema( string $tag, string $group, bool $include_descriptions ): ?array {
		$element = $this->map()[ $tag ] ?? null;
		if ( null === $element ) {
			return null;
		}

		$params = $this->params( $tag, $include_descriptions );

		$groups = array_count_values( array_column( $params, 'group' ) );
		if ( '' !== $group ) {
			$params = array_values( array_filter( $params, static fn( $param ) => 0 === strcasecmp( $param['group'], $group ) ) );
		}

		return array_merge(
			$this->summary( $tag, $element ),
			array(
				'groups' => $groups,
				'params' => $params,
			)
		);
	}

	private function map(): array {
		$this->map ??= (array) \WPBMap::getAllShortCodes();

		return $this->map;
	}

	private function legacy_elements(): array {
		$this->legacy ??= (array) apply_filters( 'uncode_builder_abilities_legacy_elements', array() );

		return $this->legacy;
	}

	private function params( string $tag, bool $include_descriptions ): array {
		$mapped = $this->normalizer->normalize_all( $this->map()[ $tag ]['params'] ?? array(), $include_descriptions );
		$names  = array_column( $mapped, 'name' );

		$implicit = array_filter(
			$this->implicit_params( $tag ),
			static fn( array $param ) => ! in_array( $param['name'], $names, true )
		);

		return array_merge( $mapped, array_values( $implicit ) );
	}

	private function implicit_params( string $tag ): array {
		$columns  = array(
			'vc_column'       => self::COLUMN_IMPLICIT_PARAMS,
			'vc_column_inner' => self::COLUMN_IMPLICIT_PARAMS,
		);
		$implicit = (array) apply_filters( 'uncode_builder_abilities_implicit_params', $columns );

		return (array) ( $implicit[ $tag ] ?? array() );
	}

	private function summary( string $tag, array $element ): array {
		$relations = $this->relations( $tag );

		return array(
			'tag'         => $tag,
			'name'        => Param_Normalizer::plain_text_or( $element['name'] ?? '', $tag ),
			'categories'  => self::categories_of( $element ),
			'description' => Param_Normalizer::plain_text( $element['description'] ?? '' ),
			'container'   => $this->is_container( $tag ),
			'insertable'  => $this->is_insertable( $tag ),
			'has_content' => $this->accepts_content( $tag ),
			'deprecated'  => ! empty( $element['deprecated'] ) || $this->is_legacy( $tag ),
			'use_instead' => $this->replacement_for( $tag ) ?? '',
			'children'    => $relations['children'],
			'parents'     => $relations['parents'],
		);
	}

	private static function compact( array $summary ): array {
		$summary['description'] = self::shorten( $summary['description'] );
		if ( ! $summary['deprecated'] ) {
			unset( $summary['deprecated'] );
		}

		return array_filter( $summary, static fn( $value ) => array() !== $value && '' !== $value );
	}

	private static function categories_of( array $element ): array {
		$categories = array_map( array( Param_Normalizer::class, 'plain_text' ), (array) ( $element['category'] ?? array() ) );

		return array_values( array_filter( $categories ) );
	}

	private static function relation( $rule ): array {
		if ( ! is_array( $rule ) ) {
			return array();
		}

		$relation = array();
		foreach ( array( 'only', 'except' ) as $mode ) {
			if ( ! empty( $rule[ $mode ] ) ) {
				$relation[ $mode ] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $rule[ $mode ] ) ) ) );
			}
		}

		return $relation;
	}

	private static function in_category( array $summary, string $category ): bool {
		foreach ( $summary['categories'] as $candidate ) {
			if ( 0 === strcasecmp( $candidate, $category ) ) {
				return true;
			}
		}

		return false;
	}

	private static function matches( array $summary, string $search ): bool {
		$haystack = implode( ' ', array( $summary['tag'], $summary['name'], $summary['description'] ) );

		return false !== stripos( $haystack, $search );
	}

	private static function shorten( string $text ): string {
		if ( mb_strlen( $text ) <= self::SUMMARY_DESCRIPTION_LENGTH ) {
			return $text;
		}

		return rtrim( mb_substr( $text, 0, self::SUMMARY_DESCRIPTION_LENGTH - 1 ) ) . '…';
	}
}
