<?php
/**
 * 404 template.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap wrap--narrow error-404">
	<h1 class="page-title"><?php esc_html_e( 'Cette page a été mangée.', 'healtheat-theme' ); ?></h1>
	<p><?php esc_html_e( 'La page que vous cherchez n\'existe plus. La carte, elle, est toujours là.', 'healtheat-theme' ); ?></p>

	<?php if ( healtheat_plugin_active() ) : ?>
		<p>
			<a class="healtheat-button" href="<?php echo esc_url( get_post_type_archive_link( 'healtheat_dish' ) ); ?>">
				<?php esc_html_e( 'Voir la carte', 'healtheat-theme' ); ?>
			</a>
		</p>
	<?php endif; ?>

	<?php get_search_form(); ?>
</div>

<?php
get_footer();
