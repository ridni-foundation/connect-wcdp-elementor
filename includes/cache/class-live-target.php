<?php
/**
 * Marks rendered markup as refreshable.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor\Cache;

use WCDP_Elementor\Data\Campaign;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the descriptor the browser sends back when it refreshes a figure.
 *
 * The descriptor carries everything that determines the printed string —
 * campaign, folded products, overrides, number format — so the REST endpoint
 * can reproduce the exact same output. Two widgets with identical settings
 * produce an identical key and therefore share one request.
 */
final class Live_Target {

	/**
	 * HTML attribute carrying the descriptor.
	 */
	const ATTRIBUTE = 'data-wcdp-live';

	/**
	 * Attribute marking an individual value inside a widget.
	 */
	const VALUE_ATTRIBUTE = 'data-wcdp-v';

	/**
	 * Build the descriptor for a campaign as one widget renders it.
	 *
	 * @param Campaign             $campaign      Campaign snapshot.
	 * @param array<string, mixed> $fmt           Format descriptor.
	 * @param array<string, mixed> $campaign_args Arguments used to build the snapshot.
	 * @return array<string, mixed>
	 */
	public static function spec( Campaign $campaign, array $fmt, array $campaign_args = array() ): array {
		// Read once. `is_numeric( $a['k'] ?? 0 )` guards only the test — the
		// branch that follows still reads a key that may not be there.
		$cheat = $campaign_args['cheat'] ?? 0;
		$goal  = $campaign_args['goal'] ?? '';

		$spec = array(
			'id'    => $campaign->get_product_id(),
			'add'   => $campaign->get_addids(),
			'cheat' => is_numeric( $cheat ) ? (float) $cheat : 0,
			'goal'  => is_numeric( $goal ) ? (float) $goal : '',
			'fmt'   => $fmt,
		);

		// The key is derived, not random: identical widgets collapse into one
		// request, and the value survives a page cache unchanged.
		$spec['k'] = substr( md5( (string) wp_json_encode( $spec ) ), 0, 16 );

		return $spec;
	}

	/**
	 * The ready-to-print attribute, or an empty string when hydration is off.
	 *
	 * @param Campaign             $campaign      Campaign snapshot.
	 * @param array<string, mixed> $fmt           Format descriptor.
	 * @param array<string, mixed> $campaign_args Arguments used to build the snapshot.
	 * @param string               $widget_mode   `inherit`, `on` or `off`.
	 * @return string
	 */
	public static function attribute( Campaign $campaign, array $fmt, array $campaign_args = array(), string $widget_mode = 'inherit' ): string {
		if ( ! self::is_enabled( $widget_mode ) ) {
			return '';
		}

		return wp_json_encode( self::spec( $campaign, $fmt, $campaign_args ) );
	}

	/**
	 * Whether this instance should refresh itself.
	 *
	 * @param string $widget_mode `inherit`, `on` or `off`.
	 * @return bool
	 */
	public static function is_enabled( string $widget_mode = 'inherit' ): bool {
		if ( 'off' === $widget_mode ) {
			return false;
		}

		if ( 'on' === $widget_mode ) {
			return true;
		}

		return Freshness::hydration_enabled();
	}

	/**
	 * Whether any widget on this page asked to be refreshed.
	 *
	 * Set during rendering and read when deciding to enqueue the script, so a
	 * page without a single live figure ships no JavaScript.
	 *
	 * @var bool
	 */
	private static $needed = false;

	/**
	 * Flag that the hydration script is required on this page.
	 */
	public static function mark_needed(): void {
		self::$needed = true;
	}

	/**
	 * Whether the hydration script is required on this page.
	 *
	 * @return bool
	 */
	public static function is_needed(): bool {
		return self::$needed;
	}
}
