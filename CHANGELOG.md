# Changelog

All notable changes to Connect WCDP for Elementor are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/).

Versions before 3.1.2 were used privately by the Ridni Foundation under the
name "WCDP Elementor Integration" and were never published; they are listed so
the history of the code is complete.

## [3.1.2] — 2026-10-21

### Changed

- First public release, on GitHub. The code is the same as in 3.1.1.
- Tested with WordPress 7.1, WooCommerce 11.2, Elementor 4.3 and Donation Platform for WooCommerce 1.5 on PHP 7.4 through 8.5.

## [3.1.1] — 2026-09-14

### Fixed

- Fixed the donation form waiting behind its loading spinner until the visitor moved the mouse. "Delay JavaScript execution" features held back the donation platform's own form script; on phones, where there is no mouse movement, the spinner stayed until the first tap or scroll. The plugin now excludes that script from delaying in WP Rocket, LiteSpeed Cache, Perfmatters and Autoptimize, and can mark it for Cloudflare Rocket Loader, WP Meteor, NitroPack and SG Optimizer.
- jQuery is always excluded together with the form script. The donation platform registers its form script without declaring jQuery as a dependency, so excluding the script on its own would run it before jQuery and break the form completely. The missing dependency is now declared as well.

### Added

- Added the "Donation form script" setting: *Only where the form is* (default) applies the exclusions only on pages that render a donation form, *Also mark the tags* adds opt-out attributes for edge optimizers that never run PHP, *Off* leaves the exclusions to you.
- Added a "Form script" diagnostics row that warns when an optimizer is detected that only the attribute mode can reach.
- The live-update script is now always excluded from delaying, so cached figures are corrected before the visitor interacts with the page.

### Changed

- If you excluded the donation platform's script or jQuery by hand in your optimizer, you can remove those entries. A manual jQuery exclusion applies to the whole site.

## [3.1.0] — 2026-09-11

### Added

- Added live figures on cached pages. A page cache stores the amount raised at the moment the page was cached, and a donation never updates a post, so cached pages kept showing an old total for hours. A small script now fetches current figures from a read-only REST endpoint (`GET /wp-json/wcdp-el/v1/campaigns`) and updates them in place. Without JavaScript the server-rendered figure stays, so nothing breaks and the layout does not jump.
- Added page cache purging when a donation is made, with adapters for WP Rocket, LiteSpeed Cache, W3 Total Cache, WP Super Cache, Cache Enabler, SG Optimizer, Nginx Helper, Hummingbird, Breeze, WP Engine and Kinsta, plus the generic `clean_post_cache()` signal used by the Cloudflare plugin and most host caches. Only the pages that actually show the campaign are purged.
- Added freshness presets in the settings (Active campaign, Balanced — the default, Maximum speed, Custom) that control the live updates, the purge throttle and how long donation data is cached.
- Added a per-widget "Live updates" switch (inherit / always / never) for Donation Progress and Donation Counter.
- Added optional live updates for dynamic tags, off by default.
- Added a "Purge page caches" button and diagnostics rows for the active preset, the last purge and the detected cache plugins.
- Added the `wcdp_el_freshness_config`, `wcdp_el_cache_adapters` and `wcdp_el_api_cache_ttl` filters and the `wcdp_el_cache_purged` action.

### Fixed

- Fixed three "Undefined array key" PHP warnings.

### Security

- The REST endpoint returns only figures that are already printed on the page that calls it. It writes nothing, exposes no personal data, caches responses for 15 seconds on the server and sends `Vary: Cookie` so a shared cache never serves one visitor's currency to another.

## [3.0.0] — 2026-09-10

### Removed

- Removed the legacy markup mode. Donation Progress and Donation Counter now always render the plugin's own markup; widgets that were still set to Legacy take their appearance from the Style tab.

### Fixed

- Fixed alignment and colour controls overflowing the Elementor panel at its minimum width.

### Changed

- Redesigned the settings screen: widget manager, diagnostics with status pills, cache reset. Still no JavaScript in the admin.

## [2.1.1] — 2026-09-09

### Fixed

- "Follow WooCommerce setting" now reads and enforces the store's currency position instead of deferring to whatever filters the format.
- Fixed Hide Symbol, Symbol Color and Symbol Size having no effect on amounts printed without markup.

### Added

- Added an "Automatic" currency position for sites that want a currency switcher to decide.

## [2.1.0] — 2026-09-09

### Added

- Added a standalone Goal element to the progress builder.
- Added separate alignment for the rows above and below the bar, with the six alignment values of an Elementor container.
- Added a currency symbol position control.

## [2.0.1] — 2026-09-09

### Fixed

- WooCommerce no longer lists the plugin as incompatible on sites with High-Performance Order Storage. The plugin now declares compatibility with HPOS and the product block editor, and incompatibility with the block checkout, which the donation platform does not support.

## [2.0.0] — 2026-09-08

### Changed

- Complete rewrite: native markup, a full Style tab on every widget, dynamic tags for campaign figures, per-instance styling and a settings screen.

## [1.0.2] — 2026-08-18

### Added

- Added variable products and variable subscriptions to the campaign picker.
- A variation ID passed to a widget now resolves to its parent campaign.
- Variations without their own image fall back to the parent product image.

## [1.0.0] — 2026-08-05

### Added

- Initial version: Elementor widgets for the donation form, progress bar, leaderboard, donation counter and campaign title, price and image, plus a Product ID dynamic tag.

[3.1.2]: https://github.com/ridni-foundation/connect-wcdp-elementor/releases/tag/v3.1.2
