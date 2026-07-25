<?php
/**
 * Price, availability and nutrition fields of a dish.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the dish meta box.
 */
class Healtheat_Dish_Meta {

	/**
	 * Hooks the meta box.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_healtheat_dish', array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_healtheat_dish_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_healtheat_dish_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Declares the meta box.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		add_meta_box(
			'healtheat-dish-details',
			__( 'Fiche produit Health\'eat', 'healtheat' ),
			array( __CLASS__, 'render' ),
			'healtheat_dish',
			'normal',
			'high'
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Current dish.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( 'healtheat_save_dish', 'healtheat_dish_nonce' );

		$price     = healtheat_get_dish_price( $post->ID );
		$nutrition = healtheat_get_nutrition( $post->ID );
		$available = 'no' !== get_post_meta( $post->ID, '_healtheat_available', true );
		$featured  = 'yes' === get_post_meta( $post->ID, '_healtheat_featured', true );
		$origin    = (string) get_post_meta( $post->ID, '_healtheat_origin', true );
		?>
		<style>
			.healtheat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 16px; }
			.healtheat-grid label { display: block; font-weight: 600; margin-bottom: 4px; }
			.healtheat-grid input[type="text"], .healtheat-grid input[type="number"] { width: 100%; }
			.healtheat-toggles label { margin-right: 24px; font-weight: 600; }
		</style>
		<div class="healtheat-grid">
			<div>
				<label for="healtheat_price"><?php esc_html_e( 'Prix TTC', 'healtheat' ); ?></label>
				<input type="text" id="healtheat_price" name="healtheat_price" value="<?php echo esc_attr( number_format( $price / 100, 2, '.', '' ) ); ?>" placeholder="12.50" />
				<p class="description"><?php esc_html_e( 'En euros, ex. 12.50', 'healtheat' ); ?></p>
			</div>
			<div>
				<label for="healtheat_calories"><?php esc_html_e( 'Calories (kcal)', 'healtheat' ); ?></label>
				<input type="number" min="0" step="1" id="healtheat_calories" name="healtheat_calories" value="<?php echo esc_attr( $nutrition['calories'] ); ?>" />
			</div>
			<div>
				<label for="healtheat_protein"><?php esc_html_e( 'Protéines (g)', 'healtheat' ); ?></label>
				<input type="number" min="0" step="0.1" id="healtheat_protein" name="healtheat_protein" value="<?php echo esc_attr( $nutrition['protein'] ); ?>" />
			</div>
			<div>
				<label for="healtheat_carbs"><?php esc_html_e( 'Glucides (g)', 'healtheat' ); ?></label>
				<input type="number" min="0" step="0.1" id="healtheat_carbs" name="healtheat_carbs" value="<?php echo esc_attr( $nutrition['carbs'] ); ?>" />
			</div>
			<div>
				<label for="healtheat_fat"><?php esc_html_e( 'Lipides (g)', 'healtheat' ); ?></label>
				<input type="number" min="0" step="0.1" id="healtheat_fat" name="healtheat_fat" value="<?php echo esc_attr( $nutrition['fat'] ); ?>" />
			</div>
			<div>
				<label for="healtheat_fiber"><?php esc_html_e( 'Fibres (g)', 'healtheat' ); ?></label>
				<input type="number" min="0" step="0.1" id="healtheat_fiber" name="healtheat_fiber" value="<?php echo esc_attr( $nutrition['fiber'] ); ?>" />
			</div>
		</div>

		<p>
			<label for="healtheat_origin"><strong><?php esc_html_e( 'Origine / producteur', 'healtheat' ); ?></strong></label><br />
			<input type="text" class="large-text" id="healtheat_origin" name="healtheat_origin" value="<?php echo esc_attr( $origin ); ?>" placeholder="<?php esc_attr_e( 'Ferme des Trois Chênes, Seine-et-Marne', 'healtheat' ); ?>" />
		</p>

		<p class="healtheat-toggles">
			<label>
				<input type="checkbox" name="healtheat_available" value="yes" <?php checked( $available ); ?> />
				<?php esc_html_e( 'Disponible à la commande', 'healtheat' ); ?>
			</label>
			<label>
				<input type="checkbox" name="healtheat_featured" value="yes" <?php checked( $featured ); ?> />
				<?php esc_html_e( 'Mettre en avant sur l\'accueil', 'healtheat' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Persists the meta box values.
	 *
	 * @param int     $post_id Dish ID.
	 * @param WP_Post $post    Dish object.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['healtheat_dish_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['healtheat_dish_nonce'] ) ), 'healtheat_save_dish' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_healtheat_price', healtheat_to_cents( isset( $_POST['healtheat_price'] ) ? wp_unslash( $_POST['healtheat_price'] ) : 0 ) );
		update_post_meta( $post_id, '_healtheat_calories', absint( $_POST['healtheat_calories'] ?? 0 ) );

		foreach ( array( 'protein', 'carbs', 'fat', 'fiber' ) as $macro ) {
			$value = isset( $_POST[ 'healtheat_' . $macro ] ) ? (float) wp_unslash( $_POST[ 'healtheat_' . $macro ] ) : 0;
			update_post_meta( $post_id, '_healtheat_' . $macro, max( 0, $value ) );
		}

		update_post_meta( $post_id, '_healtheat_origin', sanitize_text_field( wp_unslash( $_POST['healtheat_origin'] ?? '' ) ) );
		update_post_meta( $post_id, '_healtheat_available', isset( $_POST['healtheat_available'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_healtheat_featured', isset( $_POST['healtheat_featured'] ) ? 'yes' : 'no' );
	}

	/**
	 * Adds price and availability columns to the dish list.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['healtheat_price']     = __( 'Prix', 'healtheat' );
		$columns['healtheat_calories']  = __( 'kcal', 'healtheat' );
		$columns['healtheat_available'] = __( 'Disponible', 'healtheat' );

		if ( $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Renders the custom columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Dish ID.
	 * @return void
	 */
	public static function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'healtheat_price':
				echo esc_html( healtheat_format_price( healtheat_get_dish_price( $post_id ) ) );
				break;
			case 'healtheat_calories':
				$nutrition = healtheat_get_nutrition( $post_id );
				echo $nutrition['calories'] ? esc_html( $nutrition['calories'] ) : '—';
				break;
			case 'healtheat_available':
				echo healtheat_dish_is_available( $post_id ) ? '✅' : '—';
				break;
		}
	}
}
