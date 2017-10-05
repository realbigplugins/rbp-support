( function( $ ) {
	
	$( document ).ready( function() {
		
		if ( $( '.rbp-support-form' ).length <= 0 ) return;
		
		$( '.rbp-support-form' ).on( 'submit', function( event ) {
			
			event.preventDefault(); // Don't submit via PHP
			event.stopPropagation(); // Don't let this get called 17 times if more than one instance of the script is active
			
			var $form = $( this );
			
			// Grab the correct data based on Prefix. This is helpful if for some reason, multiple instances of this script are active at once
			var prefix = $form.data( 'prefix' ),
				l18n = window[ prefix + '_support_form' ];
			
			// This captures the Submit button
			// activeElement ensures it is the correct one in the event more Submit Buttons get added for some reason
			var $submitButton = $( document.activeElement );
				
			$submitButton.attr( 'disabled', true );

			// Used to construct HTML Name Attribute
			var data = {};

			$form.find( '.form-field' ).each( function( index, field ) {

				if ( $( field ).parent().hasClass( 'hidden' ) ) return true;

				var name = $( field ).attr( 'name' ),
					value = $( field ).val();

				if ( $( field ).is( 'input[type="checkbox"]' ) ) {

					value = ( $( field ).prop( 'checked' ) ) ? 1 : 0;

				}

				// Checkboxes don't place nice with my regex and I'm not rewriting it
				data[ name ] = value;

			} );

			data.action = 'rbp_support_form';
			
			// Grab the Nonce Value
			var $nonce = $form.find( 'input[id$="_support_nonce"]' );
			data[ $nonce.attr( 'name' ) ] = $nonce.val();
			
			data.plugin_prefix = prefix;
			data.license_data = l18n.license_data;

			$.ajax( {
				'type' : 'POST',
				'url' : l18n.ajaxUrl,
				'data' : data,
				success : function( response ) {
					
					$form.parent().find( '.success-message' ).fadeIn();
					
					$form.fadeOut();
					

				},
				error : function( request, status, error ) {
					
					console.log( error );

					$submitButton.attr( 'disabled', false );

				}
			} );
			
		} );
		
	} );
	
} )( jQuery );