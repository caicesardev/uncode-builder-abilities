<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Operations;
use Uncode_Builder_Abilities\Layout_Writer;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Template_Library;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Insert_Template extends Write_Ability {

	private Template_Library $library;

	public function __construct( Shortcode_Parser $parser, Layout_Operations $operations, Layout_Writer $writer, Template_Library $library ) {
		parent::__construct( $parser, $operations, $writer );
		$this->library = $library;
	}

	public function name(): string {
		return 'insert-template';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$template = $this->library->find( (string) ( $input['template'] ?? '' ) );
		if ( null === $template ) {
			return new WP_Error( 'uncode_builder_template_not_found', 'There is no template with that id. Use list-templates to find one.', array( 'status' => 404 ) );
		}

		return $this->operations->insert_source(
			$document,
			(string) ( $input['parent_path'] ?? Layout_Document::ROOT_PATH ),
			isset( $input['index'] ) ? (int) $input['index'] : null,
			$template['content']
		);
	}

	protected function label(): string {
		return __( 'Insert a page builder template', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Inserts a template from list-templates into a page, as it is designed: missing unique ids are generated and ids already used on the page are replaced, and nesting rules are checked. `parent_path` is the container to insert into (leave it out for the top level, where most templates go); `index` is the position among its children (default: at the end). Then adapt the placeholder texts, links and images with update-element using the returned `elements`. Requires the `version` from get-page-tree; use `dry_run` to preview.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema(
			array_merge(
				$this->common_properties(),
				array(
					'template'    => array(
						'type'        => 'string',
						'description' => 'Template id from list-templates.',
					),
					'parent_path' => array(
						'type'        => 'string',
						'description' => 'Path of the container to insert into. Leave it out for the top level of the page.',
					),
					'index'       => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Insert before the child at this position. Leave out to append.',
					),
				)
			),
			array( 'post_id', 'version', 'template' )
		);
	}
}
