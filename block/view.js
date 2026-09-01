/**
 * Curated Review — expand a clamped review.
 *
 * Progressive enhancement, in this order:
 *
 *   no JS      full text, no button, nothing clamped
 *   JS, short  full text, no button — the clamp never bit, so there is
 *              nothing to expand and offering it would be a lie
 *   JS, long   clamped text plus a real <button>
 *
 * That is why PHP renders the button `hidden` and the clamp is keyed on
 * data-expanded, which only this file ever sets: a reader without JS is never
 * left with truncated text and no way to reach the rest.
 *
 * Plain file, no build step — the same convention as
 * assets/js/review-form-toggle.js. block.json enqueues it as viewScript, so it
 * only loads on pages that actually contain the block.
 */
( function () {
	'use strict';

	function setup( root ) {
		var button = root.querySelector( '.kaupang-review__more' );
		var body = root.querySelector( '.kaupang-review__body' );

		if ( ! button || ! body ) {
			return;
		}

		// Clamp first, then ask whether it changed anything.
		root.setAttribute( 'data-expanded', 'false' );

		// A clamped element reports more scrollHeight than it shows. One pixel
		// of slack keeps sub-pixel line heights from producing a button that
		// expands nothing visible.
		if ( body.scrollHeight <= body.clientHeight + 1 ) {
			root.removeAttribute( 'data-expanded' );

			return;
		}

		button.hidden = false;

		button.addEventListener( 'click', function () {
			var expanded = root.getAttribute( 'data-expanded' ) === 'true';

			root.setAttribute( 'data-expanded', expanded ? 'false' : 'true' );
			button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			button.textContent = expanded
				? button.getAttribute( 'data-label-more' )
				: button.getAttribute( 'data-label-less' );

			// Collapsing from below the fold would otherwise leave the reader
			// staring at whatever followed the review.
			if ( expanded ) {
				var top = root.getBoundingClientRect().top;

				if ( top < 0 ) {
					root.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				}
			}
		} );
	}

	function init() {
		var roots = document.querySelectorAll( '.kaupang-review[data-expandable]' );

		Array.prototype.forEach.call( roots, setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
