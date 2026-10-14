( function () {
	if ( ! window.NNRManageEvents ) {
		return;
	}

	var EDITOR_ID = 'nnr_qm_description_editor';

	var panel        = document.getElementById( 'nnr-event-panel' );
	var overlay      = document.getElementById( 'nnr-event-panel-overlay' );
	var form         = document.getElementById( 'nnr-event-form' );
	var panelTitle   = document.getElementById( 'nnr-event-panel-title' );
	var tbody        = document.getElementById( 'nnr-events-tbody' );
	var spinner      = document.getElementById( 'nnr-event-spinner' );
	var submitBtn    = form ? form.querySelector( 'button[type="submit"]' ) : null;

	if ( ! panel || ! form || ! tbody ) {
		return;
	}

	/**
	 * The editor mounts once at page load (inside the initially-hidden
	 * panel) and is left alone from then on — tested in a real browser,
	 * that initial mount already comes up fully formed (toolbar included)
	 * even while its container is hidden. Tearing it down and rebuilding it
	 * on every open (an earlier version of this code did that, modeled on
	 * the classic meta box's TinyMCE-detach workaround) turned out to be
	 * the actual bug: wp.editor.initialize() silently failed to rebuild it,
	 * leaving a bare textarea with no toolbar. Swapping content into the
	 * still-mounted instance via setContent() avoids that entirely.
	 */
	function setEditorContent( content ) {
		var textarea = document.getElementById( EDITOR_ID );
		if ( textarea ) {
			textarea.value = content || '';
		}
		if ( 'undefined' === typeof tinymce ) {
			return;
		}
		var editor = tinymce.get( EDITOR_ID );
		if ( editor ) {
			editor.setContent( content || '' );
			return;
		}
		// Defensive fallback only: rebuild it if it's ever missing.
		if ( window.wp && wp.editor ) {
			var settings = {};
			if ( window.tinyMCEPreInit ) {
				if ( tinyMCEPreInit.mceInit && tinyMCEPreInit.mceInit[ EDITOR_ID ] ) {
					settings.tinymce = tinyMCEPreInit.mceInit[ EDITOR_ID ];
				}
				if ( tinyMCEPreInit.qtInit && tinyMCEPreInit.qtInit[ EDITOR_ID ] ) {
					settings.quicktags = tinyMCEPreInit.qtInit[ EDITOR_ID ];
				}
			}
			wp.editor.initialize( EDITOR_ID, settings );
		}
	}

	var daysContainer = document.querySelector( '#nnr_qm_days .nnr-days' );

	function setChecked( name, value ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		if ( el ) {
			el.checked = '1' === value;
		}
	}

	function setValue( name, value ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		if ( el ) {
			el.value = value || '';
		}
	}

	function setCategories( ids ) {
		ids = ids || [];
		form.querySelectorAll( 'input[name="nnr_categories[]"]' ).forEach( function ( cb ) {
			cb.checked = ids.indexOf( parseInt( cb.value, 10 ) ) !== -1;
		} );
	}

	function openPanel( data ) {
		panel.hidden   = false;
		overlay.hidden = false;
		panel.setAttribute( 'aria-hidden', 'false' );

		form.reset();

		var isEdit = !! ( data && data.id );

		panelTitle.textContent = isEdit ? window.NNRManageEvents.editTitle : window.NNRManageEvents.addTitle;
		document.getElementById( 'nnr_qm_post_id' ).value = isEdit ? data.id : 0;

		setValue( 'post_title', data ? data.title : '' );
		if ( daysContainer && window.NNREventDays ) {
			window.NNREventDays.set( daysContainer, data ? data._nnr_days : [], data ? data._nnr_runs_through : '' );
		}
		setValue( '_nnr_image_url', data ? data._nnr_image_url : '' );
		setValue( '_nnr_venue', data ? data._nnr_venue : '' );
		setValue( '_nnr_address', data ? data._nnr_address : '' );
		setValue( '_nnr_price', data ? data._nnr_price : '' );
		setValue( '_nnr_ticket_url', data ? data._nnr_ticket_url : '' );
		// Matches the classic meta box: pre-fill "Website" when there's no
		// button text yet, rather than leaving it blank.
		setValue( '_nnr_button_text', ( data && data._nnr_button_text ) ? data._nnr_button_text : 'Website' );

		setChecked( '_nnr_recurring_weekly', data ? data._nnr_recurring_weekly : '0' );
		setCategories( data ? data.categories : [] );

		var statusRadio = form.querySelector( '[name="post_status"][value="' + ( data && 'publish' === data.post_status ? 'publish' : 'draft' ) + '"]' );
		if ( statusRadio ) {
			statusRadio.checked = true;
		}

		setEditorContent( data ? data._nnr_description : '' );

		document.getElementById( 'nnr_qm_title' ).focus();
	}

	function closePanel() {
		panel.hidden   = true;
		overlay.hidden = true;
		panel.setAttribute( 'aria-hidden', 'true' );
	}

	var addBtn = document.getElementById( 'nnr-add-event' );
	if ( addBtn ) {
		addBtn.addEventListener( 'click', function () {
			openPanel( null );
		} );
	}

	document.getElementById( 'nnr-event-panel-close' ).addEventListener( 'click', closePanel );
	document.getElementById( 'nnr-event-cancel' ).addEventListener( 'click', closePanel );
	overlay.addEventListener( 'click', closePanel );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! panel.hidden ) {
			closePanel();
		}
	} );

	tbody.addEventListener( 'click', function ( e ) {
		var editLink = e.target.closest( '.nnr-edit-event' );
		if ( editLink ) {
			e.preventDefault();
			var id   = editLink.dataset.id;
			var data = window.NNRManageEvents.events[ id ];
			if ( data ) {
				openPanel( data );
			}
			return;
		}

		var trashLink = e.target.closest( '.nnr-trash-event' );
		var deleteForeverLink = e.target.closest( '.nnr-delete-forever-event' );
		var restoreLink = e.target.closest( '.nnr-restore-event' );

		if ( trashLink ) {
			e.preventDefault();
			if ( ! window.confirm( window.NNRManageEvents.confirmDelete ) ) {
				return;
			}
			removeRow( trashLink.dataset.id, {}, 'Could not trash that event.' );
			return;
		}

		if ( deleteForeverLink ) {
			e.preventDefault();
			if ( ! window.confirm( window.NNRManageEvents.confirmDeleteForever ) ) {
				return;
			}
			removeRow( deleteForeverLink.dataset.id, { permanent: '1' }, 'Could not permanently delete that event.' );
			return;
		}

		if ( restoreLink ) {
			e.preventDefault();
			var restoreId = restoreLink.dataset.id;
			var body      = new URLSearchParams();
			body.set( 'action', window.NNRManageEvents.restoreAction );
			body.set( 'nnr_nonce', window.NNRManageEvents.nonce );
			body.set( 'post_id', restoreId );

			fetch( window.NNRManageEvents.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
				credentials: 'same-origin',
			} )
				.then( function ( response ) { return response.json(); } )
				.then( function ( json ) {
					if ( ! json.success ) {
						window.alert( ( json.data && json.data.message ) || 'Could not restore that event.' );
						return;
					}
					// A restored event no longer belongs in the Trash view
					// it was just restored from — remove it here rather
					// than swap in its row_html (row_html is still handed
					// back so the in-memory data store stays fresh for a
					// future Edit from wherever the event shows up next).
					window.NNRManageEvents.events[ restoreId ] = json.data.event_data;
					removeRowElement( restoreId );
				} )
				.catch( function () {
					window.alert( 'Could not restore that event.' );
				} );
		}
	} );

	function removeRowElement( id ) {
		var row = tbody.querySelector( 'tr[data-id="' + id + '"]' );
		if ( row ) {
			row.remove();
		}
		if ( ! tbody.querySelector( 'tr[data-id]' ) ) {
			tbody.innerHTML = '<tr class="nnr-events-empty-row"><td colspan="4">' + ( window.NNRManageEvents.noEventsText || 'No events found.' ) + '</td></tr>';
		}
	}

	function removeRow( id, extraFields, errorMessage ) {
		var body = new URLSearchParams();
		body.set( 'action', window.NNRManageEvents.deleteAction );
		body.set( 'nnr_nonce', window.NNRManageEvents.nonce );
		body.set( 'post_id', id );
		Object.keys( extraFields || {} ).forEach( function ( key ) {
			body.set( key, extraFields[ key ] );
		} );

		fetch( window.NNRManageEvents.ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
			credentials: 'same-origin',
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( json ) {
				if ( ! json.success ) {
					window.alert( ( json.data && json.data.message ) || errorMessage );
					return;
				}
				delete window.NNRManageEvents.events[ id ];
				removeRowElement( id );
			} )
			.catch( function () {
				window.alert( errorMessage );
			} );
	}

	form.addEventListener( 'submit', function ( e ) {
		e.preventDefault();

		if ( 'undefined' !== typeof tinymce && tinymce.get( EDITOR_ID ) ) {
			tinymce.triggerSave();
		}

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}
		if ( spinner ) {
			spinner.classList.add( 'is-active' );
		}

		var formData = new FormData( form );

		fetch( window.NNRManageEvents.ajaxUrl, {
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( json ) {
				if ( ! json.success ) {
					window.alert( ( json.data && json.data.message ) || 'Error saving event.' );
					return;
				}

				var postId      = json.data.post_id;
				var existingRow = tbody.querySelector( 'tr[data-id="' + postId + '"]' );

				if ( existingRow ) {
					existingRow.outerHTML = json.data.row_html;
				} else {
					var emptyRow = tbody.querySelector( '.nnr-events-empty-row' );
					if ( emptyRow ) {
						emptyRow.remove();
					}
					tbody.insertAdjacentHTML( 'beforeend', json.data.row_html );
				}

				window.NNRManageEvents.events[ postId ] = json.data.event_data;
				closePanel();
			} )
			.catch( function () {
				window.alert( 'Error saving event.' );
			} )
			.finally( function () {
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
				if ( spinner ) {
					spinner.classList.remove( 'is-active' );
				}
			} );
	} );
} )();
