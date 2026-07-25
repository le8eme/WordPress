<?php
/**
 * Front-end shortcodes: menu grid, featured dishes and checkout.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public facing markup.
 */
class Healtheat_Shortcodes {

	/**
	 * Hooks the shortcodes and their assets.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'healtheat_menu', array( __CLASS__, 'render_menu' ) );
		add_shortcode( 'healtheat_featured', array( __CLASS__, 'render_featured' ) );
		add_shortcode( 'healtheat_order', array( __CLASS__, 'render_order' ) );
		add_shortcode( 'healtheat_hours', array( __CLASS__, 'render_hours' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_cart_drawer' ) );
	}

	/**
	 * Registers and enqueues the front-end assets.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( wp_script_is( 'healtheat-cart', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'healtheat', HEALTHEAT_URL . 'assets/css/healtheat.css', array(), HEALTHEAT_VERSION );
		wp_enqueue_script( 'healtheat-cart', HEALTHEAT_URL . 'assets/js/healtheat-cart.js', array(), HEALTHEAT_VERSION, true );

		wp_localize_script(
			'healtheat-cart',
			'healtheatData',
			array(
				'restUrl'   => esc_url_raw( rest_url( Healtheat_Rest::NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'currency'  => healtheat_get_setting( 'currency', '€' ),
				'orderUrl'  => healtheat_get_order_page_url(),
				'ordering'  => healtheat_ordering_enabled(),
				'i18n'      => array(
					'cartEmpty'  => __( 'Votre panier est vide.', 'healtheat' ),
					'added'      => __( 'ajouté au panier', 'healtheat' ),
					'total'      => __( 'Total', 'healtheat' ),
					'remove'     => __( 'Retirer', 'healtheat' ),
					'increase'   => __( 'Ajouter un exemplaire', 'healtheat' ),
					'decrease'   => __( 'Retirer un exemplaire', 'healtheat' ),
					'order'      => __( 'Commander', 'healtheat' ),
					'loading'    => __( 'Chargement…', 'healtheat' ),
					'noSlot'     => __( 'Aucun créneau disponible pour le moment.', 'healtheat' ),
					'slotFull'   => __( 'complet', 'healtheat' ),
					'sending'    => __( 'Envoi en cours…', 'healtheat' ),
					'error'      => __( 'Une erreur est survenue, merci de réessayer.', 'healtheat' ),
					'pickSlot'   => __( 'Merci de choisir un créneau de retrait.', 'healtheat' ),
				),
			)
		);
	}

	/**
	 * Enqueues the assets on the pages that need them.
	 *
	 * @return void
	 */
	public static function maybe_enqueue() {
		$needed = is_singular( 'healtheat_dish' )
			|| is_post_type_archive( 'healtheat_dish' )
			|| is_tax( array( 'healtheat_dish_cat', 'healtheat_diet', 'healtheat_allergen' ) );

		if ( ! $needed && is_singular() ) {
			$post = get_post();

			if ( $post ) {
				foreach ( array( 'healtheat_menu', 'healtheat_featured', 'healtheat_order' ) as $shortcode ) {
					if ( has_shortcode( $post->post_content, $shortcode ) ) {
						$needed = true;
						break;
					}
				}
			}
		}

		/**
		 * Filters whether the Health'eat front-end assets should load.
		 *
		 * @param bool $needed Current decision.
		 */
		if ( apply_filters( 'healtheat_load_assets', $needed ) ) {
			self::enqueue();
		}
	}

