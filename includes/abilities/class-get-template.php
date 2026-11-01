<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Option_Checker;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Template_Library;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Get_Template extends Ability {

	private Template_Library $library;

	private Shortcode_Parser $parser;

	private Option_Checker $checker;

	public function __construct( Template_Library $library, Shortcode_Parser $parser, Option_Checker $checker ) {
		$this->library = $library;
		$this->parser  = $parser;
		$this->checker = $checker;
	}

	public function name(): string {
		return 'get-template';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$input    = self::input_array( $input );
		$template = $this->library->find( (string) ( $input['id'] ?? '' ) );

		if ( null === $template ) {
			return new WP_Error( 'uncode_builder_template_not_found', 'There is no template with that id. Use list-templates to find one.', array( 'status' => 404 ) );
		}

		$parsed         = $this->parser->parse( $template['content'], (bool) ( $input['decode'] ?? false ) );
		$parsed['tree'] = Shortcode_Parser::without_source( $parsed['tree'] );
		$warnings       = $this->checker->mismatches( $template['content'] );
		unset( $template['content'] );

		return array_merge( array( 'template' => $template ), array_filter( array( 'warnings' => $warnings ) ), $parsed );
	}

	protected function label(): string {
		return __( 'Get a page builder template', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Returns one template from list-templates as a layout tree, in the same format as get-page-tree (paths, tags, attributes, content), so you can see its structure and texts before inserting it with insert-template (or before rebuilding it with insert-elements to set your texts in one call). Templates are already adapted to the site typography and palette; `warnings` lists any value that still is not one of the site options. `decode: true` adds decoded copies of encoded attributes.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'id'     => array(
					'type'        => 'string',
					'description' => 'Template id from list-templates.',
				),
				'decode' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
			'required'             => array( 'id' ),
			'additionalProperties' => false,
		);
	}
}
