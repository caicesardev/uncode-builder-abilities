<?php

namespace Uncode_Builder_Abilities\Abilities;

use Uncode_Builder_Abilities\Plugin;

defined( 'ABSPATH' ) || exit;

abstract class Ability {

	const DEFAULT_CATALOG_CAPABILITY = 'edit_posts';

	abstract public function name(): string;

	abstract public function execute( $input = null );

	abstract public function has_permission( $input = null ): bool;

	abstract protected function label(): string;

	abstract protected function description(): string;

	abstract protected function input_schema(): array;

	public function qualified_name(): string {
		return $this->ability_namespace() . '/' . $this->name();
	}

	public function definition(): array {
		return array(
			'label'               => $this->label(),
			'description'         => $this->description(),
			'category'            => Plugin::CATEGORY,
			'input_schema'        => $this->input_schema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'has_permission' ),
			'meta'                => array(
				'annotations'  => $this->annotations(),
				'show_in_rest' => (bool) apply_filters( 'uncode_builder_abilities_show_in_rest', true, $this->qualified_name() ),
			),
		);
	}

	protected function ability_namespace(): string {
		return Plugin::READ_NAMESPACE;
	}

	protected function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	protected static function input_array( $input ): array {
		return is_array( $input ) ? $input : array();
	}

	protected static function catalog_capability(): string {
		return (string) apply_filters( 'uncode_builder_abilities_catalog_capability', self::DEFAULT_CATALOG_CAPABILITY );
	}
}
