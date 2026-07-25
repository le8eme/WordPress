<?php
/**
 * Theme footer.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

$healtheat_address = healtheat_plugin_active() ? healtheat_get_setting( 'address', '' ) : '';
$healtheat_phone   = healtheat_plugin_active() ? healtheat_get_setting( 'phone', '' ) : '';
?>
	</main>

	<footer class="site-footer">
		<div class="site-footer__inner">
			<div class="site-footer__col">
				<p class="site-footer__brand"><?php bloginfo( 'name' ); ?></p>
				<p class="site-footer__baseline">
					<?php echo esc_html( healtheat_option( 'healtheat_footer_baseline', __( 'Le healthy sans compromis, à emporter.', 'healtheat-theme' ) ) ); ?>
				</p>
				<?php if ( $healtheat_address ) : ?>
					<p class="site-footer__address"><?php echo nl2br( esc_html( $healtheat_address ) ); ?></p>
				<?php endif; ?>
				<?php if ( $healtheat_phone ) : ?>
					<p><a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $healtheat_phone ) ); ?>"><?php echo esc_html( $healtheat_phone ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php if ( healtheat_plugin_active() ) : ?>
				<div class="site-footer__col">
					<h2 class="site-footer__title"><?php esc_html_e( 'Horaires de retrait', 'healtheat-theme' ); ?></h2>
					<?php echo do_shortcode( '[healtheat_hours]' ); ?>
				</div>
			<?php endif; ?>

			<div class="site-footer__col">
				<?php if ( has_nav_menu( 'footer' ) ) : ?>
					<h2 class="site-footer__title"><?php esc_html_e( 'Le restaurant', 'healtheat-theme' ); ?></h2>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'menu_class'     => 'site-footer__menu',
							'depth'          => 1,
						)
					);
					?>
				<?php endif; ?>

				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<?php dynamic_sidebar( 'footer-1' ); ?>
				<?php endif; ?>
			</div>
		</div>

		<p class="site-footer__legal">
			&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> —
			<?php esc_html_e( 'Cuisine maison, produits de saison.', 'healtheat-theme' ); ?>
		</p>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>