	/**
	 * Queries dishes.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return WP_Post[]
	 */
	protected static function query_dishes( $atts ) {
		$args = array(
			'post_type'      => 'healtheat_dish',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'tax_query'      => array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'healtheat_dish_cat',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['category'] ) ),
			);
		}

		if ( ! empty( $atts['diet'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'healtheat_diet',
				'field'    => 'slug',
				'terms'    => array_map( 'trim', explode( ',', $atts['diet'] ) ),
			);
		}

		if ( ! empty( $atts['featured'] ) ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_healtheat_featured',
					'value' => 'yes',
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Renders a single dish card.
	 *
	 * @param WP_Post $dish Dish post.
	 * @return string
	 */
	public static function render_card( $dish ) {
		$data = healtheat_get_dish_data( $dish );

		if ( empty( $data ) ) {
			return '';
		}

		$nutrition  = $data['nutrition'];
		$diet_slugs = healtheat_get_term_slugs( $data['id'], 'healtheat_diet' );
		$cat_slugs  = healtheat_get_term_slugs( $data['id'], 'healtheat_dish_cat' );

		ob_start();
		?>
		<article class="healtheat-dish<?php echo $data['available'] ? '' : ' is-unavailable'; ?>"
			data-dish-id="<?php echo esc_attr( $data['id'] ); ?>"
			data-cats="<?php echo esc_attr( implode( ' ', array_filter( $cat_slugs ) ) ); ?>"
			data-diets="<?php echo esc_attr( implode( ' ', array_filter( $diet_slugs ) ) ); ?>">

			<a class="healtheat-dish__media" href="<?php echo esc_url( $data['permalink'] ); ?>">
				<?php if ( $data['image_id'] ) : ?>
					<?php echo healtheat_dish_photo( $data['id'], 'healtheat-card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<span class="healtheat-dish__placeholder" aria-hidden="true">
						<?php
						/**
						 * Filters the visual shown when a dish has no photo.
						 *
						 * @param string $placeholder Markup or emoji.
						 * @param int    $dish_id     Dish ID.
						 */
						echo wp_kses( apply_filters( 'healtheat_dish_placeholder', '🥗', $data['id'] ), healtheat_svg_allowed_html() );
						?>
					</span>
				<?php endif; ?>
			</a>

			<div class="healtheat-dish__body">
				<h3 class="healtheat-dish__title">
					<a href="<?php echo esc_url( $data['permalink'] ); ?>"><?php echo esc_html( $data['name'] ); ?></a>
				</h3>

				<?php if ( $data['excerpt'] ) : ?>
					<p class="healtheat-dish__excerpt"><?php echo esc_html( $data['excerpt'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $data['diets'] ) ) : ?>
					<ul class="healtheat-tags">
						<?php foreach ( $data['diets'] as $diet ) : ?>
							<li class="healtheat-tag"><?php echo esc_html( $diet ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $nutrition['calories'] ) : ?>
					<ul class="healtheat-macros">
						<li><strong><?php echo esc_html( $nutrition['calories'] ); ?></strong> kcal</li>
						<li><strong><?php echo esc_html( $nutrition['protein'] ); ?></strong> g <?php esc_html_e( 'prot.', 'healtheat' ); ?></li>
						<li><strong><?php echo esc_html( $nutrition['carbs'] ); ?></strong> g <?php esc_html_e( 'gluc.', 'healtheat' ); ?></li>
						<li><strong><?php echo esc_html( $nutrition['fat'] ); ?></strong> g <?php esc_html_e( 'lip.', 'healtheat' ); ?></li>
					</ul>
				<?php endif; ?>

				<?php if ( ! empty( $data['allergens'] ) ) : ?>
					<p class="healtheat-allergens">
						<?php esc_html_e( 'Allergènes :', 'healtheat' ); ?>
						<?php echo esc_html( implode( ', ', $data['allergens'] ) ); ?>
					</p>
				<?php endif; ?>

				<div class="healtheat-dish__footer">
					<span class="healtheat-price"><?php echo esc_html( $data['price_html'] ); ?></span>

					<?php if ( healtheat_ordering_enabled() && $data['available'] ) : ?>
						<button type="button" class="healtheat-button healtheat-add"
							data-dish-id="<?php echo esc_attr( $data['id'] ); ?>"
							data-dish-name="<?php echo esc_attr( $data['name'] ); ?>"
							data-dish-price="<?php echo esc_attr( $data['price'] ); ?>">
							<?php esc_html_e( 'Ajouter', 'healtheat' ); ?>
						</button>
					<?php elseif ( ! $data['available'] ) : ?>
						<span class="healtheat-unavailable"><?php esc_html_e( 'Épuisé', 'healtheat' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the filterable menu.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_menu( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 60,
				'category' => '',
				'diet'     => '',
				'featured' => '',
				'filters'  => 'yes',
			),
			$atts,
			'healtheat_menu'
		);

		self::enqueue();

		$dishes = self::query_dishes( $atts );

		if ( empty( $dishes ) ) {
			return '<p class="healtheat-empty">' . esc_html__( 'La carte arrive très bientôt.', 'healtheat' ) . '</p>';
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'healtheat_dish_cat',
				'hide_empty' => true,
			)
		);
		$diets      = get_terms(
			array(
				'taxonomy'   => 'healtheat_diet',
				'hide_empty' => true,
			)
		);

		ob_start();
		?>
		<div class="healtheat-menu" data-healtheat-menu>
			<?php if ( 'yes' === $atts['filters'] && ( ! is_wp_error( $categories ) && $categories || ! is_wp_error( $diets ) && $diets ) ) : ?>
				<div class="healtheat-filters">
					<?php if ( ! is_wp_error( $categories ) && $categories ) : ?>
						<div class="healtheat-filters__group" role="group" aria-label="<?php esc_attr_e( 'Filtrer par catégorie', 'healtheat' ); ?>">
							<button type="button" class="healtheat-chip is-active" data-filter-cat="">
								<?php esc_html_e( 'Tout', 'healtheat' ); ?>
							</button>
							<?php foreach ( $categories as $category ) : ?>
								<button type="button" class="healtheat-chip" data-filter-cat="<?php echo esc_attr( $category->slug ); ?>">
									<?php echo esc_html( $category->name ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! is_wp_error( $diets ) && $diets ) : ?>
						<div class="healtheat-filters__group healtheat-filters__group--diets" role="group" aria-label="<?php esc_attr_e( 'Filtrer par régime', 'healtheat' ); ?>">
							<?php foreach ( $diets as $diet ) : ?>
								<button type="button" class="healtheat-chip healtheat-chip--diet" data-filter-diet="<?php echo esc_attr( $diet->slug ); ?>" aria-pressed="false">
									<?php echo esc_html( $diet->name ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p class="healtheat-menu__count" data-healtheat-count aria-live="polite"></p>

			<div class="healtheat-grid">
				<?php foreach ( $dishes as $dish ) : ?>
					<?php echo self::render_card( $dish ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>

			<p class="healtheat-empty" data-healtheat-no-result hidden>
				<?php esc_html_e( 'Aucun plat ne correspond à ces filtres.', 'healtheat' ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the highlighted dishes.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render_featured( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'limit' => 3,
			),
			$atts,
			'healtheat_featured'
		);

		self::enqueue();

		$dishes = self::query_dishes(
			array(
				'limit'    => $atts['limit'],
				'category' => '',
				'diet'     => '',
				'featured' => true,
			)
		);

		if ( empty( $dishes ) ) {
			$dishes = self::query_dishes(
				array(
					'limit'    => $atts['limit'],
					'category' => '',
					'diet'     => '',
					'featured' => '',
				)
			);
		}

		if ( empty( $dishes ) ) {
			return '';
		}

		ob_start();
		echo '<div class="healtheat-grid healtheat-grid--featured">';

		foreach ( $dishes as $dish ) {
			echo self::render_card( $dish ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Renders the checkout screen.
	 *
	 * @return string
	 */
	public static function render_order() {
		self::enqueue();

		if ( ! healtheat_ordering_enabled() ) {
			return '<p class="healtheat-notice">' . esc_html__( 'La commande en ligne est momentanément fermée. Appelez-nous pour toute demande.', 'healtheat' ) . '</p>';
		}

		wp_enqueue_script( 'healtheat-order', HEALTHEAT_URL . 'assets/js/healtheat-order.js', array( 'healtheat-cart' ), HEALTHEAT_VERSION, true );

		$minimum = (int) healtheat_get_setting( 'min_order', 0 );

		ob_start();
		?>
		<div class="healtheat-checkout" data-healtheat-checkout>
			<section class="healtheat-checkout__cart">
				<h2><?php esc_html_e( 'Votre panier', 'healtheat' ); ?></h2>
				<div data-healtheat-cart-lines></div>
				<p class="healtheat-checkout__total">
					<span><?php esc_html_e( 'Total', 'healtheat' ); ?></span>
					<strong data-healtheat-cart-total>—</strong>
				</p>
				<?php if ( $minimum ) : ?>
					<p class="healtheat-checkout__min">
						<?php
						printf(
							/* translators: %s: formatted minimum amount. */
							esc_html__( 'Commande minimum : %s', 'healtheat' ),
							esc_html( healtheat_format_price( $minimum ) )
						);
						?>
					</p>
				<?php endif; ?>
				<p><a class="healtheat-link" href="<?php echo esc_url( get_post_type_archive_link( 'healtheat_dish' ) ); ?>"><?php esc_html_e( '← Compléter ma commande', 'healtheat' ); ?></a></p>
			</section>

			<section class="healtheat-checkout__form">
				<h2><?php esc_html_e( 'Retrait &amp; coordonnées', 'healtheat' ); ?></h2>

				<form data-healtheat-form novalidate>
					<div class="healtheat-field">
						<label for="healtheat-slot"><?php esc_html_e( 'Créneau de retrait', 'healtheat' ); ?> <span aria-hidden="true">*</span></label>
						<select id="healtheat-slot" name="pickup" required data-healtheat-slots>
							<option value=""><?php esc_html_e( 'Chargement…', 'healtheat' ); ?></option>
						</select>
					</div>

					<div class="healtheat-field">
						<label for="healtheat-name"><?php esc_html_e( 'Nom', 'healtheat' ); ?> <span aria-hidden="true">*</span></label>
						<input type="text" id="healtheat-name" name="name" autocomplete="name" required />
					</div>

					<div class="healtheat-field">
						<label for="healtheat-email"><?php esc_html_e( 'E-mail', 'healtheat' ); ?> <span aria-hidden="true">*</span></label>
						<input type="email" id="healtheat-email" name="email" autocomplete="email" required />
					</div>

					<div class="healtheat-field">
						<label for="healtheat-phone"><?php esc_html_e( 'Téléphone', 'healtheat' ); ?> <span aria-hidden="true">*</span></label>
						<input type="tel" id="healtheat-phone" name="phone" autocomplete="tel" required />
					</div>

					<div class="healtheat-field">
						<label for="healtheat-notes"><?php esc_html_e( 'Allergies, remarques', 'healtheat' ); ?></label>
						<textarea id="healtheat-notes" name="notes" rows="3"></textarea>
					</div>

					<div class="healtheat-field healtheat-field--hp" aria-hidden="true">
						<label for="healtheat-website"><?php esc_html_e( 'Site web', 'healtheat' ); ?></label>
						<input type="text" id="healtheat-website" name="website" tabindex="-1" autocomplete="off" />
					</div>

					<p class="healtheat-form__feedback" data-healtheat-feedback role="status" aria-live="polite"></p>

					<button type="submit" class="healtheat-button healtheat-button--primary">
						<?php esc_html_e( 'Valider ma commande', 'healtheat' ); ?>
					</button>

					<p class="healtheat-form__legal">
						<?php esc_html_e( 'Paiement sur place au retrait. Vos coordonnées servent uniquement au suivi de la commande.', 'healtheat' ); ?>
					</p>
				</form>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Renders the pickup opening hours.
	 *
	 * @return string
	 */
	public static function render_hours() {
		$settings = Healtheat_Settings::get_settings();
		$days     = array(
			1 => __( 'Lundi', 'healtheat' ),
			2 => __( 'Mardi', 'healtheat' ),
			3 => __( 'Mercredi', 'healtheat' ),
			4 => __( 'Jeudi', 'healtheat' ),
			5 => __( 'Vendredi', 'healtheat' ),
			6 => __( 'Samedi', 'healtheat' ),
			0 => __( 'Dimanche', 'healtheat' ),
		);

		ob_start();
		echo '<ul class="healtheat-hours">';

		foreach ( $days as $index => $label ) {
			$row = $settings['hours'][ $index ] ?? array();

			printf(
				'<li><span>%1$s</span><span>%2$s</span></li>',
				esc_html( $label ),
				empty( $row['enabled'] )
					? esc_html__( 'Fermé', 'healtheat' )
					: esc_html( sprintf( '%s – %s', $row['open'], $row['close'] ) )
			);
		}

		echo '</ul>';

		return (string) ob_get_clean();
	}

	/**
	 * Prints the floating cart drawer.
	 *
	 * @return void
	 */
	public static function render_cart_drawer() {
		if ( ! wp_script_is( 'healtheat-cart', 'enqueued' ) || ! healtheat_ordering_enabled() ) {
			return;
		}
		?>
		<div class="healtheat-cart" data-healtheat-drawer hidden>
			<button type="button" class="healtheat-cart__toggle" data-healtheat-toggle aria-expanded="false">
				<span class="healtheat-cart__icon" aria-hidden="true">🛒</span>
				<span class="healtheat-cart__count" data-healtheat-badge>0</span>
				<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir le panier', 'healtheat' ); ?></span>
			</button>

			<div class="healtheat-cart__panel" data-healtheat-panel hidden>
				<h2 class="healtheat-cart__title"><?php esc_html_e( 'Votre panier', 'healtheat' ); ?></h2>
				<div data-healtheat-cart-lines></div>
				<p class="healtheat-cart__total">
					<span><?php esc_html_e( 'Total', 'healtheat' ); ?></span>
					<strong data-healtheat-cart-total>—</strong>
				</p>
				<a class="healtheat-button healtheat-button--primary" href="<?php echo esc_url( healtheat_get_order_page_url() ); ?>">
					<?php esc_html_e( 'Commander', 'healtheat' ); ?>
				</a>
			</div>
		</div>
		<?php
	}
}

/**
 * Loads the Health'eat front-end assets from a theme template.
 *
 * @return void
 */
function healtheat_enqueue_assets() {
	Healtheat_Shortcodes::enqueue();
}
