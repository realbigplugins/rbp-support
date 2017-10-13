( function( $ ) {
	
	$( document ).ready( function() {
		
		if ( $( '.rbp-support-form' ).length <= 0 ) return;
		
		// Only apply to <form> variant
		$( 'form.rbp-support-form' ).on( 'submit', function( event ) {
			
			// For some dumb reason, if we use document.activeElement here it prevents the form submission
			// Not in the JavaScript validation version though
			var $submitButton = $( this ).find( 'button[type="submit"]' );
			
			$submitButton.attr( 'disabled', true );
			
		} );
		
		if ( $( '.rbp-support-form.javascript-interrupt' ).length <= 0 ) return;
		
		$( 'form' ).on( 'submit', function( event ) {
			
			var $submitButton = $( document.activeElement );
			
			// Check to see if it is our Submit Button
			// A lot of our plugins tie into other systems (EDD, PSP, etc.) which often means we're creating something inside of another <form> with little options to place outside of it
			if ( $submitButton.attr( 'name' ).indexOf( '_support_submit' ) > -1 ) {
				
				$submitButton.attr( 'disabled', true );
				
				var $form = $( this );
				
				// Ensure any required fields have their required status
				$( this ).find( '.required' ).each( function( index, element ) {
					$( element ).attr( 'required', true );
				} );
			
				$form[0].reportValidity(); // Report Validity via HTML5 stuff
				
				if ( ! $form[0].checkValidity() ) { 
					
					// Invalid, don't submit
					event.preventDefault();
					
					// If our form is Invalid, remove the Required attributes after 2 seconds
					// The timeout is used because otherwise the little pop-up Chrome and many other browsers make goes away immediately
				
					setTimeout( function() {

						// Reset after reporting validity so future submissions of other forms don't get hung up
						$form.find( '.required' ).each( function( index, element ) {
							$( element ).attr( 'required', false );
						} );
						
						// Reset here to line up with the rest of the form "reset"
						$submitButton.attr( 'disabled', false );

					}, 2000 );
					
				}
				
			}
			
		} );
		
	} );
	
} )( jQuery );