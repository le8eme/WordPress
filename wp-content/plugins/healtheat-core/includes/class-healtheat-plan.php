<?php
/**
 * Menu de la semaine personnalisé.
 *
 * Le calcul est volontairement séparé de WordPress : `daily_calories()`,
 * `is_eligible()`, `score()` et `build_week()` ne dépendent que de tableaux,
 * ce qui les rend vérifiables hors du site.
 *
 * Aucune de ces fonctions ne pose de diagnostic : elles trient des plats
 * selon des préférences déclarées et selon les atouts que le restaurant a
 * lui-même attribués à ses recettes.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds a weekly lunch plan for a customer.
 */
class Healtheat_Plan {

	/**
	 * Part de l'apport quotidien attribuée au déjeuner.
	 */
	const LUNCH_SHARE = 0.35;

	/**
	 * Returns the supported goals.
	 *
	 * @return array<string,string>
	 */
	public static function get_goals() {
		return array(
			'perte'    => __( 'Perdre du poids', 'healtheat' ),
			'maintien' => __( 'Maintenir mon poids', 'healtheat' ),
			'prise'    => __( 'Prendre du muscle', 'healtheat' ),
			'energie'  => __( 'Avoir plus d\'énergie', 'healtheat' ),
		);
	}

	/**
	 * Returns the activity levels and their multiplier.
	 *
	 * @return array<string,array{label:string,factor:float}>
	 */
	public static function get_activities() {
		return array(
			'sedentaire' => array(
				'label'  => __( 'Sédentaire — bureau, peu de marche', 'healtheat' ),
				'factor' => 1.2,
			),
			'modere'     => array(
				'label'  => __( 'Modéré — marche quotidienne, sport occasionnel', 'healtheat' ),
				'factor' => 1.375,
			),
			'actif'      => array(
				'label'  => __( 'Actif — sport deux à trois fois par semaine', 'healtheat' ),
				'factor' => 1.55,
			),
			'sportif'    => array(
				'label'  => __( 'Sportif — entraînement quasi quotidien', 'healtheat' ),
				'factor' => 1.725,
			),
		);
	}

	/**
	 * Returns the declared blood markers and the dish strength they favour.
	 *
	 * La correspondance est volontairement explicite : chaque marqueur
	 * pointe vers un atout que le restaurant attribue à ses plats.
	 *
	 * @return array<string,array{label:string,nutrient:string,avoid:string}>
	 */
	public static function get_markers() {
		return array(
			'fer_bas'          => array(
				'label'    => __( 'Fer bas', 'healtheat' ),
				'nutrient' => 'riche-en-fer',
				'avoid'    => '',
			),
			'cholesterol_haut' => array(
				'label'    => __( 'Cholestérol élevé', 'healtheat' ),
				'nutrient' => 'riche-en-fibres',
				'avoid'    => 'fat',
			),
			'glycemie_haute'   => array(
				'label'    => __( 'Glycémie élevée', 'healtheat' ),
				'nutrient' => 'index-glycemique-bas',
				'avoid'    => 'carbs',
			),
			'vitamine_d_basse' => array(
				'label'    => __( 'Vitamine D basse', 'healtheat' ),
				'nutrient' => 'source-de-vitamine-d',
				'avoid'    => '',
			),
			'omega3_bas'       => array(
				'label'    => __( 'Oméga-3 insuffisants', 'healtheat' ),
				'nutrient' => 'riche-en-omega-3',
				'avoid'    => '',
			),
		);
	}

	/**
	 * Estimates the daily calorie need.
	 *
	 * Formule de Mifflin-St Jeor, puis facteur d'activité et ajustement
	 * selon l'objectif. Renvoie 0 si les données morphologiques manquent :
	 * mieux vaut ne rien afficher qu'un chiffre inventé.
	 *
	 * @param array<string,mixed> $profile Customer profile.
	 * @return int Calories par jour, 0 si incalculable.
	 */
	public static function daily_calories( array $profile ) {
		$weight = (float) ( $profile['weight'] ?? 0 );
		$height = (float) ( $profile['height'] ?? 0 );
		$age    = (int) ( $profile['age'] ?? 0 );

		if ( $weight < 30 || $weight > 300 || $height < 120 || $height > 230 || $age < 14 || $age > 100 ) {
			return 0;
		}

		switch ( $profile['sex'] ?? '' ) {
			case 'homme':
				$offset = 5;
				break;
			case 'femme':
				$offset = -161;
				break;
			default:
				// Sans précision, on prend la moyenne des deux constantes.
				$offset = -78;
		}

		$bmr        = 10 * $weight + 6.25 * $height - 5 * $age + $offset;
		$activities = self::get_activities();
		$activity   = $profile['activity'] ?? 'modere';
		$factor     = $activities[ $activity ]['factor'] ?? 1.375;
		$total      = $bmr * $factor;

		switch ( $profile['goal'] ?? 'maintien' ) {
			case 'perte':
				$total *= 0.85;
				break;
			case 'prise':
				$total *= 1.12;
				break;
		}

		return (int) round( $total / 10 ) * 10;
	}

