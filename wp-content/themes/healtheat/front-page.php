<?php
/**
 * Home page: concept, signature dishes and click &amp; collect steps.
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
?>

<section class="hero<?php echo $healtheat_hero_url ? ' hero--image' : ''; ?>"
	<?php if ( $healtheat_hero_url ) : ?>style="background-image:linear-gradient(rgba(12,32,20,.62),rgba(12,32,20,.62)),url('<?php echo esc_url( $healtheat_hero_url ); ?>')"<?php endif; ?>>
	<div class="hero__inner">
		<p class="hero__eyebrow"><?php echo esc_html( healtheat_option( 'healtheat_hero_eyebrow', __( 'Cuisine fraîche, préparée chaque matin', 'healtheat-theme' ) ) ); ?></p>

		<h1 class="hero__title">
			<?php echo esc_html( healtheat_option( 'healtheat_hero_title', __( 'Manger sainement, sans y passer sa pause déjeuner.', 'healtheat-theme' ) ) ); ?>
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
					<?php esc_html_e( 'Découvrir la carte', 'healtheat-theme' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="section section--promises">
	<div class="wrap">
		<ul class="promises">
			<li class="promise">
				<span class="promise__icon" aria-hidden="true">🌱</span>
				<h2 class="promise__title"><?php esc_html_e( 'Produits de saison', 'healtheat-theme' ); ?></h2>
				<p><?php esc_html_e( 'Des fermes situées à moins de 100 km, des livraisons trois fois par semaine, aucune conserve.', 'healtheat-theme' ); ?></p>
			</li>
			<li class="promise">
				<span class="promise__icon" aria-hidden="true">📊</span>
				<h2 class="promise__title"><?php esc_html_e( 'Nutrition transparente', 'healtheat-theme' ); ?></h2>
				<p><?php esc_html_e( 'Calories, protéines, glucides, lipides et allergènes indiqués sur chaque plat de la carte.', 'healtheat-theme' ); ?></p>
			</li>
			<li class="promise">
				<span class="promise__icon" aria-hidden="true">⏱️</span>
				<h2 class="promise__title"><?php esc_html_e( 'Prêt à l\'heure dite', 'healtheat-theme' ); ?></h2>
				<p><?php esc_html_e( 'Vous choisissez votre créneau, votre commande vous attend au comptoir. Zéro file d\'attente.', 'healtheat-theme' ); ?></p>
			</li>
		</ul>
	</div>
</section>

<?php if ( healtheat_plugin_active() ) : ?>
	<section class="section section--menu">
		<div class="wrap">
			<header class="section__header">
				<h2 class="section__title"><?php esc_html_e( 'Les incontournables', 'healtheat-theme' ); ?></h2>
				<p class="section__lead"><?php esc_html_e( 'Nos plats signature, disponibles toute l\'année et déclinés au fil des saisons.', 'healtheat-theme' ); ?></p>
			</header>

			<?php echo do_shortcode( '[healtheat_featured limit="3"]' ); ?>

			<?php if ( $healtheat_menu_url ) : ?>
				<p class="section__more">
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
		<header class="section__header">
			<h2 class="section__title"><?php esc_html_e( 'Le click &amp; collect en trois temps', 'healtheat-theme' ); ?></h2>
		</header>

		<ol class="steps">
			<li class="step">
				<span class="step__number">1</span>
				<h3 class="step__title"><?php esc_html_e( 'Composez', 'healtheat-theme' ); ?></h3>
				<p><?php esc_html_e( 'Filtrez la carte selon votre régime : vegan, sans gluten, riche en protéines, léger.', 'healtheat-theme' ); ?></p>
			</li>
			<li class="step">
				<span class="step__number">2</span>
				<h3 class="step__title"><?php esc_html_e( 'Choisissez votre créneau', 'healtheat-theme' ); ?></h3>
				<p><?php esc_html_e( 'Un créneau toutes les quinze minutes, jusqu\'à cinq jours à l\'avance.', 'healtheat-theme' ); ?></p>
			</li>
			<li class="step">
				<span class="step__number">3</span>
				<h3 class="step__title"><?php esc_html_e( 'Récupérez', 'healtheat-theme' ); ?></h3>
				<p><?php esc_html_e( 'Vous recevez un e-mail dès que la commande est prête. Paiement sur place, emballages recyclables.', 'healtheat-theme' ); ?></p>
			</li>
		</ol>
	</div>
</section>

<section class="section section--concept">
	<div class="wrap wrap--narrow">
		<h2 class="section__title"><?php esc_html_e( 'Notre conviction', 'healtheat-theme' ); ?></h2>
		<p class="section__quote">
			<?php esc_html_e( '« Bien manger le midi ne devrait ni prendre une heure, ni coûter une fortune, ni sacrifier le goût. »', 'healtheat-theme' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Tout est cuisiné sur place le matin même : légumes rôtis, céréales complètes, protéines grillées, sauces maison. Pas de plats reconstitués, pas de conservateurs, pas d\'ingrédients illisibles.', 'healtheat-theme' ); ?>
		</p>

		<?php if ( $healtheat_concept ) : ?>
			<p><a class="healtheat-button" href="<?php echo esc_url( $healtheat_concept ); ?>"><?php esc_html_e( 'Découvrir le concept', 'healtheat-theme' ); ?></a></p>
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
			<header class="section__header">
				<h2 class="section__title"><?php esc_html_e( 'Le journal', 'healtheat-theme' ); ?></h2>
			</header>

			<div class="post-grid">
				<?php foreach ( $healtheat_recent as $healtheat_post ) : ?>
					<article class="post-card">
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
