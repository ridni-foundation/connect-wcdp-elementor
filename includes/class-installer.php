<?php
/**
 * Activation and upgrade routines.
 *
 * @package WCDP_Elementor
 */

namespace WCDP_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records the installed version and clears Elementor's generated CSS when it
 * changes, so a release that alters control output cannot be served from a
 * stale stylesheet.
 *
 * No page data is ever rewritten.
 */
final class Installer {

	/**
	 * Option holding the last installed version.
	 */
	const VERSION_OPTION = 'wcdp_el_db_version';

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::maybe_upgrade();
	}

	/**
	 * Bring the stored version in line with the running one.
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) get_option( self::VERSION_OPTION, '' );

		if ( $stored === WCDP_EL_VERSION ) {
			return;
		}

		update_option( self::VERSION_OPTION, WCDP_EL_VERSION );

		self::clear_elementor_cache();
	}

	/**
	 * Drop Elementor's generated CSS so the new control set is regenerated.
	 */
	private static function clear_elementor_cache(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$elementor = \Elementor\Plugin::$instance;

		if ( isset( $elementor->files_manager ) ) {
			$elementor->files_manager->clear_cache();
		}
	}
}