	/**
	 * Returns the calorie window aimed at for one lunch.
	 *
	 * @param array<string,mixed> $profile Customer profile.
	 * @return int 0 when unknown.
	 */
	public static function lunch_target( array $profile ) {
		if ( ! empty( $profile['kcal_target'] ) ) {
			return (int) round( (int) $profile['kcal_target'] * self::LUNCH_SHARE );
		}

		$daily = self::daily_calories( $profile );

		return $daily ? (int) round( $daily * self::LUNCH_SHARE ) : 0;
	}

	/**
	 * Tells whether a dish may be served to this customer.
	 *
	 * Les allergènes sont éliminatoires, les régimes sont cumulatifs.
	 *
	 * @param array<string,mixed> $dish    Dish payload.
	 * @param array<string,mixed> $profile Customer profile.
	 * @return bool
	 */
	public static function is_eligible( array $dish, array $profile ) {
		if ( empty( $dish['available'] ) ) {
			return false;
		}

		/*
		 * Un jus ou un dessert n'est pas un déjeuner : seules les catégories
		 * déclarées comme plats complets entrent dans la semaine.
		 */
		$categories = array_map( 'strval', (array) ( $profile['categories'] ?? array() ) );

		if ( $categories && ! array_intersect( $categories, array_map( 'strval', (array) ( $dish['categories'] ?? array() ) ) ) ) {
			return false;
		}

		$allergens = array_map( 'strval', (array) ( $profile['allergens'] ?? array() ) );

		if ( array_intersect( $allergens, array_map( 'strval', (array) ( $dish['allergens'] ?? array() ) ) ) ) {
			return false;
		}

		foreach ( (array) ( $profile['diets'] ?? array() ) as $diet ) {
			if ( ! in_array( (string) $diet, array_map( 'strval', (array) ( $dish['diets'] ?? array() ) ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Scores a dish for a customer. Higher is better.
	 *
	 * @param array<string,mixed> $dish    Dish payload.
	 * @param array<string,mixed> $profile Customer profile.
	 * @return float
	 */
	public static function score( array $dish, array $profile ) {
		$score     = 100.0;
		$calories  = (int) ( $dish['calories'] ?? 0 );
		$target    = self::lunch_target( $profile );
		$nutrients = array_map( 'strval', (array) ( $dish['nutrients'] ?? array() ) );

		// Proximité calorique : le critère qui pèse le plus.
		if ( $target && $calories ) {
			$gap    = abs( $calories - $target ) / $target;
			$score -= min( 60, $gap * 90 );
		}

		$protein = (float) ( $dish['protein'] ?? 0 );
		$fiber   = (float) ( $dish['fiber'] ?? 0 );
		$fat     = (float) ( $dish['fat'] ?? 0 );
		$carbs   = (float) ( $dish['carbs'] ?? 0 );

		switch ( $profile['goal'] ?? 'maintien' ) {
			case 'prise':
				$score += min( 25, $protein * 0.6 );
				break;
			case 'perte':
				$score += min( 18, $fiber * 1.6 );
				$score += min( 12, $protein * 0.25 );
				$score -= min( 12, $fat * 0.25 );
				break;
			case 'energie':
				$score += min( 12, $fiber );
				$score += min( 10, $carbs * 0.1 );
				break;
		}

		// Marqueurs déclarés : bonus sur l'atout correspondant, malus léger
		// sur le macronutriment à surveiller.
		$markers = self::get_markers();

		foreach ( (array) ( $profile['markers'] ?? array() ) as $marker ) {
			if ( ! isset( $markers[ $marker ] ) ) {
				continue;
			}

			if ( in_array( $markers[ $marker ]['nutrient'], $nutrients, true ) ) {
				$score += 30;
			}

			switch ( $markers[ $marker ]['avoid'] ) {
				case 'fat':
					$score -= min( 20, $fat * 0.5 );
					break;
				case 'carbs':
					$score -= min( 20, $carbs * 0.2 );
					break;
			}
		}

		return round( $score, 2 );
	}

	/**
	 * Builds the week from a list of dishes.
	 *
	 * Un même plat n'est jamais servi deux jours de suite, et l'ensemble
	 * tourne tant qu'il reste des plats éligibles.
	 *
	 * @param array<int,array<string,mixed>> $dishes  Dish payloads.
	 * @param array<string,mixed>            $profile Customer profile.
	 * @param array<int,array<string,string>> $days   Days to fill: date + label.
	 * @return array<int,array<string,mixed>>
	 */
	public static function build_week( array $dishes, array $profile, array $days ) {
		$eligible = array();

		foreach ( $dishes as $dish ) {
			if ( self::is_eligible( $dish, $profile ) ) {
				$dish['score'] = self::score( $dish, $profile );
				$eligible[]    = $dish;
			}
		}

		if ( ! $eligible ) {
			return array();
		}

		usort(
			$eligible,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					// Ordre stable : à score égal, on suit l'identifiant.
					return (int) $a['id'] <=> (int) $b['id'];
				}

				return $b['score'] <=> $a['score'];
			}
		);

		$plan   = array();
		$served = array();
		$last   = 0;

		foreach ( $days as $day ) {
			$pick = null;

			foreach ( $eligible as $dish ) {
				if ( (int) $dish['id'] === $last ) {
					continue;
				}

				if ( in_array( (int) $dish['id'], $served, true ) ) {
					continue;
				}

				$pick = $dish;
				break;
			}

			// Tous les plats ont été servis : on repart du meilleur, sans
			// répéter celui de la veille.
			if ( null === $pick ) {
				$served = array();

				foreach ( $eligible as $dish ) {
					if ( (int) $dish['id'] !== $last ) {
						$pick = $dish;
						break;
					}
				}
			}

			if ( null === $pick ) {
				$pick = $eligible[0];
			}

			$served[] = (int) $pick['id'];
			$last     = (int) $pick['id'];

			$plan[] = array(
				'date'  => $day['date'] ?? '',
				'label' => $day['label'] ?? '',
				'dish'  => $pick,
			);
		}

		return $plan;
	}

	/**
	 * Returns the dish payloads of the published menu.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_dish_payloads() {
		$dishes   = get_posts(
			array(
				'post_type'      => 'healtheat_dish',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		$payloads = array();

		foreach ( $dishes as $dish ) {
			$nutrition = healtheat_get_nutrition( $dish->ID );

			$payloads[] = array(
				'id'        => (int) $dish->ID,
				'name'      => get_the_title( $dish ),
				'permalink' => get_permalink( $dish ),
				'price'     => healtheat_get_dish_price( $dish->ID ),
				'available' => healtheat_dish_is_available( $dish->ID ),
				'calories'  => $nutrition['calories'],
				'protein'   => $nutrition['protein'],
				'carbs'     => $nutrition['carbs'],
				'fat'       => $nutrition['fat'],
				'fiber'     => $nutrition['fiber'],
				'categories' => healtheat_get_term_slugs( $dish->ID, 'healtheat_dish_cat' ),
				'diets'     => healtheat_get_term_slugs( $dish->ID, 'healtheat_diet' ),
				'allergens' => healtheat_get_term_slugs( $dish->ID, 'healtheat_allergen' ),
				'nutrients' => healtheat_get_term_slugs( $dish->ID, 'healtheat_nutrient' ),
			);
		}

		return $payloads;
	}

	/**
	 * Returns the open days of the coming week.
	 *
	 * @param int $count Number of days to return.
	 * @return array<int,array<string,string>>
	 */
	public static function get_week_days( $count = 5 ) {
		$settings = Healtheat_Settings::get_settings();
		$now      = new DateTimeImmutable( 'now', wp_timezone() );
		$days     = array();

		for ( $offset = 0; $offset < 14 && count( $days ) < $count; $offset++ ) {
			$date    = $now->modify( '+' . $offset . ' days' );
			$weekday = (int) $date->format( 'w' );

			if ( empty( $settings['hours'][ $weekday ]['enabled'] ) ) {
				continue;
			}

			$days[] = array(
				'date'  => $date->format( 'Y-m-d' ),
				'label' => wp_date( 'l j F', $date->getTimestamp() ),
			);
		}

		return $days;
	}

	/**
	 * Returns the plan of the current week, building it when needed.
	 *
	 * @param int  $user_id User ID.
	 * @param bool $rebuild Force a fresh plan.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_plan( $user_id, $rebuild = false ) {
		$key    = '_healtheat_plan_' . wp_date( 'o-\WW' );
		$stored = get_user_meta( $user_id, $key, true );

		if ( ! $rebuild && is_array( $stored ) && $stored ) {
			return $stored;
		}

		$profile               = Healtheat_Accounts::get_profile( $user_id );
		$profile['categories'] = (array) healtheat_get_setting( 'plan_categories', array() );

		$plan = self::build_week( self::get_dish_payloads(), $profile, self::get_week_days() );

		update_user_meta( $user_id, $key, $plan );

		return $plan;
	}
}
