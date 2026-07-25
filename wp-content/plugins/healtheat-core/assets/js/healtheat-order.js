/**
 * Health'eat — tunnel de commande click & collect.
 */
( function () {
	'use strict';

	var data = window.healtheatData || {};
	var i18n = data.i18n || {};

	/**
	 * Charge les créneaux de retrait disponibles.
	 *
	 * @param {HTMLSelectElement} select Liste déroulante à remplir.
	 */
	function loadSlots( select ) {
		window
			.fetch( data.restUrl + '/slots', { headers: { Accept: 'application/json' } } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				select.innerHTML = '';

				var days = ( payload && payload.days ) || [];

				if ( ! days.length ) {
					select.appendChild( new Option( i18n.noSlot || '', '' ) );
					select.disabled = true;

					return;
				}

				select.appendChild( new Option( '—', '' ) );

				days.forEach( function ( day ) {
					var group = document.createElement( 'optgroup' );
					group.label = day.label;

					day.slots.forEach( function ( slot ) {
						var option = new Option(
							slot.full ? slot.label + ' (' + ( i18n.slotFull || '' ) + ')' : slot.label,
							slot.value
						);
						option.disabled = slot.full;
						group.appendChild( option );
					} );

					select.appendChild( group );
				} );
			} )
			.catch( function () {
				select.innerHTML = '';
				select.appendChild( new Option( i18n.noSlot || '', '' ) );
				select.disabled = true;
			} );
	}

	/**
	 * Affiche un message de retour sous le formulaire.
	 *
	 * @param {HTMLElement} node    Zone de message.
	 * @param {string}      message Texte.
	 * @param {string}      type    error|success|info.
	 */
	function feedback( node, message, type ) {
		if ( ! node ) {
			return;
		}

		node.textContent = message;
		node.className = 'healtheat-form__feedback is-' + ( type || 'info' );
	}

	/**
	 * Branche le formulaire de commande.
	 *
	 * @param {HTMLElement} checkout Bloc de commande.
	 */
	function bindForm( checkout ) {
		var form = checkout.querySelector( '[data-healtheat-form]' );
		var select = checkout.querySelector( '[data-healtheat-slots]' );
		var message = checkout.querySelector( '[data-healtheat-feedback]' );

		if ( ! form || ! select ) {
			return;
		}

		loadSlots( select );

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			var cart = window.healtheatCart;
			var items = cart ? cart.payload() : [];

			if ( ! items.length ) {
				feedback( message, i18n.cartEmpty || '', 'error' );

				return;
			}

			if ( ! select.value ) {
				feedback( message, i18n.pickSlot || '', 'error' );
				select.focus();

				return;
			}

			if ( ! form.checkValidity() ) {
				form.reportValidity();

				return;
			}

			var button = form.querySelector( 'button[type="submit"]' );
			var original = button ? button.textContent : '';

			if ( button ) {
				button.disabled = true;
				button.textContent = i18n.sending || '';
			}

			feedback( message, '', 'info' );

			var headers = { 'Content-Type': 'application/json' };

			if ( data.nonce ) {
				headers['X-WP-Nonce'] = data.nonce;
			}

			window
				.fetch( data.restUrl + '/orders', {
					method: 'POST',
					headers: headers,
					body: JSON.stringify( {
						name: form.elements.name.value,
						email: form.elements.email.value,
						phone: form.elements.phone.value,
						notes: form.elements.notes.value,
						website: form.elements.website.value,
						pickup: select.value,
						items: items
					} )
				} )
				.then( function ( response ) {
					return response.json().then( function ( payload ) {
						return { ok: response.ok, payload: payload };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						throw new Error( ( result.payload && result.payload.message ) || i18n.error );
					}

					cart.clear();
					form.reset();
					checkout.innerHTML =
						'<div class="healtheat-success"><h2>' +
						escapeHtml( result.payload.reference ) +
						'</h2><p>' +
						escapeHtml( result.payload.message ) +
						'</p><p class="healtheat-success__total">' +
						escapeHtml( result.payload.total_html ) +
						'</p></div>';
					checkout.scrollIntoView( { behavior: 'smooth', block: 'center' } );
				} )
				.catch( function ( error ) {
					feedback( message, error.message || i18n.error, 'error' );

					if ( select.value ) {
						loadSlots( select );
					}
				} )
				.finally( function () {
					if ( button ) {
						button.disabled = false;
						button.textContent = original;
					}
				} );
		} );
	}

	/**
	 * Échappe une chaîne avant injection HTML.
	 *
	 * @param {string} value Chaîne d'origine.
	 * @return {string} Chaîne échappée.
	 */
	function escapeHtml( value ) {
		var node = document.createElement( 'div' );
		node.textContent = value || '';

		return node.innerHTML;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '[data-healtheat-checkout]' ).forEach( bindForm );
	} );
} )();
