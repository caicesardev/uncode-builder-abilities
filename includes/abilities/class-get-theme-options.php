<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Theme_Options;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Get_Theme_Options extends Ability {

	const DEFAULT_CAPABILITY = 'edit_theme_options';

	const DEFAULT_LIMIT = 50;

	const MAX_LIMIT = 200;

	private Theme_Options $options;

	public function __construct( Theme_Options $options ) {
		$this->options = $options;
	}

	public function name(): string {
		return 'get-theme-options';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( (string) apply_filters( 'uncode_builder_abilities_theme_options_capability', self::DEFAULT_CAPABILITY ) );
	}

	public function execute( $input = null ) {
		$input    = self::input_array( $input );
		$sections = $this->options->sections();
		$names    = array_values( array_filter( array_map( 'strval', (array) ( $input['fields'] ?? array() ) ) ) );
		$section  = trim( (string) ( $input['section'] ?? '' ) );
		$search   = trim( (string) ( $input['search'] ?? '' ) );
		$changed  = ! empty( $input['changed_only'] );
		$limit    = max( 1, min( self::MAX_LIMIT, (int) ( $input['limit'] ?? self::DEFAULT_LIMIT ) ) );

		if ( array() === $names && '' === $section && '' === $search && ! $changed ) {
			return array( 'sections' => $sections );
		}

		if ( '' !== $section ) {
			$section = self::matching_section( $sections, $section );
			if ( '' === $section ) {
				return new WP_Error( 'uncode_builder_unknown_section', sprintf( 'Sections: %s.', implode( ', ', array_column( $sections, 'name' ) ) ), array( 'status' => 404 ) );
			}
		}

		$fields = array() === $names ? array_values( $this->options->fields() ) : array_values( array_filter( array_map( array( $this->options, 'field' ), $names ) ) );
		$fields = array_values(
			array_filter(
				$fields,
				fn( array $field ) => ( '' === $section || $section === $field['section'] )
					&& ( '' === $search || self::matches( $field, $search ) )
					&& ( ! $changed || ! $this->options->is_default( $field ) )
			)
		);

		return array_filter(
			array(
				'fields'         => array_map( array( $this->options, 'described' ), array_slice( $fields, 0, $limit ) ),
				'total'          => count( $fields ),
				'truncated'      => count( $fields ) > $limit,
				'unknown_fields' => array_values( array_diff( $names, array_keys( $this->options->fields() ) ) ),
			),
			static fn( $value ) => array() !== $value
		);
	}

	private static function matches( array $field, string $search ): bool {
		$haystack = implode( ' ', array( str_replace( '_', ' ', $field['name'] ), $field['label'], $field['section'], $field['group'] ?? '' ) );
		foreach ( preg_split( '/\s+/', $search ) as $word ) {
			if ( false === stripos( $haystack, $word ) ) {
				return false;
			}
		}

		return true;
	}

	private static function matching_section( array $sections, string $requested ): string {
		foreach ( array_column( $sections, 'name' ) as $name ) {
			if ( 0 === strcasecmp( (string) $name, $requested ) ) {
				return (string) $name;
			}
		}

		return '';
	}

	protected function label(): string {
		return __( 'Read theme options (Uncode global settings)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Reads the global theme options (with Uncode: Theme Options panel, such as layout width, header type, button hover effect, typography sizes, colour palette, custom CSS, performance settings…). Read-only: use it to explain why a page looks or behaves as it does, for example a button setting with no effect because the site-wide hover effect overrides it. Without input it lists the sections with their group and number of options. Ask for one `section`, exact option names in `fields`, options whose name, label or section contain every word of `search`, or `changed_only` for the options that differ from the theme defaults (combinable). Each option comes with its kind, `value` (the default when it was never saved), `value_label` for choices, `default`, `is_default`, `visible_when` and `applies` (false when its conditions, or those of the options they depend on, are not met, so the panel hides it and it usually has no effect). Values that look like secrets (API keys, tokens, licences) are redacted. Page-level settings that override these are read with get-page-options.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'section'      => array(
					'type'        => 'string',
					'description' => 'Section name, as listed in `sections`.',
				),
				'fields'       => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Exact option names, from any section.',
				),
				'search'       => array(
					'type'        => 'string',
					'description' => 'Words to look for in option names, labels and sections, for example "button hover" or "width".',
				),
				'changed_only' => array(
					'type'        => 'boolean',
					'description' => 'Only options whose saved value differs from the theme default.',
				),
				'limit'        => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => self::MAX_LIMIT,
					'default' => self::DEFAULT_LIMIT,
				),
			),
			'additionalProperties' => false,
		);
	}
}
