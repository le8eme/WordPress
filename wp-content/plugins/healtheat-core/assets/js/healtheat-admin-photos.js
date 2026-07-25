/**
 * Health'eat — affectation en masse des photos depuis la médiathèque.
 *
 * Chaque ligne ouvre le sélecteur de médias : la photo choisie est retenue
 * dans un champ caché, puis appliquée à l'envoi du formulaire.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}

		var strings = window.healtheatPhotos || {};

		document.querySelectorAll( '[data-healtheat-row]' ).forEach( function ( row ) {
			var button = row.querySelector( '[data-healtheat-pick]' );
			var input = row.querySelector( '[data-healtheat-picked-id]' );
			var label = row.querySelector( '[data-healtheat-picked]' );
			var preview = row.querySelector( '[data-healtheat-preview]' );
			var frame;

			if ( ! button || ! input ) {
				return;
			}

			button.addEventListener( 'click', function () {
				if ( ! frame ) {
					frame = window.wp.media( {
						title: strings.title || '',
						button: { text: strings.button || '' },
						library: { type: 'image' },
						multiple: false
					} );

					frame.on( 'select', function () {
						var attachment = frame.state().get( 'selection' ).first().toJSON();
						var sizes = attachment.sizes || {};
						var thumb = ( sizes.thumbnail || sizes.medium || sizes.full || {} ).url || attachment.url;

						input.value = attachment.id;

						if ( label ) {
							label.textContent = ' ' + ( attachment.filename || '' );
						}

						if ( preview && thumb ) {
							preview.innerHTML = '<img src="' + thumb + '" alt="" width="90" />';
						}
					} );
				}

				frame.open();
			} );
		} );
	} );
} )();
