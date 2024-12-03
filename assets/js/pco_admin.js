jQuery( document ).ready( function( $ ) {
    if ( '#failed-actions' === window.location.hash ) {
        $( '.faulty-actions__ul' ).show();
    }

    $( '.faulty-actions a' ).on( 'click', function() {
        $( '.faulty-actions__ul' ).slideToggle('fast');
    } );
} );

