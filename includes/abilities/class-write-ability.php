<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Draft_Copies;
use Uncode_Builder_Abilities\Element_Advisor;
use Uncode_Builder_Abilities\Layout_Document;
use Uncode_Builder_Abilities\Layout_Operations;
use Uncode_Builder_Abilities\Layout_Writer;
use Uncode_Builder_Abilities\Plugin;
use Uncode_Builder_Abilities\Shortcode_Parser;
use Uncode_Builder_Abilities\Write_Policy;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

abstract class Write_Ability extends Ability {

	protected Layout_Operations $operations;

	private Shortcode_Parser $parser;

	private Layout_Writer $writer;

	public function __construct( Shortcode_Parser $parser, Layout_Operations $operations, Layout_Writer $writer ) {
		$this->parser     = $parser;
		$this->operations = $operations;
		$this->writer     = $writer;
	}

	abstract protected function edit( Layout_Document $document, WP_Post $post, array $input );

	public function has_permission( $input = null ): bool {
		$post = get_post( (int) ( self::input_array( $input )['post_id'] ?? 0 ) );
		if ( ! $post instanceof WP_Post || ! Draft_Copies::is_layout_target( $post ) || ! current_user_can( 'edit_post', Draft_Copies::permission_target_id( $post ) ) ) {
			return false;
		}

		return (bool) apply_filters( 'uncode_builder_abilities_can_write_post', true, $post, $this->qualified_name() );
	}

	public function execute( $input = null ) {
		$input = self::input_array( $input );
		$post  = get_post( (int) ( $input['post_id'] ?? 0 ) );

		if ( ! $post instanceof WP_Post || ! Draft_Copies::is_layout_target( $post ) ) {
			return new WP_Error( 'uncode_builder_post_not_found', 'The post does not exist.', array( 'status' => 404 ) );
		}

		if ( ! Write_Policy::allows( $post ) ) {
			return new WP_Error(
				'uncode_builder_drafts_only',
				sprintf( 'This site only lets these abilities edit drafts, and "%s" is %s. Create a draft copy with create-draft-copy and edit that, or ask an administrator to allow it in Settings → Uncode Builder Abilities.', $post->post_title, $post->post_status ),
				array( 'status' => 403 )
			);
		}

		$document = $this->open( $post->post_content );
		if ( (string) ( $input['version'] ?? '' ) !== $document->version() ) {
			return new WP_Error(
				'uncode_builder_version_conflict',
				'The layout changed since it was read. Read it again with get-page-tree and redo the change with the new `version`.',
				array(
					'status'          => 409,
					'current_version' => $document->version(),
				)
			);
		}

		$edit = $this->edit( $document, $post, $input );
		if ( is_wp_error( $edit ) ) {
			return $edit;
		}

		$meta = $edit['meta'] ?? array();
		if ( array() !== $meta && Draft_Copies::is_autosave( $post ) ) {
			return new WP_Error(
				'uncode_builder_meta_in_autosave',
				'WordPress autosaves only keep the title, content and excerpt, so page options cannot be changed on this copy. Change them on a draft, or on a Rewrite & Republish copy.',
				array( 'status' => 400 )
			);
		}

		$reply = $this->describe_edit( $document, $edit, $post );

		if ( ! $reply['changed'] ) {
			return array_merge(
				$reply,
				array(
					'saved'   => false,
					'version' => $document->version(),
				)
			);
		}

		if ( ! empty( $input['dry_run'] ) ) {
			return array_merge(
				$reply,
				array(
					'dry_run' => true,
					'saved'   => false,
					'version' => $document->version(),
				)
			);
		}

		$saved = $this->writer->commit( $post, $edit['content'], $this->qualified_name(), $meta );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$warnings = array_merge( $reply['warnings'] ?? array(), $saved['warnings'] ?? array() );
		$reply    = array_merge( $reply, array( 'saved' => true ), $saved );
		if ( array() !== $warnings ) {
			$reply['warnings'] = $warnings;
		}

		return $reply;
	}

