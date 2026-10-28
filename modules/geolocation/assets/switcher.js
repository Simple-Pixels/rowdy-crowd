/**
 * [region_switcher] dropdown behaviour.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-rowdy-crowd-region-switcher]' ).forEach( ( switcher ) => {
		const toggle = switcher.querySelector( '.rowdy-crowd-region-switcher__toggle' );
		const menu = switcher.querySelector( '.rowdy-crowd-region-switcher__menu' );

		const setOpen = ( open ) => {
			toggle.setAttribute( 'aria-expanded', String( open ) );
			menu.hidden = ! open;
		};

		toggle.addEventListener( 'click', () => {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );

		document.addEventListener( 'click', ( event ) => {
			if ( ! switcher.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		switcher.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && ! menu.hidden ) {
				setOpen( false );
				toggle.focus();
			}
		} );
	} );
}() );
