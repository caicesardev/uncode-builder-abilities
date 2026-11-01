<?php

namespace Uncode_Builder_Abilities;

defined( 'ABSPATH' ) || exit;

final class Template_Library {

	const DEFAULT_PREFIX = 'default:';

	const SAVED_PREFIX = 'saved:';

	const SAVED_OPTION = 'wpb_js_templates';

	const SAVED_CATEGORY = 'saved';

	const UNCATEGORIZED = 'general';

	const CONTENT_BLOCK_CLASS = 'for-content-blocks';

	const SEARCH_ALIASES = array(
		'hero'         => 'header',
		'banner'       => 'header',
		'cta'          => 'call to action',
		'testimonial'  => 'quote',
		'review'       => 'quote',
		'team'         => 'team member',
		'faq'          => 'accordion',
		'prices'       => 'pricing',
		'plans'        => 'pricing',
		'features'     => 'icon',
		'services'     => 'icon',
		'contact'      => 'form',
		'stats'        => 'counter',
		'portfolio'    => 'portfolio',
		'logos'        => 'carousel',
	);

	private ?array $templates = null;

	public function all(): array {
		if ( null === $this->templates ) {
			$this->templates = array_merge( $this->default_templates(), $this->saved_templates() );
		}

		return $this->templates;
	}

	public function find( string $id ): ?array {
		return $this->all()[ $id ] ?? null;
	}

	public function categories(): array {
		$counts = array_count_values( array_column( $this->all(), 'category' ) );
		ksort( $counts );

		return $counts;
	}

	public function search( string $search, string $category ): array {
		$terms = self::search_terms( $search );

		return array_values(
			array_filter(
				$this->all(),
				static fn( array $template ) => ( '' === $category || 0 === strcasecmp( $template['category'], $category ) )
					&& ( '' === $search || self::matches_any( $template, $terms ) )
			)
		);
	}

	private static function search_terms( string $search ): array {
		$terms = array( $search );
		foreach ( (array) apply_filters( 'uncode_builder_abilities_template_search_aliases', self::SEARCH_ALIASES ) as $word => $alias ) {
			if ( false !== stripos( $search, (string) $word ) ) {
				$terms[] = str_ireplace( (string) $word, (string) $alias, $search );
			}
		}

		return array_values( array_unique( $terms ) );
	}

	private static function matches_any( array $template, array $terms ): bool {
		$haystack = implode( ' ', array( $template['name'], $template['category_label'], str_replace( array( '-', '_' ), ' ', $template['category'] ) ) );
		foreach ( $terms as $term ) {
			if ( false !== stripos( $haystack, $term ) ) {
				return true;
			}
		}

		return false;
	}

	private function default_templates(): array {
		if ( ! function_exists( 'visual_composer' ) ) {
			return array();
		}

		do_action( 'uncode_builder_abilities_before_load_templates' );

		$templates = array();
		foreach ( (array) visual_composer()->templatesPanelEditor()->loadDefaultTemplates() as $template ) {
			if ( empty( $template['content'] ) ) {
				continue;
			}

			$classes  = preg_split( '/\s+/', trim( (string) ( $template['custom_class'] ?? '' ) ) );
			$category = sanitize_key( $classes[0] ?? '' );
			$category = '' === $category ? self::UNCATEGORIZED : $category;
			$id       = self::unique_id( self::DEFAULT_PREFIX . sanitize_title( (string) ( $template['name'] ?? '' ) ), $templates );

			$templates[ $id ] = array(
				'id'                 => $id,
				'name'               => Param_Normalizer::plain_text( $template['name'] ?? '' ),
				'category'           => $category,
				'category_label'     => Param_Normalizer::plain_text( $template['cat_name'] ?? $category ),
				'for_content_blocks' => in_array( self::CONTENT_BLOCK_CLASS, $classes, true ),
				'image'              => (string) ( $template['image_path'] ?? $template['image'] ?? '' ),
				'content'            => trim( (string) $template['content'] ),
			);
		}

		return $templates;
	}

	private function saved_templates(): array {
		$templates = array();
		foreach ( (array) get_option( self::SAVED_OPTION, array() ) as $key => $template ) {
			if ( ! is_array( $template ) || empty( $template['template'] ) ) {
				continue;
			}

			$id               = self::SAVED_PREFIX . sanitize_key( (string) $key );
			$templates[ $id ] = array(
				'id'                 => $id,
				'name'               => Param_Normalizer::plain_text( $template['name'] ?? $key ),
				'category'           => self::SAVED_CATEGORY,
				'category_label'     => 'Saved templates',
				'for_content_blocks' => false,
				'image'              => '',
				'content'            => trim( (string) $template['template'] ),
			);
		}

		return $templates;
	}

	private static function unique_id( string $id, array $taken ): string {
		$candidate = $id;
		for ( $suffix = 2; isset( $taken[ $candidate ] ); $suffix++ ) {
			$candidate = $id . '-' . $suffix;
		}

		return $candidate;
	}
}
