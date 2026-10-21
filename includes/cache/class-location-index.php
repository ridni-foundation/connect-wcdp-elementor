<?php
/**
 * Where each campaign is displayed.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor\Cache;

use WCDP_Elementor\Data\Product_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A self-building map of campaign → pages that show it.
 *
 * Purging needs to know which URLs carry a campaign's figures, and nothing in
 * WordPress knows that: a widget can be dropped on any page, in any template,
 * in a loop over an archive. Rather than asking the site owner to maintain a
 * list, the index records the pairing the first time a widget renders it.
 *
 * Writes are rare by construction — a page that is already in the index costs
 * one memoized option read, and a page behind a page cache only renders on a
 * cache miss anyway.
 */
final class Location_Index {

	/**
	 * Option name. Never autoloaded: it is only read when purging.
	 */
	const OPTION = 'wcdp_el_locations';

	/**
	 * Pages remembered per campaign. Beyond this the oldest entries are
	 * dropped, so a campaign shown in a site-wide footer cannot grow the
	 * option without bound.
	 */
	const MAX_POSTS_PER_CAMPAIGN = 30;

	/**
	 * Campaigns remembered in total.
	 */
	const MAX_CAMPAIGNS = 200;

	/**
	 * How long an unseen pairing stays in the index.
	 */
	const ENTRY_TTL = 30 * DAY_IN_SECONDS;

	/**
	 * Memoized index for this request.
	 *
	 * @var array<int, array<int, int>>|null
	 */
	private static $index = null;

	/**
	 * Pairings discovered in this request that are not in the index yet.
	 *
	 * @var array<int, array<int, int>>
	 */
	private static $pending = array();

	/**
	 * Whether the shutdown writer is attached.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Read the whole index.
	 *
	 * @return array<int, array<int, int>>
	 */
	public static function all(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}

		$stored = get_option( self::OPTION, array() );

		self::$index = is_array( $stored ) ? $stored : array();

		return self::$index;
	}

	/**
	 * Note that a campaign is rendered on the current page.
	 *
	 * @param int   $product_id Primary campaign product ID.
	 * @param int[] $addids     Additional products folded into the same figures.
	 */
	public static function record( int $product_id, array $addids = array() ): void {
		if ( ! self::should_record() ) {
			return;
		}

		$post_id = self::current_post_id();

		if ( $post_id <= 0 || $product_id <= 0 ) {
			return;
		}

		$index = self::all();
		$now   = time();

		// Every contributing product is indexed, not just the primary one: a
		// donation to a translation or to an `addids` sibling changes the same
		// displayed total and must purge the same pages.
		$products = array_merge( array( $product_id ), array_map( 'absint', $addids ) );

		foreach ( array_unique( array_filter( $products ) ) as $id ) {
			$seen = $index[ $id ][ $post_id ] ?? 0;

			// Refresh a third of the way through the TTL so an actively viewed
			// page never ages out, without writing on every render.
			if ( $seen && ( $now - $seen ) < ( self::ENTRY_TTL / 3 ) ) {
				continue;
			}

			self::$pending[ $id ][ $post_id ] = $now;
		}

		if ( empty( self::$pending ) || self::$hooked ) {
			return;
		}

		self::$hooked = true;

		add_action( 'shutdown', array( __CLASS__, 'flush_pending' ), 90 );
	}

	/**
	 * Write the pairings discovered in this request.
	 */
	public static function flush_pending(): void {
		if ( empty( self::$pending ) ) {
			return;
		}

		// Re-read rather than trusting the memoized copy: another request may
		// have written between this page's first render and shutdown.
		$stored = get_option( self::OPTION, array() );
		$index  = is_array( $stored ) ? $stored : array();

		foreach ( self::$pending as $product_id => $posts ) {
			foreach ( $posts as $post_id => $timestamp ) {
				$index[ $product_id ][ $post_id ] = $timestamp;
			}
		}

		self::$pending = array();

		$index = self::prune( $index );

		update_option( self::OPTION, $index, false );

		self::$index = $index;
	}

	/**
	 * Drop expired and overflowing entries.
	 *
	 * @param array<int, array<int, int>> $index Raw index.
	 * @return array<int, array<int, int>>
	 */
	private static function prune( array $index ): array {
		$cutoff = time() - self::ENTRY_TTL;

		foreach ( $index as $product_id => $posts ) {
			if ( ! is_array( $posts ) ) {
				unset( $index[ $product_id ] );

				continue;
			}

			$posts = array_filter(
				$posts,
				static function ( $timestamp ) use ( $cutoff ) {
					return (int) $timestamp >= $cutoff;
				}
			);

			if ( empty( $posts ) ) {
				unset( $index[ $product_id ] );

				continue;
			}

			if ( count( $posts ) > self::MAX_POSTS_PER_CAMPAIGN ) {
				arsort( $posts );
				$posts = array_slice( $posts, 0, self::MAX_POSTS_PER_CAMPAIGN, true );
			}

			$index[ $product_id ] = $posts;
		}

		if ( count( $index ) > self::MAX_CAMPAIGNS ) {
			// Keep the campaigns seen most recently.
			uasort(
				$index,
				static function ( $a, $b ) {
					return max( $b ) <=> max( $a );
				}
			);

			$index = array_slice( $index, 0, self::MAX_CAMPAIGNS, true );
		}

		return $index;
	}

	/**
	 * Posts known to display any of the given campaigns.
	 *
	 * @param int[] $product_ids Campaign product IDs.
	 * @return int[]
	 */
	public static function posts_for( array $product_ids ): array {
		$index = self::all();
		$posts = array();

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );

			if ( empty( $index[ $product_id ] ) || ! is_array( $index[ $product_id ] ) ) {
				continue;
			}

			foreach ( array_keys( $index[ $product_id ] ) as $post_id ) {
				$posts[] = absint( $post_id );
			}
		}

		return array_values( array_unique( array_filter( $posts ) ) );
	}

	/**
	 * How many pairings are currently indexed.
	 *
	 * @return array{campaigns:int,pages:int}
	 */
	public static function stats(): array {
		$index = self::all();
		$pages = 0;

		foreach ( $index as $posts ) {
			$pages += is_array( $posts ) ? count( $posts ) : 0;
		}

		return array(
			'campaigns' => count( $index ),
			'pages'     => $pages,
		);
	}

	/**
	 * Empty the index.
	 */
	public static function clear(): void {
		delete_option( self::OPTION );

		self::$index   = null;
		self::$pending = array();
	}

	/**
	 * Whether this request may add to the index.
	 *
	 * Only an ordinary front-end page view describes a real location. Editor
	 * previews, REST hydration calls and admin screens would all record
	 * misleading pairings.
	 *
	 * @return bool
	 */
	private static function should_record(): bool {
		if ( Freshness::PURGE_INDEX !== Freshness::config()['purge'] ) {
			return false;
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( Product_Resolver::is_elementor_render_context() ) {
			return false;
		}

		return true;
	}

	/**
	 * The post this render belongs to.
	 *
	 * Deliberately the queried object, not the loop item: purging works on the
	 * page that has a cache entry, and an archive's loop items do not.
	 *
	 * @return int
	 */
	private static function current_post_id(): int {
		$queried = get_queried_object_id();

		if ( $queried ) {
			return (int) $queried;
		}

		$current = get_the_ID();

		return $current ? (int) $current : 0;
	}
}
