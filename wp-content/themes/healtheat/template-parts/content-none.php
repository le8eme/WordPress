<?php
/**
 * Empty results message.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="no-results">
	<p><?php esc_html_e( 'Rien à afficher ici pour le moment.', 'healtheat-theme' ); ?></p>
	<?php get_search_form(); ?>
</div>
