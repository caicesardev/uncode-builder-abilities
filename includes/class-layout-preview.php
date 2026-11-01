<?php

namespace Uncode_Builder_Abilities;

use DOMDocument;
use DOMElement;
use DOMNode;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Layout_Preview {

	const MAX_OUTLINE = 150;

	const TEXT_EXCERPT = 140;

	const SKIPPED_TAGS = array( 'script', 'style', 'noscript', 'template', 'svg', 'head' );

	const TEXT_TAGS = array( 'p', 'li', 'blockquote', 'figcaption', 'td', 'dd' );

	const HEADING_PATTERN = '/^h([1-6])$/';

	const SHORTCODE_PATTERN = '/\[\/?([a-z][\w-]*)(?=[\s\]\/])[^\]]*\]/i';

	const UNSAFE_HREF_PATTERN = '/^\s*(javascript|data|vbscript):/i';

	private array $outline = array();

	private array $issues = array();

	private int $previous_heading = 0;

	public function render( WP_Post $context, string $source ): string {
		if ( class_exists( 'WPBMap' ) && method_exists( 'WPBMap', 'addAllMappedShortcodes' ) ) {
			\WPBMap::addAllMappedShortcodes();
		}

		global $post;
		$previous = $post;
		$post     = $context; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );

		ob_start();
		try {
			$html = (string) apply_filters( 'the_content', $source );
		} finally {
			$echoed = (string) ob_get_clean();
			$post   = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous instanceof WP_Post ) {
				setup_postdata( $previous );
			} else {
				wp_reset_postdata();
			}
		}

		return $echoed . $html;
	}

	public function analyze( string $html ): array {
		$this->outline          = array();
		$this->issues           = array();
		$this->previous_heading = 0;

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$this->walk( $document->documentElement );
		$text = self::text_of( $document->documentElement );

		preg_match_all( self::SHORTCODE_PATTERN, $text, $shortcodes );

		return array(
			'html_bytes'            => strlen( $html ),
			'words'                 => str_word_count( wp_strip_all_tags( $text ) ),
			'unrendered_shortcodes' => array_values( array_unique( $shortcodes[1] ) ),
			'issues'                => array_values( array_unique( $this->issues ) ),
			'outline'               => array_slice( $this->outline, 0, self::MAX_OUTLINE ),
			'outline_truncated'     => count( $this->outline ) > self::MAX_OUTLINE,
		);
	}

	private function walk( ?DOMNode $node ): void {
		if ( null === $node ) {
			return;
		}

		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$tag = strtolower( $child->tagName );
			if ( in_array( $tag, self::SKIPPED_TAGS, true ) ) {
				continue;
			}

			if ( preg_match( self::HEADING_PATTERN, $tag, $level ) ) {
				$this->heading( (int) $level[1], self::text_of( $child ) );
				continue;
			}

			$this->describe( $tag, $child );
			$this->walk( $child );
		}
	}

	private function describe( string $tag, DOMElement $element ): void {
		switch ( $tag ) {
			case 'a':
				$this->link( $element );
				return;
			case 'img':
				$this->image( $element );
				return;
			case 'button':
				$this->add( array( 'button' => self::text_of( $element ) ) );
				return;
			case 'iframe':
			case 'video':
				$this->add( array( 'media' => $element->getAttribute( 'src' ) ) );
				return;
		}

		if ( in_array( $tag, self::TEXT_TAGS, true ) ) {
			$text = self::text_of( $element );
			if ( '' !== $text ) {
				$this->add( array( 'text' => self::excerpt( $text ) ) );
			}
		}
	}

	private function heading( int $level, string $text ): void {
		if ( $this->previous_heading > 0 && $level > $this->previous_heading + 1 ) {
			$this->issues[] = sprintf( 'Heading level jumps from h%d to h%d ("%s").', $this->previous_heading, $level, self::excerpt( $text ) );
		}
		if ( '' === $text ) {
			$this->issues[] = sprintf( 'Empty h%d heading.', $level );
		}

		$this->previous_heading = $level;
		$this->add( array( 'heading' => $level, 'text' => self::excerpt( $text ) ) );
	}

	private function link( DOMElement $element ): void {
		$href = trim( $element->getAttribute( 'href' ) );
		$text = self::text_of( $element ) ?: trim( $element->getAttribute( 'aria-label' ) );

		if ( '' === $href || '#' === $href || preg_match( self::UNSAFE_HREF_PATTERN, $href ) ) {
			$this->issues[] = sprintf( 'Link "%s" has no real destination (href "%s").', self::excerpt( $text ), $href );
		}
		if ( '' === $text ) {
			$this->issues[] = sprintf( 'Link to "%s" has no text or aria-label.', $href );
		}

		$this->add( array( 'link' => $href, 'text' => self::excerpt( $text ) ) );
	}

	private function image( DOMElement $element ): void {
		$src = $element->getAttribute( 'src' ) ?: $element->getAttribute( 'data-src' );
		if ( ! $element->hasAttribute( 'alt' ) ) {
			$this->issues[] = sprintf( 'Image "%s" has no alt attribute.', basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) );
		}

		$this->add( array( 'image' => $src, 'alt' => $element->getAttribute( 'alt' ) ) );
	}

	private function add( array $entry ): void {
		$this->outline[] = $entry;
	}

	private static function text_of( ?DOMNode $node ): string {
		return null === $node ? '' : trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
	}

	private static function excerpt( string $text ): string {
		return mb_strlen( $text ) > self::TEXT_EXCERPT ? rtrim( mb_substr( $text, 0, self::TEXT_EXCERPT - 1 ) ) . '…' : $text;
	}
}
