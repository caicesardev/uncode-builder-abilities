<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Insert_Elements extends Write_Ability {

	public function name(): string {
		return 'insert-elements';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		return $this->operations->insert(
			$document,
			(string) ( $input['parent_path'] ?? Layout_Document::ROOT_PATH ),
			isset( $input['index'] ) ? (int) $input['index'] : null,
			(array) ( $input['elements'] ?? array() )
		);
	}

	protected function label(): string {
		return __( 'Insert page builder elements', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Inserts one or more WPBakery / Uncode elements, with their children, into a page. Each element is { tag, attributes?: { parameter: value }, content?: string, children?: [elements] }. Parameters and allowed values come from get-element-schema; structured values can be sent as JSON (links as { url, title, target, rel }, gradients, design CSS as { declarations: { "margin-top": "10px" } }, parameter groups) and are encoded the way WPBakery stores them. Unique IDs (uncode_shortcode_id, tab_id) are generated when left out. `parent_path` is the container to insert into (leave it out for the top level of the page, where only rows and sections go); `index` is the position among its children (default: at the end). Everything is validated first: unknown parameters, values outside the options and wrong nesting refuse the whole call. Requires the `version` from get-page-tree; use `dry_run` to preview. The previous content is kept as a snapshot that restore-snapshot brings back.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		$element = array(
			'type'       => 'object',
			'properties' => array(
				'tag'        => array( 'type' => 'string' ),
				'attributes' => array( 'type' => 'object' ),
				'content'    => array( 'type' => 'string' ),
				'children'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'tag' ),
		);

		return self::object_schema(
			array_merge(
				$this->common_properties(),
				array(
					'parent_path' => array(
						'type'        => 'string',
						'default'     => Layout_Document::ROOT_PATH,
						'description' => 'Path of the container to insert into, such as "2.0". Leave it out for the top level of the page.',
					),
					'index'       => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => 'Insert before the child at this position. Leave out to append.',
					),
					'elements'    => array(
						'type'     => 'array',
						'minItems' => 1,
						'items'    => $element,
					),
				)
			),
			array( 'post_id', 'version', 'elements' )
		);
	}
}
