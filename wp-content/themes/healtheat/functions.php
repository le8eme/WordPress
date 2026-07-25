<?php
/**
 * Health'eat theme functions.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'HEALTHEAT_THEME_VERSION', '2.0.0' );

require_once get_template_directory() . '/inc/food-svg.php';

/**
 * Theme setup.
 *
 * @return void
 */
function healtheat_theme_setup() {
	load_theme_textdomain( 'healtheat-theme', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );

	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Menu principal', 'healtheat-theme' ),
			'footer'  => __( 'Menu du pied de page', 'healtheat-theme' ),
		)
	);

	add_image_size( 'healtheat-hero', 1800, 1000, true );
}
add_action( 'after_setup_theme', 'healtheat_theme_setup' );

/**
 * Sets the content width.
 *
 * @return void
 */
function healtheat_content_width() {
	$GLOBALS['content_width'] = 780;
}
add_action( 'after_setup_theme', 'healtheat_content_width', 0 );

/**
 * Enqueues the theme assets.
 *
 * @return void
 */
function healtheat_theme_assets() {
	wp_enqueue_style( 'healtheat-theme', get_stylesheet_uri(), array(), HEALTHEAT_THEME_VERSION );
	wp_enqueue_style( 'healtheat-animations', get_template_directory_uri() . '/assets/css/animations.css', array( 'healtheat-theme' ), HEALTHEAT_THEME_VERSION );
	wp_enqueue_script( 'healtheat-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), HEALTHEAT_THEME_VERSION, true );
	wp_enqueue_script( 'healtheat-animations', get_template_directory_uri() . '/assets/js/animations.js', array(), HEALTHEAT_THEME_VERSION, true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'healtheat_theme_assets' );

/**
 * Registers the footer widget area.
 *
 * @return void
 */
function healtheat_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Pied de page', 'healtheat-theme' ),
			'id'            => 'footer-1',
			'description'   => __( 'Affiché dans la première colonne du pied de page.', 'healtheat-theme' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'healtheat_widgets_init' );

/**
 * Tells whether the Health'eat plugin is active.
 *
 * @return bool
 */
function healtheat_plugin_active() {
	return function_exists( 'healtheat_get_dish_data' );
}

/**
 * Returns a theme option with its default value.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 * @return mixed
 */
function healtheat_option( $key, $default = '' ) {
	return get_theme_mod( $key, $default );
}

/**
 * Registers the customizer settings.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @return void
 */
function healtheat_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'healtheat_home',
		array(
			'title'    => __( 'Page d\'accueil Health\'eat', 'healtheat-theme' ),
			'priority' => 30,
		)
	);

	$fields = array(
		'healtheat_hero_eyebrow'  => array(
			'label'   => __( 'Sur-titre', 'healtheat-theme' ),
			'default' => __( 'Cuisine fraîche, préparée chaque matin', 'healtheat-theme' ),
			'type'    => 'text',
		),
		'healtheat_hero_title'    => array(
			'label'   => __( 'Titre principal (entourez un mot d\'astérisques pour le mettre en dégradé)', 'healtheat-theme' ),
			'default' => __( 'Manger *sainement*, sans y passer sa pause déjeuner.', 'healtheat-theme' ),
			'type'    => 'textarea',
		),
		'healtheat_hero_text'     => array(
			'label'   => __( 'Texte d\'introduction', 'healtheat-theme' ),
			'default' => __( 'Des bowls, salades et jus composés avec des produits locaux et de saison. Calories et allergènes affichés sur chaque plat. Commandez en ligne, retirez sur place.', 'healtheat-theme' ),
			'type'    => 'textarea',
		),
		'healtheat_hero_button'   => array(
			'label'   => __( 'Texte du bouton', 'healtheat-theme' ),
			'default' => __( 'Commander maintenant', 'healtheat-theme' ),
			'type'    => 'text',
		),
		'healtheat_footer_baseline' => array(
			'label'   => __( 'Baseline du pied de page', 'healtheat-theme' ),
			'default' => __( 'Le healthy sans compromis, à emporter.', 'healtheat-theme' ),
			'type'    => 'text',
		),
	);

	foreach ( $fields as $key => $field ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $field['default'],
				'sanitize_callback' => 'textarea' === $field['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			$key,
			array(
				'label'   => $field['label'],
				'section' => 'healtheat_home',
				'type'    => $field['type'],
			)
		);
	}

	$wp_customize->add_setting(
		'healtheat_hero_image',
		array(
			'default'           => '',
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'healtheat_hero_image',
			array(
				'label'     => __( 'Image d\'en-tête', 'healtheat-theme' ),
				'section'   => 'healtheat_home',
				'mime_type' => 'image',
			)
		)
	);
}
add_action( 'customize_register', 'healtheat_customize_register' );

/**
 * Builds the primary navigation, falling back to the page list.
 *
 * @return void
 */
function healtheat_primary_menu() {
	if ( has_nav_menu( 'primary' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'healtheat-nav__list',
				'depth'          => 2,
			)
		);

		return;
	}

	wp_page_menu(
		array(
			'menu_class'  => 'healtheat-nav__list',
			'container'   => 'ul',
			'show_home'   => __( 'Accueil', 'healtheat-theme' ),
			'depth'       => 1,
			'before'      => '',
			'after'       => '',
		)
	);
}

