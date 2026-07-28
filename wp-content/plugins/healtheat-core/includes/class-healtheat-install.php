<?php
/**
 * Activation routine: demo content, pages and rewrite rules.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeds the site so the concept is visible right after activation.
 */
class Healtheat_Install {

	const SEED_OPTION = 'healtheat_seeded';

	/**
	 * Runs on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		Healtheat_Post_Types::register_post_types();
		Healtheat_Post_Types::register_taxonomies();

		if ( ! get_option( self::SEED_OPTION ) ) {
			self::seed_terms();
			self::seed_dishes();
			self::seed_pages();

			update_option( self::SEED_OPTION, HEALTHEAT_VERSION );
		}

		flush_rewrite_rules();
	}

	/**
	 * Runs on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Creates the default taxonomy terms.
	 *
	 * @return void
	 */
	protected static function seed_terms() {
		$terms = array(
			'healtheat_dish_cat' => array( 'Bowls', 'Salades', 'Petit-déjeuner', 'Jus & smoothies', 'Desserts' ),
			'healtheat_diet'     => array( 'Vegan', 'Végétarien', 'Sans gluten', 'Riche en protéines', 'Léger' ),
			'healtheat_allergen' => array( 'Gluten', 'Lactose', 'Fruits à coque', 'Sésame', 'Œuf', 'Soja', 'Poisson' ),
			'healtheat_nutrient' => array( 'Riche en fer', 'Riche en fibres', 'Index glycémique bas', 'Source de vitamine D', 'Riche en oméga-3' ),
		);

		foreach ( $terms as $taxonomy => $names ) {
			foreach ( $names as $name ) {
				if ( ! term_exists( $name, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy );
				}
			}
		}
	}

	/**
	 * Creates a starter menu so the site is never empty.
	 *
	 * @return void
	 */
	protected static function seed_dishes() {
		foreach ( self::get_sample_dishes() as $index => $dish ) {
			$existing = get_page_by_path( sanitize_title( $dish['title'] ), OBJECT, 'healtheat_dish' );

			if ( $existing ) {
				continue;
			}

			$dish_id = wp_insert_post(
				array(
					'post_type'    => 'healtheat_dish',
					'post_status'  => 'publish',
					'post_title'   => $dish['title'],
					'post_excerpt' => $dish['excerpt'],
					'post_content' => $dish['content'],
					'menu_order'   => $index,
				)
			);

			if ( ! $dish_id || is_wp_error( $dish_id ) ) {
				continue;
			}

			update_post_meta( $dish_id, '_healtheat_price', healtheat_to_cents( $dish['price'] ) );
			update_post_meta( $dish_id, '_healtheat_calories', $dish['calories'] );
			update_post_meta( $dish_id, '_healtheat_protein', $dish['protein'] );
			update_post_meta( $dish_id, '_healtheat_carbs', $dish['carbs'] );
			update_post_meta( $dish_id, '_healtheat_fat', $dish['fat'] );
			update_post_meta( $dish_id, '_healtheat_fiber', $dish['fiber'] );
			update_post_meta( $dish_id, '_healtheat_origin', $dish['origin'] );
			update_post_meta( $dish_id, '_healtheat_available', 'yes' );
			update_post_meta( $dish_id, '_healtheat_featured', $dish['featured'] ? 'yes' : 'no' );

			wp_set_object_terms( $dish_id, $dish['category'], 'healtheat_dish_cat' );
			wp_set_object_terms( $dish_id, $dish['diets'], 'healtheat_diet' );
			wp_set_object_terms( $dish_id, $dish['allergens'], 'healtheat_allergen' );
			wp_set_object_terms( $dish_id, $dish['nutrients'], 'healtheat_nutrient' );
		}
	}

	/**
	 * Creates the concept, menu and order pages.
	 *
	 * @return void
	 */
	protected static function seed_pages() {
		$pages = array(
			'concept' => array(
				'title'   => 'Le concept',
				'content' => self::get_concept_content(),
			),
			'menu'    => array(
				'title'   => 'La carte',
				'content' => "[healtheat_menu]",
			),
			'order'   => array(
				'title'   => 'Commander',
				'content' => "[healtheat_order]",
			),
			'account' => array(
				'title'   => 'Mon espace',
				'content' => "<!-- wp:paragraph -->\n<p>Indiquez votre objectif, vos régimes et vos allergies : nous composons votre déjeuner pour toute la semaine.</p>\n<!-- /wp:paragraph -->\n\n[healtheat_account]",
			),
			'contact' => array(
				'title'   => 'Nous trouver',
				'content' => "<!-- wp:paragraph -->\n<p>Nous vous accueillons du lundi au samedi pour déjeuner sur place ou récupérer votre commande.</p>\n<!-- /wp:paragraph -->\n\n[healtheat_hours]",
			),
		);

		$created = array();

		foreach ( $pages as $key => $page ) {
			$existing = get_page_by_path( sanitize_title( $page['title'] ) );

			if ( $existing ) {
				$created[ $key ] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$created[ $key ] = $page_id;
			}
		}

		if ( ! empty( $created['order'] ) ) {
			$settings                  = get_option( Healtheat_Settings::OPTION, array() );
			$settings                  = is_array( $settings ) ? $settings : array();
			$settings                  = array_merge( Healtheat_Settings::get_defaults(), $settings );
			$settings['order_page_id'] = (int) $created['order'];

			update_option( Healtheat_Settings::OPTION, $settings );
			Healtheat_Settings::flush_cache();
		}

		update_option( 'healtheat_pages', $created );
	}

