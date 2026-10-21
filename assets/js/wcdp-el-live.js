/**
 * WCDP Elementor Integration — live figures.
 *
 * Page caches store the HTML a campaign had when the file was written, which
 * is why a fundraiser under paid traffic can spend hours showing its opening
 * total. This script fetches the current figures over a route no page cache
 * touches and patches them in, so the age of the HTML stops mattering.
 *
 * Design rules:
 *  - Never throw. A failed refresh must leave the server-rendered figure alone,
 *    because that figure is correct-ish, and a blank one is not.
 *  - Never write to the DOM unless the value actually changed; a pointless
 *    rewrite drops the visitor's text selection.
 *  - Never run in the Elementor editor, where the numbers are a preview.
 */
( function () {
	'use strict';

	var cfg = window.wcdpElLive;

	if ( ! cfg || ! cfg.endpoint || ! window.fetch ) {
		return;
	}

	// The editor renders widgets through its own pipeline; hydrating there
	// would fight the preview rather than help it.
	if ( window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode() ) {
		return;
	}

	var SELECTOR = '[data-wcdp-live]';
	var inFlight = false;
	var everRefreshed = false;

	/**
	 * Collect the widgets on the page, grouped by identical request.
	 */
	function collect() {
		var nodes = document.querySelectorAll( SELECTOR );
		var groups = {};

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];
			var spec;

			try {
				spec = JSON.parse( node.getAttribute( 'data-wcdp-live' ) );
			} catch ( e ) {
				continue;
			}

			if ( ! spec || ! spec.k ) {
				continue;
			}

			if ( ! groups[ spec.k ] ) {
				groups[ spec.k ] = { spec: spec, nodes: [] };
			}

			groups[ spec.k ].nodes.push( node );
		}

		return groups;
	}

	/**
	 * Replace one value, leaving the DOM untouched when nothing changed.
	 */
	function setValue( node, name, html ) {
		var targets = [];

		// A widget marks a container and holds its values inside it; a dynamic
		// tag marks the value span itself, so the node can be its own target.
		if ( node.getAttribute( 'data-wcdp-v' ) === name ) {
			targets.push( node );
		}

		var inner = node.querySelectorAll( '[data-wcdp-v="' + name + '"]' );

		for ( var j = 0; j < inner.length; j++ ) {
			targets.push( inner[ j ] );
		}

		for ( var i = 0; i < targets.length; i++ ) {
			if ( targets[ i ].innerHTML !== html ) {
				targets[ i ].innerHTML = html;
			}
		}
	}

	/**
	 * Apply one campaign payload to every widget that asked for it.
	 */
	function apply( nodes, payload ) {
		if ( ! payload || ! payload.values ) {
			return;
		}

		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[ i ];

			for ( var name in payload.values ) {
				if ( Object.prototype.hasOwnProperty.call( payload.values, name ) ) {
					setValue( node, name, payload.values[ name ] );
				}
			}

			if ( ! payload.state ) {
				continue;
			}

			// The bar is driven by a custom property rather than an inline
			// width, so the CSS transition carries it to the new value.
			if ( typeof payload.state.fill === 'number' ) {
				node.style.setProperty( '--wcdp-el-fill', payload.state.fill + '%' );

				var bar = node.querySelector( '[role="progressbar"]' );

				if ( bar ) {
					bar.setAttribute( 'aria-valuenow', String( payload.state.raised ) );
					bar.setAttribute( 'aria-valuemax', String( payload.state.goal ) );
				}
			}

			node.classList.toggle( 'wcdp-el-progress--reached', !! payload.state.reached );
		}
	}

	/**
	 * Fetch and apply.
	 */
	function refresh() {
		if ( inFlight || document.hidden ) {
			return;
		}

		var groups = collect();
		var keys = Object.keys( groups );

		if ( ! keys.length ) {
			return;
		}

		var query = keys.map( function ( key ) {
			return groups[ key ].spec;
		} );

		inFlight = true;

		var url = cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'q=' + encodeURIComponent( JSON.stringify( query ) );

		window.fetch( url, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
			cache: 'no-store'
		} )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( ! data || ! data.items ) {
					return;
				}

				everRefreshed = true;

				for ( var key in data.items ) {
					if ( Object.prototype.hasOwnProperty.call( data.items, key ) && groups[ key ] ) {
						apply( groups[ key ].nodes, data.items[ key ] );
					}
				}

				document.dispatchEvent( new CustomEvent( 'wcdp-el:refreshed', { detail: data } ) );
			} )
			.catch( function () {
				// Leave the server-rendered figures in place.
			} )
			.then( function () {
				inFlight = false;
			} );
	}

	/**
	 * Run the first refresh without competing with the page's own load work.
	 */
	function start() {
		if ( window.requestIdleCallback ) {
			window.requestIdleCallback( refresh, { timeout: 2000 } );
		} else {
			window.setTimeout( refresh, 300 );
		}

		// A tab opened in the background never runs the first refresh, because
		// refresh() declines to work for a hidden page. Every mode therefore
		// watches for the tab becoming visible at least until it has succeeded
		// once; the focus and interval modes keep watching after that.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				return;
			}

			if ( ! everRefreshed || cfg.mode === 'focus' || cfg.mode === 'interval' ) {
				refresh();
			}
		} );

		if ( cfg.mode === 'interval' && cfg.interval > 0 ) {
			window.setInterval( refresh, cfg.interval * 1000 );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}

	// Elementor rebuilds widget markup on popups and lazy sections.
	window.addEventListener( 'elementor/popup/show', refresh );

	window.wcdpElRefresh = refresh;
}() );
