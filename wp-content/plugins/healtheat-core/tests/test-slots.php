<?php
/**
 * Harnais de test hors WordPress : vérifie le calcul des créneaux de retrait,
 * la validation d'un créneau soumis et la conversion des montants.
 *
 * Lancement : php wp-content/plugins/healtheat-core/tests/test-slots.php
 */

define( 'ABSPATH', __DIR__ );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );

// --- Stubs WordPress minimalistes ---------------------------------------

$GLOBALS['options'] = array();

function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function __( $text ) { return $text; }
function _n_noop( $s, $p ) { return array( $s, $p ); }
function esc_html__( $text ) { return $text; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
function register_setting() {}
function add_submenu_page() {}
function wp_timezone() { return new DateTimeZone( 'Europe/Paris' ); }
function wp_date( $format, $timestamp = null ) {
	$date = new DateTime( '@' . ( $timestamp ?? time() ) );
	$date->setTimezone( wp_timezone() );

	return $date->format( $format );
}
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_email( $v ) { return (string) $v; }
function absint( $v ) { return abs( (int) $v ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( $n, $d, ',', ' ' ); }
function is_email( $v ) { return (bool) filter_var( $v, FILTER_VALIDATE_EMAIL ); }

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

// Faux $wpdb : deux commandes déjà posées sur le même créneau.
class Fake_Wpdb {
	public $postmeta = 'wp_postmeta';
	public $posts    = 'wp_posts';
	public $booked   = array();

	public function prepare( $query, ...$args ) { return $query; }
	public function esc_like( $text ) { return $text; }

	public function get_results() {
		$rows = array();

		foreach ( $this->booked as $slot => $count ) {
			$rows[] = array( 'pickup' => $slot, 'total' => $count );
		}

		return $rows;
	}
}

$GLOBALS['wpdb'] = new Fake_Wpdb();

require __DIR__ . '/../includes/class-healtheat-settings.php';
require __DIR__ . '/../includes/class-healtheat-slots.php';
require __DIR__ . '/../includes/functions.php';

// --- Scénario ------------------------------------------------------------

$failures = 0;

/**
 * Assertion minimaliste.
 *
 * @param string $label Nom du test.
 * @param bool   $ok    Résultat.
 * @param string $extra Détail affiché en cas d'échec.
 */
function check( $label, $ok, $extra = '' ) {
	global $failures;

	if ( $ok ) {
		echo "  OK   $label\n";

		return;
	}

	$failures++;
	echo "  FAIL $label $extra\n";
}

// Horaires : ouvert tous les jours 11h30-14h30, créneaux de 15 min, 2 places.
$hours = array();

foreach ( range( 0, 6 ) as $day ) {
	$hours[ $day ] = array( 'enabled' => 1, 'open' => '11:30', 'close' => '14:30' );
}

update_option(
	Healtheat_Settings::OPTION,
	array_merge(
		Healtheat_Settings::get_defaults(),
		array(
			'hours'         => $hours,
			'slot_interval' => 15,
			'slot_capacity' => 2,
			'lead_time'     => 30,
			'days_ahead'    => 3,
		)
	)
);
Healtheat_Settings::flush_cache();

$days = Healtheat_Slots::get_available_days();

check( 'au plus days_ahead journées', count( $days ) <= 3, '(' . count( $days ) . ')' );
check( 'au moins une journée ouverte', count( $days ) >= 1 );

$last = end( $days );
check( '13 créneaux sur une journée complète (11h30→14h30)', 13 === count( $last['slots'] ), '(' . count( $last['slots'] ) . ')' );
check( 'premier créneau à 11:30', '11:30' === $last['slots'][0]['label'], '(' . $last['slots'][0]['label'] . ')' );
check( 'dernier créneau à 14:30', '14:30' === $last['slots'][ count( $last['slots'] ) - 1 ]['label'] );

// Aucun créneau ne doit être antérieur à maintenant + lead time.
$earliest = new DateTimeImmutable( '+30 minutes', wp_timezone() );
$past     = 0;

foreach ( $days as $day ) {
	foreach ( $day['slots'] as $slot ) {
		if ( new DateTimeImmutable( $slot['value'], wp_timezone() ) < $earliest ) {
			$past++;
		}
	}
}

check( 'aucun créneau avant le délai de préparation', 0 === $past, "($past en trop)" );

// Capacité : on remplit un créneau et il doit devenir complet puis refusé.
$target = $last['slots'][3]['value'];
$GLOBALS['wpdb']->booked = array( $target => 2 );

$days  = Healtheat_Slots::get_available_days();
$found = null;

foreach ( $days as $day ) {
	foreach ( $day['slots'] as $slot ) {
		if ( $slot['value'] === $target ) {
			$found = $slot;
		}
	}
}

check( 'créneau saturé marqué complet', $found && $found['full'] && 0 === $found['left'] );
check( 'créneau complet refusé', is_wp_error( Healtheat_Slots::validate( $target ) ) );
check( 'créneau inconnu refusé', is_wp_error( Healtheat_Slots::validate( '2019-01-01 12:00' ) ) );

$GLOBALS['wpdb']->booked = array();
check( 'créneau libre accepté', true === Healtheat_Slots::validate( $target ) );

// Jour fermé : plus aucun créneau.
$closed = $hours;

foreach ( $closed as $day => $row ) {
	$closed[ $day ]['enabled'] = 0;
}

update_option(
	Healtheat_Settings::OPTION,
	array_merge( get_option( Healtheat_Settings::OPTION ), array( 'hours' => $closed ) )
);
Healtheat_Settings::flush_cache();

check( 'restaurant fermé : aucune journée', array() === Healtheat_Slots::get_available_days() );

// Conversion des montants.
check( 'healtheat_to_cents( "12,50" ) === 1250', 1250 === healtheat_to_cents( '12,50' ) );
check( 'healtheat_to_cents( "9.99 €" ) === 999', 999 === healtheat_to_cents( '9.99 €' ) );
check( 'healtheat_to_cents( "" ) === 0', 0 === healtheat_to_cents( '' ) );
check( 'healtheat_to_cents( -5 ) === 0', 0 === healtheat_to_cents( -5 ) );
check( 'total 3 × 13,90 = 41,70', 4170 === 3 * healtheat_to_cents( '13.90' ) );

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent\n";
exit( $failures ? 1 : 0 );
