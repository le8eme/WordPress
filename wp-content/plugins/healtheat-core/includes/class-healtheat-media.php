<?php
/**
 * Photos des plats : formats d'image, galerie, aperçu flouté et import.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles everything related to dish photography.
 */
class Healtheat_Media {

	/**
	 * Hooks the media features.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'after_setup_theme', array( __CLASS__, 'register_image_sizes' ) );
		add_filter( 'wp_generate_attachment_metadata', array( __CLASS__, 'store_placeholder' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_gallery_box' ) );
		add_action( 'save_post_healtheat_dish', array( __CLASS__, 'save_gallery' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_import_page' ) );
		add_action( 'admin_post_healtheat_import_photos', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'import_notice' ) );
	}

	/**
	 * Registers the crops used across the site.
	 *
	 * @return void
	 */
	public static function register_image_sizes() {
		// Carte : format paysage homogène quelles que soient les photos fournies.
		add_image_size( 'healtheat-card', 900, 675, true );
		// Fiche plat : grand format, hauteur libre.
		add_image_size( 'healtheat-large', 1400, 1050, false );
		// Vignettes rondes (orbite, bandeau, galerie).
		add_image_size( 'healtheat-round', 400, 400, true );
		// Bandeau pleine largeur.
		add_image_size( 'healtheat-wide', 2000, 1100, true );
	}

