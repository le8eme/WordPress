<?php
/**
 * Custom post types, taxonomies and order statuses.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Health'eat content structure.
 */
class Healtheat_Post_Types {

	/**
	 * Hooks the registrations.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_order_statuses' ), 5 );
		add_action( 'after_setup_theme', array( __CLASS__, 'register_image_sizes' ) );
	}

	/**
	 * Registers the dish and order post types.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		register_post_type(
			'healtheat_dish',
			array(
				'labels'          => array(
					'name'               => __( 'Plats', 'healtheat' ),
					'singular_name'      => __( 'Plat', 'healtheat' ),
					'add_new'            => __( 'Ajouter un plat', 'healtheat' ),
					'add_new_item'       => __( 'Ajouter un plat', 'healtheat' ),
					'edit_item'          => __( 'Modifier le plat', 'healtheat' ),
					'new_item'           => __( 'Nouveau plat', 'healtheat' ),
					'view_item'          => __( 'Voir le plat', 'healtheat' ),
					'search_items'       => __( 'Rechercher un plat', 'healtheat' ),
					'not_found'          => __( 'Aucun plat trouvé', 'healtheat' ),
					'not_found_in_trash' => __( 'Aucun plat dans la corbeille', 'healtheat' ),
					'all_items'          => __( 'Tous les plats', 'healtheat' ),
					'menu_name'          => __( "Health'eat", 'healtheat' ),
				),
				'public'          => true,
				'has_archive'     => true,
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-carrot',
				'menu_position'   => 25,
				'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
				'rewrite'         => array( 'slug' => 'la-carte', 'with_front' => false ),
				'capability_type' => 'post',
			)
		);

		register_post_type(
			'healtheat_order',
			array(
				'labels'              => array(
					'name'          => __( 'Commandes', 'healtheat' ),
					'singular_name' => __( 'Commande', 'healtheat' ),
					'edit_item'     => __( 'Commande', 'healtheat' ),
					'search_items'  => __( 'Rechercher une commande', 'healtheat' ),
					'not_found'     => __( 'Aucune commande', 'healtheat' ),
					'all_items'     => __( 'Commandes', 'healtheat' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=healtheat_dish',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap'        => true,
			)
		);
	}

	/**
	 * Registers the dish taxonomies.
	 *
	 * @return void
	 */
	public static function register_taxonomies() {
		register_taxonomy(
			'healtheat_dish_cat',
			'healtheat_dish',
			array(
				'labels'            => array(
					'name'          => __( 'Catégories de plats', 'healtheat' ),
					'singular_name' => __( 'Catégorie', 'healtheat' ),
					'add_new_item'  => __( 'Ajouter une catégorie', 'healtheat' ),
					'menu_name'     => __( 'Catégories', 'healtheat' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'carte', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'healtheat_diet',
			'healtheat_dish',
			array(
				'labels'            => array(
					'name'          => __( 'Régimes', 'healtheat' ),
					'singular_name' => __( 'Régime', 'healtheat' ),
					'add_new_item'  => __( 'Ajouter un régime', 'healtheat' ),
					'menu_name'     => __( 'Régimes', 'healtheat' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'regime', 'with_front' => false ),
			)
		);

		register_taxonomy(
			'healtheat_allergen',
			'healtheat_dish',
			array(
				'labels'            => array(
					'name'          => __( 'Allergènes', 'healtheat' ),
					'singular_name' => __( 'Allergène', 'healtheat' ),
					'add_new_item'  => __( 'Ajouter un allergène', 'healtheat' ),
					'menu_name'     => __( 'Allergènes', 'healtheat' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
			)
		);
	}

	/**
	 * Registers the click &amp; collect order statuses.
	 *
	 * @return void
	 */
	public static function register_order_statuses() {
		foreach ( self::get_order_statuses() as $status => $label ) {
			register_post_status(
				$status,
				array(
					'label'                     => $label,
					'public'                    => false,
					'internal'                  => false,
					/*
					 * Sans « protected », WP_Query écarte le statut de la liste
					 * « Tous » de l'administration : les commandes existent en
					 * base mais n'apparaissent nulle part.
					 * Voir wp-includes/class-wp-query.php, get_post_stati(
					 * array( 'protected' => true, 'show_in_admin_all_list' => true ) ).
					 */
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					/* translators: %s: number of orders. */
					'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'healtheat' ),
				)
			);
		}
	}

	/**
	 * Returns the list of order statuses.
	 *
	 * @return array<string,string>
	 */
	public static function get_order_statuses() {
		return array(
			'he-pending'   => __( 'À confirmer', 'healtheat' ),
			'he-confirmed' => __( 'Confirmée', 'healtheat' ),
			'he-ready'     => __( 'Prête', 'healtheat' ),
			'he-collected' => __( 'Retirée', 'healtheat' ),
			'he-cancelled' => __( 'Annulée', 'healtheat' ),
		);
	}

	/**
	 * Registers the dish image size.
	 *
	 * @return void
	 */
	public static function register_image_sizes() {
		add_image_size( 'healtheat-dish', 800, 600, true );
	}
}
