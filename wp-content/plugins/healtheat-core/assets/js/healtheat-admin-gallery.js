/**
 * Health'eat — sélection des photos supplémentaires d'un plat.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		var box = document.querySelector( '[data-healtheat-gallery]' );

		if ( ! box || ! window.wp || ! window.wp.media ) {
			return;
		}

		var list = box.querySelector( '[data-healtheat-gallery-list]' );
		var input = box.querySelector( '[data-healtheat-gallery-input]' );
		var addButton = box.querySelector( '[data-healtheat-gallery-add]' );
		var strings = window.healtheatGallery || {};
		var frame;

		/**
		 * Recalcule la liste d'identifiants stockée dans le champ caché.
		 */
		function sync() {
			var ids = Array.prototype.map.call( list.children, function ( item ) {
				return item.getAttribute( 'data-id' );
			} );

			input.value = ids.join( ',' );
		}

		/**
		 * Ajoute une vignette à la liste.
		 *
		 * @param {Object} attachment Pièce jointe sélectionnée.
		 */
		function addItem( attachment ) {
			if ( list.querySelector( '[data-id="' + attachment.id + '"]' ) ) {
				return;
			}

			var sizes = attachment.sizes || {};
			var thumb = ( sizes.thumbnail || sizes.medium || sizes.full || {} ).url || attachment.url;

			var item = document.createElement( 'li' );
			item.setAttribute( 'data-id', attachment.id );
			item.innerHTML =
				'<img src="' + thumb + '" alt="" />' +
				'<button type="button" class="healtheat-gallery__remove" aria-label="&times;">&times;</button>';

			list.appendChild( item );
		}

		addButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = window.wp.media( {
					title: strings.title || '',
					button: { text: strings.button || '' },
					library: { type: 'image' },
					multiple: true
				} );

				frame.on( 'select', function () {
					frame
						.state()
						.get( 'selection' )
						.toJSON()
						.forEach( addItem );

					sync();
				} );
			}

			frame.open();
		} );

		list.addEventListener( 'click', function ( event ) {
			if ( ! event.target.classList.contains( 'healtheat-gallery__remove' ) ) {
				return;
			}

			event.preventDefault();
			event.target.closest( 'li' ).remove();
			sync();
		} );
	} );
} )( window.jQuery );
