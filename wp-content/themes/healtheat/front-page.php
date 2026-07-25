<?php
/**
 * Home page: hero en orbite, bandeau d'ingrédients, assemblage du bol,
 * plats signature et étapes du click &amp; collect.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

$healtheat_hero_id  = (int) healtheat_option( 'healtheat_hero_image', 0 );
$healtheat_hero_url = $healtheat_hero_id ? wp_get_attachment_image_url( $healtheat_hero_id, 'healtheat-hero' ) : '';
$healtheat_pages    = get_option( 'healtheat_pages', array() );
$healtheat_concept  = ! empty( $healtheat_pages['concept'] ) ? get_permalink( (int) $healtheat_pages['concept'] ) : '';
$healtheat_menu_url = healtheat_plugin_active() ? get_post_type_archive_link( 'healtheat_dish' ) : '';

/*
 * Les astérisques du titre marquent le segment mis en dégradé :
 * « Manger *sainement*, sans y passer sa pause. »
 */
$healtheat_title = healtheat_option( 'healtheat_hero_title', __( 'Manger *sainement*, sans y passer sa pause déjeuner.', 'healtheat-theme' ) );
?>

<section class="hero"<?php echo $healtheat_hero_url ? ' style="background-image:linear-gradient(rgba(4,7,12,.86),rgba(4,7,12,.94)),url(\'' . esc_url( $healtheat_hero_url ) . '\');background-size:cover;background-position:center"' : ''; ?>>
	<?php healtheat_food_field(); ?>

	<div class="hero__inner">
		<div class="hero__content">
			<p class="eyebrow"><?php echo esc_html( healtheat_option( 'healtheat_hero_eyebrow', __( 'Cuisine fraîche, préparée chaque matin', 'healtheat-theme' ) ) ); ?></p>

			<h1 class="hero__title">
				<?php echo healtheat_animated_title( $healtheat_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span class="cursor-blink" aria-hidden="true">_</span>
			</h1>

			<p class="hero__text">
				<?php echo esc_html( healtheat_option( 'healtheat_hero_text', __( 'Des bowls, salades et jus composés avec des produits locaux et de saison. Calories et allergènes affichés sur chaque plat. Commandez en ligne, retirez sur place.', 'healtheat-theme' ) ) ); ?>
			</p>

			<div class="hero__actions">
				<?php if ( healtheat_plugin_active() && healtheat_ordering_enabled() ) : ?>
					<a class="healtheat-button healtheat-button--hero" href="<?php echo esc_url( healtheat_theme_order_url() ); ?>">
						<?php echo esc_html( healtheat_option( 'healtheat_hero_button', __( 'Commander maintenant', 'healtheat-theme' ) ) ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $healtheat_menu_url ) : ?>
					<a class="hero__link" href="<?php echo esc_url( $healtheat_menu_url ); ?>">
						<?php esc_html_e( 'Explorer la carte', 'healtheat-theme' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<ul class="hero__stats">
				<li class="stat">
					<span class="stat__value" data-count="100" data-count-suffix=" %">0</span>
					<span class="stat__label"><?php esc_html_e( 'Fait maison', 'healtheat-theme' ); ?></span>
				</li>
				<li class="stat">
					<span class="stat__value" data-count="100" data-count-suffix=" km">0</span>
					<span class="stat__label"><?php esc_html_e( 'Rayon d\'approvisionnement', 'healtheat-theme' ); ?></span>
				</li>
				<li class="stat">
					<span class="stat__value" data-count="15" data-count-suffix=" min">0</span>
					<span class="stat__label"><?php esc_html_e( 'Entre deux créneaux', 'healtheat-theme' ); ?></span>
				</li>
			</ul>
		</div>

		<div class="hero__visual">
			<div class="orbit" data-parallax="0.15">
				<div class="orbit__ring"></div>
				<div class="orbit__ring orbit__ring--inner"></div>
				<div class="orbit__core"></div>
				<div class="orbit__core-food"><?php echo healtheat_food_svg( 'avocado' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

				<div class="orbit__satellites">
					<?php
					$healtheat_satellites = array( 'leaf', 'tomato', 'citrus', 'broccoli', 'berry', 'grain' );

					foreach ( $healtheat_satellites as $healtheat_index => $healtheat_food ) :
						$healtheat_angle = $healtheat_index * ( 360 / count( $healtheat_satellites ) );
						?>
						<div class="orbit__item" style="--angle:<?php echo esc_attr( $healtheat_angle ); ?>deg;--radius:calc(min(430px, 82vw) / 2 - 34px)">
							<?php echo healtheat_food_svg( $healtheat_food ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>

<div class="marquee" aria-hidden="true">
	<div class="marquee__track" data-marquee>
		<?php
		$healtheat_words = array(
			'leaf'     => __( 'Vegan', 'healtheat-theme' ),
			'grain'    => __( 'Sans gluten', 'healtheat-theme' ),
			'tomato'   => __( 'Maraîchers locaux', 'healtheat-theme' ),
			'citrus'   => __( 'Pressé à froid', 'healtheat-theme' ),
			'broccoli' => __( 'Zéro conservateur', 'healtheat-theme' ),
			'berry'    => __( 'Sucre non raffiné', 'healtheat-theme' ),
			'carrot'   => __( 'De saison', 'healtheat-theme' ),
			'droplet'  => __( 'Emballage recyclable', 'healtheat-theme' ),
		);

		foreach ( $healtheat_words as $healtheat_food => $healtheat_word ) :
			?>
			<span class="marquee__item">
				<?php echo healtheat_food_svg( $healtheat_food ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span><?php echo esc_html( $healtheat_word ); ?></span>
			</span>
		<?php endforeach; ?>
	</div>
</div>

<section class="section section--promises">
	<div class="wrap">
		<ul class="promises">
			<?php
			$healtheat_promises = array(
				array(
					'food'  => 'leaf',
					'title' => __( 'Produits de saison', 'healtheat-theme' ),
					'text'  => __( 'Des fermes situées à moins de 100 km, des livraisons trois fois par semaine, aucune conserve.', 'healtheat-theme' ),
				),
				array(
					'food'  => 'citrus',
					'title' => __( 'Nutrition transparente', 'healtheat-theme' ),
					'text'  => __( 'Calories, protéines, glucides, lipides et allergènes indiqués sur chaque plat de la carte.', 'healtheat-theme' ),
				),
				array(
					'food'  => 'droplet',
					'title' => __( 'Prêt à l\'heure dite', 'healtheat-theme' ),
					'text'  => __( 'Vous choisissez votre créneau, votre commande vous attend au comptoir. Zéro file d\'attente.', 'healtheat-theme' ),
				),
			);

			foreach ( $healtheat_promises as $healtheat_index => $healtheat_promise ) :
				?>
				<li class="promise glass" data-reveal data-tilt style="--delay:<?php echo esc_attr( $healtheat_index * 0.12 ); ?>s">
					<span class="promise__icon"><?php echo healtheat_food_svg( $healtheat_promise['food'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h2 class="promise__title"><?php echo esc_html( $healtheat_promise['title'] ); ?></h2>
					<p><?php echo esc_html( $healtheat_promise['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<section class="section section--assembly">
	<div class="wrap">
		<div data-reveal>
			<p class="eyebrow"><?php esc_html_e( 'Composition', 'healtheat-theme' ); ?></p>
			<h2 class="section__title"><?php esc_html_e( 'Un bol se construit sous vos yeux', 'healtheat-theme' ); ?></h2>
			<p class="section__lead">
				<?php esc_html_e( 'Une base de céréales complètes, des légumes rôtis le matin, une protéine au choix, des graines et une sauce maison. Rien d\'autre.', 'healtheat-theme' ); ?>
			</p>

			<ul class="assembly-list">
				<?php
				$healtheat_layers = array(
					array( 'grain', __( 'Base', 'healtheat-theme' ), __( 'quinoa, boulgour ou riz complet', 'healtheat-theme' ) ),
					array( 'broccoli', __( 'Légumes', 'healtheat-theme' ), __( 'rôtis à basse température', 'healtheat-theme' ) ),
					array( 'avocado', __( 'Bon gras', 'healtheat-theme' ), __( 'avocat, graines, huile d\'olive', 'healtheat-theme' ) ),
					array( 'droplet', __( 'Sauce', 'healtheat-theme' ), __( 'préparée chaque matin, sans additif', 'healtheat-theme' ) ),
				);

				foreach ( $healtheat_layers as $healtheat_layer ) :
					?>
					<li>
						<?php echo healtheat_food_svg( $healtheat_layer[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><strong><?php echo esc_html( $healtheat_layer[1] ); ?></strong> — <?php echo esc_html( $healtheat_layer[2] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="assembly" aria-hidden="true">
			<div class="assembly__glow"></div>

			<?php
			$healtheat_drops = array(
				array( 'leaf', '8%', '4%', 78, '0s', 7 ),
				array( 'tomato', '62%', '0%', 62, '0.12s', 8 ),
				array( 'broccoli', '30%', '18%', 88, '0.24s', 6.5 ),
				array( 'citrus', '74%', '26%', 58, '0.36s', 9 ),
				array( 'grain', '16%', '38%', 52, '0.48s', 7.5 ),
				array( 'berry', '52%', '40%', 44, '0.6s', 8.5 ),
			);

			foreach ( $healtheat_drops as $healtheat_drop ) :
				?>
				<div class="assembly__item"
					style="--x:<?php echo esc_attr( $healtheat_drop[1] ); ?>;--y:<?php echo esc_attr( $healtheat_drop[2] ); ?>;--size:<?php echo esc_attr( $healtheat_drop[3] ); ?>px;--delay:<?php echo esc_attr( $healtheat_drop[4] ); ?>;--duration:<?php echo esc_attr( $healtheat_drop[5] ); ?>s">
					<?php echo healtheat_food_svg( $healtheat_drop[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endforeach; ?>

			<div class="assembly__bowl"><?php echo healtheat_food_svg( 'bowl' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
	</div>
</section>

<?php if ( healtheat_plugin_active() ) : ?>
	<section class="section section--menu">
		<div class="wrap">
			<header class="section__header" data-reveal>
				<p class="eyebrow"><?php esc_html_e( 'La carte', 'healtheat-theme' ); ?></p>
				<h2 class="section__title"><?php esc_html_e( 'Les incontournables', 'healtheat-theme' ); ?></h2>
				<p class="section__lead"><?php esc_html_e( 'Nos plats signature, disponibles toute l\'année et déclinés au fil des saisons.', 'healtheat-theme' ); ?></p>
			</header>

			<div data-reveal>
				<?php echo do_shortcode( '[healtheat_featured limit="3"]' ); ?>
			</div>

			<?php if ( $healtheat_menu_url ) : ?>
				<p class="section__more" data-reveal>
					<a class="healtheat-link" href="<?php echo esc_url( $healtheat_menu_url ); ?>">
						<?php esc_html_e( 'Voir toute la carte →', 'healtheat-theme' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<section class="section section--steps">
	<div class="wrap">
		<header class="section__header" data-reveal>
			<p class="eyebrow"><?php esc_html_e( 'Click &amp; collect', 'healtheat-theme' ); ?></p>
			<h2 class="section__title"><?php esc_html_e( 'Trois gestes, zéro attente', 'healtheat-theme' ); ?></h2>
		</header>

		<ol class="steps">
			<?php
			$healtheat_steps = array(
				array( __( 'Composez', 'healtheat-theme' ), __( 'Filtrez la carte selon votre régime : vegan, sans gluten, riche en protéines, léger.', 'healtheat-theme' ) ),
				array( __( 'Choisissez votre créneau', 'healtheat-theme' ), __( 'Un créneau toutes les quinze minutes, jusqu\'à cinq jours à l\'avance.', 'healtheat-theme' ) ),
				array( __( 'Récupérez', 'healtheat-theme' ), __( 'Vous recevez un e-mail dès que la commande est prête. Paiement sur place, emballages recyclables.', 'healtheat-theme' ) ),
			);

			foreach ( $healtheat_steps as $healtheat_index => $healtheat_step ) :
				?>
				<li class="step glass" data-reveal data-tilt style="--delay:<?php echo esc_attr( $healtheat_index * 0.12 ); ?>s">
					<span class="step__number"><?php echo esc_html( str_pad( (string) ( $healtheat_index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="step__title"><?php echo esc_html( $healtheat_step[0] ); ?></h3>
					<p><?php echo esc_html( $healtheat_step[1] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>

<section class="section section--concept">
	<div class="wrap wrap--narrow" data-reveal>
		<p class="eyebrow"><?php esc_html_e( 'Notre conviction', 'healtheat-theme' ); ?></p>
		<p class="section__quote">
			<?php esc_html_e( '« Bien manger le midi ne devrait ni prendre une heure, ni coûter une fortune, ni sacrifier le goût. »', 'healtheat-theme' ); ?>
		</p>
		<p class="section__lead">
			<?php esc_html_e( 'Tout est cuisiné sur place le matin même : légumes rôtis, céréales complètes, protéines grillées, sauces maison. Pas de plats reconstitués, pas de conservateurs, pas d\'ingrédients illisibles.', 'healtheat-theme' ); ?>
		</p>

		<?php if ( $healtheat_concept ) : ?>
			<p class="section__more"><a class="healtheat-button" href="<?php echo esc_url( $healtheat_concept ); ?>"><?php esc_html_e( 'Découvrir le concept', 'healtheat-theme' ); ?></a></p>
		<?php endif; ?>
	</div>
</section>

<?php
$healtheat_recent = get_posts(
	array(
		'posts_per_page' => 3,
		'post_status'    => 'publish',
	)
);

if ( $healtheat_recent ) :
	?>
	<section class="section section--journal">
		<div class="wrap">
			<header class="section__header" data-reveal>
				<p class="eyebrow"><?php esc_html_e( 'Journal', 'healtheat-theme' ); ?></p>
				<h2 class="section__title"><?php esc_html_e( 'Dans les cuisines', 'healtheat-theme' ); ?></h2>
			</header>

			<div class="post-grid">
				<?php foreach ( $healtheat_recent as $healtheat_index => $healtheat_post ) : ?>
					<article class="post-card" data-reveal style="--delay:<?php echo esc_attr( $healtheat_index * 0.1 ); ?>s">
						<?php if ( has_post_thumbnail( $healtheat_post ) ) : ?>
							<a class="post-card__media" href="<?php echo esc_url( get_permalink( $healtheat_post ) ); ?>">
								<?php echo get_the_post_thumbnail( $healtheat_post, 'medium_large' ); ?>
							</a>
						<?php endif; ?>
						<h3 class="post-card__title">
							<a href="<?php echo esc_url( get_permalink( $healtheat_post ) ); ?>"><?php echo esc_html( get_the_title( $healtheat_post ) ); ?></a>
						</h3>
						<p class="post-card__meta"><?php echo esc_html( get_the_date( '', $healtheat_post ) ); ?></p>
						<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $healtheat_post ) ) ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
<?php endif; ?>

<?php
get_footer();
