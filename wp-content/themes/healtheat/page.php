<?php
/**
 * Single page template.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'page-single' ); ?>>
		<header class="page-header">
			<div class="wrap wrap--narrow">
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="wrap">
				<div class="page-thumbnail"><?php the_post_thumbnail( 'healtheat-hero' ); ?></div>
			</div>
		<?php endif; ?>

		<div class="wrap wrap--narrow entry-content">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<nav class="page-links">',
					'after'  => '</nav>',
				)
			);
			?>
		</div>
	</article>

	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="wrap wrap--narrow">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
