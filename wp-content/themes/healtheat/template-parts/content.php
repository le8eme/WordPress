<?php
/**
 * Post card used in the loops.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
	<?php if ( has_post_thumbnail() ) : ?>
		<a class="post-card__media" href="<?php the_permalink(); ?>">
			<?php the_post_thumbnail( 'medium_large' ); ?>
		</a>
	<?php endif; ?>

	<h2 class="post-card__title">
		<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
	</h2>

	<p class="post-card__meta"><?php echo esc_html( get_the_date() ); ?></p>

	<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>

	<p><a class="healtheat-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Lire la suite →', 'healtheat-theme' ); ?></a></p>
</article>
