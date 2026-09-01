/**
 * Curated Review — editor component.
 *
 * Pickers and toggles, nothing else. The PHP render callback is the single
 * source of truth for the markup, and the preview is a ServerSideRender of
 * exactly that callback — there is deliberately no JS-side markup here.
 */

import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import {
	ComboboxControl,
	Disabled,
	Notice,
	PanelBody,
	Placeholder,
	ToggleControl,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from '../block.json';

// Emitted as build/style-index.css and registered by block.json's "style".
import './style.scss';

const SNIPPET_LENGTH = 60;
const PRODUCTS_PER_PAGE = 20;

/**
 * REST hands back rendered HTML; a picker label needs plain text.
 *
 * DOMParser rather than innerHTML: it neither runs script nor fetches
 * subresources, and it decodes entities on the way — which a tag-stripping
 * regex does not.
 *
 * @param {string} html Rendered HTML.
 * @return {string} Collapsed plain text.
 */
const plainText = ( html ) =>
	new window.DOMParser()
		.parseFromString( html || '', 'text/html' )
		.body.textContent.replace( /\s+/g, ' ' )
		.trim();

/**
 * @param {string} html Rendered HTML.
 * @return {string} Plain text, truncated for a one-line option label.
 */
const snippet = ( html ) => {
	const text = plainText( html );

	return text.length > SNIPPET_LENGTH
		? text.slice( 0, SNIPPET_LENGTH ).trimEnd() + '…'
		: text;
};

const productOption = ( product ) => ( {
	value: String( product.id ),
	label: plainText( product?.title?.rendered ),
} );

function Edit( { attributes, setAttributes } ) {
	const { productId, reviewId } = attributes;

	const [ productSearch, setProductSearch ] = useState( '' );
	const [ reviews, setReviews ] = useState( [] );
	// idle (no product yet) · loading · ready · forbidden · error
	const [ reviewsState, setReviewsState ] = useState( 'idle' );
	const [ reviewsError, setReviewsError ] = useState( '' );

	const { products, selectedProduct, isProductPost, currentPostId } =
		useSelect(
			( select ) => {
				const { getEntityRecords, getEntityRecord } =
					select( coreStore );
				const editor = select( 'core/editor' );

				const query = {
					per_page: PRODUCTS_PER_PAGE,
					orderby: 'title',
					order: 'asc',
					_fields: 'id,title',
				};

				if ( productSearch ) {
					query.search = productSearch;
				}

				return {
					products: getEntityRecords( 'postType', 'product', query ),
					// The saved product is rarely in the first page of
					// results, so fetch it separately or its label goes blank
					// on reload.
					selectedProduct: productId
						? getEntityRecord( 'postType', 'product', productId )
						: null,
					isProductPost: editor?.getCurrentPostType() === 'product',
					currentPostId: editor?.getCurrentPostId(),
				};
			},
			[ productSearch, productId ]
		);

	// Editor convenience only: pre-fill from the product being edited, and
	// only while nothing has been chosen.
	useEffect( () => {
		if ( ! productId && isProductPost && currentPostId ) {
			setAttributes( { productId: currentPostId } );
		}
	}, [ productId, isProductPost, currentPostId, setAttributes ] );

	useEffect( () => {
		let cancelled = false;

		if ( ! productId ) {
			setReviews( [] );
			setReviewsState( 'idle' );
		} else {
			setReviewsState( 'loading' );

			apiFetch( {
				path:
					'/wc/v3/products/reviews?status=approved&per_page=100&product=' +
					productId,
			} )
				.then( ( items ) => {
					if ( cancelled ) {
						return;
					}
					setReviews( Array.isArray( items ) ? items : [] );
					setReviewsState( 'ready' );
				} )
				.catch( ( error ) => {
					if ( cancelled ) {
						return;
					}
					setReviews( [] );

					// Reading this endpoint needs `moderate_comments`, so an
					// Author or Contributor gets a hard 403 (401 when the
					// cookie has lapsed). Every other failure — offline,
					// WooCommerce inactive, a 500 — is a different story and
					// must not be mislabelled as a permission problem. None of
					// them is an empty result list either: `reviewsState`
					// keeps the three apart.
					const status = error?.data?.status;

					if ( status === 401 ) {
						// Not a capability problem: the cookie lapsed. Saying
						// "ask an administrator" to an administrator whose own
						// session expired sends them to the wrong place.
						setReviewsState( 'expired' );
					} else if ( status === 403 ) {
						setReviewsState( 'forbidden' );
					} else {
						setReviewsError( error?.message || '' );
						setReviewsState( 'error' );
					}
				} );
		}

		return () => {
			cancelled = true;
		};
	}, [ productId ] );

	const productOptions = ( products || [] ).map( productOption );

	if (
		selectedProduct &&
		! productOptions.some( ( o ) => o.value === String( productId ) )
	) {
		productOptions.unshift( productOption( selectedProduct ) );
	}

	const reviewOptions = reviews.map( ( review ) => ( {
		value: String( review.id ),
		label: sprintf(
			/* translators: 1: reviewer name. 2: rating, 1-5. 3: opening words of the review. */
			__( '%1$s · %2$d★ · %3$s', 'kaupang-review-images' ),
			review.reviewer || __( 'Anonymous', 'kaupang-review-images' ),
			review.rating,
			snippet( review.review )
		),
	} ) );

	let reviewHelp;

	if ( ! productId ) {
		reviewHelp = __( 'Select a product first', 'kaupang-review-images' );
	} else if ( reviewsState === 'loading' ) {
		reviewHelp = __( 'Loading reviews…', 'kaupang-review-images' );
	} else if ( reviewsState === 'ready' && reviewOptions.length === 0 ) {
		reviewHelp = __(
			'This product has no approved reviews yet.',
			'kaupang-review-images'
		);
	}

	const toggle = ( attribute, label, help ) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			checked={ !! attributes[ attribute ] }
			onChange={ ( value ) => setAttributes( { [ attribute ]: value } ) }
		/>
	);

	return (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Review source', 'kaupang-review-images' ) }
				>
					<ComboboxControl
						label={ __( 'Product', 'kaupang-review-images' ) }
						help={ __(
							'Type to search all products.',
							'kaupang-review-images'
						) }
						value={ productId ? String( productId ) : null }
						options={ productOptions }
						onFilterValueChange={ setProductSearch }
						onChange={ ( next ) => {
							const picked = Number( next ) || 0;

							// Only a genuine change invalidates the review.
							// Re-picking the product already selected used to
							// throw the saved review away.
							if ( picked === productId ) {
								return;
							}

							setAttributes( {
								productId: picked,
								// A review belongs to one product.
								reviewId: 0,
							} );
						} }
					/>

					{ reviewsState === 'expired' && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Your session has expired — save your work, reload the editor, and sign in again.',
								'kaupang-review-images'
							) }
						</Notice>
					) }

					{ reviewsState === 'forbidden' && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'You need review-moderation permission to pick a review — ask an administrator.',
								'kaupang-review-images'
							) }
						</Notice>
					) }

					{ reviewsState === 'error' && (
						<Notice status="error" isDismissible={ false }>
							{ reviewsError ||
								__(
									'The review list could not be loaded.',
									'kaupang-review-images'
								) }
						</Notice>
					) }

					<Disabled
						isDisabled={
							reviewsState !== 'ready' ||
							reviewOptions.length === 0
						}
					>
						<ComboboxControl
							label={ __( 'Review', 'kaupang-review-images' ) }
							help={ reviewHelp }
							value={ reviewId ? String( reviewId ) : null }
							options={ reviewOptions }
							isLoading={ reviewsState === 'loading' }
							onChange={ ( next ) =>
								setAttributes( {
									reviewId: Number( next ) || 0,
								} )
							}
						/>
					</Disabled>
				</PanelBody>

				<PanelBody
					title={ __( 'Content', 'kaupang-review-images' ) }
					initialOpen={ false }
				>
					{ toggle(
						'showBody',
						__( 'Review text', 'kaupang-review-images' )
					) }
					{ attributes.showBody &&
						toggle(
							'expandable',
							__(
								'Shorten with a Read more link',
								'kaupang-review-images'
							),
							__(
								'Only appears when the review is actually long enough to be cut off.',
								'kaupang-review-images'
							)
						) }
					{ toggle(
						'showReviewImages',
						__( 'Customer photos', 'kaupang-review-images' )
					) }
					{ toggle(
						'showRating',
						__( 'Star rating', 'kaupang-review-images' )
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Attribution', 'kaupang-review-images' ) }
					initialOpen={ false }
				>
					{ toggle(
						'showAuthor',
						__( 'Reviewer name', 'kaupang-review-images' )
					) }
					{ toggle(
						'showAvatar',
						__( 'Avatar', 'kaupang-review-images' )
					) }
					{ toggle(
						'showVerified',
						__( 'Verified-owner badge', 'kaupang-review-images' )
					) }
					{ toggle(
						'showDate',
						__( 'Date', 'kaupang-review-images' )
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Product', 'kaupang-review-images' ) }
					initialOpen={ false }
				>
					{ toggle(
						'showProductName',
						__( 'Product name', 'kaupang-review-images' )
					) }
					{ toggle(
						'showProductImage',
						__( 'Product image', 'kaupang-review-images' )
					) }
				</PanelBody>
			</InspectorControls>

			{ reviewId ? (
				<ServerSideRender
					block={ metadata.name }
					attributes={ attributes }
				/>
			) : (
				<Placeholder
					icon={ metadata.icon }
					label={ __( 'Curated Review', 'kaupang-review-images' ) }
					instructions={ __(
						'Pick a product, then one of its reviews, in the block settings sidebar.',
						'kaupang-review-images'
					) }
				/>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	// Dynamic block: PHP renders it, so nothing is serialised into the post.
	save: () => null,
} );
