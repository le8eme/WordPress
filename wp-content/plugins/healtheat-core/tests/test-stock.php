<?php
/**
 * Harnais de test hors WordPress : lecture des résultats de la banque
 * d'images et contrôle de l'hôte avant tout téléchargement.
 *
 * Lancement : php wp-content/plugins/healtheat-core/tests/test-stock.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function __( $text ) { return $text; }

/**
 * Version minimaliste de la fonction WordPress du même nom.
 *
 * @param string $url       Adresse à découper.
 * @param int    $component Constante PHP_URL_*.
 * @return mixed
 */
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

require __DIR__ . '/../includes/class-healtheat-stock.php';

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

// --- Contrôle de l'hôte --------------------------------------------------

check( 'hôte officiel accepté', Healtheat_Stock::url_is_allowed( 'https://images.pexels.com/photos/1/x.jpg' ) );
check( 'casse ignorée', Healtheat_Stock::url_is_allowed( 'https://IMAGES.PEXELS.COM/photos/1/x.jpg' ) );
check( 'autre domaine refusé', ! Healtheat_Stock::url_is_allowed( 'https://exemple.test/photo.jpg' ) );
check( 'sous-domaine trompeur refusé', ! Healtheat_Stock::url_is_allowed( 'https://images.pexels.com.exemple.test/photo.jpg' ) );
check( 'chemin local refusé', ! Healtheat_Stock::url_is_allowed( '/wp-content/uploads/photo.jpg' ) );
check( 'adresse vide refusée', ! Healtheat_Stock::url_is_allowed( '' ) );

// --- Lecture de la réponse ----------------------------------------------

$photos = Healtheat_Stock::parse_response( pexels_fixture() );

check( 'seules les photos exploitables sont retenues', 2 === count( $photos ), '(' . count( $photos ) . ')' );
check( 'le grand format est préféré', isset( $photos[0]['full'] ) && false !== strpos( $photos[0]['full'], 'large2x' ) );
check( 'aperçu récupéré', isset( $photos[0]['preview'] ) && false !== strpos( $photos[0]['preview'], 'medium' ) );
check( 'photographe conservé', 'Marta Cuisine' === ( $photos[0]['photographer'] ?? '' ) );
check( 'page de la photo conservée', ! empty( $photos[0]['page'] ) );
check( 'repli sur large quand large2x manque', isset( $photos[1]['full'] ) && false !== strpos( $photos[1]['full'], '/large.jpg' ), $photos[1]['full'] ?? '' );

$hosts_ok = true;

foreach ( $photos as $photo ) {
	$hosts_ok = $hosts_ok && Healtheat_Stock::url_is_allowed( $photo['full'] );
}

check( 'aucune adresse hors banque ne franchit le filtre', $hosts_ok );

check( 'réponse illisible : aucun résultat', array() === Healtheat_Stock::parse_response( 'pas du json' ) );
check( 'réponse sans photos : aucun résultat', array() === Healtheat_Stock::parse_response( '{"photos":[]}' ) );
check( 'réponse vide : aucun résultat', array() === Healtheat_Stock::parse_response( '' ) );

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent\n";
exit( $failures ? 1 : 0 );

/**
 * Réponse type de l'API : une photo complète, une sans large2x, une servie
 * par un autre domaine, une sans bloc src.
 *
 * @return string
 */
function pexels_fixture() {
	return json_encode(
		array(
			'page'          => 1,
			'total_results' => 4,
			'photos'        => array(
				array(
					'id'               => 3184183,
					'url'              => 'https://www.pexels.com/photo/quinoa-bowl-3184183/',
					'photographer'     => 'Marta Cuisine',
					'photographer_url' => 'https://www.pexels.com/@marta',
					'alt'              => 'Bowl de quinoa et légumes rôtis',
					'src'              => array(
						'original' => 'https://images.pexels.com/photos/3184183/original.jpg',
						'large2x'  => 'https://images.pexels.com/photos/3184183/large2x.jpg',
						'large'    => 'https://images.pexels.com/photos/3184183/large.jpg',
						'medium'   => 'https://images.pexels.com/photos/3184183/medium.jpg',
					),
				),
				array(
					'id'           => 4113,
					'url'          => 'https://www.pexels.com/photo/salade-4113/',
					'photographer' => 'Léo Vert',
					'src'          => array(
						'original' => 'https://images.pexels.com/photos/4113/original.jpg',
						'large'    => 'https://images.pexels.com/photos/4113/large.jpg',
						'small'    => 'https://images.pexels.com/photos/4113/small.jpg',
					),
				),
				array(
					'id'           => 9999,
					'url'          => 'https://exemple.test/photo/9999',
					'photographer' => 'Inconnu',
					'src'          => array(
						'large2x' => 'https://cdn.exemple.test/photos/9999/large2x.jpg',
					),
				),
				array(
					'id'           => 12345,
					'photographer' => 'Sans fichier',
				),
			),
		)
	);
}
