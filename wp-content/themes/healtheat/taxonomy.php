<?php
/**
 * Taxonomy archives.
 *
 * Dish taxonomies reuse the menu layout; anything else falls back to the
 * standard post loop.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( is_tax( array( 'healtheat_dish_cat', 'healtheat_diet', 'healtheat_allergen' ) ) && healtheat_plugin_active() ) {
	include locate_template( 'archive-healtheat_dish.php' );

	return;
}

include locate_template( 'index.php' );
