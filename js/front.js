/**
 * Custom Sidebar
 */

( function( $, bp, wp ) {

	if ( 'undefined' === typeof _activitesDePublicationSettings ) {
		return;
	}

    $( '#comments' ).append( $( '<div></div>' ).prop( 'id', 'bp-nouveau-activity-form' ) );

    var postForm = bp.Views.PostForm;

    /**
     * Activity Post Form overrides.
     */
    bp.Views.PostForm = postForm.extend( {
        postUpdate: function( event ) {
            if ( event ) {
				if ( 'keydown' === event.type && ( 13 !== event.keyCode || ! event.ctrlKey ) ) {
					return event;
				}

				event.preventDefault();
            }

            var self = this,
                meta = {};

            // Set the content and meta
			_.each( this.$el.serializeArray(), function( pair ) {
				pair.name = pair.name.replace( '[]', '' );
				if ( 'whats-new' === pair.name ) {
					self.model.set( 'content', pair.value );
				} else if ( -1 === _.indexOf( ['aw-whats-new-submit', 'whats-new-post-in'], pair.name ) ) {
					if ( _.isUndefined( meta[ pair.name ] ) ) {
						meta[ pair.name ] = pair.value;
					} else {
						if ( ! _.isArray( meta[ pair.name ] ) ) {
							meta[ pair.name ] = [ meta[ pair.name ] ];
						}

						meta[ pair.name ].push( pair.value );
					}
				}
			} );

			// Silently add meta
			this.model.set( meta, { silent: true } );
        }
    } );

    bp.Nouveau.Activity.postForm.start();

    // @todo Backbone model/collection and views to list Post activities.
    wp.apiRequest( {
		path: _activitesDePublicationSettings.versionString + '/activity/',
		type: 'GET',
		data: {
			type : 'publication_activity',
			'primary_id' : _activitesDePublicationSettings.primaryID,
			'secondary_id' : _activitesDePublicationSettings.secondaryID,
		},
		dataType: 'json'
	} ).done( function( response ) {
		console.log( response );

	} ).fail( function( response ) {
		console.log( response );
	} );

} )( jQuery, window.bp || {}, window.wp || {} );
