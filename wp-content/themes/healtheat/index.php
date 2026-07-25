<?php
/**
 * Main template: blog index and fallback.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap">
	<header class="page-header">
		<?php if ( is_home() && ! is_front_page() ) : ?>
			<h1 class="page-title"><?php single_post_title(); ?></h1>
		<?php elseif ( is_archive() ) : ?>
			<h1 class="page-title"><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		<?php elseif ( is_search() ) : ?>
			<h1 class="page-title">
				<?php
				printf(
					/* translators: %s: search terms. */
					esc_html__( 'Résultats pour « %s »', 'healtheat-theme' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
		<?php else : ?>
			<h1 class="page-title"><?php esc_html_e( 'Le journal', 'healtheat-theme' ); ?></h1>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content' );
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
</div>

<?php
get_footer();