/**
 * Returns the URL of the click &amp; collect page.
 *
 * @return string
 */
function healtheat_theme_order_url() {
	if ( healtheat_plugin_active() ) {
		return healtheat_get_order_page_url();
	}

	return home_url( '/' );
}

/**
 * Creates the navigation menu the first time the theme is activated.
 *
 * @return void
 */
function healtheat_after_switch_theme() {
	if ( has_nav_menu( 'primary' ) ) {
		return;
	}

	$menu_name = __( 'Menu principal', 'healtheat-theme' );
	$menu      = wp_get_nav_menu_object( $menu_name );

	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return;
		}

		$pages = get_option( 'healtheat_pages', array() );

		foreach ( array( 'concept', 'menu', 'order', 'contact' ) as $key ) {
			if ( empty( $pages[ $key ] ) ) {
				continue;
			}

			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-object-id' => (int) $pages[ $key ],
					'menu-item-object'    => 'page',
					'menu-item-type'      => 'post_type',
					'menu-item-status'    => 'publish',
				)
			);
		}

		$locations            = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}
}
add_action( 'after_switch_theme', 'healtheat_after_switch_theme' );

/**
 * Shortens the default excerpt.
 *
 * @param int $length Current length.
 * @return int
 */
function healtheat_excerpt_length( $length ) {
	return is_admin() ? $length : 28;
}
add_filter( 'excerpt_length', 'healtheat_excerpt_length' );

/**
 * Replaces the excerpt ellipsis.
 *
 * @return string
 */
function healtheat_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'healtheat_excerpt_more' );

/**
 * Wraps each word of a title so it can rise one after the other.
 *
 * The `<em>` markers of the source string are kept to highlight a segment.
 *
 * @param string $text Raw title.
 * @return string
 */
function healtheat_animated_title( $text ) {
	$words  = preg_split( '/\s+/', trim( wp_strip_all_tags( $text, false ) ) );
	$output = '';

	foreach ( (array) $words as $index => $word ) {
		if ( '' === $word ) {
			continue;
		}

		/*
		 * Le segment entre astérisques est mis en dégradé. La ponctuation
		 * qui colle au mot — « *sainement*, » — est conservée telle quelle.
		 */
		if ( preg_match( '/^(.*?)\*(.+?)\*(.*)$/u', $word, $matches ) ) {
			$content = esc_html( $matches[1] ) . '<em>' . esc_html( $matches[2] ) . '</em>' . esc_html( $matches[3] );
		} else {
			$content = esc_html( $word );
		}

		$output .= sprintf(
			'<span class="word" style="--i:%1$d">%2$s</span> ',
			(int) $index,
			$content
		);
	}

	return trim( $output );
}

/**
 * Prints the animated background layers.
 *
 * @return void
 */
function healtheat_backdrop() {
	?>
	<div class="he-backdrop" aria-hidden="true">
		<div class="he-backdrop__grid"></div>
		<div class="he-backdrop__blob he-backdrop__blob--1"></div>
		<div class="he-backdrop__blob he-backdrop__blob--2"></div>
		<div class="he-backdrop__blob he-backdrop__blob--3"></div>
	</div>
	<?php
}

/**
 * Prints the floating food layer of the hero.
 *
 * @return void
 */
function healtheat_food_field() {
	$items = array(
		array( 'leaf', '8%', '14%', 74, 0.9, '0s', 12 ),
		array( 'tomato', '82%', '10%', 62, 0.55, '-2.5s', 15 ),
		array( 'citrus', '68%', '72%', 88, 0.75, '-5s', 18 ),
		array( 'berry', '14%', '78%', 54, 0.4, '-1.5s', 13 ),
		array( 'grain', '46%', '6%', 48, 0.35, '-3.5s', 16 ),
		array( 'carrot', '90%', '46%', 58, 0.6, '-6s', 14 ),
	);
	?>
	<div class="food-field" aria-hidden="true">
		<?php foreach ( $items as $index => $item ) : ?>
			<div class="food-field__item<?php echo 0 === $index % 2 ? '' : ' food-field__item--reverse'; ?>"
				data-parallax="<?php echo esc_attr( $item[4] ); ?>"
				style="left:<?php echo esc_attr( $item[1] ); ?>;top:<?php echo esc_attr( $item[2] ); ?>;--size:<?php echo esc_attr( $item[3] ); ?>px;--delay:<?php echo esc_attr( $item[5] ); ?>;--duration:<?php echo esc_attr( $item[6] ); ?>s;--rotation:<?php echo esc_attr( $item[6] * 3 ); ?>s">
				<span class="food-field__float">
					<?php echo healtheat_food_svg( $item[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Adds a body class when the Health'eat plugin is missing.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function healtheat_body_class( $classes ) {
	if ( ! healtheat_plugin_active() ) {
		$classes[] = 'healtheat-no-plugin';
	}

	return $classes;
}
add_filter( 'body_class', 'healtheat_body_class' );

/**
 * Invites the admin to install the companion plugin.
 *
 * @return void
 */
function healtheat_plugin_notice() {
	if ( healtheat_plugin_active() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Le thème Health\'eat a besoin de l\'extension « Health\'eat Core » pour afficher la carte et le click & collect.', 'healtheat-theme' )
	);
}
add_action( 'admin_notices', 'healtheat_plugin_notice' );
