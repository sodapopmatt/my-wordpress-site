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

	// "Read more": shows the button only when the clamped excerpt actually overflows.
	function checkExcerptOverflow( root ) {
		( root || document ).querySelectorAll( '.nnr-event-card__excerpt' ).forEach( function ( excerpt ) {
			var btn = excerpt.nextElementSibling;
			if ( ! btn || ! btn.classList.contains( 'nnr-event-card__read-more' ) ) {
				return;
			}
			btn.hidden = excerpt.scrollHeight <= excerpt.clientHeight + 1;
		} );
	}

	checkExcerptOverflow();
	window.addEventListener( 'resize', function () {
		checkExcerptOverflow();
	} );
	window.addEventListener( 'load', function () {
		checkExcerptOverflow();
	} );

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
					checkExcerptOverflow( grid );
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
				checkExcerptOverflow( grid );

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

	// Image lightbox: click (or Enter/Space) an event image to view it enlarged.
	var lightbox = null;
	var lightboxImg = null;
	var lightboxOpenerEl = null;

	function buildLightbox() {
		if ( lightbox ) {
			return;
		}
		lightbox = document.createElement( 'div' );
		lightbox.className = 'nnr-lightbox';
		lightbox.hidden = true;

		lightboxImg = document.createElement( 'img' );
		lightboxImg.className = 'nnr-lightbox__img';
		lightboxImg.alt = '';

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'nnr-lightbox__close';
		closeBtn.setAttribute( 'aria-label', window.NNREventsFrontend ? window.NNREventsFrontend.closeLightboxText : 'Close' );
		closeBtn.innerHTML = '&times;';

		lightbox.appendChild( lightboxImg );
		lightbox.appendChild( closeBtn );
		document.body.appendChild( lightbox );

		closeBtn.addEventListener( 'click', closeLightbox );
		lightbox.addEventListener( 'click', function ( e ) {
			if ( e.target === lightbox ) {
				closeLightbox();
			}
		} );
	}

	function openLightbox( url, opener ) {
		buildLightbox();
		lightboxImg.src = url;
		lightbox.hidden = false;
		document.body.classList.add( 'nnr-lightbox-open' );
		lightboxOpenerEl = opener || null;
		lightbox.querySelector( '.nnr-lightbox__close' ).focus();
	}

	function closeLightbox() {
		if ( ! lightbox || lightbox.hidden ) {
			return;
		}
		lightbox.hidden = true;
		lightboxImg.src = '';
		document.body.classList.remove( 'nnr-lightbox-open' );
		if ( lightboxOpenerEl ) {
			lightboxOpenerEl.focus();
			lightboxOpenerEl = null;
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '[data-nnr-lightbox]' );
		if ( ! trigger ) {
			return;
		}
		openLightbox( trigger.dataset.nnrLightbox, trigger );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		var trigger = e.target.closest && e.target.closest( '[data-nnr-lightbox]' );
		if ( trigger && ( 'Enter' === e.key || ' ' === e.key ) ) {
			e.preventDefault();
			openLightbox( trigger.dataset.nnrLightbox, trigger );
			return;
		}
		if ( 'Escape' === e.key ) {
			closeLightbox();
			closeTextModal();
		}
	} );

	// "Read more" text modal: shows the full description without growing the card.
	var textModal = null;
	var textModalTitle = null;
	var textModalBody = null;
	var textModalOpenerEl = null;

	function buildTextModal() {
		if ( textModal ) {
			return;
		}
		textModal = document.createElement( 'div' );
		textModal.className = 'nnr-text-modal';
		textModal.hidden = true;

		var panel = document.createElement( 'div' );
		panel.className = 'nnr-text-modal__panel';

		var closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'nnr-text-modal__close';
		closeBtn.setAttribute( 'aria-label', window.NNREventsFrontend ? window.NNREventsFrontend.closeLightboxText : 'Close' );
		closeBtn.innerHTML = '&times;';

		textModalTitle = document.createElement( 'h3' );
		textModalTitle.className = 'nnr-text-modal__title';

		textModalBody = document.createElement( 'div' );
		textModalBody.className = 'nnr-text-modal__body';

		panel.appendChild( closeBtn );
		panel.appendChild( textModalTitle );
		panel.appendChild( textModalBody );
		textModal.appendChild( panel );
		document.body.appendChild( textModal );

		closeBtn.addEventListener( 'click', closeTextModal );
		textModal.addEventListener( 'click', function ( e ) {
			if ( e.target === textModal ) {
				closeTextModal();
			}
		} );
	}

	function openTextModal( title, html, opener ) {
		buildTextModal();
		textModalTitle.textContent = title;
		textModalBody.innerHTML = html;
		textModal.hidden = false;
		document.body.classList.add( 'nnr-lightbox-open' );
		textModalOpenerEl = opener || null;
		textModal.querySelector( '.nnr-text-modal__close' ).focus();
	}

	function closeTextModal() {
		if ( ! textModal || textModal.hidden ) {
			return;
		}
		textModal.hidden = true;
		document.body.classList.remove( 'nnr-lightbox-open' );
		if ( textModalOpenerEl ) {
			textModalOpenerEl.focus();
			textModalOpenerEl = null;
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.nnr-event-card__read-more' );
		if ( ! btn ) {
			return;
		}
		var excerpt = btn.previousElementSibling;
		if ( ! excerpt ) {
			return;
		}
		openTextModal( btn.dataset.nnrTitle || '', excerpt.innerHTML, btn );
	} );
} )();
