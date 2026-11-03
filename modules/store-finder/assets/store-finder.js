/**
 * Store finder: search, state filter and Google Map.
 *
 * The server renders the list (templates/store-finder.php) and each item carries its own
 * data-* attributes, so the list works without this script. The script adds the search box,
 * the state filter and the map. Google calls rowdyCrowdStoreFinderInit once the Maps API
 * has loaded.
 */
( function () {
	'use strict';

	const DEFAULT_CENTER = { lat: -25.2744, lng: 133.7751 };
	const DEFAULT_ZOOM = 4;
	const MAX_FIT_ZOOM = 15;
	const SELECT_ZOOM = 14;
	const FIT_PADDING = 48;

	const finders = [];

	/**
	 * Spreads markers that share exact coordinates into a small circle so each can be clicked.
	 *
	 * @param {number} lat   Shared latitude.
	 * @param {number} lng   Shared longitude.
	 * @param {number} count Number of markers at this spot.
	 * @return {Array<{lat: number, lng: number}>} One position per marker.
	 */
	function fanOut( lat, lng, count ) {
		if ( count === 1 ) {
			return [ { lat, lng } ];
		}

		const radiusMeters = Math.min( 25, 8 + ( count - 2 ) * 2.5 );
		const metersPerDegree = 111111;
		const lngScale = metersPerDegree * ( Math.cos( ( lat * Math.PI ) / 180 ) || 1 );

		return Array.from( { length: count }, ( _, index ) => {
			const angle = ( 2 * Math.PI * index ) / count;

			return {
				lat: lat + ( Math.sin( angle ) * radiusMeters ) / metersPerDegree,
				lng: lng + ( Math.cos( angle ) * radiusMeters ) / lngScale,
			};
		} );
	}

	class StoreFinder {
		constructor( root ) {
			this.root = root;
			this.list = root.querySelector( '.rowdy-crowd-store-finder__list' );
			this.mapElement = root.querySelector( '.rowdy-crowd-store-finder__map' );
			this.searchInput = root.querySelector( '.rowdy-crowd-store-finder__search' );
			this.stateSelect = root.querySelector( '.rowdy-crowd-store-finder__state' );
			this.countElement = root.querySelector( '.rowdy-crowd-store-finder__count' );
			this.emptyElement = root.querySelector( '.rowdy-crowd-store-finder__empty' );

			this.items = Array.from( root.querySelectorAll( '.rowdy-crowd-store-finder__item' ) ).map( ( element ) => ( {
				element,
				lat: parseFloat( element.dataset.lat ),
				lng: parseFloat( element.dataset.lng ),
				state: element.dataset.state,
				text: element.dataset.search,
				visible: true,
				marker: null,
				position: null,
			} ) );

			this.map = null;
			this.infoWindow = null;

			this.searchInput.addEventListener( 'input', () => this.applyFilters() );

			if ( this.stateSelect ) {
				this.stateSelect.addEventListener( 'change', () => this.applyFilters() );
			}

			this.list.addEventListener( 'click', ( event ) => this.onListClick( event ) );

			// Browsers can restore the search box after going back.
			this.applyFilters();
		}

		/**
		 * Builds the map and its markers. Called once the Maps API has loaded.
		 */
		async initMap() {
			if ( this.map || ! this.mapElement ) {
				return;
			}

			try {
				const { Map: GoogleMap, InfoWindow } = await google.maps.importLibrary( 'maps' );
				const { AdvancedMarkerElement, PinElement } = await google.maps.importLibrary( 'marker' );

				this.map = new GoogleMap( this.mapElement, {
					center: DEFAULT_CENTER,
					zoom: DEFAULT_ZOOM,
					mapId: this.root.dataset.mapId,
					mapTypeControl: false,
					streetViewControl: false,
					fullscreenControl: false,
					gestureHandling: 'cooperative',
				} );
				this.infoWindow = new InfoWindow( { maxWidth: 320 } );

				this.placeMarkers( AdvancedMarkerElement, PinElement );
				this.root.classList.add( 'is-map-ready' );
				this.applyFilters();
			} catch ( error ) {
				// eslint-disable-next-line no-console
				console.error( 'Store finder map failed to load:', error );
				this.disableMap();
			}
		}

		placeMarkers( AdvancedMarkerElement, PinElement ) {
			const groups = new Map();

			this.items.forEach( ( item ) => {
				const key = item.lat.toFixed( 6 ) + ',' + item.lng.toFixed( 6 );

				if ( ! groups.has( key ) ) {
					groups.set( key, [] );
				}

				groups.get( key ).push( item );
			} );

			groups.forEach( ( group ) => {
				const positions = fanOut( group[ 0 ].lat, group[ 0 ].lng, group.length );

				group.forEach( ( item, index ) => {
					const pin = new PinElement( {
						background: this.root.dataset.pinColor,
						borderColor: '#ffffff',
						glyphColor: '#ffffff',
					} );

					item.position = positions[ index ];
					item.marker = new AdvancedMarkerElement( {
						map: this.map,
						position: item.position,
						title: item.element.querySelector( '.rowdy-crowd-store-finder__name' ).textContent,
						// Newer Maps versions make PinElement the element itself.
						content: pin.element || pin,
						gmpClickable: true,
					} );

					item.marker.addEventListener( 'gmp-click', () => this.select( item, false ) );
				} );
			} );
		}

		/**
		 * Shows only the stores that match the search and the state, in the list and on the map.
		 */
		applyFilters() {
			const terms = this.searchInput.value.toLowerCase().split( /\s+/ ).filter( Boolean );
			const state = this.stateSelect ? this.stateSelect.value : '';
			let visibleCount = 0;

			this.items.forEach( ( item ) => {
				item.visible = ( state === '' || item.state === state ) && terms.every( ( term ) => item.text.includes( term ) );
				item.element.hidden = ! item.visible;

				if ( item.marker ) {
					item.marker.map = item.visible ? this.map : null;
				}

				if ( item.visible ) {
					visibleCount++;
				}
			} );

			this.countElement.textContent = visibleCount === 1
				? this.root.dataset.countOne
				: this.root.dataset.countMany.replace( '%d', visibleCount );
			this.emptyElement.hidden = visibleCount > 0;

			if ( this.infoWindow ) {
				this.infoWindow.close();
			}

			this.fitToVisible();
		}

		fitToVisible() {
			if ( ! this.map ) {
				return;
			}

			const bounds = new google.maps.LatLngBounds();
			let any = false;

			this.items.forEach( ( item ) => {
				if ( item.visible && item.position ) {
					bounds.extend( item.position );
					any = true;
				}
			} );

			if ( ! any ) {
				return;
			}

			this.map.fitBounds( bounds, FIT_PADDING );

			// A single store (or a tight cluster) shouldn't zoom in to street level.
			google.maps.event.addListenerOnce( this.map, 'idle', () => {
				if ( this.map.getZoom() > MAX_FIT_ZOOM ) {
					this.map.setZoom( MAX_FIT_ZOOM );
				}
			} );
		}

		onListClick( event ) {
			const button = event.target.closest( '.rowdy-crowd-store-finder__locate' );

			if ( ! button ) {
				return;
			}

			const element = button.closest( '.rowdy-crowd-store-finder__item' );
			const item = this.items.find( ( candidate ) => candidate.element === element );

			if ( item ) {
				this.select( item, true );
				// On phones the map sits above the list, so bring it into view.
				this.mapElement.scrollIntoView( { behavior: this.scrollBehavior(), block: 'nearest' } );
			}
		}

		/**
		 * Highlights a store, opens its info window and, from the list, moves the map to it.
		 *
		 * @param {Object}  item    The store.
		 * @param {boolean} moveMap True when chosen from the list.
		 */
		select( item, moveMap ) {
			this.items.forEach( ( other ) => other.element.classList.toggle( 'is-active', other === item ) );

			if ( ! this.map || ! item.marker ) {
				return;
			}

			this.infoWindow.setContent( this.buildInfoContent( item ) );
			this.infoWindow.open( { map: this.map, anchor: item.marker } );

			if ( moveMap ) {
				this.map.panTo( item.position );
				this.map.setZoom( Math.max( this.map.getZoom() || 0, SELECT_ZOOM ) );
			} else {
				this.revealInList( item );
			}
		}

		/**
		 * Info window content, copied from the list item so the two never disagree.
		 *
		 * @param {Object} item The store.
		 * @return {HTMLElement} Content for the info window.
		 */
		buildInfoContent( item ) {
			const wrapper = document.createElement( 'div' );
			wrapper.className = 'rowdy-crowd-store-finder__info';

			[ 'name', 'address', 'phone', 'website', 'directions' ].forEach( ( part ) => {
				const source = item.element.querySelector( '.rowdy-crowd-store-finder__' + part );

				if ( source ) {
					wrapper.appendChild( source.cloneNode( true ) );
				}
			} );

			return wrapper;
		}

		/**
		 * Scrolls the list (not the page) so a store chosen on the map is visible.
		 *
		 * @param {Object} item The store.
		 */
		revealInList( item ) {
			const { element } = item;
			const outOfView = element.offsetTop < this.list.scrollTop ||
				element.offsetTop + element.offsetHeight > this.list.scrollTop + this.list.clientHeight;

			if ( outOfView ) {
				this.list.scrollTo( { top: Math.max( 0, element.offsetTop - 8 ), behavior: this.scrollBehavior() } );
			}
		}

		scrollBehavior() {
			return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth';
		}

		/**
		 * Falls back to the list alone if Google Maps can't load (e.g. a bad API key).
		 */
		disableMap() {
			this.root.classList.remove( 'is-map-ready' );
			this.root.classList.add( 'has-map-error' );

			if ( this.mapElement ) {
				this.mapElement.hidden = true;
			}
		}
	}

	document.querySelectorAll( '[data-rowdy-crowd-store-finder]' ).forEach( ( root ) => {
		finders.push( new StoreFinder( root ) );
	} );

	window.rowdyCrowdStoreFinderInit = () => {
		finders.forEach( ( finder ) => finder.initMap() );
	};

	// Google calls this when the API key is rejected.
	window.gm_authFailure = () => {
		finders.forEach( ( finder ) => finder.disableMap() );
	};
}() );
