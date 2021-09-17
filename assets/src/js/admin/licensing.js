( function( $ ) {
    
    $( document ).on( 'ready', function() {
        
        if ( $( 'input[name$="_enable_beta"]' ).length <= 0 ) return;
        
        // Submit Form on Beta Status toggle
        $( 'input[name$="_enable_beta"]' ).on( 'click', function() {
            
            $( this ).closest( 'form' ).trigger( 'submit' );
            
        } );
        
    } );
    
} )( jQuery );