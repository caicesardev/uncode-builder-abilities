<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Template_Library;

defined( 'ABSPATH' ) || exit;

final class List_Templates extends Ability {

	const DEFAULT_LIMIT = 30;

	const MAX_LIMIT = 100;

	private Template_Library $library;

	public function __construct( Template_Library $library ) {
		$this->library = $library;
	}

	public function name(): string {
		return 'list-templates';
	}

	public function has_permission( $input = null ): bool {
		return current_user_can( self::catalog_capability() );
	}

	public function execute( $input = null ) {
		$input    = self::input_array( $input );
		$search   = trim( (string) ( $input['search'] ?? '' ) );
		$category = trim( (string) ( $input['category'] ?? '' ) );

		$reply = array(
			'total'      => count( $this->library->all() ),
			'categories' => $this->library->categories(),
		);

		if ( '' === $search && '' === $category ) {
			$reply['note'] = 'Send `category` or `search` to list templates.';
			return $reply;
		}

		$limit   = min( self::MAX_LIMIT, max( 1, (int) ( $input['limit'] ?? self::DEFAULT_LIMIT ) ) );
		$matches = $this->library->search( $search, $category );

		return array_merge(
			$reply,
			array(
				'matches'   => count( $matches ),
				'templates' => array_map( array( __CLASS__, 'summary' ), array_slice( $matches, 0, $limit ) ),
			)
		);
	}

	private static function summary( array $template ): array {
		return array_filter(
			array(
				'id'                 => $template['id'],
				'name'               => $template['name'],
				'category'           => $template['category'],
				'for_content_blocks' => $template['for_content_blocks'],
				'image'              => $template['image'],
				'bytes'              => strlen( $template['content'] ),
			)
		);
	}

	protected function label(): string {
		return __( 'List page builder templates', 'uncode-builder-abilities' );
	}

	protected function description(): string {
		return __( 'Lists ready-made sections from the page builder template library: WPBakery default templates (with Uncode, its 900+ wireframes: headers, contents, call to actions, pricing tables, team members, footers…) and templates saved by the site users. Without filters it returns only the categories and their counts; send `category` and/or `search` (matches name and category, and understands common words such as hero, cta, testimonials, team, faq, pricing, features or contact) to get templates with their `id`, a thumbnail `image` and size. Read one with get-template and insert it with insert-template, then adapt its texts and images with update-element. Starting from a template gives better designed sections than composing them element by element.', 'uncode-builder-abilities' );
	}

	protected function input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'category' => array(
					'type'        => 'string',
					'description' => 'Category id, as listed in `categories`.',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Text to look for in the template name and category.',
				),
				'limit'    => array(
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
