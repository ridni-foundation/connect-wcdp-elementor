<?php
/**
 * Safe invocation of WCDP shortcodes.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls WCDP shortcodes through their registered callbacks instead of building
 * shortcode strings.
 *
 * Building `[wcdp_leaderboard button="…"]` by concatenation breaks as soon as a
 * label contains a bracket or a quote — a real failure mode of the 1.0.x
 * widgets. Passing the attribute array straight to the callback removes the
 * escaping round-trip entirely.
 */
final class Shortcode_Runner {

	/**
	 * Whether a shortcode tag is currently registered.
	 *
	 * @param string $tag Shortcode tag.
	 * @return bool
	 */
	public static function exists( string $tag ): bool {
		return isset( $GLOBALS['shortcode_tags'][ $tag ] ) && is_callable( $GLOBALS['shortcode_tags'][ $tag ] );
	}

	/**
	 * Invoke a shortcode callback with an attribute array.
	 *
	 * @param string               $tag  Shortcode tag.
	 * @param array<string, mixed> $atts Attributes, exactly as the callback expects them.
	 * @return string Shortcode output, or an empty string when unavailable.
	 */
	public static function run( string $tag, array $atts ): string {
		if ( ! self::exists( $tag ) ) {
			return '';
		}

		$output = call_user_func( $GLOBALS['shortcode_tags'][ $tag ], $atts, null, $tag );

		return is_string( $output ) ? $output : '';
	}

	/**
	 * Retrieve the object a shortcode callback is bound to.
	 *
	 * WCDP registers most shortcodes as instance methods but exposes no
	 * accessor for those instances; the shortcode registry is the only public
	 * route to them.
	 *
	 * @param string $tag Shortcode tag.
	 * @return object|null
	 */
	public static function get_handler( string $tag ) {
		if ( ! isset( $GLOBALS['shortcode_tags'][ $tag ] ) ) {
			return null;
		}

		$callback = $GLOBALS['shortcode_tags'][ $tag ];

		if ( is_array( $callback ) && isset( $callback[0] ) && is_object( $callback[0] ) ) {
			return $callback[0];
		}

		return null;
	}

	/**
	 * Remove every `<style>` element from a markup string.
	 *
	 * WCDP prints its progress-bar and leaderboard CSS inline, once per page,
	 * behind `define()` guards — so a second widget of the same style inherits
	 * the first one's CSS and per-instance styling is impossible. Compat mode
	 * drops that CSS and supplies a registered stylesheet plus Elementor's
	 * per-instance rules instead.
	 *
	 * @param string $html Markup to clean.
	 * @return string
	 */
	public static function strip_inline_styles( string $html ): string {
		$clean = preg_replace( '#<style\b[^>]*>.*?</style>#is', '', $html );

		return null === $clean ? $html : $clean;
	}

	/**
	 * Remove `:root` custom-property blocks while keeping other inline CSS.
	 *
	 * Used where WCDP's inline CSS carries both page-global variables and
	 * genuinely needed rules.
	 *
	 * @param string $html Markup to clean.
	 * @return string
	 */
	public static function strip_root_variables( string $html ): string {
		$clean = preg_replace( '#:root\s*\{[^}]*\}#i', '', $html );

		return null === $clean ? $html : $clean;
	}
}
