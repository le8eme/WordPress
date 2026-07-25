<?php
/**
 * Full menu: every dish with its filters.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap">
	<header class="page-header page-header--menu">
		<h1 class="page-title">
			<?php
			if ( is_tax() ) {
				the_archive_title();
			} else {
				esc_html_e( 'La carte', 'healtheat-theme' );
			}
			?>
		</h1>

		<?php if ( is_tax() ) : ?>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		<?php else : ?>
			<p class="section__lead">
				<?php esc_html_e( 'Composée chaque matin, adaptée aux saisons. Chaque plat affiche ses valeurs nutritionnelles et ses allergènes.', 'healtheat-theme' ); ?>
			</p>
		<?php endif; ?>
	</header>

	<?php if ( is_tax() ) : ?>
		<?php if ( have_posts() ) : ?>
			<div class="healtheat-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					echo Healtheat_Shortcodes::render_card( get_post() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'prev_text' => __( '← Précédent', 'healtheat-theme' ),
					'next_text' => __( 'Suivant →', 'healtheat-theme' ),
				)
			);
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	<?php else : ?>
		<?php echo do_shortcode( '[healtheat_menu limit="100"]' ); ?>
	<?php endif; ?>
</div>

<?php
get_footer();
