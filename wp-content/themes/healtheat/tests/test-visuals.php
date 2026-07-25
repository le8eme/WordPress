<?php
/**
 * Harnais de test hors WordPress : valide les illustrations SVG et le
 * découpage du titre animé.
 *
 * Lancement : php wp-content/themes/healtheat/tests/test-visuals.php
 */

define( 'ABSPATH', __DIR__ );
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function add_filter() {}
function apply_filters( $t, $v ) { return $v; }
function wp_strip_all_tags( $v, $breaks = false ) { return strip_tags( (string) $v ); }
function get_template_directory() { return dirname( __DIR__ ); }
function get_template_directory_uri() { return 'http://example.test/theme'; }
function add_action() {}
function add_theme_support() {}
function add_editor_style() {}
function register_nav_menus() {}
function add_image_size() {}
function load_theme_textdomain() {}
function register_sidebar() {}
function get_theme_mod( $key, $default = '' ) { return $default; }
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
require dirname( __DIR__ ) . '/functions.php';

$fails = 0;
$names = array( 'avocado', 'leaf', 'tomato', 'citrus', 'berry', 'broccoli', 'grain', 'carrot', 'bowl', 'droplet' );

foreach ( $names as $name ) {
	$svg = healtheat_food_svg( $name );
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $svg );

	if ( ! $xml ) {
		$fails++;
		echo "  FAIL $name : XML invalide\n";
		continue;
	}

	printf( "  OK   %-9s %d éléments, %d octets\n", $name, count( $xml->children() ), strlen( $svg ) );
}

// Titre animé : découpage en mots + segment mis en avant.
$html = healtheat_animated_title( 'Manger *sainement*, sans y passer sa pause.' );
$words = substr_count( $html, 'class="word"' );
$em    = substr_count( $html, '<em>' );
printf( "  %s   titre animé : %d mots, %d segment(s) en dégradé\n", ( 7 === $words && 1 === $em ) ? 'OK  ' : 'FAIL', $words, $em );
$fails += ( 7 === $words && 1 === $em ) ? 0 : 1;

// Aucun aliment inconnu ne doit produire de balise vide.
$fails += ( '' === healtheat_food_svg( 'inconnu' ) ) ? 0 : 1;
echo ( '' === healtheat_food_svg( 'inconnu' ) ) ? "  OK   aliment inconnu : rien n'est rendu\n" : "  FAIL aliment inconnu\n";

echo $fails ? "\n$fails échec(s)\n" : "\nTous les visuels sont valides\n";
exit( $fails ? 1 : 0 );
