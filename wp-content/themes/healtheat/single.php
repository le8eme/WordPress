<?php
/**
 * Single post template.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-single' ); ?>>
		<header class="page-header">
			<div class="wrap wrap--narrow">
				<h1 class="page-title"><?php the_title(); ?></h1>
				<p class="post-card__meta">
					<?php echo esc_html( get_the_date() ); ?>
					<?php if ( has_category() ) : ?>
						— <?php the_category( ', ' ); ?>
					<?php endif; ?>
				</p>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="wrap">
				<div class="page-thumbnail"><?php the_post_thumbnail( 'healtheat-hero' ); ?></div>
			</div>
		<?php endif; ?>

		<div class="wrap wrap--narrow entry-content">
			<?php the_content(); ?>
		</div>

		<footer class="wrap wrap--narrow post-single__footer">
			<?php the_tags( '<p class="post-tags">', ', ', '</p>' ); ?>
			<?php
			the_post_navigation(
				array(
					'prev_text' => '← %title',
					'next_text' => '%title →',
				)
			);
			?>
		</footer>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="wrap wrap--narrow">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
