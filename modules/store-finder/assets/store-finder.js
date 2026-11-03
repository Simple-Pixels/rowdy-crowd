/**
 * Store finder: search, state chips, "Stores near me" and Google Map.
 *
 * The server renders the list (templates/store-finder.php) and each item carries its own
 * data-* attributes, so the list works without this script. The script adds the search box,
 * the state filter, distance sorting and the map. Google calls rowdyCrowdStoreFinderInit
 * once the Maps API has loaded.
 *
 * "Stores near me" uses the browser's location. It stays in the browser: nothing is sent
 * to the server or stored.
 */
( function () {
	'use strict';

	const DEFAULT_CENTER = { lat: -25.2744, lng: 133.7751 };
	const DEFAULT_ZOOM = 4;
	const MAX_FIT_ZOOM = 15;
	const SELECT_ZOOM = 14;
	const FIT_PADDING = 48;
	const NEAR_ME_COUNT = 3; // How many of the nearest stores the map frames after "Stores near me".
	const EARTH_RADIUS_KM = 6371;
	const KM_PER_MILE = 1.609344;
	const GEOLOCATION_OPTIONS = { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 };
	const TOAST_RESULT_MS = 6000; // How long "Nearest store: ..." stays on screen.
	const TOAST_ERROR_MS = 8000; // Problems stay a little longer.
	const CLASS = 'rowdy-crowd-store-finder__';

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

	/**
	 * Straight-line distance between two points (haversine).
	 *
	 * @param {{lat: number, lng: number}} a First point.
	 * @param {{lat: number, lng: number}} b Second point.
	 * @return {number} Kilometres.
	 */
	function distanceKm( a, b ) {
		const toRadians = ( degrees ) => ( degrees * Math.PI ) / 180;
		const dLat = toRadians( b.lat - a.lat );
		const dLng = toRadians( b.lng - a.lng );
		const h = Math.sin( dLat / 2 ) ** 2 + Math.cos( toRadians( a.lat ) ) * Math.cos( toRadians( b.lat ) ) * Math.sin( dLng / 2 ) ** 2;

		return 2 * EARTH_RADIUS_KM * Math.asin( Math.sqrt( h ) );
	}

	class StoreFinder {
		constructor( root ) {
			this.root = root;
			this.list = root.querySelector( '.' + CLASS + 'list' );
			this.mapElement = root.querySelector( '.' + CLASS + 'map' );
			this.searchInput = root.querySelector( '.' + CLASS + 'search' );
			this.stateSelect = root.querySelector( '.' + CLASS + 'state' );
			this.chips = Array.from( root.querySelectorAll( '.' + CLASS + 'chip' ) );
			this.nearMeButton = root.querySelector( '.' + CLASS + 'near-me' );
			this.countElement = root.querySelector( '.' + CLASS + 'count' );
			this.messageElement = root.querySelector( '.' + CLASS + 'message' );
			this.messageTimer = null;

			// A fixed toast must be a child of <body>: inside an Elementor section with a transform,
			// "fixed" is measured from that section instead of the screen.
			document.body.appendChild( this.messageElement );
			this.emptyElement = root.querySelector( '.' + CLASS + 'empty' );
			this.clearButton = root.querySelector( '.' + CLASS + 'clear' );

			this.items = Array.from( root.querySelectorAll( '.' + CLASS + 'item' ) ).map( ( element ) => ( {
				element,
				name: element.querySelector( '.' + CLASS + 'name' ).textContent.trim(),
				lat: parseFloat( element.dataset.lat ),
				lng: parseFloat( element.dataset.lng ),
				state: element.dataset.state,
				text: element.dataset.search,
				visible: true,
				marker: null,
				position: null,
				distance: null,
			} ) );
			this.originalOrder = this.items.slice();

			this.state = '';
			this.origin = null;
			this.map = null;
			this.infoWindow = null;
			this.userMarker = null;
			this.AdvancedMarkerElement = null;

			this.searchInput.addEventListener( 'input', () => this.applyFilters() );
			this.chips.forEach( ( chip ) => chip.addEventListener( 'click', () => this.chooseState( chip.dataset.state ) ) );

			if ( this.stateSelect ) {
				this.stateSelect.addEventListener( 'change', () => this.chooseState( this.stateSelect.value ) );
			}

			this.list.addEventListener( 'click', ( event ) => this.onListClick( event ) );
			this.nearMeButton.addEventListener( 'click', () => this.toggleNearMe() );
			this.clearButton.addEventListener( 'click', () => this.clearFilters() );

			// Browsers can restore the search box after going back.
			this.applyFilters();
		}

		/* Map ---------------------------------------------------------------- */

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

				this.AdvancedMarkerElement = AdvancedMarkerElement;
				this.map = new GoogleMap( this.mapElement, {
					center: DEFAULT_CENTER,
					zoom: DEFAULT_ZOOM,
					mapId: this.root.dataset.mapId,
					mapTypeControl: false,
					streetViewControl: false,
					fullscreenControl: false,
					gestureHandling: 'cooperative',
				} );
				// The header row is removed because it only holds Google's close button, and leaves a
				// gap above the content. The popup draws its own close button instead.
				this.infoWindow = new InfoWindow( { maxWidth: 320, headerDisabled: true } );

				this.placeMarkers( AdvancedMarkerElement, PinElement );
				this.root.classList.add( 'is-map-ready' );

				if ( this.origin ) {
					this.showUserMarker();
				}

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
						title: item.name,
						// Newer Maps versions make PinElement the element itself.
						content: pin.element || pin,
						gmpClickable: true,
					} );

					item.marker.addEventListener( 'gmp-click', () => this.select( item, false ) );
				} );
			} );
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

			// Nothing to select on any more.
			this.root.querySelectorAll( '.' + CLASS + 'select' ).forEach( ( button ) => {
				button.disabled = true;
			} );
		}

		/**
		 * Frames the stores that are showing. After "Stores near me" it frames the visitor and the
		 * nearest few stores instead, because a map of the whole country isn't useful then.
		 */
		fitToVisible() {
			if ( ! this.map ) {
				return;
			}

			const visible = this.items.filter( ( item ) => item.visible && item.position );

			if ( visible.length === 0 ) {
				this.map.setCenter( DEFAULT_CENTER );
				this.map.setZoom( DEFAULT_ZOOM );
				return;
			}

			const bounds = new google.maps.LatLngBounds();
			// Items are sorted nearest-first while the visitor's location is known.
			const framed = this.origin ? visible.slice( 0, NEAR_ME_COUNT ) : visible;

			framed.forEach( ( item ) => bounds.extend( item.position ) );

			if ( this.origin ) {
				bounds.extend( this.origin );
			}

			this.map.fitBounds( bounds, FIT_PADDING );

			// A single store (or a tight cluster) shouldn't zoom in to street level.
			google.maps.event.addListenerOnce( this.map, 'idle', () => {
				if ( this.map.getZoom() > MAX_FIT_ZOOM ) {
					this.map.setZoom( MAX_FIT_ZOOM );
				}
			} );
		}

		/* Filtering ---------------------------------------------------------- */

		chooseState( state ) {
			this.setState( state );
			this.applyFilters();
		}

		/**
		 * Records the chosen state and keeps the chips (or the dropdown) in step.
		 *
		 * @param {string} state State code, or '' for every state.
		 */
		setState( state ) {
			this.state = state;
			this.chips.forEach( ( chip ) => chip.setAttribute( 'aria-pressed', String( chip.dataset.state === state ) ) );

			if ( this.stateSelect ) {
				this.stateSelect.value = state;
			}
		}

		clearFilters() {
			this.searchInput.value = '';
			this.setState( '' );
			this.applyFilters();
			this.searchInput.focus();
		}

		/**
		 * Shows only the stores that match the search and the state, in the list and on the map.
		 */
		applyFilters() {
			const terms = this.searchInput.value.toLowerCase().split( /\s+/ ).filter( Boolean );
			let visibleCount = 0;

			this.closeInfo();

			this.items.forEach( ( item ) => {
				item.visible = ( this.state === '' || item.state === this.state ) && terms.every( ( term ) => item.text.includes( term ) );
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

			// The empty message takes the list's place, so the map stays where it is.
			this.list.hidden = visibleCount === 0;
			this.emptyElement.hidden = visibleCount > 0;

			this.fitToVisible();
		}

		/* Selecting a store -------------------------------------------------- */

		onListClick( event ) {
			// Phone, website and directions links keep doing their own thing.
			if ( ! this.map || event.target.closest( 'a' ) ) {
				return;
			}

			const element = event.target.closest( '.' + CLASS + 'item' );
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

		closeInfo() {
			this.items.forEach( ( item ) => item.element.classList.remove( 'is-active' ) );

			if ( this.infoWindow ) {
				this.infoWindow.close();
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
			wrapper.className = CLASS + 'info';

			const close = document.createElement( 'button' );
			close.type = 'button';
			close.className = CLASS + 'info-close';
			close.setAttribute( 'aria-label', this.root.dataset.labelClose );
			close.textContent = '×';
			close.addEventListener( 'click', () => this.closeInfo() );
			wrapper.appendChild( close );

			// The name in the list is a button, so the popup gets plain text.
			const name = document.createElement( 'p' );
			name.className = CLASS + 'name';
			name.textContent = item.name;
			wrapper.appendChild( name );

			[ 'address', 'phone', 'website', 'actions' ].forEach( ( part ) => {
				const source = item.element.querySelector( '.' + CLASS + part );

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

		/* Stores near me ----------------------------------------------------- */

		toggleNearMe() {
			if ( this.origin ) {
				this.clearNearMe();
				return;
			}

			if ( ! navigator.geolocation ) {
				this.showMessage( this.root.dataset.msgUnavailable, true, TOAST_ERROR_MS );
				return;
			}

			this.nearMeButton.setAttribute( 'aria-busy', 'true' );
			// No timeout: it stays until the lookup succeeds or fails.
			this.showMessage( this.root.dataset.msgLocating, false, 0 );

			navigator.geolocation.getCurrentPosition(
				( position ) => this.onLocated( position ),
				( error ) => this.onLocateError( error ),
				GEOLOCATION_OPTIONS
			);
		}

		onLocated( position ) {
			this.nearMeButton.removeAttribute( 'aria-busy' );
			this.origin = { lat: position.coords.latitude, lng: position.coords.longitude };

			this.items.forEach( ( item ) => {
				item.distance = distanceKm( this.origin, item );
			} );

			// Nearest first. Every store is shown, so the search and the state filter are cleared.
			this.items.sort( ( a, b ) => a.distance - b.distance );
			this.items.forEach( ( item ) => {
				this.list.appendChild( item.element );
				this.setDistanceBadge( item );
			} );

			this.searchInput.value = '';
			this.setState( '' );
			this.nearMeButton.setAttribute( 'aria-pressed', 'true' );
			this.showUserMarker();
			this.showMessage( this.root.dataset.msgNearest.replace( '%s', this.formatDistance( this.items[ 0 ].distance ) ), false, TOAST_RESULT_MS );
			this.applyFilters();
			this.list.scrollTop = 0;
		}

		onLocateError( error ) {
			this.nearMeButton.removeAttribute( 'aria-busy' );
			// Code 1 is "permission denied". Anything else is a timeout or no signal.
			this.showMessage( error && error.code === 1 ? this.root.dataset.msgDenied : this.root.dataset.msgFailed, true, TOAST_ERROR_MS );
		}

		/**
		 * Goes back to the normal order, without distances.
		 */
		clearNearMe() {
			this.origin = null;
			this.items = this.originalOrder.slice();

			this.items.forEach( ( item ) => {
				this.list.appendChild( item.element );
				item.distance = null;
				this.setDistanceBadge( item );
			} );

			if ( this.userMarker ) {
				this.userMarker.map = null;
				this.userMarker = null;
			}

			this.nearMeButton.setAttribute( 'aria-pressed', 'false' );
			this.hideMessage();
			this.applyFilters();
			this.list.scrollTop = 0;
		}

		/**
		 * Marks the visitor's position on the map with a blue dot.
		 */
		showUserMarker() {
			if ( ! this.map || ! this.AdvancedMarkerElement || ! this.origin || this.userMarker ) {
				return;
			}

			const dot = document.createElement( 'div' );
			dot.className = CLASS + 'you';

			this.userMarker = new this.AdvancedMarkerElement( {
				map: this.map,
				position: this.origin,
				title: this.root.dataset.labelYou,
				content: dot,
			} );
		}

		/**
		 * Adds, updates or removes the distance next to a store's name.
		 *
		 * @param {Object} item The store.
		 */
		setDistanceBadge( item ) {
			let badge = item.element.querySelector( '.' + CLASS + 'distance' );

			if ( item.distance === null ) {
				if ( badge ) {
					badge.remove();
				}
				return;
			}

			if ( ! badge ) {
				badge = document.createElement( 'span' );
				badge.className = CLASS + 'distance';
				item.element.querySelector( '.' + CLASS + 'name' ).appendChild( badge );
			}

			badge.textContent = this.formatDistance( item.distance );
		}

		/**
		 * @param {number} km Distance in kilometres.
		 * @return {string} e.g. "4.2 km", or "130 mi" on the US site.
		 */
		formatDistance( km ) {
			const miles = this.root.dataset.units === 'mi';
			const value = miles ? km / KM_PER_MILE : km;
			// One decimal under 10, whole numbers above. 9.96 is 10, not "10.0".
			const text = value < 9.95 ? value.toFixed( 1 ) : Math.round( value ).toLocaleString();

			return text + ( miles ? ' mi' : ' km' );
		}

		/**
		 * Shows the toast at the bottom of the screen.
		 *
		 * @param {string}  text      Message for the visitor.
		 * @param {boolean} isError   True for problems, so they look different.
		 * @param {number}  hideAfter Milliseconds before it goes away. 0 keeps it until replaced or hidden.
		 */
		showMessage( text, isError, hideAfter ) {
			window.clearTimeout( this.messageTimer );

			this.messageElement.textContent = text;
			this.messageElement.classList.toggle( 'is-error', isError );
			this.messageElement.hidden = false;

			if ( hideAfter > 0 ) {
				this.messageTimer = window.setTimeout( () => this.hideMessage(), hideAfter );
			}
		}

		hideMessage() {
			window.clearTimeout( this.messageTimer );

			this.messageElement.hidden = true;
			this.messageElement.textContent = '';
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
