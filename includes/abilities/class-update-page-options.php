<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Operations;
use Uncode_Builder_Abilities\Layout_Writer;
use Uncode_Builder_Abilities\Page_Options;
use Uncode_Builder_Abilities\Shortcode_Parser;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Update_Page_Options extends Write_Ability {

	private Page_Options $options;

	public function __construct( Shortcode_Parser $parser, Layout_Operations $operations, Layout_Writer $writer, Page_Options $options ) {
		parent::__construct( $parser, $operations, $writer );
		$this->options = $options;
	}

	public function name(): string {
		return 'update-page-options';
	}

	protected function edit( Layout_Document $document, WP_Post $post, array $input ) {
		$changes = $input['options'] ?? array();
		if ( ! is_array( $changes ) || array() === $changes ) {
			return new WP_Error( 'uncode_builder_nothing_to_update', 'Send `options`: an object of option names and values.', array( 'status' => 400 ) );
		}

		$validated = $this->options->validate( $changes );
		if ( array() !== $validated['errors'] ) {
			$first = $validated['errors'][0];

			return new WP_Error(
				'uncode_builder_invalid_options',
				sprintf( '%d problem(s) found; nothing was changed. First: %s: %s', count( $validated['errors'] ), $first['at'], $first['message'] ),
				array(
					'status' => 400,
					'errors' => $validated['errors'],
				)
			);
		}

		return array(
			'content' => $document->content(),
			'meta'    => $validated['meta'],
			'focus'   => array(),
		);
	}

	protected function is_destructive(): bool {
		return true;
	}

	protected function label(): string {
		return __( 'Update page options (Uncode page settings)', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Changes per-page settings read with get-page-options (header, menu, width, content blocks, sidebar, scroll…) of one item; it never touches the global theme options. `options` maps option names to values: select-like options take one of their `options` values, toggles "on"/"off", measurements "1200px" or [1200, "px"], colours a palette value from get-design-tokens, backgrounds an object of background-color, background-image (attachment ID or URL), background-repeat, background-attachment, background-position and background-size. A null value removes the setting so the item inherits the theme default again. Everything is validated first; the previous values are kept as a snapshot that restore-snapshot brings back. Requires the `version` from get-page-tree or get-page-options; use `dry_run` to preview. Not available on autosave copies, which cannot hold settings.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return self::object_schema(
			array_merge(
				$this->common_properties(),
				array(
					'options' => array(
						'type'        => 'object',
						'description' => 'Option names and their new values; null restores the theme default.',
					),
				)
			),
			array( 'post_id', 'version', 'options' )
		);
	}
}
