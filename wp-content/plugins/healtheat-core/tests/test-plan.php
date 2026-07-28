<?php
/**
 * Harnais de test hors WordPress : besoins caloriques, éligibilité des
 * plats, notation et composition de la semaine.
 *
 * Lancement : php wp-content/plugins/healtheat-core/tests/test-plan.php
 */

define( 'ABSPATH', __DIR__ );

function __( $text ) { return $text; }
function add_action() {}
function add_filter() {}
function add_shortcode() {}

require __DIR__ . '/../includes/class-healtheat-plan.php';

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

/**
 * Fabrique un plat de test.
 *
 * @param int   $id       Identifiant.
 * @param array $overrides Valeurs à remplacer.
 * @return array<string,mixed>
 */
function dish( $id, array $overrides = array() ) {
	return array_merge(
		array(
			'id'        => $id,
			'name'      => 'Plat ' . $id,
			'available' => true,
			'calories'  => 600,
			'protein'   => 25,
			'carbs'     => 60,
			'fat'       => 20,
			'fiber'     => 8,
			'diets'     => array(),
			'allergens' => array(),
			'nutrients' => array(),
		),
		$overrides
	);
}

// --- Besoins caloriques --------------------------------------------------

$base = array( 'sex' => 'femme', 'age' => 35, 'height' => 168, 'weight' => 62, 'activity' => 'modere' );

$maintien = Healtheat_Plan::daily_calories( array_merge( $base, array( 'goal' => 'maintien' ) ) );
$perte    = Healtheat_Plan::daily_calories( array_merge( $base, array( 'goal' => 'perte' ) ) );
$prise    = Healtheat_Plan::daily_calories( array_merge( $base, array( 'goal' => 'prise' ) ) );

check( 'besoin de maintien plausible', $maintien > 1600 && $maintien < 2400, "($maintien kcal)" );
check( 'perte de poids : besoin réduit', $perte < $maintien );
check( 'prise de muscle : besoin augmenté', $prise > $maintien );
check(
	'homme plus élevé que femme, à morphologie égale',
	Healtheat_Plan::daily_calories( array_merge( $base, array( 'sex' => 'homme', 'goal' => 'maintien' ) ) ) > $maintien
);
check(
	'sédentaire moins que sportif',
	Healtheat_Plan::daily_calories( array_merge( $base, array( 'activity' => 'sedentaire' ) ) )
		< Healtheat_Plan::daily_calories( array_merge( $base, array( 'activity' => 'sportif' ) ) )
);

check( 'données manquantes : aucun chiffre inventé', 0 === Healtheat_Plan::daily_calories( array( 'goal' => 'perte' ) ) );
check( 'valeurs aberrantes écartées', 0 === Healtheat_Plan::daily_calories( array_merge( $base, array( 'weight' => 800 ) ) ) );

check( 'le déjeuner vaut environ un tiers de la journée', abs( Healtheat_Plan::lunch_target( array_merge( $base, array( 'goal' => 'maintien' ) ) ) - $maintien * 0.35 ) < 2 );
check( 'objectif saisi à la main prioritaire', 700 === Healtheat_Plan::lunch_target( array( 'kcal_target' => 2000 ) ) );

// --- Éligibilité ---------------------------------------------------------

$profile = array( 'goal' => 'maintien', 'allergens' => array( 'gluten' ), 'diets' => array( 'vegan' ) );

check( 'allergène présent : plat écarté', ! Healtheat_Plan::is_eligible( dish( 1, array( 'allergens' => array( 'gluten' ), 'diets' => array( 'vegan' ) ) ), $profile ) );
check( 'régime absent : plat écarté', ! Healtheat_Plan::is_eligible( dish( 2, array( 'diets' => array() ) ), $profile ) );
check( 'plat conforme : retenu', Healtheat_Plan::is_eligible( dish( 3, array( 'diets' => array( 'vegan', 'sans-gluten' ) ) ), $profile ) );
check( 'plat indisponible : écarté', ! Healtheat_Plan::is_eligible( dish( 4, array( 'available' => false, 'diets' => array( 'vegan' ) ) ), $profile ) );
check(
	'plusieurs régimes exigés simultanément',
	! Healtheat_Plan::is_eligible(
		dish( 5, array( 'diets' => array( 'vegan' ) ) ),
		array( 'diets' => array( 'vegan', 'sans-gluten' ) )
	)
);

// --- Notation ------------------------------------------------------------

$target = array( 'goal' => 'maintien', 'kcal_target' => 2000 ); // 700 kcal au déjeuner.

check(
	'plat proche de la cible mieux noté',
	Healtheat_Plan::score( dish( 6, array( 'calories' => 700 ) ), $target )
		> Healtheat_Plan::score( dish( 7, array( 'calories' => 1200 ) ), $target )
);

check(
	'prise de muscle : les protéines comptent',
	Healtheat_Plan::score( dish( 8, array( 'protein' => 45 ) ), array( 'goal' => 'prise', 'kcal_target' => 2000 ) )
		> Healtheat_Plan::score( dish( 9, array( 'protein' => 10 ) ), array( 'goal' => 'prise', 'kcal_target' => 2000 ) )
);