	protected function ability_namespace(): string {
		return Plugin::WRITE_NAMESPACE;
	}

	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => $this->is_destructive(),
			'idempotent'  => false,
		);
	}

	protected function is_destructive(): bool {
		return false;
	}

	protected function target( Layout_Document $document, array $input ) {
		$path = isset( $input['path'] ) ? (string) $input['path'] : null;
		$id   = isset( $input['id'] ) ? (string) $input['id'] : null;

		if ( ( null === $path ) === ( null === $id ) ) {
			return new WP_Error( 'uncode_builder_target_required', 'Send either `path` or `id` to choose the element, not both.', array( 'status' => 400 ) );
		}

		if ( null !== $path ) {
			$node = $document->node( $path );
		} else {
			$matches = $document->nodes_with_id( $id );
			if ( count( $matches ) > 1 ) {
				return new WP_Error( 'uncode_builder_ambiguous_id', sprintf( '%d elements share the ID "%s"; use `path` instead.', count( $matches ), $id ), array( 'status' => 400 ) );
			}
			$node = $matches[0] ?? null;
		}

		if ( null === $node ) {
			return new WP_Error( 'uncode_builder_element_not_found', 'No element matches that `path` or `id`. Read the page again with get-page-tree.', array( 'status' => 404 ) );
		}

		$expected = $input['expect_tag'] ?? null;
		if ( null !== $expected && ( $node['tag'] ?? null ) !== $expected ) {
			return new WP_Error(
				'uncode_builder_unexpected_tag',
				sprintf( 'The element there is "%s", not "%s". Read the page again with get-page-tree.', $node['tag'] ?? 'text', $expected ),
				array( 'status' => 409 )
			);
		}

		return $node;
	}

	protected function common_properties(): array {
		return array(
			'post_id' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'version' => array(
				'type'        => 'string',
				'description' => 'The `version` returned by get-page-tree or by the previous change. The call is refused if the layout changed since.',
			),
			'dry_run' => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Validate and preview the change without saving it.',
			),
		);
	}

	protected static function target_properties(): array {
		return array(
			'path'       => array(
				'type'        => 'string',
				'description' => 'Element path from get-page-tree, such as "0.1.2". Send `path` or `id`.',
			),
			'id'         => array(
				'type'        => 'string',
				'description' => 'Element id from get-page-tree (uncode_shortcode_id, el_id or tab_id). Send `path` or `id`.',
			),
			'expect_tag' => array(
				'type'        => 'string',
				'description' => 'The tag the element must have; the call is refused otherwise.',
			),
		);
	}

	protected static function object_schema( array $properties, array $required ): array {
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		);
	}

	private function open( string $content ): Layout_Document {
		return new Layout_Document( $content, $this->parser->parse( $content, false )['tree'] );
	}

	private function describe_edit( Layout_Document $before, array $edit, WP_Post $post ): array {
		$after    = $this->open( $edit['content'] );
		$options  = self::option_changes( $post->ID, $edit['meta'] ?? array() );
		$elements = array();
		$focused  = array();
		foreach ( $edit['focus'] as $offset ) {
			$node = $after->element_starting_at( $offset );
			if ( null !== $node ) {
				$focused[]  = $node;
				$elements[] = array_filter(
					array(
						'path' => $node['path'],
						'tag'  => $node['tag'],
						'id'   => $node['id'] ?? null,
					),
					static fn( $value ) => null !== $value
				);
			}
		}

		return array_filter(
			array(
				'changed'       => $edit['content'] !== $before->content() || array() !== $options,
				'elements'      => $elements,
				'options'       => $options,
				'generated_ids' => $edit['generated_ids'] ?? array(),
				'snippet'       => $edit['snippet'] ?? null,
				'warnings'      => $edit['warnings'] ?? array(),
				'advice'        => Element_Advisor::advice_for( $focused ),
			),
			static fn( $value ) => false === $value || ( null !== $value && array() !== $value )
		);
	}

	private static function option_changes( int $post_id, array $meta ): array {
		$changes = array();
		foreach ( $meta as $key => $value ) {
			$before = metadata_exists( 'post', $post_id, $key ) ? get_metadata( 'post', $post_id, $key, true ) : null;
			if ( $before !== $value ) {
				$changes[ $key ] = array(
					'before' => $before,
					'after'  => $value,
				);
			}
		}

		return $changes;
	}
}