	/**
	 * Stores a tiny blurred preview so photos can fade in without jumping.
	 *
	 * @param array<string,mixed> $metadata      Attachment metadata.
	 * @param int                 $attachment_id Attachment ID.
	 * @return array<string,mixed>
	 */
	public static function store_placeholder( $metadata, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return $metadata;
		}

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return $metadata;
		}

		$editor->resize( 24, 24, false );

		$temp = wp_tempnam( 'healtheat-lqip' );
		$save = $editor->save( $temp, 'image/jpeg' );

		if ( is_wp_error( $save ) || empty( $save['path'] ) || ! file_exists( $save['path'] ) ) {
			return $metadata;
		}

		$contents = file_get_contents( $save['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		wp_delete_file( $save['path'] );

		if ( $temp !== $save['path'] && file_exists( $temp ) ) {
			wp_delete_file( $temp );
		}

		if ( $contents ) {
			update_post_meta( $attachment_id, '_healtheat_lqip', 'data:image/jpeg;base64,' . base64_encode( $contents ) );
		}

		return $metadata;
	}

	/**
	 * Returns the blurred preview of an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function get_placeholder( $attachment_id ) {
		return (string) get_post_meta( (int) $attachment_id, '_healtheat_lqip', true );
	}

	/**
	 * Returns the gallery attachment IDs of a dish.
	 *
	 * @param int $dish_id Dish ID.
	 * @return int[]
	 */
	public static function get_gallery( $dish_id ) {
		$ids = get_post_meta( $dish_id, '_healtheat_gallery', true );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Adds the gallery meta box.
	 *
	 * @return void
	 */
	public static function add_gallery_box() {
		add_meta_box(
			'healtheat-dish-gallery',
			__( 'Photos supplémentaires', 'healtheat' ),
			array( __CLASS__, 'render_gallery_box' ),
			'healtheat_dish',
			'side',
			'low'
		);
	}

	/**
	 * Renders the gallery meta box.
	 *
	 * @param WP_Post $post Dish.
	 * @return void
	 */
	public static function render_gallery_box( $post ) {
		wp_nonce_field( 'healtheat_save_gallery', 'healtheat_gallery_nonce' );

		$ids = self::get_gallery( $post->ID );
		?>
		<p class="description">
			<?php esc_html_e( 'La photo principale reste l\'image mise en avant. Ajoutez ici les vues complémentaires affichées sur la fiche du plat.', 'healtheat' ); ?>
		</p>

		<div class="healtheat-gallery" data-healtheat-gallery>
			<ul class="healtheat-gallery__list" data-healtheat-gallery-list>
				<?php foreach ( $ids as $id ) : ?>
					<li data-id="<?php echo esc_attr( $id ); ?>">
						<?php echo wp_get_attachment_image( $id, 'thumbnail' ); ?>
						<button type="button" class="healtheat-gallery__remove" aria-label="<?php esc_attr_e( 'Retirer', 'healtheat' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<input type="hidden" name="healtheat_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" data-healtheat-gallery-input />

			<button type="button" class="button" data-healtheat-gallery-add>
				<?php esc_html_e( 'Ajouter des photos', 'healtheat' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Saves the gallery.
	 *
	 * @param int $post_id Dish ID.
	 * @return void
	 */
	public static function save_gallery( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['healtheat_gallery_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['healtheat_gallery_nonce'] ) ), 'healtheat_save_gallery' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST['healtheat_gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['healtheat_gallery'] ) ) : '';
		$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );

		update_post_meta( $post_id, '_healtheat_gallery', $ids );
	}

	/**
	 * Loads the media modal on the dish screen.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public static function admin_assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen || 'healtheat_dish' !== $screen->post_type ) {
			return;
		}

		// Écran d'affectation en masse : simple sélecteur médiathèque par plat.
		if ( 'healtheat_dish_page_healtheat-photos' === $screen->id ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'healtheat-admin-photos',
				HEALTHEAT_URL . 'assets/js/healtheat-admin-photos.js',
				array(),
				HEALTHEAT_VERSION,
				true
			);
			wp_localize_script(
				'healtheat-admin-photos',
				'healtheatPhotos',
				array(
					'title'  => __( 'Choisir la photo du plat', 'healtheat' ),
					'button' => __( 'Utiliser cette photo', 'healtheat' ),
				)
			);

			return;
		}

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'healtheat-admin-gallery',
			HEALTHEAT_URL . 'assets/js/healtheat-admin-gallery.js',
			array( 'jquery' ),
			HEALTHEAT_VERSION,
			true
		);
		wp_localize_script(
			'healtheat-admin-gallery',
			'healtheatGallery',
			array(
				'title'  => __( 'Photos du plat', 'healtheat' ),
				'button' => __( 'Utiliser ces photos', 'healtheat' ),
			)
		);
		wp_add_inline_style(
			'wp-admin',
			'.healtheat-gallery__list{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0 0 10px;padding:0;list-style:none}
			.healtheat-gallery__list li{position:relative;margin:0}
			.healtheat-gallery__list img{width:100%;height:auto;display:block;border-radius:4px}
			.healtheat-gallery__remove{position:absolute;top:-6px;right:-6px;width:22px;height:22px;border:0;border-radius:50%;background:#b32d2e;color:#fff;line-height:1;cursor:pointer}'
		);
	}

	/**
	 * Adds the photo import screen.
	 *
	 * @return void
	 */
	public static function add_import_page() {
		add_submenu_page(
			'edit.php?post_type=healtheat_dish',
			__( 'Photos des plats', 'healtheat' ),
			__( 'Photos', 'healtheat' ),
			'upload_files',
			'healtheat-photos',
			array( __CLASS__, 'render_import_page' )
		);
	}

	/**
	 * Renders the photo import screen.
	 *
	 * @return void
	 */
	public static function render_import_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
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
		<div class="wrap">
			<h1><?php esc_html_e( 'Photos des plats', 'healtheat' ); ?></h1>

			<p>
				<?php esc_html_e( 'Chaque plat s\'affiche avec sa photo dès qu\'une image mise en avant lui est associée. Deux façons de procéder ici : choisir une photo déjà présente dans la médiathèque, ou coller l\'adresse publique d\'une image à télécharger. Dans les deux cas, elle est recadrée aux formats du site et devient l\'image principale du plat.', 'healtheat' ); ?>
			</p>

			<p>
				<?php esc_html_e( 'Photos achetées sur une banque payante (iStock, Getty, Adobe Stock…) : téléversez d\'abord les fichiers dans la médiathèque, puis affectez-les ci-dessous. L\'import par adresse ne fonctionne qu\'avec une image publiquement accessible — les liens de téléchargement d\'une banque payante sont liés à votre session et ne peuvent pas être utilisés ainsi. Les aperçus filigranés d\'une page de recherche ne sont, eux, pas utilisables : seule la version achetée l\'est.', 'healtheat' ); ?>
			</p>

			<p class="description">
				<?php esc_html_e( 'Si vous aviez déjà des photos avant l\'installation, régénérez les miniatures (extension « Regenerate Thumbnails » ou commande wp media regenerate) pour qu\'elles adoptent les formats du site.', 'healtheat' ); ?>
			</p>

			<p class="description">
				<?php
				printf(
					/* translators: 1: Pexels link, 2: Unsplash link. */
					esc_html__( 'Banques de photos gratuites et utilisables commercialement : %1$s et %2$s. Vérifiez toujours la licence de la photo avant de l\'utiliser.', 'healtheat' ),
					'<a href="https://www.pexels.com/fr-fr/chercher/healthy%20bowl/" target="_blank" rel="noopener noreferrer">Pexels</a>',
					'<a href="https://unsplash.com/fr/s/photos/healthy-food" target="_blank" rel="noopener noreferrer">Unsplash</a>'
				);
				?>
			</p>

			<?php Healtheat_Stock::render_panel(); ?>

			<?php self::render_provisional_panel(); ?>

			<h2><?php esc_html_e( 'Photo par plat', 'healtheat' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="healtheat_import_photos" />
				<?php wp_nonce_field( 'healtheat_import_photos' ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:110px"><?php esc_html_e( 'Photo', 'healtheat' ); ?></th>
							<th style="width:220px"><?php esc_html_e( 'Plat', 'healtheat' ); ?></th>
							<th><?php esc_html_e( 'Adresse de la photo à importer', 'healtheat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $dishes as $dish ) : ?>
							<tr data-healtheat-row>
								<td data-healtheat-preview>
									<?php if ( has_post_thumbnail( $dish ) ) : ?>
										<?php echo wp_get_attachment_image( get_post_thumbnail_id( $dish ), array( 90, 68 ) ); ?>
										<?php if ( get_post_meta( get_post_thumbnail_id( $dish ), Healtheat_Provisional::FLAG, true ) ) : ?>
											<br /><span style="font-size:11px;color:#8a5300"><?php esc_html_e( 'provisoire', 'healtheat' ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span style="color:#b32d2e">— <?php esc_html_e( 'aucune', 'healtheat' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<strong><a href="<?php echo esc_url( (string) get_edit_post_link( $dish ) ); ?>"><?php echo esc_html( get_the_title( $dish ) ); ?></a></strong>
									<br />
									<a href="<?php echo esc_url( 'https://www.pexels.com/fr-fr/chercher/' . rawurlencode( get_the_title( $dish ) ) . '/' ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Chercher une photo libre →', 'healtheat' ); ?>
									</a>
								</td>
								<td>
									<p>
										<button type="button" class="button" data-healtheat-pick>
											<?php esc_html_e( 'Choisir dans la médiathèque', 'healtheat' ); ?>
										</button>
										<span data-healtheat-picked></span>
										<input type="hidden" name="healtheat_photo_id[<?php echo esc_attr( $dish->ID ); ?>]" value="" data-healtheat-picked-id />
									</p>
									<p>
										<input type="url" class="large-text code" name="healtheat_photo[<?php echo esc_attr( $dish->ID ); ?>]" placeholder="<?php esc_attr_e( '…ou coller l\'adresse publique d\'une image', 'healtheat' ); ?>" />
									</p>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'Importer les photos renseignées', 'healtheat' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the temporary photo panel, used before the real photo shoot.
	 *
	 * @return void
	 */
	protected static function render_provisional_panel() {
		$provisional = count( Healtheat_Provisional::get_provisional_ids() );
		?>
		<div class="card" style="max-width:820px;padding:8px 20px 20px">
			<h2><?php esc_html_e( 'En attendant le shooting', 'healtheat' ); ?></h2>

			<p>
				<?php esc_html_e( 'Deux façons de ne pas laisser la carte vide avant vos vraies photos. Les visuels ajoutés ici sont marqués comme provisoires : une fois le shooting fait, un seul bouton les retire tous.', 'healtheat' ); ?>
			</p>

			<?php if ( $provisional ) : ?>
				<p style="padding:10px 14px;background:#fff4e5;border-left:4px solid #d68000">
					<?php
					printf(
						/* translators: %d: number of provisional photos. */
						esc_html( _n( '%d photo provisoire est actuellement en ligne.', '%d photos provisoires sont actuellement en ligne.', $provisional, 'healtheat' ) ),
						(int) $provisional
					);
					?>
				</p>
			<?php endif; ?>

			<div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="healtheat_provisional_fetch" />
					<?php wp_nonce_field( 'healtheat_provisional_fetch' ); ?>
					<?php submit_button( __( 'Importer des photos libres', 'healtheat' ), 'primary', 'submit', false ); ?>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="healtheat_provisional_generate" />
					<?php wp_nonce_field( 'healtheat_provisional_generate' ); ?>
					<?php submit_button( __( 'Générer des visuels de remplacement', 'healtheat' ), 'secondary', 'submit', false ); ?>
				</form>

				<?php if ( $provisional ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer définitivement toutes les photos provisoires ?', 'healtheat' ) ); ?>');">
						<input type="hidden" name="action" value="healtheat_provisional_purge" />
						<?php wp_nonce_field( 'healtheat_provisional_purge' ); ?>
						<?php submit_button( __( 'Supprimer les photos provisoires', 'healtheat' ), 'delete', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>

			<p class="description" style="margin-top:14px">
				<?php esc_html_e( '« Importer des photos libres » interroge Wikimedia Commons et ne retient que les images dont la licence autorise un usage commercial (CC0, domaine public, CC BY, CC BY-SA). L\'auteur et la licence sont enregistrés avec la photo et affichés sous le plat, comme ces licences l\'exigent. Les deux actions ne concernent que les plats qui n\'ont pas encore de photo.', 'healtheat' ); ?>
			</p>

			<p class="description">
				<?php esc_html_e( '« Générer des visuels de remplacement » ne demande aucun accès à Internet : le site fabrique lui-même un aplat dégradé par plat. Ce n\'est pas une photographie et cela ne prétend pas l\'être — juste de quoi tenir jusqu\'au shooting.', 'healtheat' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Downloads the submitted photos and attaches them to their dish.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'healtheat' ) );
		}

		check_admin_referer( 'healtheat_import_photos' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$submitted = isset( $_POST['healtheat_photo'] ) ? (array) wp_unslash( $_POST['healtheat_photo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$picked    = isset( $_POST['healtheat_photo_id'] ) ? (array) wp_unslash( $_POST['healtheat_photo_id'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$done      = 0;
		$errors    = array();

		// Photos déjà présentes dans la médiathèque : simple affectation.
		foreach ( $picked as $dish_id => $attachment_id ) {
			$dish_id       = absint( $dish_id );
			$attachment_id = absint( $attachment_id );

			if ( ! $dish_id || ! $attachment_id ) {
				continue;
			}

			if ( ! current_user_can( 'edit_post', $dish_id ) ) {
				$errors[] = sprintf(
					/* translators: %s: dish name. */
					__( '%s : modification refusée.', 'healtheat' ),
					get_the_title( $dish_id )
				);
				continue;
			}

			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				$errors[] = sprintf(
					/* translators: %s: dish name. */
					__( '%s : le fichier choisi n\'est pas une image.', 'healtheat' ),
					get_the_title( $dish_id )
				);
				continue;
			}

			set_post_thumbnail( $dish_id, $attachment_id );
			$done++;
		}

		foreach ( $submitted as $dish_id => $url ) {
			$dish_id = absint( $dish_id );
			$url     = esc_url_raw( trim( (string) $url ) );

			if ( ! $dish_id || '' === $url ) {
				continue;
			}

			$result = self::sideload( $url, $dish_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s : %s', get_the_title( $dish_id ), $result->get_error_message() );
				continue;
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
	 * Downloads one photo and sets it as the dish thumbnail.
	 *
	 * @param string $url     Remote image URL.
	 * @param int    $dish_id Dish ID.
	 * @return int|WP_Error Attachment ID on success.
	 */
	public static function sideload( $url, $dish_id ) {
		if ( ! current_user_can( 'edit_post', $dish_id ) ) {
			return new WP_Error( 'healtheat_forbidden', __( 'Vous ne pouvez pas modifier ce plat.', 'healtheat' ) );
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'healtheat_bad_url', __( 'Adresse invalide.', 'healtheat' ) );
		}

		$temp = download_url( $url, 30 );

		if ( is_wp_error( $temp ) ) {
			return $temp;
		}

		$name  = basename( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
		$name  = $name ? sanitize_file_name( $name ) : 'healtheat-photo.jpg';
		$check = wp_check_filetype_and_ext( $temp, $name );

		if ( empty( $check['type'] ) || ! str_starts_with( (string) $check['type'], 'image/' ) ) {
			wp_delete_file( $temp );

			return new WP_Error( 'healtheat_not_an_image', __( 'Ce fichier n\'est pas une image.', 'healtheat' ) );
		}

		if ( ! empty( $check['proper_filename'] ) ) {
			$name = $check['proper_filename'];
		} elseif ( ! pathinfo( $name, PATHINFO_EXTENSION ) ) {
			$name .= '.' . ( 'image/png' === $check['type'] ? 'png' : 'jpg' );
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $temp,
			),
			$dish_id,
			get_the_title( $dish_id )
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp );

			return $attachment_id;
		}

		set_post_thumbnail( $dish_id, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Shows the outcome of the last import.
	 *
	 * @return void
	 */
	public static function import_notice() {
		$key    = 'healtheat_import_result_' . get_current_user_id();
		$result = get_transient( $key );

		if ( ! $result ) {
			return;
		}

		delete_transient( $key );

		if ( ! empty( $result['done'] ) ) {
			$message = ! empty( $result['message'] )
				? $result['message']
				: sprintf(
					/* translators: %d: number of imported photos. */
					_n( '%d photo importée.', '%d photos importées.', (int) $result['done'], 'healtheat' ),
					(int) $result['done']
				);

			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}

		foreach ( (array) ( $result['errors'] ?? array() ) as $error ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
		}
	}
}
