/**
 * First-visit region redirect.
 *
 * Printed inline in <head> on mapped domains. Runs client-side so it works on
 * cached pages. Expects window.rowdyCrowdGeolocation (see Module::print_head_assets()).
 */
( function ( config ) {
	'use strict';

	const BOT_PATTERN = /bot|crawl|spider|slurp|lighthouse|facebookexternalhit|preview/i;

	if ( ! config || BOT_PATTERN.test( navigator.userAgent ) ) {
		return;
	}

	const isRegion = ( value ) => config.regions.indexOf( value ) !== -1;

	const readCookie = () => {
		const match = document.cookie.match( new RegExp( '(?:^|; )' + config.cookie + '=([^;]*)' ) );
		return match ? decodeURIComponent( match[ 1 ] ) : null;
	};

	const remember = ( region ) => {
		const secure = window.location.protocol === 'https:' ? '; secure' : '';
		document.cookie = config.cookie + '=' + region + '; path=/; max-age=31536000; samesite=lax' + secure;
	};

	const goTo = ( region ) => {
		const url = new URL( window.location.href );
		url.host = config.domains[ region ];
		url.searchParams.set( config.queryVar, region );
		window.location.replace( url.toString() );
	};

	// A region chosen via ?rc_region=. PHP normally handles this; this covers cached responses.
	const url = new URL( window.location.href );
	const chosen = url.searchParams.get( config.queryVar );

	if ( isRegion( chosen ) ) {
		remember( chosen );
		url.searchParams.delete( config.queryVar );
		window.history.replaceState( null, '', url.toString() );
		return;
	}

	// Returning visitor: keep them on the region they chose.
	const saved = readCookie();

	if ( isRegion( saved ) ) {
		if ( saved !== config.region ) {
			goTo( saved );
		}
		return;
	}

	// First visit: hide the page until we know whether to redirect.
	const root = document.documentElement;
	const reveal = () => root.classList.remove( 'rowdy-crowd-geolocating' );

	root.classList.add( 'rowdy-crowd-geolocating' );
	window.setTimeout( reveal, config.timeout );

	window
		.fetch( config.endpoint, { credentials: 'omit', headers: { Accept: 'application/json' } } )
		.then( ( response ) => ( response.ok ? response.json() : Promise.reject( response ) ) )
		.then( ( data ) => {
			if ( ! isRegion( data.region ) ) {
				reveal();
				return;
			}

			remember( data.region );

			if ( data.region === config.region ) {
				reveal();
			} else {
				goTo( data.region );
			}
		} )
		.catch( reveal );
}( window.rowdyCrowdGeolocation ) );
