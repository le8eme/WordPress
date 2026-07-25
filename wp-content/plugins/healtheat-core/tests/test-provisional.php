<?php
/**
 * Harnais de test hors WordPress : filtrage des licences, lecture de la
 * réponse de Wikimedia Commons et fabrication des visuels provisoires.
 *
 * Lancement : php wp-content/plugins/healtheat-core/tests/test-provisional.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function __( $text ) { return $text; }
function wp_strip_all_tags( $value ) { return trim( strip_tags( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }

class WP_Error {
	public $code;
	public $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

require __DIR__ . '/../includes/class-healtheat-provisional.php';

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

// --- Licences ------------------------------------------------------------

$allowed = array( 'CC0', 'Public domain', 'CC BY 4.0', 'CC BY-SA 3.0', 'cc-by-sa-4.0', 'PDM 1.0' );
$refused = array( 'CC BY-NC 4.0', 'CC BY-NC-SA 3.0', 'CC BY-ND 2.0', 'Fair use', 'Copyrighted, non-free', '', 'Tous droits réservés' );

foreach ( $allowed as $license ) {
	check( "licence acceptée : $license", Healtheat_Provisional::license_is_allowed( $license ) );
}

foreach ( $refused as $license ) {
	check( 'licence refusée : ' . ( $license ? $license : '(vide)' ), ! Healtheat_Provisional::license_is_allowed( $license ) );
}

// --- Lecture de la réponse Commons ---------------------------------------

$payload = wp_json_fixture();
$photos  = Healtheat_Provisional::parse_commons_response( $payload );

check( 'seules les photos réutilisables sont retenues', 1 === count( $photos ), '(' . count( $photos ) . ')' );
check( 'adresse récupérée', ! empty( $photos[0]['url'] ) && str_starts_with( $photos[0]['url'], 'https://' ) );
check( 'auteur nettoyé de son HTML', 'Jane Cook' === ( $photos[0]['author'] ?? '' ), '(' . ( $photos[0]['author'] ?? '' ) . ')' );
check( 'licence conservée', 'CC BY-SA 4.0' === ( $photos[0]['license'] ?? '' ) );
check( 'page source conservée', ! empty( $photos[0]['source'] ) );

check( 'réponse illisible : aucune photo', array() === Healtheat_Provisional::parse_commons_response( 'pas du json' ) );
check( 'réponse vide : aucune photo', array() === Healtheat_Provisional::parse_commons_response( '{"query":{"pages":[]}}' ) );
check( 'réponse sans clé query : aucune photo', array() === Healtheat_Provisional::parse_commons_response( '{"batchcomplete":true}' ) );

// --- Couleurs ------------------------------------------------------------

list( $r, $g, $b ) = Healtheat_Provisional::hue_to_rgb( 140, 0.6, 0.3 );
check( 'teinte convertie dans les bornes', $r >= 0 && $r <= 255 && $g >= 0 && $g <= 255 && $b >= 0 && $b <= 255 );
check( 'teinte verte : le vert domine', $g > $r && $g > $b, "($r,$g,$b)" );

$first  = Healtheat_Provisional::hue_to_rgb( 0, 0.6, 0.3 );
$second = Healtheat_Provisional::hue_to_rgb( 200, 0.6, 0.3 );
check( 'deux teintes donnent deux couleurs', $first !== $second );

// --- Visuel provisoire ---------------------------------------------------

if ( function_exists( 'imagecreatetruecolor' ) ) {
	$bytes = Healtheat_Provisional::render_tile_bytes( 7 );

	check( 'visuel fabriqué', is_string( $bytes ) && strlen( $bytes ) > 5000, is_string( $bytes ) ? '(' . strlen( $bytes ) . ' octets)' : '(erreur)' );

	$info = @getimagesizefromstring( $bytes );
	check( 'JPEG 1200x900 valide', $info && 1200 === $info[0] && 900 === $info[1] && IMAGETYPE_JPEG === $info[2] );

	$other = Healtheat_Provisional::render_tile_bytes( 8 );
	check( 'un plat différent donne un visuel différent', $bytes !== $other );

	if ( getenv( 'HEALTHEAT_WRITE_SAMPLE' ) ) {
		file_put_contents( getenv( 'HEALTHEAT_WRITE_SAMPLE' ), $bytes );
		echo "  ->   échantillon écrit dans " . getenv( 'HEALTHEAT_WRITE_SAMPLE' ) . "\n";
	}
} else {
	echo "  --   GD absent, fabrication des visuels non testée\n";
}

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent\n";
exit( $failures ? 1 : 0 );

/**
 * Réponse type de l'API Commons : une photo réutilisable, une en licence
 * non commerciale, une entrée incomplète.
 *
 * @return string
 */
function wp_json_fixture() {
	return json_encode(
		array(
			'query' => array(
				'pages' => array(
					array(
						'title'     => 'File:Quinoa bowl.jpg',
						'imageinfo' => array(
							array(
								'url'            => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Quinoa_bowl.jpg',
								'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Quinoa_bowl.jpg',
								'mime'           => 'image/jpeg',
								'extmetadata'    => array(
									'LicenseShortName' => array( 'value' => 'CC BY-SA 4.0' ),
									'Artist'           => array( 'value' => '<a href="//commons.wikimedia.org/wiki/User:Jane">Jane Cook</a>' ),
								),
							),
						),
					),
					array(
						'title'     => 'File:Salad closeup.jpg',
						'imageinfo' => array(
							array(
								'url'            => 'https://upload.wikimedia.org/wikipedia/commons/b/bc/Salad_closeup.jpg',
								'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Salad_closeup.jpg',
								'mime'           => 'image/jpeg',
								'extmetadata'    => array(
									'LicenseShortName' => array( 'value' => 'CC BY-NC 4.0' ),
									'Artist'           => array( 'value' => 'Someone' ),
								),
							),
						),
					),
					array(
						'title' => 'File:Broken entry.jpg',
					),
				),
			),
		)
	);
}
