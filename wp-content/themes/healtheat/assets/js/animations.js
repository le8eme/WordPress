/**
 * Health'eat — animations : révélation au défilement, compteurs,
 * parallaxe des aliments et inclinaison des cartes.
 *
 * Aucune dépendance. Tout se désactive si le visiteur a demandé moins
 * d'animations, et le contenu reste entièrement lisible sans JavaScript.
 */
( function () {
	'use strict';

	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Révèle les blocs quand ils entrent dans le champ de vision.
	 */
	function initReveal() {
		var targets = document.querySelectorAll( '[data-reveal], .assembly' );

		if ( ! targets.length ) {
			return;
		}

		if ( reduced || ! ( 'IntersectionObserver' in window ) ) {
			targets.forEach( function ( target ) {
				target.classList.add( 'is-visible' );
			} );

			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}

					entry.target.classList.add( 'is-visible' );
					observer.unobserve( entry.target );
				} );
			},
			{ rootMargin: '0px 0px -12% 0px', threshold: 0.15 }
		);

		targets.forEach( function ( target ) {
			observer.observe( target );
		} );
	}

	/**
	 * Anime un nombre de 0 jusqu'à sa valeur cible.
	 *
	 * @param {HTMLElement} node Élément portant data-count.
	 */
	function countUp( node ) {
		var target = parseFloat( node.getAttribute( 'data-count' ) );
		var suffix = node.getAttribute( 'data-count-suffix' ) || '';
		var decimals = ( node.getAttribute( 'data-count' ) || '' ).indexOf( '.' ) === -1 ? 0 : 1;

		if ( isNaN( target ) ) {
			return;
		}

		if ( reduced ) {
			node.textContent = target.toFixed( decimals ).replace( '.', ',' ) + suffix;

			return;
		}

		var duration = 1400;
		var start = null;

		function frame( timestamp ) {
			if ( null === start ) {
				start = timestamp;
			}

			var progress = Math.min( 1, ( timestamp - start ) / duration );
			// Décélération douce.
			var eased = 1 - Math.pow( 1 - progress, 3 );
			var value = ( target * eased ).toFixed( decimals ).replace( '.', ',' );

			node.textContent = value + suffix;

			if ( progress < 1 ) {
				window.requestAnimationFrame( frame );
			}
		}

		window.requestAnimationFrame( frame );
	}

	/**
	 * Déclenche les compteurs à l'apparition.
	 */
	function initCounters() {
		var counters = document.querySelectorAll( '[data-count]' );

		if ( ! counters.length ) {
			return;
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			counters.forEach( countUp );

			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}

					countUp( entry.target );
					observer.unobserve( entry.target );
				} );
			},
			{ threshold: 0.5 }
		);

		counters.forEach( function ( counter ) {
			observer.observe( counter );
		} );
	}

	/**
	 * Fait réagir les aliments flottants à la souris et au défilement.
	 */
	function initParallax() {
		var items = Array.prototype.slice.call( document.querySelectorAll( '[data-parallax]' ) );

		if ( reduced || ! items.length ) {
			return;
		}

		var pointerX = 0;
		var pointerY = 0;
		var scrollY = 0;
		var ticking = false;

		function apply() {
			items.forEach( function ( item ) {
				var depth = parseFloat( item.getAttribute( 'data-parallax' ) ) || 0.2;

				item.style.setProperty( '--px', ( pointerX * depth * 40 ).toFixed( 2 ) + 'px' );
				item.style.setProperty( '--py', ( pointerY * depth * 40 + scrollY * depth * 0.25 ).toFixed( 2 ) + 'px' );
			} );

			ticking = false;
		}

		function request() {
			if ( ticking ) {
				return;
			}

			ticking = true;
			window.requestAnimationFrame( apply );
		}

		window.addEventListener(
			'pointermove',
			function ( event ) {
				pointerX = event.clientX / window.innerWidth - 0.5;
				pointerY = event.clientY / window.innerHeight - 0.5;
				request();
			},
			{ passive: true }
		);

		window.addEventListener(
			'scroll',
			function () {
				scrollY = window.scrollY;
				request();
			},
			{ passive: true }
		);

		apply();
	}

	/**
	 * Incline légèrement les cartes au survol.
	 */
	function initTilt() {
		if ( reduced ) {
			return;
		}

		document.querySelectorAll( '[data-tilt]' ).forEach( function ( card ) {
			card.addEventListener(
				'pointermove',
				function ( event ) {
					var bounds = card.getBoundingClientRect();
					var x = ( event.clientX - bounds.left ) / bounds.width - 0.5;
					var y = ( event.clientY - bounds.top ) / bounds.height - 0.5;

					card.style.transform =
						'perspective(900px) rotateX(' + ( -y * 6 ).toFixed( 2 ) + 'deg) rotateY(' + ( x * 6 ).toFixed( 2 ) + 'deg) translateY(-4px)';
				},
				{ passive: true }
			);

			card.addEventListener( 'pointerleave', function () {
				card.style.transform = '';
			} );
		} );
	}

	/**
	 * Duplique le bandeau défilant pour une boucle sans couture.
	 */
	function initMarquee() {
		document.querySelectorAll( '[data-marquee]' ).forEach( function ( track ) {
			track.innerHTML += track.innerHTML;
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initMarquee();
		initReveal();
		initCounters();
		initParallax();
		initTilt();
	} );
} )();
