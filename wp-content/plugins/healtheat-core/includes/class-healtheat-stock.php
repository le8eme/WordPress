<?php
/**
 * Banque d'images : recherche et import de vraies photos.
 *
 * L'intégration vise Pexels, dont la licence autorise l'usage commercial
 * sans achat ni attribution obligatoire. La clé d'API est gratuite et
 * s'obtient en quelques minutes sur pexels.com/api.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Searches a stock photo library and imports the chosen pictures.
 */
class Healtheat_Stock {

	const OPTION  = 'healtheat_pexels_key';
	const ENDPOINT = 'https://api.pexels.com/v1/search';

	/**
	 * Seul hôte accepté pour un import : les fichiers servis par Pexels.
	 */
	const ALLOWED_HOST = 'images.pexels.com';

	/**
	 * Hooks the admin actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_healtheat_stock_key', array( __CLASS__, 'handle_key' ) );
		add_action( 'admin_post_healtheat_stock_import', array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Returns the configured API key.
	 *
	 * @return string
	 */
	public static function get_key() {
		if ( defined( 'HEALTHEAT_PEXELS_KEY' ) && HEALTHEAT_PEXELS_KEY ) {
			return (string) HEALTHEAT_PEXELS_KEY;
		}

		return (string) get_option( self::OPTION, '' );
	}

	/**
	 * Extracts the usable photos from an API response.
	 *
	 * Toute entrée incomplète est ignorée plutôt que de faire échouer la
	 * recherche entière.
	 *
	 * @param string $body JSON body.
	 * @return array<int,array<string,string>>
	 */
	public static function parse_response( $body ) {
		$data = json_decode( (string) $body, true );

		if ( ! is_array( $data ) || empty( $data['photos'] ) || ! is_array( $data['photos'] ) ) {
			return array();
		}

		$photos = array();

		foreach ( $data['photos'] as $photo ) {
			if ( ! is_array( $photo ) || empty( $photo['src'] ) || ! is_array( $photo['src'] ) ) {
				continue;
			}

			$full    = $photo['src']['large2x'] ?? $photo['src']['large'] ?? $photo['src']['original'] ?? '';
			$preview = $photo['src']['medium'] ?? $photo['src']['small'] ?? $photo['src']['tiny'] ?? $full;

			if ( ! $full || ! self::url_is_allowed( $full ) ) {
				continue;
			}

			$photos[] = array(
				'id'               => (string) ( $photo['id'] ?? '' ),
				'full'             => (string) $full,
				'preview'          => (string) $preview,
				'photographer'     => (string) ( $photo['photographer'] ?? '' ),
				'photographer_url' => (string) ( $photo['photographer_url'] ?? '' ),
				'page'             => (string) ( $photo['url'] ?? '' ),
				'alt'              => (string) ( $photo['alt'] ?? '' ),
			);
		}

		return $photos;
	}

