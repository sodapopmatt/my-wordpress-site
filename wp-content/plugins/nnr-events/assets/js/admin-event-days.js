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
	 * Sets a time input with a 15-minute step (the browser's picker offers
	 * :00/:15/:30/:45). The step is left off for a saved time that isn't on
	 * that grid, since the browser would otherwise refuse to submit the form.
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

	function initAll() {
		document.querySelectorAll( '.nnr-days' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
