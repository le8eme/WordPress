<?php
/**
 * Theme header.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Aller au contenu', 'healtheat-theme' ); ?></a>

<div class="site">
	<header class="site-header">
		<div class="site-header__inner">
			<div class="site-branding">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<p class="site-title">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
							<span class="site-title__mark" aria-hidden="true">🌿</span>
							<?php bloginfo( 'name' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>

			<button class="nav-toggle" aria-expanded="false" aria-controls="primary-navigation">
				<span class="nav-toggle__bar" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir le menu', 'healtheat-theme' ); ?></span>
			</button>

			<nav class="healtheat-nav" id="primary-navigation" aria-label="<?php esc_attr_e( 'Navigation principale', 'healtheat-theme' ); ?>">
				<?php healtheat_primary_menu(); ?>
			</nav>

			<?php if ( healtheat_plugin_active() && healtheat_ordering_enabled() ) : ?>
				<a class="healtheat-button site-header__cta" href="<?php echo esc_url( healtheat_theme_order_url() ); ?>">
					<?php esc_html_e( 'Commander', 'healtheat-theme' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<main id="content" class="site-content">