	/**
	 * Checks that a file URL really comes from the photo library.
	 *
	 * Sans ce garde-fou, un formulaire trafiqué pourrait faire télécharger
	 * n'importe quelle adresse par le serveur.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public static function url_is_allowed( $url ) {
		$host = wp_parse_url( (string) $url, PHP_URL_HOST );

		return self::ALLOWED_HOST === strtolower( (string) $host );
	}

	/**
	 * Runs a search against the photo library.
	 *
	 * @param string $query    Search terms.
	 * @param int    $per_page Number of results.
	 * @return array<int,array<string,string>>|WP_Error
	 */
	public static function search( $query, $per_page = 12 ) {
		$key = self::get_key();

		if ( ! $key ) {
			return new WP_Error( 'healtheat_no_key', __( 'Renseignez d\'abord votre clé d\'API Pexels.', 'healtheat' ) );
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'query'       => rawurlencode( $query ),
					'per_page'    => max( 1, min( 40, (int) $per_page ) ),
					'orientation' => 'landscape',
					'locale'      => 'fr-FR',
				),
				self::ENDPOINT
			),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => $key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new WP_Error( 'healtheat_bad_key', __( 'Clé d\'API refusée par Pexels.', 'healtheat' ) );
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'healtheat_stock_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'La banque d\'images a répondu %d.', 'healtheat' ),
					$code
				)
			);
		}

		return self::parse_response( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Saves the API key.
	 *
	 * @return void
	 */
	public static function handle_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'healtheat' ) );
		}

		check_admin_referer( 'healtheat_stock_key' );

		update_option( self::OPTION, sanitize_text_field( wp_unslash( $_POST['healtheat_pexels_key'] ?? '' ) ) );

		wp_safe_redirect( admin_url( 'edit.php?post_type=healtheat_dish&page=healtheat-photos' ) );
		exit;
	}

	/**
	 * Imports the photos selected in the results grid.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'healtheat' ) );
		}

		check_admin_referer( 'healtheat_stock_import' );

		$choices     = isset( $_POST['healtheat_stock'] ) ? (array) wp_unslash( $_POST['healtheat_stock'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$provisional = ! empty( $_POST['healtheat_stock_provisional'] );
		$done        = 0;
		$errors      = array();

		foreach ( $choices as $choice ) {
			$dish_id = absint( $choice['dish'] ?? 0 );
			$url     = esc_url_raw( (string) ( $choice['url'] ?? '' ) );

			if ( ! $dish_id || ! $url ) {
				continue;
			}

			if ( ! self::url_is_allowed( $url ) ) {
				$errors[] = __( 'Adresse de photo refusée.', 'healtheat' );
				continue;
			}

			$attachment_id = Healtheat_Media::sideload( $url, $dish_id );

			if ( is_wp_error( $attachment_id ) ) {
				$errors[] = sprintf( '%s : %s', get_the_title( $dish_id ), $attachment_id->get_error_message() );
				continue;
			}

			update_post_meta(
				$attachment_id,
				Healtheat_Provisional::CREDIT,
				array(
					'author'  => sanitize_text_field( (string) ( $choice['author'] ?? '' ) ),
					'license' => 'Licence Pexels',
					'source'  => esc_url_raw( (string) ( $choice['page'] ?? '' ) ),
				)
			);

			if ( $provisional ) {
				Healtheat_Provisional::mark_provisional( $attachment_id );
			}

			$done++;
		}

		set_transient(
			'healtheat_import_result_' . get_current_user_id(),
			array(
				'done'   => $done,
				'errors' => $errors,
			),
			60
		);

		wp_safe_redirect( admin_url( 'edit.php?post_type=healtheat_dish&page=healtheat-photos' ) );
		exit;
	}

	/**
	 * Renders the search panel on the photo screen.
	 *
	 * @return void
	 */
	public static function render_panel() {
		$key = self::get_key();
		// La recherche est une simple lecture : un nonce suffirait à gêner
		// l'usage normal du bouton « précédent ».
		$query = isset( $_GET['healtheat_q'] ) ? sanitize_text_field( wp_unslash( $_GET['healtheat_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="card" style="max-width:1100px;padding:8px 20px 20px">
			<h2><?php esc_html_e( 'Banque d\'images', 'healtheat' ); ?></h2>

			<?php if ( ! $key ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: link to the Pexels API page. */
						esc_html__( 'Pour chercher de vraies photos depuis cet écran, collez une clé d\'API Pexels. Elle est gratuite et immédiate : %s.', 'healtheat' ),
						'<a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">pexels.com/api</a>'
					);
					?>
				</p>
				<p class="description">
					<?php esc_html_e( 'La licence Pexels autorise l\'usage commercial, y compris sur le site d\'un restaurant, sans achat ni mention obligatoire. Nous enregistrons quand même le nom du photographe.', 'healtheat' ); ?>
				</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="healtheat_stock_key" />
					<?php wp_nonce_field( 'healtheat_stock_key' ); ?>
					<input type="text" class="regular-text code" name="healtheat_pexels_key" placeholder="<?php esc_attr_e( 'Votre clé d\'API', 'healtheat' ); ?>" />
					<?php submit_button( __( 'Enregistrer la clé', 'healtheat' ), 'primary', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
					<input type="hidden" name="post_type" value="healtheat_dish" />
					<input type="hidden" name="page" value="healtheat-photos" />
					<input type="search" class="regular-text" name="healtheat_q" value="<?php echo esc_attr( $query ); ?>"
						placeholder="<?php esc_attr_e( 'bowl quinoa, salade césar, jus détox…', 'healtheat' ); ?>" />
					<?php submit_button( __( 'Chercher des photos', 'healtheat' ), 'primary', 'submit', false ); ?>
				</form>

				<?php if ( $query ) : ?>
					<?php self::render_results( $query ); ?>
				<?php endif; ?>

				<p class="description" style="margin-top:16px">
					<?php esc_html_e( 'Clé enregistrée. Pour la changer, videz le champ ci-dessous et enregistrez.', 'healtheat' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="healtheat_stock_key" />
					<?php wp_nonce_field( 'healtheat_stock_key' ); ?>
					<input type="text" class="regular-text code" name="healtheat_pexels_key" value="" placeholder="<?php esc_attr_e( 'Nouvelle clé', 'healtheat' ); ?>" />
					<?php submit_button( __( 'Mettre à jour la clé', 'healtheat' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the result grid for a search.
	 *
	 * @param string $query Search terms.
	 * @return void
	 */
	protected static function render_results( $query ) {
		$photos = self::search( $query );

		if ( is_wp_error( $photos ) ) {
			printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $photos->get_error_message() ) );

			return;
		}

		if ( empty( $photos ) ) {
			printf( '<p>%s</p>', esc_html__( 'Aucun résultat pour cette recherche.', 'healtheat' ) );

			return;
		}

		$dishes = get_posts(
			array(
				'post_type'      => 'healtheat_dish',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 100,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="healtheat_stock_import" />
			<?php wp_nonce_field( 'healtheat_stock_import' ); ?>

			<p>
				<label>
					<input type="checkbox" name="healtheat_stock_provisional" value="1" checked="checked" />
					<?php esc_html_e( 'Marquer ces photos comme provisoires (supprimables en un clic après le shooting)', 'healtheat' ); ?>
				</label>
			</p>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:18px;margin:16px 0">
				<?php foreach ( $photos as $index => $photo ) : ?>
					<div style="border:1px solid #dcdcde;border-radius:6px;overflow:hidden;background:#fff">
						<img src="<?php echo esc_url( $photo['preview'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ); ?>" style="width:100%;height:150px;object-fit:cover;display:block" />

						<div style="padding:10px">
							<p style="margin:0 0 8px;font-size:12px;color:#646970">
								<?php echo esc_html( $photo['photographer'] ); ?>
								<?php if ( $photo['page'] ) : ?>
									— <a href="<?php echo esc_url( $photo['page'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'voir', 'healtheat' ); ?></a>
								<?php endif; ?>
							</p>

							<input type="hidden" name="healtheat_stock[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_url( $photo['full'] ); ?>" />
							<input type="hidden" name="healtheat_stock[<?php echo esc_attr( $index ); ?>][author]" value="<?php echo esc_attr( $photo['photographer'] ); ?>" />
							<input type="hidden" name="healtheat_stock[<?php echo esc_attr( $index ); ?>][page]" value="<?php echo esc_url( $photo['page'] ); ?>" />

							<select name="healtheat_stock[<?php echo esc_attr( $index ); ?>][dish]" style="width:100%">
								<option value="0"><?php esc_html_e( '— ne pas importer —', 'healtheat' ); ?></option>
								<?php foreach ( $dishes as $dish ) : ?>
									<option value="<?php echo esc_attr( $dish->ID ); ?>"><?php echo esc_html( get_the_title( $dish ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button( __( 'Importer les photos choisies', 'healtheat' ) ); ?>
		</form>
		<?php
	}
}
