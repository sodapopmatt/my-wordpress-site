( function ( $ ) {
	if ( typeof inlineEditPost === 'undefined' ) {
		return;
	}

	var wpInlineEdit = inlineEditPost.edit;

	inlineEditPost.edit = function ( id ) {
		wpInlineEdit.apply( this, arguments );

		var postId = 'object' === typeof id ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );
		if ( ! postId ) {
			return;
		}

		var dataEl = document.getElementById( 'nnr-inline-' + postId );
		if ( ! dataEl ) {
			return;
		}

		var editRow = $( '#edit-' + postId );
		var data = dataEl.dataset;

		editRow.find( 'input[name="_nnr_start_date"]' ).val( data.nnrStartDate || '' );
		editRow.find( 'input[name="_nnr_start_time"]' ).val( data.nnrStartTime || '' );
		editRow.find( 'input[name="_nnr_end_date"]' ).val( data.nnrEndDate || '' );
		editRow.find( 'input[name="_nnr_end_time"]' ).val( data.nnrEndTime || '' );
		editRow.find( 'input[name="_nnr_venue"]' ).val( data.nnrVenue || '' );
		editRow.find( 'input[name="_nnr_price"]' ).val( data.nnrPrice || '' );
		editRow.find( '.nnr-quick-edit-nonce' ).val( data.nonce || '' );
	};
} )( jQuery );
