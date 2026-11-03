<?php

namespace Uncode_Builder_Abilities\Tests;

use Brain\Monkey\Filters;
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

	public function test_ignores_code_and_keeps_words_apart_at_line_breaks(): void {
		$analysis = ( new Layout_Preview() )->analyze( '<h2>Web design<br>made to measure</h2><script>var row = "[vc_row]";</script><style>.a{}</style><p>One two</p>' );

		$this->assertSame( 'Web design made to measure', $analysis['outline'][0]['text'] );
		$this->assertSame( array(), $analysis['unrendered_shortcodes'] );
		$this->assertSame( 7, $analysis['words'] );
	}

	public function test_lines_drawn_by_classes_keep_their_words_apart(): void {
		Filters\expectApplied( 'uncode_builder_abilities_preview_line_classes' )->andReturn( array( 'heading-text-inner' ) );

		$analysis = ( new Layout_Preview() )->analyze( '<h1><span class="heading-text-inner">Web design</span><span class="heading-text-inner">made to measure</span></h1>' );

		$this->assertSame( 'Web design made to measure', $analysis['outline'][0]['text'] );
	}

	public function test_reports_issues_and_unrendered_shortcodes(): void {
		$analysis = ( new Layout_Preview() )->analyze( self::HTML );

		$this->assertSame(
			array(
				'Link "Read more" has no real destination (href "#").',
				'Image "photo.jpg" has no alt attribute.',
				'Heading level jumps from h1 to h3 ("Skipped level") on desktop, tablet, mobile.',
			),
			$analysis['issues']
		);
		$this->assertSame( array( 'vc_button' ), $analysis['unrendered_shortcodes'] );
	}

	public function test_checks_headings_per_device(): void {
		Filters\expectApplied( 'uncode_builder_abilities_preview_hidden_classes' )->andReturn(
			array(
				'desktop' => 'desktop-hidden',
				'tablet'  => 'tablet-hidden',
				'mobile'  => 'mobile-hidden',
			)
		);

		$analysis = ( new Layout_Preview() )->analyze(
			'<div class="mobile-hidden"><h1>Home</h1></div><div class="desktop-hidden tablet-hidden"><h1>Home</h1></div>'
			. '<div class="tablet-hidden"><h1>Second</h1></div>'
		);

		$this->assertSame( array( 'desktop', 'tablet' ), $analysis['outline'][0]['visible_on'] );
		$this->assertSame( array( 'mobile' ), $analysis['outline'][1]['visible_on'] );
		$this->assertSame( array( 'More than one h1 ("Second") on desktop, mobile.' ), $analysis['issues'] );
	}
}
