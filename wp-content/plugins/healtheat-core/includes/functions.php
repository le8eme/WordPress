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
		'image'       => get_the_post_thumbnail_url( $dish, 'healtheat-dish' ),
		'available'   => healtheat_dish_is_available( $dish->ID ),
		'nutrition'   => healtheat_get_nutrition( $dish->ID ),
		'diets'       => healtheat_get_term_names( $dish->ID, 'healtheat_diet' ),
		'allergens'   => healtheat_get_term_names( $dish->ID, 'healtheat_allergen' ),
		'categories'  => healtheat_get_term_names( $dish->ID, 'healtheat_dish_cat' ),
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
