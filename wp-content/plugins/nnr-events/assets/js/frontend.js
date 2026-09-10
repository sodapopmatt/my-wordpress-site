( function () {
	// Scroll reveal: fade/slide event cards in as they enter the viewport.
	var revealObserver = null;
	if ( 'IntersectionObserver' in window && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		revealObserver = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						entry.target.classList.add( 'is-visible' );
						revealObserver.unobserve( entry.target );
					}
				} );
			},
			{ rootMargin: '0px 0px -10% 0px', threshold: 0.1 }
		);
	}

	function observeReveal( root ) {
		if ( ! revealObserver ) {
			return;
		}
		( root || document ).querySelectorAll( '.nnr-event-card' ).forEach( function ( card ) {
			card.classList.add( 'nnr-events-reveal' );
			revealObserver.observe( card );
		} );
	}

	observeReveal();

	function fetchEvents( params ) {
		var body = new URLSearchParams();
		body.set( 'action', 'nnr_load_more_events' );
		Object.keys( params ).forEach( function ( key ) {
			body.set( key, params[ key ] || '' );
		} );

		return fetch( window.NNREventsFrontend.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( ! json.success ) {
					throw new Error( 'nnr_load_more_events failed' );
				}
				return json;
			} );
	}

	// "Load More": appends the next page of results.
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.nnr-events__load-more' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();

		var wrap = btn.closest( '.nnr-events-wrap' );
		var grid = wrap ? wrap.querySelector( '.nnr-events' ) : null;
		var loadMoreWrap = wrap ? wrap.querySelector( '.nnr-events__load-more-wrap' ) : null;
		if ( ! grid || ! window.NNREventsFrontend ) {
			return;
		}

		var originalText = btn.textContent;
		btn.disabled = true;
		btn.textContent = window.NNREventsFrontend.loadingText;

		fetchEvents( {
			nonce: btn.dataset.nonce,
			offset: btn.dataset.offset,
			limit: btn.dataset.limit,
			category: btn.dataset.category,
			layout: btn.dataset.layout,
			image: btn.dataset.image,
			color: btn.dataset.color,
		} )
			.then( function ( json ) {
				if ( json.data.html ) {
					grid.insertAdjacentHTML( 'beforeend', json.data.html );
					observeReveal( grid );
				}

				if ( json.data.has_more ) {
					btn.dataset.offset = String(
						parseInt( btn.dataset.offset, 10 ) + parseInt( btn.dataset.limit, 10 )
					);
					btn.disabled = false;
					btn.textContent = originalText;
				} else {
					btn.hidden = true;
					if ( loadMoreWrap ) {
						loadMoreWrap.hidden = true;
					}
				}
			} )
			.catch( function () {
				btn.disabled = false;
				btn.textContent = originalText;
			} );
	} );

	// Category filter pills: replaces the whole grid with the filtered set.
	document.addEventListener( 'click', function ( e ) {
		var pill = e.target.closest( '.nnr-events__filter-pill' );
		if ( ! pill || pill.classList.contains( 'is-active' ) ) {
			return;
		}
		e.preventDefault();

		var filtersBar = pill.closest( '.nnr-events__filters' );
		var wrap = pill.closest( '.nnr-events-wrap' );
		var grid = wrap ? wrap.querySelector( '.nnr-events' ) : null;
		var loadMoreBtn = wrap ? wrap.querySelector( '.nnr-events__load-more' ) : null;
		var loadMoreWrap = wrap ? wrap.querySelector( '.nnr-events__load-more-wrap' ) : null;
		if ( ! grid || ! loadMoreBtn || ! window.NNREventsFrontend ) {
			return;
		}

		filtersBar.querySelectorAll( '.nnr-events__filter-pill' ).forEach( function ( p ) {
			p.classList.remove( 'is-active' );
			p.setAttribute( 'aria-pressed', 'false' );
		} );
		pill.classList.add( 'is-active' );
		pill.setAttribute( 'aria-pressed', 'true' );

		var category = pill.dataset.category || '';
		grid.classList.add( 'is-loading' );

		fetchEvents( {
			nonce: loadMoreBtn.dataset.nonce,
			offset: 0,
			limit: loadMoreBtn.dataset.limit,
			category: category,
			layout: loadMoreBtn.dataset.layout,
			image: loadMoreBtn.dataset.image,
			color: loadMoreBtn.dataset.color,
		} )
			.then( function ( json ) {
				grid.classList.remove( 'is-loading' );
				grid.innerHTML = json.data.html || '';
				observeReveal( grid );

				loadMoreBtn.dataset.category = category;
				loadMoreBtn.dataset.offset = loadMoreBtn.dataset.limit;

				loadMoreBtn.hidden = ! json.data.has_more;
				if ( loadMoreWrap ) {
					loadMoreWrap.hidden = ! json.data.has_more;
				}
			} )
			.catch( function () {
				grid.classList.remove( 'is-loading' );
			} );
	} );

	// "Add to Calendar" dropdown: toggle open/closed, close on outside click.
	function closeAllCalMenus( except ) {
		document.querySelectorAll( '.nnr-event-card__cal-menu' ).forEach( function ( menu ) {
			if ( menu === except ) {
				return;
			}
			menu.hidden = true;
			var btn = menu.previousElementSibling;
			if ( btn ) {
				btn.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.nnr-event-card__ics' );

		if ( btn ) {
			e.preventDefault();
			var menu = btn.nextElementSibling;
			if ( ! menu ) {
				return;
			}
			var willOpen = menu.hidden;
			closeAllCalMenus( willOpen ? menu : null );
			menu.hidden = ! willOpen;
			btn.setAttribute( 'aria-expanded', willOpen ? 'true' : 'false' );
			return;
		}

		if ( ! e.target.closest( '.nnr-event-card__cal-menu' ) ) {
			closeAllCalMenus();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closeAllCalMenus();
		}
	} );
} )();
