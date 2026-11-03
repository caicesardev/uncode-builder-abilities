<?php

namespace Uncode_Builder_Abilities;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Layout_Preview {

	const MAX_OUTLINE = 150;

	const TEXT_EXCERPT = 140;

	const SKIPPED_TAGS = array( 'script', 'style', 'noscript', 'template', 'svg', 'head' );

	const TEXT_TAGS = array( 'p', 'li', 'blockquote', 'figcaption', 'td', 'dd' );

	const BLOCK_TAGS = array( 'p', 'div', 'li', 'blockquote', 'figcaption', 'td', 'th', 'dd', 'dt', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'header', 'footer' );

	const HEADING_PATTERN = '/^h([1-6])$/';

	const SHORTCODE_PATTERN = '/\[\/?([a-z][\w-]*)(?=[\s\]\/])[^\]]*\]/i';

	const UNSAFE_HREF_PATTERN = '/^\s*(javascript|data|vbscript):/i';

	private array $outline = array();

	private array $issues = array();

	private array $device_issues = array();

	private array $previous_heading = array();

	private array $h1_count = array();

	private array $hidden_classes = array();

	private array $visible = Device_Visibility::DEVICES;

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
			$html = (string) apply_filters( 'the_content', $source ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.
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
		$this->device_issues    = array();
		$this->previous_heading = array();
		$this->h1_count         = array();
		$this->hidden_classes   = (array) apply_filters( 'uncode_builder_abilities_preview_hidden_classes', array() );

		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		self::keep_visible_text( $document );
		$this->walk( $document->documentElement, Device_Visibility::DEVICES );
		$text = self::text_of( $document->documentElement );

		preg_match_all( self::SHORTCODE_PATTERN, $text, $shortcodes );

		return array(
			'html_bytes'            => strlen( $html ),
			'words'                 => str_word_count( wp_strip_all_tags( $text ) ),
			'unrendered_shortcodes' => array_values( array_unique( $shortcodes[1] ) ),
			'issues'                => array_values( array_unique( array_merge( $this->issues, $this->device_issue_messages() ) ) ),
			'outline'               => array_slice( $this->outline, 0, self::MAX_OUTLINE ),
			'outline_truncated'     => count( $this->outline ) > self::MAX_OUTLINE,
		);
	}

	private static function keep_visible_text( DOMDocument $document ): void {
		foreach ( self::SKIPPED_TAGS as $tag ) {
			foreach ( iterator_to_array( $document->getElementsByTagName( $tag ) ) as $element ) {
				$element->parentNode->removeChild( $element );
			}
		}

		foreach ( iterator_to_array( $document->getElementsByTagName( 'br' ) ) as $line_break ) {
			$line_break->parentNode->replaceChild( $document->createTextNode( ' ' ), $line_break );
		}

		foreach ( self::line_elements( $document ) as $line ) {
			$line->appendChild( $document->createTextNode( ' ' ) );
		}
	}

	private static function line_elements( DOMDocument $document ): array {
		$lines = array();
		foreach ( self::BLOCK_TAGS as $tag ) {
			$lines = array_merge( $lines, iterator_to_array( $document->getElementsByTagName( $tag ) ) );
		}

		$xpath = new DOMXPath( $document );
		foreach ( (array) apply_filters( 'uncode_builder_abilities_preview_line_classes', array() ) as $class ) {
			$query = sprintf( "//*[contains(concat(' ', normalize-space(@class), ' '), ' %s ')]", sanitize_html_class( $class ) );
			$lines = array_merge( $lines, iterator_to_array( $xpath->query( $query ) ) );
		}

		return $lines;
	}

	private function walk( ?DOMNode $node, array $visible ): void {
		if ( null === $node ) {
			return;
		}

		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}

			$this->visible = Device_Visibility::without( $visible, $this->hidden_devices( $child ) );

			$tag = strtolower( $child->tagName );
			if ( preg_match( self::HEADING_PATTERN, $tag, $level ) ) {
				$this->heading( (int) $level[1], self::text_of( $child ) );
				continue;
			}

			$this->describe( $tag, $child );
			$this->walk( $child, $this->visible );
		}
	}

	private function hidden_devices( DOMElement $element ): array {
		$classes = explode( ' ', (string) preg_replace( '/\s+/', ' ', $element->getAttribute( 'class' ) ) );

		return array_keys( array_intersect( $this->hidden_classes, $classes ) );
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
		foreach ( $this->visible as $device ) {
			$previous = $this->previous_heading[ $device ] ?? 0;
			if ( $previous > 0 && $level > $previous + 1 ) {
				$this->device_issue( sprintf( 'Heading level jumps from h%d to h%d ("%s")', $previous, $level, self::excerpt( $text ) ), $device );
			}
			if ( 1 === $level ) {
				$this->h1_count[ $device ] = ( $this->h1_count[ $device ] ?? 0 ) + 1;
				if ( $this->h1_count[ $device ] > 1 ) {
					$this->device_issue( sprintf( 'More than one h1 ("%s")', self::excerpt( $text ) ), $device );
				}
			}
			$this->previous_heading[ $device ] = $level;
		}
		if ( '' === $text ) {
			$this->issues[] = sprintf( 'Empty h%d heading.', $level );
		}

		$this->add(
			array(
				'heading' => $level,
				'text'    => self::excerpt( $text ),
			)
		);
	}

	private function link( DOMElement $element ): void {
		$href = trim( $element->getAttribute( 'href' ) );
		$text = self::text_of( $element );
		$text = '' === $text ? trim( $element->getAttribute( 'aria-label' ) ) : $text;

		if ( '' === $href || '#' === $href || preg_match( self::UNSAFE_HREF_PATTERN, $href ) ) {
			$this->issues[] = sprintf( 'Link "%s" has no real destination (href "%s").', self::excerpt( $text ), $href );
		}
		if ( '' === $text ) {
			$this->issues[] = sprintf( 'Link to "%s" has no text or aria-label.', $href );
		}

		$this->add(
			array(
				'link' => $href,
				'text' => self::excerpt( $text ),
			)
		);
	}

	private function image( DOMElement $element ): void {
		$src = $element->getAttribute( 'src' );
		$src = '' === $src ? $element->getAttribute( 'data-src' ) : $src;
		if ( ! $element->hasAttribute( 'alt' ) ) {
			$this->issues[] = sprintf( 'Image "%s" has no alt attribute.', basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) );
		}

		$this->add(
			array(
				'image' => $src,
				'alt'   => $element->getAttribute( 'alt' ),
			)
		);
	}

	private function device_issue( string $message, string $device ): void {
		$this->device_issues[ $message ][] = $device;
	}

	private function device_issue_messages(): array {
		$messages = array();
		foreach ( $this->device_issues as $message => $devices ) {
			$messages[] = sprintf( '%s on %s.', $message, implode( ', ', Device_Visibility::sorted( array_unique( $devices ) ) ) );
		}

		return $messages;
	}

	private function add( array $entry ): void {
		if ( Device_Visibility::DEVICES !== $this->visible ) {
			$entry['visible_on'] = $this->visible;
		}
		$this->outline[] = $entry;
	}

	private static function text_of( ?DOMNode $node ): string {
		return null === $node ? '' : trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
	}

	private static function excerpt( string $text ): string {
		return mb_strlen( $text ) > self::TEXT_EXCERPT ? rtrim( mb_substr( $text, 0, self::TEXT_EXCERPT - 1 ) ) . '…' : $text;
	}
}
