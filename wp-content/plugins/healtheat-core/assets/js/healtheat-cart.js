/**
 * Health'eat — panier click & collect et filtres de la carte.
 *
 * Le panier ne stocke que des identifiants et des quantités ; les prix
 * affichés sont indicatifs, le serveur recalcule toujours le total réel.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'healtheat_cart_v1';
	var data = window.healtheatData || {};
	var i18n = data.i18n || {};
	var listeners = [];

	/**
	 * Lit le panier stocké localement.
	 *
	 * @return {Array} Lignes du panier.
	 */
	function read() {
		try {
			var raw = window.localStorage.getItem( STORAGE_KEY );
			var parsed = raw ? JSON.parse( raw ) : [];

			return Array.isArray( parsed ) ? parsed.filter( isValidLine ) : [];
		} catch ( error ) {
			return [];
		}
	}

	/**
	 * Vérifie qu'une ligne de panier est exploitable.
	 *
	 * @param {Object} line Ligne à tester.
	 * @return {boolean} Vrai si la ligne est valide.
	 */
	function isValidLine( line ) {
		return line && typeof line === 'object' && parseInt( line.id, 10 ) > 0 && parseInt( line.qty, 10 ) > 0;
	}

	/**
	 * Écrit le panier et prévient les abonnés.
	 *
	 * @param {Array} lines Lignes du panier.
	 */
	function write( lines ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( lines ) );
		} catch ( error ) {
			// Mode navigation privée : on continue sans persistance.
		}

		listeners.forEach( function ( callback ) {
			callback( lines );
		} );

		render();
	}

	/**
	 * Formate un montant en centimes.
	 *
	 * @param {number} cents Montant en centimes.
	 * @return {string} Montant lisible.
	 */
	function formatPrice( cents ) {
		var amount = ( Math.round( cents ) / 100 ).toFixed( 2 ).replace( '.', ',' );

		return amount + ' ' + ( data.currency || '€' );
	}

	var cart = {
		get: read,

		count: function () {
			return read().reduce( function ( total, line ) {
				return total + parseInt( line.qty, 10 );
			}, 0 );
		},

		total: function () {
			return read().reduce( function ( total, line ) {
				return total + parseInt( line.price, 10 ) * parseInt( line.qty, 10 );
			}, 0 );
		},

		add: function ( dish, qty ) {
			var lines = read();
			var found = false;

			qty = qty || 1;

			lines.forEach( function ( line ) {
				if ( line.id === dish.id ) {
					line.qty = Math.min( 20, line.qty + qty );
					found = true;
				}
			} );

			if ( ! found ) {
				lines.push( { id: dish.id, name: dish.name, price: dish.price, qty: Math.min( 20, qty ) } );
			}

			write( lines );
		},

		setQty: function ( id, qty ) {
			var lines = read()
				.map( function ( line ) {
					if ( line.id === id ) {
						line.qty = Math.max( 0, Math.min( 20, qty ) );
					}

					return line;
				} )
				.filter( function ( line ) {
					return line.qty > 0;
				} );

			write( lines );
		},

		remove: function ( id ) {
			write(
				read().filter( function ( line ) {
					return line.id !== id;
				} )
			);
		},

		clear: function () {
			write( [] );
		},

		payload: function () {
			return read().map( function ( line ) {
				return { id: line.id, qty: line.qty };
			} );
		},

		subscribe: function ( callback ) {
			listeners.push( callback );
		},

		formatPrice: formatPrice
	};

	/**
	 * Dessine les lignes du panier dans un conteneur.
	 *
	 * @param {HTMLElement} container Conteneur cible.
	 * @param {Array}       lines     Lignes du panier.
	 */
	function renderLines( container, lines ) {
		container.innerHTML = '';

		if ( ! lines.length ) {
			var empty = document.createElement( 'p' );
			empty.className = 'healtheat-cart__empty';
			empty.textContent = i18n.cartEmpty || '';
			container.appendChild( empty );

			return;
		}

		var list = document.createElement( 'ul' );
		list.className = 'healtheat-cart__lines';

		lines.forEach( function ( line ) {
			var item = document.createElement( 'li' );
			item.className = 'healtheat-cart__line';

			var name = document.createElement( 'span' );
			name.className = 'healtheat-cart__name';
			name.textContent = line.name;

			var controls = document.createElement( 'span' );
			controls.className = 'healtheat-cart__controls';

			var minus = document.createElement( 'button' );
			minus.type = 'button';
			minus.className = 'healtheat-qty';
			minus.textContent = '−';
			minus.setAttribute( 'aria-label', i18n.decrease || '-' );
			minus.addEventListener( 'click', function () {
				cart.setQty( line.id, line.qty - 1 );
			} );

			var qty = document.createElement( 'span' );
			qty.className = 'healtheat-cart__qty';
			qty.textContent = line.qty;

			var plus = document.createElement( 'button' );
			plus.type = 'button';
			plus.className = 'healtheat-qty';
			plus.textContent = '+';
			plus.setAttribute( 'aria-label', i18n.increase || '+' );
			plus.addEventListener( 'click', function () {
				cart.setQty( line.id, line.qty + 1 );
			} );

			var price = document.createElement( 'span' );
			price.className = 'healtheat-cart__price';
			price.textContent = formatPrice( line.price * line.qty );

			controls.appendChild( minus );
			controls.appendChild( qty );
			controls.appendChild( plus );

			item.appendChild( name );
			item.appendChild( controls );
			item.appendChild( price );
			list.appendChild( item );
		} );

		container.appendChild( list );
	}

	/**
	 * Rafraîchit toutes les zones liées au panier.
	 */
	function render() {
		var lines = read();
		var count = cart.count();

		document.querySelectorAll( '[data-healtheat-cart-lines]' ).forEach( function ( container ) {
			renderLines( container, lines );
		} );

		document.querySelectorAll( '[data-healtheat-cart-total]' ).forEach( function ( node ) {
			node.textContent = formatPrice( cart.total() );
		} );

		document.querySelectorAll( '[data-healtheat-badge]' ).forEach( function ( node ) {
			node.textContent = count;
		} );

		var drawer = document.querySelector( '[data-healtheat-drawer]' );

		if ( drawer ) {
			drawer.hidden = 0 === count;
		}
	}

	/**
	 * Affiche une confirmation éphémère.
	 *
	 * @param {string} message Texte à afficher.
	 */
	function toast( message ) {
		var node = document.createElement( 'div' );
		node.className = 'healtheat-toast';
		node.setAttribute( 'role', 'status' );
		node.textContent = message;
		document.body.appendChild( node );

		window.setTimeout( function () {
			node.classList.add( 'is-leaving' );
			window.setTimeout( function () {
				node.remove();
			}, 300 );
		}, 2000 );
	}

	/**
	 * Branche les boutons « Ajouter ».
	 */
	function bindAddButtons() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.healtheat-add' );

			if ( ! button ) {
				return;
			}

			event.preventDefault();

			cart.add( {
				id: parseInt( button.getAttribute( 'data-dish-id' ), 10 ),
				name: button.getAttribute( 'data-dish-name' ),
				price: parseInt( button.getAttribute( 'data-dish-price' ), 10 )
			} );

			toast( button.getAttribute( 'data-dish-name' ) + ' ' + ( i18n.added || '' ) );
		} );
	}

	/**
	 * Branche l'ouverture du panier flottant.
	 */
	function bindDrawer() {
		var toggle = document.querySelector( '[data-healtheat-toggle]' );
		var panel = document.querySelector( '[data-healtheat-panel]' );

		if ( ! toggle || ! panel ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var open = panel.hidden;

			panel.hidden = ! open;
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && ! panel.hidden ) {
				panel.hidden = true;
				toggle.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	}

	/**
	 * Branche les filtres de la carte.
	 */
	function bindFilters() {
		document.querySelectorAll( '[data-healtheat-menu]' ).forEach( function ( menu ) {
			var dishes = Array.prototype.slice.call( menu.querySelectorAll( '.healtheat-dish' ) );
			var counter = menu.querySelector( '[data-healtheat-count]' );
			var noResult = menu.querySelector( '[data-healtheat-no-result]' );
			var activeCat = '';
			var activeDiets = [];

			/**
			 * Applique les filtres courants.
			 */
			function apply() {
				var visible = 0;

				dishes.forEach( function ( dish ) {
					var cats = ( dish.getAttribute( 'data-cats' ) || '' ).split( ' ' );
					var diets = ( dish.getAttribute( 'data-diets' ) || '' ).split( ' ' );

					var matchCat = ! activeCat || cats.indexOf( activeCat ) !== -1;
					var matchDiet = activeDiets.every( function ( diet ) {
						return diets.indexOf( diet ) !== -1;
					} );

					var show = matchCat && matchDiet;
					dish.hidden = ! show;

					if ( show ) {
						visible++;
					}
				} );

				if ( counter ) {
					counter.textContent = visible + ' ' + ( 1 === visible ? 'plat' : 'plats' );
				}

				if ( noResult ) {
					noResult.hidden = visible > 0;
				}
			}

			menu.querySelectorAll( '[data-filter-cat]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					activeCat = button.getAttribute( 'data-filter-cat' );

					menu.querySelectorAll( '[data-filter-cat]' ).forEach( function ( other ) {
						other.classList.toggle( 'is-active', other === button );
					} );

					apply();
				} );
			} );

			menu.querySelectorAll( '[data-filter-diet]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					var diet = button.getAttribute( 'data-filter-diet' );
					var index = activeDiets.indexOf( diet );

					if ( index === -1 ) {
						activeDiets.push( diet );
						button.classList.add( 'is-active' );
						button.setAttribute( 'aria-pressed', 'true' );
					} else {
						activeDiets.splice( index, 1 );
						button.classList.remove( 'is-active' );
						button.setAttribute( 'aria-pressed', 'false' );
					}

					apply();
				} );
			} );

			apply();
		} );
	}

	window.healtheatCart = cart;

	document.addEventListener( 'DOMContentLoaded', function () {
		bindAddButtons();
		bindDrawer();
		bindFilters();
		render();
	} );
} )();
