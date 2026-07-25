<?php
/**
 * Shared helpers.
 *
 * Prices are always stored as integer cents to avoid float rounding errors,
 * and only converted to a human readable string at render time.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns a plugin setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Value returned when the setting is missing.
 * @return mixed
 */
function healtheat_get_setting( $key, $default = null ) {
	$settings = Healtheat_Settings::get_settings();

	return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
}

/**
 * Converts a user supplied amount (ex: "12,50") into cents.
 *
 * @param mixed $value Raw amount.
 * @return int
 */
function healtheat_to_cents( $value ) {
	$value = str_replace( array( ' ', ',' ), array( '', '.' ), (string) $value );
	$value = preg_replace( '/[^0-9.\-]/', '', $value );

	if ( '' === $value || null === $value ) {
		return 0;
	}

	return (int) max( 0, round( (float) $value * 100 ) );
}

/**
 * Formats cents for display, ex: 1250 => "12,50 €".
 *
 * @param int $cents Amount in cents.
 * @return string
 */
function healtheat_format_price( $cents ) {
	$cents    = (int) $cents;
	$currency = healtheat_get_setting( 'currency', '€' );
	$amount   = number_format_i18n( $cents / 100, 2 );

	return trim( $amount . ' ' . $currency );
}

/**
 * Returns the price of a dish, in cents.
 *
 * @param int $dish_id Dish post ID.
 * @return int
 */
function healtheat_get_dish_price( $dish_id ) {
	return (int) get_post_meta( $dish_id, '_healtheat_price', true );
}

/**
 * Tells whether a dish can currently be ordered.
 *
 * @param int $dish_id Dish post ID.
 * @return bool
 */
function healtheat_dish_is_available( $dish_id ) {
	if ( 'publish' !== get_post_status( $dish_id ) ) {
		return false;
	}

	return 'no' !== get_post_meta( $dish_id, '_healtheat_available', true );
}

/**
 * Returns the nutrition facts of a dish.
 *
 * @param int $dish_id Dish post ID.
 * @return array<string,float|int>
 */
function healtheat_get_nutrition( $dish_id ) {
	return array(
		'calories' => (int) get_post_meta( $dish_id, '_healtheat_calories', true ),
		'protein'  => (float) get_post_meta( $dish_id, '_healtheat_protein', true ),
		'carbs'    => (float) get_post_meta( $dish_id, '_healtheat_carbs', true ),
		'fat'      => (float) get_post_meta( $dish_id, '_healtheat_fat', true ),
		'fiber'    => (float) get_post_meta( $dish_id, '_healtheat_fiber', true ),
	);
}

/**
 * Returns the labels of the terms attached to a dish for a given taxonomy.
 *
 * @param int    $dish_id  Dish post ID.
 * @param string $taxonomy Taxonomy name.
 * @return string[]
 */
function healtheat_get_term_names( $dish_id, $taxonomy ) {
	$terms = get_the_terms( $dish_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	return wp_list_pluck( $terms, 'name' );
}

/**
 * Returns the slugs of the terms attached to a dish for a given taxonomy.
 *
 * @param int    $dish_id  Dish post ID.
 * @param string $taxonomy Taxonomy name.
 * @return string[]
 */
function healtheat_get_term_slugs( $dish_id, $taxonomy ) {
	$terms = get_the_terms( $dish_id, $taxonomy );

	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}

	return wp_list_pluck( $terms, 'slug' );
}

/**
 * Returns the front-end payload describing a dish.
 *
 * @param int|WP_Post $dish Dish post or ID.
 * @return array<string,mixed>
 */
function healtheat_get_dish_data( $dish ) {
	$dish = get_post( $dish );

	if ( ! $dish ) {
		return array();
	}

	$price = healtheat_get_dish_price( $dish->ID );

	return array(
		'id'          => (int) $dish->ID,
		'name'        => get_the_title( $dish ),
		'permalink'   => get_permalink( $dish ),
		'excerpt'     => wp_strip_all_tags( get_the_excerpt( $dish ) ),
		'price'       => $price,
		'price_html'  => healtheat_format_price( $price ),
		'image'       => get_the_post_thumbnail_url( $dish, 'healtheat-card' ),
		'image_id'    => (int) get_post_thumbnail_id( $dish ),
		'photos'      => healtheat_get_dish_photos( $dish->ID ),
		'available'   => healtheat_dish_is_available( $dish->ID ),
		'nutrition'   => healtheat_get_nutrition( $dish->ID ),
		'diets'       => healtheat_get_term_names( $dish->ID, 'healtheat_diet' ),
		'allergens'   => healtheat_get_term_names( $dish->ID, 'healtheat_allergen' ),
		'categories'  => healtheat_get_term_names( $dish->ID, 'healtheat_dish_cat' ),
	);
}

