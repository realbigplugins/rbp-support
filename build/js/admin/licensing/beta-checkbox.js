( function( $ ) {
	
	$( document ).ready( function() {
		
		if ( $( '.rbp-support-licensing-form input[name="ld_mailchimp_enable_beta"]' ).length <= 0 ) return;
		
		// Submit Form on Beta Status toggle
		$( 'input[name="ld_mailchimp_enable_beta"]' ).on( 'click', function() {
			
			$( this ).closest( 'form' ).submit();
			
		} );
		
	} );
	
} )( jQuery );