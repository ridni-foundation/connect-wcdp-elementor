<?php
/**
 * Shared formatting of campaign figures.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a campaign snapshot into the strings a widget prints.
 *
 * This exists so that server rendering and the REST endpoint that refreshes it
 * cannot drift apart. If the two formatted a number even slightly differently —
 * a thousands separator, a currency position, a trailing `.00` — every
 * hydration would visibly rewrite the page for no reason.
 *
 * The `fmt` array is deliberately short-keyed: it travels in a data attribute
 * on every widget instance and back through a query string.
 */
final class Value_Formatter {

	/**
	 * Every value a template placeholder can resolve to.
	 */
	const VALUES = array( 'raised', 'goal', 'remaining', 'percentage', 'count', 'timeleft' );

	/**
	 * Build the format descriptor from widget settings.
	 *
	 * @param array<string, mixed> $settings Widget settings.
	 * @return array<string, mixed>
	 */
	public static function fmt_from_settings( array $settings ): array {
		return self::sanitize_fmt(
			array(
				'src' => 'custom' === ( $settings['amount_source'] ?? 'site' ) ? 'custom' : 'site',
				'cmp' => 'yes' === ( $settings['compact_amounts'] ?? '' ) ? 1 : 0,
				'dec' => $settings['amount_decimals'] ?? '',
				'tz'  => 'yes' === ( $settings['trim_zero_decimals'] ?? 'yes' ) ? 1 : 0,
				'cp'  => (string) ( $settings['currency_position'] ?? '' ),
				'pd'  => $settings['percentage_decimals'] ?? 0,
				'cc'  => $settings['cheat_count'] ?? 0,
			)
		);
	}

	/**
	 * Clamp a format descriptor arriving from the browser.
	 *
	 * @param array<string, mixed> $fmt Raw descriptor.
	 * @return array<string, mixed>
	 */
	public static function sanitize_fmt( array $fmt ): array {
		$position = (string) ( $fmt['cp'] ?? '' );

		if ( ! in_array( $position, array( '', 'auto', 'left', 'right', 'left_space', 'right_space' ), true ) ) {
			$position = '';
		}

		// Each value is read once: `is_numeric( $a['k'] ?? 0 )` guards only the
		// test, leaving the branch after it to read a key that may be absent.
		$decimals   = $fmt['dec'] ?? '';
		$percentage = $fmt['pd'] ?? 0;
		$count      = $fmt['cc'] ?? 0;

		return array(
			'src' => 'custom' === ( $fmt['src'] ?? 'site' ) ? 'custom' : 'site',
			'cmp' => empty( $fmt['cmp'] ) ? 0 : 1,
			'dec' => is_numeric( $decimals ) ? max( 0, min( 4, (int) $decimals ) ) : '',
			'tz'  => empty( $fmt['tz'] ) ? 0 : 1,
			'cp'  => $position,
			'pd'  => is_numeric( $percentage ) ? max( 0, min( 4, (int) $percentage ) ) : 0,
			'cc'  => is_numeric( $count ) ? (int) $count : 0,
		);
	}

	/**
	 * Format every value of a campaign.
	 *
	 * @param Campaign             $campaign Campaign snapshot.
	 * @param array<string, mixed> $fmt      Format descriptor.
	 * @return array<string, string> Value name → ready-to-print HTML.
	 */
	public static function values( Campaign $campaign, array $fmt ): array {
		$fmt = self::sanitize_fmt( $fmt );

		$money = static function ( float $amount, string $which ) use ( $campaign, $fmt ): string {
			// In site mode the string WCDP produced wins. That is the only
			// variant a currency switcher has had a chance to convert;
			// re-formatting the raw float would print the base-currency number
			// under the visitor's currency symbol.
			if ( 'site' === $fmt['src'] && '' !== $which ) {
				$site_formatted = $campaign->get_formatted( $which );

				if ( null !== $site_formatted ) {
					return $site_formatted;
				}
			}

			if ( $fmt['cmp'] ) {
				return esc_html( Campaign::format_compact( $amount ) );
			}

			return Campaign::format_amount(
				$amount,
				array(
					'decimals'          => $fmt['dec'],
					'trim_zeros'        => (bool) $fmt['tz'],
					'currency_position' => $fmt['cp'],
				)
			);
		};

		$count = max( 0, $campaign->get_order_count() + (int) $fmt['cc'] );

		return array(
			'raised'     => $money( $campaign->get_raised(), 'raised' ),
			'goal'       => $money( $campaign->get_goal(), 'goal' ),
			'remaining'  => $money( $campaign->get_remaining(), 'remaining' ),
			'percentage' => esc_html( $campaign->format_percentage( (int) $fmt['pd'] ) ),
			'count'      => esc_html( number_format_i18n( $count ) ),
			'timeleft'   => wp_kses_post( $campaign->format_time_diff() ),
		);
	}

	/**
	 * The numeric state a hydrating client needs beyond the strings.
	 *
	 * @param Campaign $campaign Campaign snapshot.
	 * @return array<string, mixed>
	 */
	public static function state( Campaign $campaign ): array {
		return array(
			'fill'     => round( $campaign->get_fill_percentage(), 2 ),
			'raised'   => round( $campaign->get_raised(), 2 ),
			'goal'     => round( $campaign->get_goal(), 2 ),
			'reached'  => $campaign->is_goal_reached(),
			'has_goal' => $campaign->has_goal(),
			'ended'    => $campaign->is_ended(),
		);
	}
}
