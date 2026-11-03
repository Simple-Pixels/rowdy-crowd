/**
 * Shared dropdown behaviour (region and currency switchers).
 *
 * Options are plain links, so the switchers still work without JavaScript.
 * This only opens and closes the menu and keeps it on screen.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-rowdy-crowd-dropdown]' ).forEach( ( dropdown ) => {
		const toggle = dropdown.querySelector( '.rowdy-crowd-dropdown__toggle' );
		const menu = dropdown.querySelector( '.rowdy-crowd-dropdown__menu' );

		// Opens upwards or aligns to the right edge when the menu would overflow the viewport.
		const keepOnScreen = () => {
			dropdown.classList.remove( 'rowdy-crowd-dropdown--up', 'rowdy-crowd-dropdown--end' );

			const menuRect = menu.getBoundingClientRect();
			const toggleRect = toggle.getBoundingClientRect();

			if ( menuRect.bottom > window.innerHeight && toggleRect.top > menuRect.height ) {
				dropdown.classList.add( 'rowdy-crowd-dropdown--up' );
			}

			if ( menuRect.right > window.innerWidth ) {
				dropdown.classList.add( 'rowdy-crowd-dropdown--end' );
			}
		};

		const setOpen = ( open ) => {
			toggle.setAttribute( 'aria-expanded', String( open ) );
			menu.hidden = ! open;

			if ( open ) {
				keepOnScreen();
			}
		};

		toggle.addEventListener( 'click', () => {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );

		document.addEventListener( 'click', ( event ) => {
			if ( ! dropdown.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		dropdown.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Escape' && ! menu.hidden ) {
				setOpen( false );
				toggle.focus();
			}
		} );
	} );
}() );
