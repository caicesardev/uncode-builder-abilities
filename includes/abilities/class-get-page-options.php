<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Page_Options;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Get_Page_Options extends Ability {

	private Page_Options $options;

	public function __construct( Page_Options $options ) {
		$this->options = $options;
	}

	public function name(): string {
		return 'get-page-options';
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

		$sections = $this->options->sections();
		$names    = array_values( array_filter( array_map( 'strval', (array) ( $input['fields'] ?? array() ) ) ) );
		$section  = (string) ( $input['section'] ?? '' );

		if ( array() !== $names ) {
			$fields = array_values( array_filter( array_map( array( $this->options, 'field' ), $names ) ) );
		} else {
			$section = '' === $section ? (string) array_key_first( $sections ) : self::matching_section( $sections, $section );
			if ( '' === $section ) {
				return new WP_Error( 'uncode_builder_unknown_section', sprintf( 'Sections: %s.', implode( ', ', array_keys( $sections ) ) ), array( 'status' => 404 ) );
			}
			$fields = array_map( array( $this->options, 'field' ), $sections[ $section ] );
		}

		return array_filter(
			array(
				'post_id'          => $post->ID,
				'version'          => Layout_Document::version_of( $post->post_content ),
				'sections'         => $sections,
				'values'           => (object) $this->options->stored_values( Draft_Copies::permission_target_id( $post ) ),
				'returned_section' => array() === $names ? $section : null,
				'fields'           => $fields,
				'unknown_fields'   => array_values( array_diff( $names, array_column( $fields, 'name' ) ) ),
			),
			static fn( $value ) => null !== $value && array() !== $value
		);
	}

	private static function matching_section( array $sections, string $requested ): string {
		foreach ( array_keys( $sections ) as $section ) {
			if ( 0 === strcasecmp( (string) $section, $requested ) ) {
				return (string) $section;
			}
		}

		return '';
	}

	protected function label(): string {
		return __( 'Read page options (Uncode page settings)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Reads the per-page settings of the theme that live outside the layout (with Uncode: header type and size, menu transparency, page width, content and footer blocks, sidebar, scroll effects…). These are settings of this item only, not the global theme options. The reply lists every option name by section in `sections`, the values stored for this item in `values` (missing ones inherit the theme defaults), and the definition of one section (the first by default, or `section`) or of the options named in `fields`: kind, allowed `options`, `default` and `visible_when` (the option only applies when other options have those values). Change them with update-page-options using the same `version`. For an autosave copy the values shown are those of the published item.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'section' => array(
					'type'        => 'string',
					'description' => 'Section to describe, as listed in `sections`.',
				),
				'fields'  => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Exact option names to describe, from any section.',
				),
			),
			'required'             => array( 'post_id' ),
			'additionalProperties' => false,
		);
	}
}
