<?php

namespace Uncode_Builder_Abilities\Tests;

use Uncode_Builder_Abilities\Layout_Preview;

final class LayoutPreviewTest extends Test_Case {

	const HTML = '<h1>Title</h1><h3>Skipped level</h3><p>Body text</p>'
		. '<a href="#">Read more</a><a href="/close" aria-label="Close"></a><img data-src="/images/photo.jpg"><p>[vc_button]</p>';

	public function test_outlines_the_rendered_page(): void {
		$analysis = ( new Layout_Preview() )->analyze( self::HTML );

		$this->assertSame(
			array(
				array(
					'heading' => 1,
					'text'    => 'Title',
				),
				array(
					'heading' => 3,
					'text'    => 'Skipped level',
				),
				array( 'text' => 'Body text' ),
				array(
					'link' => '#',
					'text' => 'Read more',
				),
				array(
					'link' => '/close',
					'text' => 'Close',
				),
				array(
					'image' => '/images/photo.jpg',
					'alt'   => '',
				),
				array( 'text' => '[vc_button]' ),
			),
			$analysis['outline']
		);
	}

	public function test_reports_issues_and_unrendered_shortcodes(): void {
		$analysis = ( new Layout_Preview() )->analyze( self::HTML );

		$this->assertSame(
			array(
				'Heading level jumps from h1 to h3 ("Skipped level").',
				'Link "Read more" has no real destination (href "#").',
				'Image "photo.jpg" has no alt attribute.',
			),
			$analysis['issues']
		);
		$this->assertSame( array( 'vc_button' ), $analysis['unrendered_shortcodes'] );
	}
}