	/**
	 * Returns the concept page content.
	 *
	 * @return string
	 */
	protected static function get_concept_content() {
		$blocks = array(
			"<!-- wp:paragraph -->\n<p>Health'eat est né d'une conviction simple : bien manger le midi ne devrait ni prendre une heure, ni coûter une fortune, ni sacrifier le goût.</p>\n<!-- /wp:paragraph -->",
			"<!-- wp:heading -->\n<h2>Une cuisine vraie, préparée chaque matin</h2>\n<!-- /wp:heading -->",
			"<!-- wp:paragraph -->\n<p>Tout est cuisiné sur place, le matin même : légumes rôtis, céréales complètes, protéines grillées, sauces maison. Pas de plats reconstitués, pas de conservateurs, pas de listes d'ingrédients illisibles.</p>\n<!-- /wp:paragraph -->",
			"<!-- wp:heading -->\n<h2>La transparence nutritionnelle</h2>\n<!-- /wp:heading -->",
			"<!-- wp:paragraph -->\n<p>Chaque plat de notre carte affiche ses calories, ses macronutriments et ses allergènes. Vous savez exactement ce que vous mangez, et vous choisissez en connaissance de cause — que vous soyez sportif, végétarien, intolérant au gluten ou simplement curieux.</p>\n<!-- /wp:paragraph -->",
			"<!-- wp:heading -->\n<h2>Des producteurs que nous connaissons</h2>\n<!-- /wp:heading -->",
			"<!-- wp:paragraph -->\n<p>Nos légumes viennent de fermes situées à moins de 100 km. Nos œufs sont bio et plein air, nos poissons issus de pêche durable. Nous indiquons l'origine sur chaque fiche produit, parce qu'un plat sain commence par une matière première honnête.</p>\n<!-- /wp:paragraph -->",
			"<!-- wp:heading -->\n<h2>Zéro attente : le click &amp; collect</h2>\n<!-- /wp:heading -->",
			"<!-- wp:paragraph -->\n<p>Commandez en ligne, choisissez votre créneau de retrait, passez au comptoir : votre repas est prêt, emballé dans des contenants recyclables. Le temps de pause vous appartient de nouveau.</p>\n<!-- /wp:paragraph -->",
		);

		return implode( "\n\n", $blocks );
	}

