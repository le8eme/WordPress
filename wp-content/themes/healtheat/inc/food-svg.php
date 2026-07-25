<?php
/**
 * Illustrations d'aliments en SVG inline.
 *
 * Chaque forme est découpée en parties nommées (`.food__shell`, `.food__pit`,
 * `.food__vein`…) pour que la feuille d'animations puisse les faire vivre
 * indépendamment les unes des autres.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the raw markup of a food illustration.
 *
 * @param string $name Food key.
 * @return string
 */
function healtheat_food_shapes( $name ) {
	$shapes = array(

		'avocado'  => '<path class="food__shell" d="M50 8c19 0 31 20 31 43 0 24-13 41-31 41S19 75 19 51C19 28 31 8 50 8z"/>
			<path class="food__flesh" d="M50 20c12 0 20 15 20 31 0 17-9 29-20 29s-20-12-20-29c0-16 8-31 20-31z"/>
			<circle class="food__pit" cx="50" cy="55" r="13"/>',

		'leaf'     => '<path class="food__shell" d="M16 86C10 50 34 14 86 12c4 40-22 72-70 74z"/>
			<path class="food__vein" d="M16 86C34 66 56 44 84 20"/>
			<path class="food__vein" d="M40 74c2-12 0-22-4-30M58 60c3-12 2-23-2-32M72 44c3-10 3-19 1-26"/>',

		'tomato'   => '<circle class="food__shell" cx="50" cy="58" r="34"/>
			<path class="food__flesh" d="M36 44c-6 5-9 12-9 19"/>
			<path class="food__stem" d="M50 26l-15-9 6 13-15 2 13 6M50 26l15-9-6 13 15 2-13 6"/>
			<path class="food__stem" d="M50 14v14"/>',

		'citrus'   => '<circle class="food__shell" cx="50" cy="50" r="38"/>
			<circle class="food__flesh" cx="50" cy="50" r="30"/>
			<g class="food__segments">
				<line x1="50" y1="50" x2="50" y2="21"/>
				<line x1="50" y1="50" x2="71" y2="29"/>
				<line x1="50" y1="50" x2="79" y2="50"/>
				<line x1="50" y1="50" x2="71" y2="71"/>
				<line x1="50" y1="50" x2="50" y2="79"/>
				<line x1="50" y1="50" x2="29" y2="71"/>
				<line x1="50" y1="50" x2="21" y2="50"/>
				<line x1="50" y1="50" x2="29" y2="29"/>
			</g>
			<circle class="food__pit" cx="50" cy="50" r="5"/>',

		'berry'    => '<circle class="food__shell" cx="50" cy="56" r="30"/>
			<path class="food__stem" d="M50 26l-11-7 4 10-11 2 10 5M50 26l11-7-4 10 11 2-10 5"/>
			<circle class="food__flesh" cx="39" cy="45" r="7"/>',

		'broccoli' => '<path class="food__stem" d="M42 92V58h16v34z"/>
			<circle class="food__shell" cx="32" cy="46" r="17"/>
			<circle class="food__shell" cx="68" cy="46" r="17"/>
			<circle class="food__shell" cx="50" cy="32" r="20"/>
			<circle class="food__flesh" cx="42" cy="34" r="6"/>
			<circle class="food__flesh" cx="62" cy="44" r="5"/>',

		'grain'    => '<ellipse class="food__shell" cx="32" cy="42" rx="10" ry="19" transform="rotate(-24 32 42)"/>
			<ellipse class="food__shell" cx="60" cy="34" rx="10" ry="19" transform="rotate(18 60 34)"/>
			<ellipse class="food__shell" cx="48" cy="70" rx="10" ry="19" transform="rotate(-6 48 70)"/>
			<path class="food__vein" d="M32 26v32M60 18v32M48 54v32"/>',

		'carrot'   => '<path class="food__shell" d="M50 94L34 40c10-7 22-7 32 0z"/>
			<path class="food__vein" d="M40 56h20M43 70h14"/>
			<path class="food__stem" d="M50 40V18M50 26L34 12M50 26l16-14"/>',

		'bowl'     => '<path class="food__shell" d="M10 48h80c0 24-18 42-40 42S10 72 10 48z"/>
			<ellipse class="food__rim" cx="50" cy="48" rx="40" ry="9"/>
			<path class="food__vein" d="M26 62c6 9 14 14 24 15"/>',

		'droplet'  => '<path class="food__shell" d="M50 10c16 20 26 33 26 46a26 26 0 11-52 0c0-13 10-26 26-46z"/>
			<path class="food__flesh" d="M38 58c0 9 5 15 12 17"/>',
	);

	return $shapes[ $name ] ?? '';
}

/**
 * Prints a food illustration.
 *
 * @param string $name    Food key.
 * @param string $classes Extra CSS classes.
 * @return string
 */
function healtheat_food_svg( $name, $classes = '' ) {
	$shapes = healtheat_food_shapes( $name );

	if ( ! $shapes ) {
		return '';
	}

	return sprintf(
		'<svg class="food food--%1$s %2$s" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $name ),
		esc_attr( $classes ),
		$shapes
	);
}

/**
 * Returns the food keys used for the ambient animations.
 *
 * @return string[]
 */
function healtheat_food_keys() {
	return array( 'avocado', 'leaf', 'tomato', 'citrus', 'berry', 'broccoli', 'grain', 'carrot' );
}

/**
 * Replaces the plugin dish placeholder with an animated illustration.
 *
 * @param string $placeholder Default placeholder.
 * @param int    $dish_id     Dish ID.
 * @return string
 */
function healtheat_dish_svg_placeholder( $placeholder, $dish_id ) {
	$keys = healtheat_food_keys();
	$key  = $keys[ (int) $dish_id % count( $keys ) ];

	return healtheat_food_svg( $key, 'food--placeholder is-spinning' );
}
add_filter( 'healtheat_dish_placeholder', 'healtheat_dish_svg_placeholder', 10, 2 );
