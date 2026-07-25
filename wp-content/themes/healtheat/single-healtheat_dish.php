<?php
/**
 * Single dish sheet: photo, nutrition, allergens and add to cart.
 *
 * @package Healtheat_Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$healtheat_data      = healtheat_get_dish_data( get_post() );
	$healtheat_nutrition = $healtheat_data['nutrition'];
	$healtheat_origin    = get_post_meta( get_the_ID(), '_healtheat_origin', true );
	?>

	<article id="post-<?php the_ID(); ?>" <?php post_class( 'dish-single' ); ?>>
		<div class="wrap">
			<div class="dish-single__layout">
				<div class="dish-single__media" data-reveal>
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'healtheat-dish' ); ?>
					<?php else : ?>
						<div class="dish-single__placeholder" aria-hidden="true">
							<?php
							$healtheat_keys = healtheat_food_keys();
							echo healtheat_food_svg( $healtheat_keys[ get_the_ID() % count( $healtheat_keys ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					<?php endif; ?>
				</div>

				<div class="dish-single__body">
					<?php if ( ! empty( $healtheat_data['categories'] ) ) : ?>
						<p class="dish-single__cat"><?php echo esc_html( implode( ' · ', $healtheat_data['categories'] ) ); ?></p>
					<?php endif; ?>

					<h1 class="page-title"><?php the_title(); ?></h1>

					<?php if ( ! empty( $healtheat_data['diets'] ) ) : ?>
						<ul class="healtheat-tags">
							<?php foreach ( $healtheat_data['diets'] as $healtheat_diet ) : ?>
								<li class="healtheat-tag"><?php echo esc_html( $healtheat_diet ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="entry-content"><?php the_content(); ?></div>

					<?php if ( $healtheat_nutrition['calories'] ) : ?>
						<div class="nutrition" data-reveal>
							<h2 class="nutrition__title"><?php esc_html_e( 'Valeurs nutritionnelles', 'healtheat-theme' ); ?></h2>
							<ul class="nutrition__list">
								<?php
								$healtheat_macros = array(
									array( $healtheat_nutrition['calories'], '', __( 'kcal', 'healtheat-theme' ) ),
									array( $healtheat_nutrition['protein'], ' g', __( 'Protéines', 'healtheat-theme' ) ),
									array( $healtheat_nutrition['carbs'], ' g', __( 'Glucides', 'healtheat-theme' ) ),
									array( $healtheat_nutrition['fat'], ' g', __( 'Lipides', 'healtheat-theme' ) ),
									array( $healtheat_nutrition['fiber'], ' g', __( 'Fibres', 'healtheat-theme' ) ),
								);

								foreach ( $healtheat_macros as $healtheat_macro ) :
									?>
									<li>
										<strong data-count="<?php echo esc_attr( $healtheat_macro[0] ); ?>" data-count-suffix="<?php echo esc_attr( $healtheat_macro[1] ); ?>">
											<?php echo esc_html( $healtheat_macro[0] . $healtheat_macro[1] ); ?>
										</strong>
										<span><?php echo esc_html( $healtheat_macro[2] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $healtheat_data['allergens'] ) ) : ?>
						<p class="dish-single__allergens">
							<strong><?php esc_html_e( 'Allergènes :', 'healtheat-theme' ); ?></strong>
							<?php echo esc_html( implode( ', ', $healtheat_data['allergens'] ) ); ?>
						</p>
					<?php endif; ?>

					<?php if ( $healtheat_origin ) : ?>
						<p class="dish-single__origin">
							<span aria-hidden="true">🚜</span>
							<?php echo esc_html( $healtheat_origin ); ?>
						</p>
					<?php endif; ?>

					<div class="dish-single__buy">
						<span class="healtheat-price healtheat-price--large"><?php echo esc_html( $healtheat_data['price_html'] ); ?></span>

						<?php if ( healtheat_ordering_enabled() && $healtheat_data['available'] ) : ?>
							<button type="button" class="healtheat-button healtheat-add"
								data-dish-id="<?php echo esc_attr( $healtheat_data['id'] ); ?>"
								data-dish-name="<?php echo esc_attr( $healtheat_data['name'] ); ?>"
								data-dish-price="<?php echo esc_attr( $healtheat_data['price'] ); ?>">
								<?php esc_html_e( 'Ajouter au panier', 'healtheat-theme' ); ?>
							</button>
						<?php else : ?>
							<span class="healtheat-unavailable"><?php esc_html_e( 'Épuisé pour aujourd\'hui', 'healtheat-theme' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php
			$healtheat_related = get_posts(
				array(
					'post_type'      => 'healtheat_dish',
					'posts_per_page' => 3,
					'post__not_in'   => array( get_the_ID() ),
					'orderby'        => 'rand',
				)
			);

			if ( $healtheat_related ) :
				?>
				<section class="section section--related">
					<h2 class="section__title"><?php esc_html_e( 'À accompagner de…', 'healtheat-theme' ); ?></h2>
					<div class="healtheat-grid">
						<?php foreach ( $healtheat_related as $healtheat_item ) : ?>
							<?php echo Healtheat_Shortcodes::render_card( $healtheat_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>
		</div>
	</article>

	<?php
endwhile;

get_footer();
