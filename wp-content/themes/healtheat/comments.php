<?php
/**
 * Comments template.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			printf(
				/* translators: %s: comment count. */
				esc_html( _n( '%s commentaire', '%s commentaires', get_comments_number(), 'healtheat-theme' ) ),
				esc_html( number_format_i18n( get_comments_number() ) )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php
		the_comments_pagination(
			array(
				'prev_text' => __( '← Précédent', 'healtheat-theme' ),
				'next_text' => __( 'Suivant →', 'healtheat-theme' ),
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Les commentaires sont fermés.', 'healtheat-theme' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply' => __( 'Laisser un commentaire', 'healtheat-theme' ),
			'class_submit' => 'healtheat-button',
		)
	);
	?>
</section>
