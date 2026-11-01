<?php

final class WPBMap {

	public static array $map = array();

	public static function getAllShortCodes(): array {
		return self::$map;
	}

	public static function addAllMappedShortcodes(): void {
	}

	public static function use_fixture(): void {
		self::$map = array(
			'vc_row'            => array(
				'name'         => 'Row',
				'category'     => array( 'Essentials' ),
				'is_container' => true,
				'params'       => array(
					array( 'type' => 'textfield', 'param_name' => 'el_class', 'heading' => 'Extra class' ),
					array(
						'type'       => 'type_numeric_slider',
						'param_name' => 'top_padding',
						'heading'    => 'Top padding',
						'min'        => 0,
						'max'        => 7,
						'step'       => 1,
						'group'      => 'Aspect',
					),
				),
			),
			'vc_column'         => array(
				'name'            => 'Column',
				'is_container'    => true,
				'content_element' => false,
				'params'          => array(
					array( 'type' => 'textfield', 'param_name' => 'el_class', 'heading' => 'Extra class' ),
				),
			),
			'vc_column_text'    => array(
				'name'   => 'Text Block',
				'params' => array(
					array( 'type' => 'textarea_html', 'param_name' => 'content', 'heading' => 'Text' ),
					array( 'type' => 'css_editor', 'param_name' => 'css', 'heading' => 'CSS', 'group' => 'Design' ),
				),
			),
			'vc_custom_heading' => array(
				'name'   => 'Heading',
				'params' => array(
					array( 'type' => 'textarea_html', 'param_name' => 'content', 'heading' => 'Text' ),
					array(
						'type'       => 'dropdown',
						'param_name' => 'heading_semantic',
						'heading'    => 'Semantic',
						'std'        => 'h2',
						'value'      => array(
							'h1' => 'h1',
							'h2' => 'h2',
							'h3' => 'h3',
						),
					),
					array(
						'type'       => 'dropdown',
						'param_name' => 'text_size',
						'heading'    => 'Size',
						'value'      => array(
							'Default' => '',
							'h1'      => 'h1',
						),
						'dependency' => array(
							'element' => 'heading_semantic',
							'value'   => array( 'h1', 'h2' ),
						),
					),
					array(
						'type'       => 'checkbox',
						'param_name' => 'uppercase',
						'heading'    => 'Uppercase',
						'value'      => array( '' => 'yes' ),
					),
				),
			),
			'vc_button'         => array(
				'name'   => 'Button',
				'params' => array(
					array( 'type' => 'textfield', 'param_name' => 'content', 'heading' => 'Text' ),
					array( 'type' => 'vc_link', 'param_name' => 'link', 'heading' => 'Link' ),
					array( 'type' => 'uncode_gradientpicker', 'param_name' => 'gradient', 'heading' => 'Gradient' ),
				),
			),
			'vc_btn'            => array(
				'name'   => 'Legacy button',
				'params' => array(
					array( 'type' => 'textfield', 'param_name' => 'title', 'heading' => 'Title' ),
				),
			),
			'vc_progress_bar'   => array(
				'name'   => 'Progress bar',
				'params' => array(
					array(
						'type'       => 'param_group',
						'param_name' => 'values',
						'heading'    => 'Values',
					),
				),
			),
			'vc_raw_html'       => array(
				'name'   => 'Raw HTML',
				'params' => array(
					array( 'type' => 'textarea_raw_html', 'param_name' => 'content', 'heading' => 'HTML' ),
				),
			),
			'vc_empty_space'    => array(
				'name'   => 'Empty space',
				'params' => array(
					array( 'type' => 'textfield', 'param_name' => 'empty_h', 'heading' => 'Height' ),
				),
			),
			'vc_row_inner'      => array(
				'name'            => 'Inner Row',
				'is_container'    => true,
				'content_element' => false,
				'params'          => array(),
			),
			'vc_column_inner'   => array(
				'name'            => 'Inner Column',
				'is_container'    => true,
				'content_element' => false,
				'params'          => array(),
			),
			'vc_tta_tabs'       => array(
				'name'         => 'Tabs',
				'is_container' => true,
				'as_parent'    => array( 'only' => 'vc_tta_section' ),
				'params'       => array(),
			),
			'vc_tta_section'    => array(
				'name'         => 'Section',
				'is_container' => true,
				'as_child'     => array( 'only' => 'vc_tta_tabs' ),
				'params'       => array(
					array( 'type' => 'textfield', 'param_name' => 'title', 'heading' => 'Title' ),
					array( 'type' => 'tab_id', 'param_name' => 'tab_id', 'heading' => 'Tab ID' ),
				),
			),
		);
	}
}