/**
 * Renders the photo of a dish, ready for responsive display.
 *
 * The tiny blurred preview stored at upload time is painted behind the
 * image, so the layout never jumps and the photo fades in once decoded.
 *
 * @param int    $dish_id Dish ID.
 * @param string $size    Registered image size.
 * @param array  $attrs   Extra image attributes.
 * @return string Empty string when the dish has no photo.
 */
function healtheat_dish_photo( $dish_id, $size = 'healtheat-card', $attrs = array() ) {
	$photo_id = get_post_thumbnail_id( $dish_id );

	if ( ! $photo_id ) {
		return '';
	}

	$attrs = wp_parse_args(
		$attrs,
		array(
			'class'    => 'healtheat-photo',
			'loading'  => 'lazy',
			'decoding' => 'async',
			'sizes'    => '(max-width: 600px) 100vw, (max-width: 1000px) 50vw, 33vw',
		)
	);

	$preview = Healtheat_Media::get_placeholder( $photo_id );

	if ( $preview ) {
		$attrs['class'] .= ' healtheat-photo--blurup';
		$attrs['style']  = 'background-image:url(' . $preview . ');';
	}

	return (string) wp_get_attachment_image( $photo_id, $size, false, $attrs );
}

/**
 * Returns the photo IDs of a dish: featured image first, then its gallery.
 *
 * @param int $dish_id Dish ID.
 * @return int[]
 */
function healtheat_get_dish_photos( $dish_id ) {
	$photos = array();
	$thumb  = (int) get_post_thumbnail_id( $dish_id );

	if ( $thumb ) {
		$photos[] = $thumb;
	}

	foreach ( Healtheat_Media::get_gallery( $dish_id ) as $photo_id ) {
		if ( ! in_array( $photo_id, $photos, true ) ) {
			$photos[] = $photo_id;
		}
	}

	return $photos;
}

/**
 * Returns the photos of the published dishes, newest first.
 *
 * Used by the theme to build the photographic hero and marquee.
 *
 * @param int $limit Maximum number of dishes.
 * @return array<int,array<string,mixed>> Dish ID, title, permalink and photo ID.
 */
function healtheat_get_photo_dishes( $limit = 8 ) {
	$dishes = get_posts(
		array(
			'post_type'      => 'healtheat_dish',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_thumbnail_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	$results = array();

	foreach ( $dishes as $dish ) {
		$results[] = array(
			'id'        => (int) $dish->ID,
			'title'     => get_the_title( $dish ),
			'permalink' => get_permalink( $dish ),
			'photo_id'  => (int) get_post_thumbnail_id( $dish ),
		);
	}

	return $results;
}

/**
 * Returns the tags and attributes allowed when a theme injects an SVG.
 *
 * @return array<string,array<string,bool>>
 */
function healtheat_svg_allowed_html() {
	$attributes = array(
		'class'             => true,
		'style'             => true,
		'aria-hidden'       => true,
		'focusable'         => true,
		'role'              => true,
		'viewbox'           => true,
		'xmlns'             => true,
		'width'             => true,
		'height'            => true,
		'fill'              => true,
		'stroke'            => true,
		'stroke-width'      => true,
		'stroke-linecap'    => true,
		'stroke-linejoin'   => true,
		'stroke-dasharray'  => true,
		'opacity'           => true,
		'transform'         => true,
		'd'                 => true,
		'cx'                => true,
		'cy'                => true,
		'r'                 => true,
		'rx'                => true,
		'ry'                => true,
		'x'                 => true,
		'y'                 => true,
		'x1'                => true,
		'y1'                => true,
		'x2'                => true,
		'y2'                => true,
		'points'            => true,
		'offset'            => true,
		'stop-color'        => true,
		'stop-opacity'      => true,
		'id'                => true,
		'gradientunits'     => true,
	);

	return array(
		'svg'            => $attributes,
		'g'              => $attributes,
		'path'           => $attributes,
		'circle'         => $attributes,
		'ellipse'        => $attributes,
		'rect'           => $attributes,
		'line'           => $attributes,
		'polyline'       => $attributes,
		'polygon'        => $attributes,
		'defs'           => $attributes,
		'lineargradient' => $attributes,
		'radialgradient' => $attributes,
		'stop'           => $attributes,
		'title'          => $attributes,
	);
}

/**
 * Returns the URL of the click &amp; collect page.
 *
 * @return string
 */
function healtheat_get_order_page_url() {
	$page_id = (int) healtheat_get_setting( 'order_page_id', 0 );

	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return get_permalink( $page_id );
	}

	return home_url( '/' );
}

/**
 * Tells whether online ordering is switched on.
 *
 * @return bool
 */
function healtheat_ordering_enabled() {
	return (bool) healtheat_get_setting( 'ordering_enabled', true );
}