check(
	'perte de poids : les fibres comptent',
	Healtheat_Plan::score( dish( 10, array( 'fiber' => 14 ) ), array( 'goal' => 'perte', 'kcal_target' => 2000 ) )
		> Healtheat_Plan::score( dish( 11, array( 'fiber' => 2 ) ), array( 'goal' => 'perte', 'kcal_target' => 2000 ) )
);

$marker_profile = array( 'goal' => 'maintien', 'kcal_target' => 2000, 'markers' => array( 'fer_bas' ) );

check(
	'marqueur fer bas : le plat qui en apporte passe devant',
	Healtheat_Plan::score( dish( 12, array( 'nutrients' => array( 'riche-en-fer' ) ) ), $marker_profile )
		> Healtheat_Plan::score( dish( 13 ), $marker_profile )
);

$chol = array( 'goal' => 'maintien', 'kcal_target' => 2000, 'markers' => array( 'cholesterol_haut' ) );

check(
	'cholestérol élevé : le plat gras est pénalisé',
	Healtheat_Plan::score( dish( 14, array( 'fat' => 45 ) ), $chol )
		< Healtheat_Plan::score( dish( 15, array( 'fat' => 8 ) ), $chol )
);

check(
	'marqueur inconnu ignoré sans erreur',
	is_float( Healtheat_Plan::score( dish( 16 ), array( 'markers' => array( 'inventé' ), 'kcal_target' => 2000 ) ) )
);

// --- Catégories autorisées ----------------------------------------------

$meals = array( 'categories' => array( 'bowls', 'salades' ) );

check(
	'un jus n\'entre pas dans le menu du midi',
	! Healtheat_Plan::is_eligible( dish( 17, array( 'categories' => array( 'jus-smoothies' ) ) ), $meals )
);
check(
	'un bowl y entre',
	Healtheat_Plan::is_eligible( dish( 18, array( 'categories' => array( 'bowls' ) ) ), $meals )
);
check(
	'aucune catégorie exigée : toute la carte est utilisable',
	Healtheat_Plan::is_eligible( dish( 19, array( 'categories' => array( 'desserts' ) ) ), array() )
);

// --- Composition de la semaine -------------------------------------------

$days = array();

foreach ( array( 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi' ) as $index => $label ) {
	$days[] = array( 'date' => '2026-08-0' . ( $index + 3 ), 'label' => $label );
}

$catalogue = array(
	dish( 21, array( 'calories' => 700, 'nutrients' => array( 'riche-en-fer' ), 'categories' => array( 'bowls' ) ) ),
	dish( 22, array( 'calories' => 690, 'categories' => array( 'bowls' ) ) ),
	dish( 23, array( 'calories' => 720, 'categories' => array( 'salades' ) ) ),
	dish( 24, array( 'calories' => 1400, 'categories' => array( 'bowls' ) ) ),
	dish( 25, array( 'allergens' => array( 'gluten' ), 'categories' => array( 'bowls' ) ) ),
	dish( 26, array( 'calories' => 120, 'categories' => array( 'jus-smoothies' ) ) ),
);

$profile_semaine = array(
	'goal'       => 'maintien',
	'kcal_target' => 2000,
	'allergens'  => array( 'gluten' ),
	'categories' => array( 'bowls', 'salades' ),
);

$plan = Healtheat_Plan::build_week( $catalogue, $profile_semaine, $days );

check( 'une proposition par jour ouvré', 5 === count( $plan ), '(' . count( $plan ) . ')' );

$ids = array_map( static function ( $day ) { return $day['dish']['id']; }, $plan );

check( 'le plat allergène n\'apparaît jamais', ! in_array( 25, $ids, true ) );
check( 'le jus n\'apparaît jamais au déjeuner', ! in_array( 26, $ids, true ) );

$repeat = false;

for ( $i = 1; $i < count( $ids ); $i++ ) {
	if ( $ids[ $i ] === $ids[ $i - 1 ] ) {
		$repeat = true;
	}
}

check( 'jamais le même plat deux jours de suite', ! $repeat, '(' . implode( ', ', $ids ) . ')' );
check( 'les quatre plats éligibles sont utilisés', count( array_unique( $ids ) ) >= 4, '(' . count( array_unique( $ids ) ) . ' distincts)' );
check( 'les jours conservent leur libellé', 'lundi' === $plan[0]['label'] && 'vendredi' === $plan[4]['label'] );

$empty = Healtheat_Plan::build_week( $catalogue, array( 'diets' => array( 'regime-inexistant' ) ), $days );
check( 'aucun plat éligible : semaine vide plutôt qu\'approximative', array() === $empty );

$single = Healtheat_Plan::build_week( array( dish( 30, array( 'categories' => array( 'bowls' ) ) ) ), array( 'kcal_target' => 2000, 'categories' => array( 'bowls' ) ), $days );
check( 'un seul plat disponible : la semaine reste remplie', 5 === count( $single ) );

// Deux compositions successives, mêmes données : même résultat.
$again = Healtheat_Plan::build_week( $catalogue, $profile_semaine, $days );
check( 'résultat stable à données égales', $ids === array_map( static function ( $day ) { return $day['dish']['id']; }, $again ) );

echo $failures ? "\n$failures test(s) en échec\n" : "\nTous les tests passent\n";
exit( $failures ? 1 : 0 );