	/**
	 * Returns the sample dishes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected static function get_sample_dishes() {
		return array(
			array(
				'title'     => 'Bowl Green Power',
				'excerpt'   => 'Quinoa, avocat, edamame, brocoli rôti, graines de courge et sauce citron-tahini.',
				'content'   => 'Notre bowl signature : une base de quinoa tricolore, des légumes verts rôtis à basse température, de l\'avocat frais et une sauce tahini-citron préparée le matin. Rassasiant sans être lourd.',
				'price'     => '13.90',
				'calories'  => 620,
				'protein'   => 24,
				'carbs'     => 58,
				'fat'       => 28,
				'fiber'     => 14,
				'origin'    => 'Ferme des Trois Chênes, Seine-et-Marne',
				'category'  => 'Bowls',
				'diets'     => array( 'Vegan', 'Végétarien', 'Sans gluten' ),
				'allergens' => array( 'Sésame', 'Soja' ),
				'nutrients' => array( 'Riche en fibres', 'Index glycémique bas' ),
				'featured'  => true,
			),
			array(
				'title'     => 'Bowl Poulet Fermier & Patate Douce',
				'excerpt'   => 'Poulet mariné au paprika fumé, patate douce rôtie, épinards, boulgour et yaourt aux herbes.',
				'content'   => 'Un bowl protéiné pensé pour les journées chargées : poulet fermier mariné 12 heures, patates douces caramélisées au four et une sauce yaourt-ciboulette légère.',
				'price'     => '14.90',
				'calories'  => 710,
				'protein'   => 45,
				'carbs'     => 62,
				'fat'       => 22,
				'fiber'     => 9,
				'origin'    => 'Volailles de la Vallée, Loiret',
				'category'  => 'Bowls',
				'diets'     => array( 'Riche en protéines' ),
				'allergens' => array( 'Gluten', 'Lactose' ),
				'nutrients' => array( 'Riche en fer' ),
				'featured'  => true,
			),
			array(
				'title'     => 'Bowl Saumon Teriyaki',
				'excerpt'   => 'Saumon label rouge laqué, riz complet, chou rouge, concombre et sésame noir.',
				'content'   => 'Saumon issu de pêche durable, laqué d\'une sauce teriyaki maison peu sucrée, servi tiède sur un riz complet et des crudités croquantes.',
				'price'     => '16.50',
				'calories'  => 680,
				'protein'   => 38,
				'carbs'     => 55,
				'fat'       => 30,
				'fiber'     => 7,
				'origin'    => 'Pêche durable, Atlantique Nord-Est',
				'category'  => 'Bowls',
				'diets'     => array( 'Riche en protéines' ),
				'allergens' => array( 'Poisson', 'Soja', 'Sésame' ),
				'nutrients' => array( 'Riche en oméga-3', 'Source de vitamine D' ),
				'featured'  => true,
			),
			array(
				'title'     => 'Salade Lentilles & Feta',
				'excerpt'   => 'Lentilles vertes du Puy, feta AOP, tomates séchées, roquette et vinaigrette au miel.',
				'content'   => 'Une salade complète et réconfortante, riche en fibres, parfaite en accompagnement ou en repas léger.',
				'price'     => '11.50',
				'calories'  => 480,
				'protein'   => 22,
				'carbs'     => 42,
				'fat'       => 20,
				'fiber'     => 12,
				'origin'    => 'Lentilles AOP du Puy, Haute-Loire',
				'category'  => 'Salades',
				'diets'     => array( 'Végétarien', 'Sans gluten', 'Léger' ),
				'allergens' => array( 'Lactose' ),
				'nutrients' => array( 'Riche en fer', 'Riche en fibres' ),
				'featured'  => false,
			),
			array(
				'title'     => 'Salade César Revisitée',
				'excerpt'   => 'Poulet grillé, kale massé, parmesan, croûtons complets et sauce César allégée au yaourt.',
				'content'   => 'La César, sans la lourdeur : notre sauce est montée au yaourt grec plutôt qu\'à la mayonnaise, et la laitue laisse place au kale massé à l\'huile d\'olive.',
				'price'     => '13.50',
				'calories'  => 520,
				'protein'   => 40,
				'carbs'     => 28,
				'fat'       => 24,
				'fiber'     => 6,
				'origin'    => 'Volailles de la Vallée, Loiret',
				'category'  => 'Salades',
				'diets'     => array( 'Riche en protéines' ),
				'allergens' => array( 'Gluten', 'Lactose', 'Œuf' ),
				'nutrients' => array( 'Index glycémique bas' ),
				'featured'  => false,
			),
			array(
				'title'     => 'Porridge Avoine & Fruits Rouges',
				'excerpt'   => 'Flocons d\'avoine, lait d\'amande, myrtilles, framboises, beurre de cacahuète et graines de chia.',
				'content'   => 'Notre petit-déjeuner le plus demandé : lent à digérer, riche en fibres, il tient jusqu\'au déjeuner sans coup de barre.',
				'price'     => '6.90',
				'calories'  => 420,
				'protein'   => 14,
				'carbs'     => 52,
				'fat'       => 16,
				'fiber'     => 10,
				'origin'    => 'Avoine bio, Beauce',
				'category'  => 'Petit-déjeuner',
				'diets'     => array( 'Vegan', 'Végétarien' ),
				'allergens' => array( 'Gluten', 'Fruits à coque' ),
				'nutrients' => array( 'Riche en fibres' ),
				'featured'  => false,
			),
			array(
				'title'     => 'Jus Détox Vert',
				'excerpt'   => 'Pomme, concombre, céleri, épinard, citron vert et gingembre pressés à froid.',
				'content'   => 'Pressé à froid le matin même pour préserver les vitamines. Sans sucre ajouté, jamais pasteurisé.',
				'price'     => '5.50',
				'calories'  => 120,
				'protein'   => 2,
				'carbs'     => 26,
				'fat'       => 0.5,
				'fiber'     => 3,
				'origin'    => 'Vergers de l\'Oise',
				'category'  => 'Jus & smoothies',
				'diets'     => array( 'Vegan', 'Végétarien', 'Sans gluten', 'Léger' ),
				'allergens' => array(),
				'nutrients' => array(),
				'featured'  => false,
			),
			array(
				'title'     => 'Energy Ball Cacao & Datte',
				'excerpt'   => 'Dattes Medjool, amandes, cacao cru et noix de coco. Sans sucre ajouté.',
				'content'   => 'Le petit format qui accompagne le café : trois ingrédients, aucun sucre raffiné, une belle densité nutritionnelle.',
				'price'     => '3.50',
				'calories'  => 180,
				'protein'   => 5,
				'carbs'     => 22,
				'fat'       => 9,
				'fiber'     => 5,
				'origin'    => 'Amandes de Provence',
				'category'  => 'Desserts',
				'diets'     => array( 'Vegan', 'Végétarien', 'Sans gluten' ),
				'allergens' => array( 'Fruits à coque' ),
				'nutrients' => array( 'Riche en fibres' ),
				'featured'  => false,
			),
		);
	}
}
