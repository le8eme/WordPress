/**
 * Health'eat — navigation mobile.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.querySelector( '.nav-toggle' );
		var nav = document.getElementById( 'primary-navigation' );

		if ( ! toggle || ! nav ) {
			return;
		}

		toggle.addEventListener( 'click', function () {
			var open = 'true' === toggle.getAttribute( 'aria-expanded' );

			toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
			nav.classList.toggle( 'is-open', ! open );
			document.body.classList.toggle( 'nav-is-open', ! open );
		} );

		nav.addEventListener( 'click', function ( event ) {
			if ( 'A' === event.target.tagName ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
				nav.classList.remove( 'is-open' );
				document.body.classList.remove( 'nav-is-open' );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key && nav.classList.contains( 'is-open' ) ) {
				toggle.setAttribute( 'aria-expanded', 'false' );
				nav.classList.remove( 'is-open' );
				document.body.classList.remove( 'nav-is-open' );
				toggle.focus();
			}
		} );
	} );
} )();
