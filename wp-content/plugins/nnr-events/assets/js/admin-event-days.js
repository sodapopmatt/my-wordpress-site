/**
 * The "Dates" field shared by the classic meta box and Quick Manage: a list
 * of days (date, start time, end time) with "Add another day". One day can
 * optionally "run through" a later date (e.g. an exhibit); that option hides
 * once a second day is added, since per-day rows already cover the span.
 *
 * Markup comes from NNR_Meta_Box::render_days_field(). Every .nnr-days
 * container on the page is wired up on load; Quick Manage re-fills its one
 * via NNREventDays.set() each time the panel opens.
 */
( function () {
	function rows( container ) {
		return container.querySelectorAll( '.nnr-days__row' );
	}

	function nextIndex( container ) {
		var max = -1;
		rows( container ).forEach( function ( row ) {
			var input = row.querySelector( 'input[name*="[date]"]' );
			var match = input && input.name.match( /\[(\d+)\]/ );
			if ( match ) {
				max = Math.max( max, parseInt( match[ 1 ], 10 ) );
			}
		} );
		return max + 1;
	}

	function addRow( container, day ) {
		var i  = nextIndex( container );
		var tr = document.createElement( 'tr' );
		tr.className = 'nnr-days__row';
		tr.innerHTML =
			'<td><input type="date" name="_nnr_sessions[' + i + '][date]" /></td>' +
			'<td><input type="time" name="_nnr_sessions[' + i + '][start_time]" /></td>' +
			'<td><input type="time" name="_nnr_sessions[' + i + '][end_time]" /></td>' +
			'<td><button type="button" class="button-link nnr-days__remove"></button></td>';
		tr.querySelector( '.nnr-days__remove' ).textContent = container.dataset.removeText || 'Remove';

		day = day || {};
		tr.querySelector( 'input[name*="[date]"]' ).value = day.date || '';
		setTime( tr.querySelector( 'input[name*="[start_time]"]' ), day.start_time );
		setTime( tr.querySelector( 'input[name*="[end_time]"]' ), day.end_time );

		container.querySelector( '.nnr-days__table tbody' ).appendChild( tr );
		return tr;
	}

	/**
	 * Sets a time input with a 15-minute step (arrow keys move in 15-minute
	 * jumps; the picker below handles clicking). The step is left off for a
	 * saved time that isn't on that grid, since the browser would otherwise
	 * refuse to submit the form.
	 * Mirrors NNR_Meta_Box::time_step_attr().
	 */
	function setTime( input, value ) {
		value = value || '';
		input.value = value;
		if ( '' === value || 0 === parseInt( value.slice( 3, 5 ), 10 ) % 15 ) {
			input.step = '900';
		} else {
			input.removeAttribute( 'step' );
		}
	}

	function refresh( container ) {
		var all = rows( container );

		// Only the first day's date is required, so a half-filled extra row
		// doesn't block saving — the server just drops rows without a date.
		all.forEach( function ( row, index ) {
			row.querySelector( 'input[name*="[date]"]' ).required = 0 === index;
		} );

		var through = container.querySelector( '.nnr-days__through' );
		if ( through ) {
			through.hidden = all.length > 1;
		}
	}

	function set( container, days, runsThrough ) {
		rows( container ).forEach( function ( row ) {
			row.remove();
		} );
		( days && days.length ? days : [ {} ] ).forEach( function ( day ) {
			addRow( container, day );
		} );
		var throughInput = container.querySelector( 'input[name="_nnr_runs_through"]' );
		if ( throughInput ) {
			throughInput.value = runsThrough || '';
		}
		refresh( container );
	}

	function init( container ) {
		if ( container.dataset.nnrDaysReady ) {
			return;
		}
		container.dataset.nnrDaysReady = '1';

		container.querySelector( '.nnr-days__add' ).addEventListener( 'click', function () {
			var all  = rows( container );
			var last = all[ all.length - 1 ];
			var prev = last ? {
				date: last.querySelector( 'input[name*="[date]"]' ).value,
				start_time: last.querySelector( 'input[name*="[start_time]"]' ).value,
				end_time: last.querySelector( 'input[name*="[end_time]"]' ).value,
			} : {};

			// Pre-fill the next day with the same hours, one day later — the
			// common case for festivals/weekend runs. Easy to overwrite.
			var next = { start_time: prev.start_time, end_time: prev.end_time };
			if ( prev.date ) {
				var d = new Date( prev.date + 'T00:00:00' );
				d.setDate( d.getDate() + 1 );
				next.date = d.getFullYear() + '-' +
					String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' +
					String( d.getDate() ).padStart( 2, '0' );
			}

			addRow( container, next ).querySelector( 'input[name*="[date]"]' ).focus();
			refresh( container );
		} );

		container.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.nnr-days__remove' );
			if ( ! btn ) {
				return;
			}
			var row = btn.closest( '.nnr-days__row' );
			if ( rows( container ).length <= 1 ) {
				row.querySelectorAll( 'input' ).forEach( function ( input ) {
					input.value = '';
				} );
			} else {
				row.remove();
			}
			refresh( container );
		} );

		refresh( container );
	}

	window.NNREventDays = { set: set };

	/**
	 * Time picker popup for the day list's time inputs. Chrome's own picker
	 * lists every minute regardless of the input's step, so its button is
	 * hidden (see admin-event-days.css) and this one — same hour / minute /
	 * AM-PM columns, but minutes limited to :00/:15/:30/:45 — opens on click
	 * instead. Focus stays in the native input, so typing still works.
	 */
	( function () {
		var SELECTOR = '.nnr-days input[type="time"]';
		var MINUTES  = [ '00', '15', '30', '45' ];
		var HOURS    = [ 12, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11 ];

		var popup = null;
		var input = null;

		function cell( part, value, label ) {
			return '<button type="button" class="nnr-tp__cell" data-part="' + part + '" data-value="' + value + '">' + label + '</button>';
		}

		function build() {
			popup = document.createElement( 'div' );
			popup.className = 'nnr-tp';
			popup.hidden    = true;
			popup.innerHTML =
				'<div class="nnr-tp__cols">' +
					'<div class="nnr-tp__col">' + HOURS.map( function ( h ) {
						return cell( 'hour', h, String( h ).padStart( 2, '0' ) );
					} ).join( '' ) + '</div>' +
					'<div class="nnr-tp__col">' + MINUTES.map( function ( m ) {
						return cell( 'minute', m, m );
					} ).join( '' ) + '</div>' +
					'<div class="nnr-tp__col">' + cell( 'ampm', 'AM', 'AM' ) + cell( 'ampm', 'PM', 'PM' ) + '</div>' +
				'</div>' +
				'<button type="button" class="button-link nnr-tp__clear">Clear</button>';

			// Keep focus in the input while clicking around the popup, so
			// blur only fires when the user really leaves the field.
			popup.addEventListener( 'mousedown', function ( e ) {
				e.preventDefault();
			} );
			popup.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '.nnr-tp__clear' ) ) {
					apply( '' );
					close();
					return;
				}
				var btn = e.target.closest( '.nnr-tp__cell' );
				if ( btn ) {
					pick( btn.dataset.part, btn.dataset.value );
				}
			} );
			document.body.appendChild( popup );
		}

		/** @return {{hour: number, minute: string, ampm: string}|null} */
		function parse( value ) {
			var match = /^(\d{2}):(\d{2})/.exec( value || '' );
			if ( ! match ) {
				return null;
			}
			var h24 = parseInt( match[ 1 ], 10 );
			return { hour: ( h24 % 12 ) || 12, minute: match[ 2 ], ampm: h24 < 12 ? 'AM' : 'PM' };
		}

		function format( parts ) {
			var h24 = ( parts.hour % 12 ) + ( 'PM' === parts.ampm ? 12 : 0 );
			return String( h24 ).padStart( 2, '0' ) + ':' + parts.minute;
		}

		function apply( value ) {
			setTime( input, value );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			highlight();
		}

		function pick( part, value ) {
			// Starting from an empty input, fill the other columns with
			// 12:00 PM so a single click already gives a valid time.
			var parts = parse( input.value ) || { hour: 12, minute: '00', ampm: 'PM' };
			parts[ part ] = 'hour' === part ? parseInt( value, 10 ) : value;
			apply( format( parts ) );
		}

		function highlight() {
			var parts = parse( input.value );
			popup.querySelectorAll( '.nnr-tp__cell' ).forEach( function ( btn ) {
				var on = !! parts && String( parts[ btn.dataset.part ] ) === btn.dataset.value;
				btn.classList.toggle( 'is-selected', on );
				btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}

		function position() {
			var rect   = input.getBoundingClientRect();
			var height = popup.offsetHeight;
			popup.style.left = Math.max( 4, rect.left ) + 'px';
			// Open below unless it doesn't fit there and there's more room above.
			var spaceBelow = window.innerHeight - rect.bottom;
			popup.style.top = ( height + 4 > spaceBelow && rect.top > spaceBelow
				? Math.max( 4, rect.top - height - 2 )
				: rect.bottom + 2 ) + 'px';
		}

		function open( target ) {
			if ( ! popup ) {
				build();
			}
			input        = target;
			popup.hidden = false;
			highlight();
			position();
			var selected = popup.querySelector( '.nnr-tp__cell[data-part="hour"].is-selected' );
			var hourCol  = popup.querySelector( '.nnr-tp__col' );
			hourCol.scrollTop = selected ? selected.offsetTop - hourCol.offsetTop : 0;
		}

		function close() {
			if ( popup ) {
				popup.hidden = true;
			}
			input = null;
		}

		document.addEventListener( 'click', function ( e ) {
			var target = e.target.closest && e.target.closest( SELECTOR );
			if ( target && target !== input ) {
				open( target );
			}
		} );

		document.addEventListener( 'focusout', function ( e ) {
			if ( input && e.target === input ) {
				close();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			var target = e.target.closest && e.target.closest( SELECTOR );
			if ( ! target ) {
				return;
			}
			// Alt+Down / F4 would open Chrome's own picker — open ours instead.
			if ( ( e.altKey && ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) ) || 'F4' === e.key ) {
				e.preventDefault();
				if ( input === target ) {
					close();
				} else {
					open( target );
				}
			} else if ( input && ( 'Escape' === e.key || 'Enter' === e.key ) ) {
				// Stop here so Escape doesn't also close Quick Manage's panel.
				e.preventDefault();
				e.stopImmediatePropagation();
				close();
			}
		} );

		// A fixed-position popup would drift away from its input on scroll
		// (the page, or Quick Manage's scrolling panel) — just close it.
		window.addEventListener( 'scroll', function ( e ) {
			if ( input && ! ( popup && popup.contains( e.target ) ) ) {
				close();
			}
		}, true );
		window.addEventListener( 'resize', close );
	} )();

	function initAll() {
		document.querySelectorAll( '.nnr-days' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
