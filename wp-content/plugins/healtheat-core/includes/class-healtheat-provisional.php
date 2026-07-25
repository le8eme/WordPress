<?php
/**
 * Photos provisoires, en attendant le shooting.
 *
 * Deux sources : de vraies photos sous licence libre récupérées sur
 * Wikimedia Commons, et — si le serveur n'a pas d'accès sortant — des
 * vignettes dégradées générées sur place. Dans les deux cas la pièce
 * jointe est marquée comme provisoire et se supprime en un clic.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fills the site with temporary visuals.
 */
class Healtheat_Provisional {

	const FLAG   = '_healtheat_provisional';
	const CREDIT = '_healtheat_credit';

	/**
	 * Hooks the admin actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_healtheat_provisional_fetch', array( __CLASS__, 'handle_fetch' ) );
		add_action( 'admin_post_healtheat_provisional_generate', array( __CLASS__, 'handle_generate' ) );
		add_action( 'admin_post_healtheat_provisional_purge', array( __CLASS__, 'handle_purge' ) );
	}

	/**
	 * Licences acceptées : réutilisation commerciale autorisée.
	 *
	 * Les mentions « NC » (non commercial), « ND » (pas de modification)
	 * et « fair use » sont écartées.
	 *
	 * @param string $license Nom court de la licence renvoyé par Commons.
	 * @return bool
	 */
	public static function license_is_allowed( $license ) {
		$license = strtolower( trim( (string) $license ) );

		if ( '' === $license ) {
			return false;
		}

		foreach ( array( '-nc', ' nc', 'noncommercial', '-nd', ' nd', 'noderiv', 'fair use', 'non-free', 'copyright' ) as $forbidden ) {
			if ( false !== strpos( $license, $forbidden ) ) {
				return false;
			}
		}

		foreach ( array( 'cc0', 'cc-zero', 'public domain', 'pd-', 'pdm', 'cc by', 'cc-by' ) as $allowed ) {
			if ( false !== strpos( $license, $allowed ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extrait les photos exploitables d'une réponse de l'API Commons.
	 *
	 * La fonction est volontairement tolérante : toute page incomplète est
	 * ignorée plutôt que de faire échouer l'ensemble.
	 *
	 * @param string $body Corps JSON de la réponse.
	 * @return array<int,array<string,string>>
	 */
	public static function parse_commons_response( $body ) {
		$data = json_decode( (string) $body, true );

		if ( ! is_array( $data ) || empty( $data['query']['pages'] ) || ! is_array( $data['query']['pages'] ) ) {
			return array();
		}

		$photos = array();

		foreach ( $data['query']['pages'] as $page ) {
			$info = $page['imageinfo'][0] ?? null;

			if ( ! is_array( $info ) || empty( $info['url'] ) ) {
				continue;
			}

			$meta    = is_array( $info['extmetadata'] ?? null ) ? $info['extmetadata'] : array();
			$license = (string) ( $meta['LicenseShortName']['value'] ?? '' );

			if ( ! self::license_is_allowed( $license ) ) {
				continue;
			}

			$mime = (string) ( $info['mime'] ?? '' );

			if ( $mime && ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
				continue;
			}

			$photos[] = array(
				'url'     => (string) $info['url'],
				'source'  => (string) ( $info['descriptionurl'] ?? '' ),
				'author'  => trim( wp_strip_all_tags( (string) ( $meta['Artist']['value'] ?? '' ) ) ),
				'license' => $license,
				'title'   => (string) ( $page['title'] ?? '' ),
			);
		}

		return $photos;
	}

	/**
	 * Interroge Wikimedia Commons.
	 *
	 * @param string $query Termes de recherche.
	 * @param int    $limit Nombre de résultats souhaités.
	 * @return array<int,array<string,string>>|WP_Error
	 */
	public static function search_commons( $query, $limit = 6 ) {
		$url = add_query_arg(
			array(
				'action'      => 'query',
				'format'      => 'json',
				'formatversion' => '2',
				'generator'   => 'search',
				'gsrsearch'   => 'filetype:bitmap ' . $query,
				'gsrnamespace' => '6',
				'gsrlimit'    => max( 1, min( 20, (int) $limit ) ),
				'prop'        => 'imageinfo',
				'iiprop'      => 'url|mime|extmetadata',
				'iiurlwidth'  => '1600',
			),
			'https://commons.wikimedia.org/w/api.php'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'healtheat_commons_http',
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Wikimedia Commons a répondu %d.', 'healtheat' ),
					(int) $code
				)
			);
		}

		return self::parse_commons_response( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Returns the search terms used for a dish.
	 *
	 * @param int $dish_id Dish ID.
	 * @return string
	 */
	public static function build_query( $dish_id ) {
		$terms = healtheat_get_term_names( $dish_id, 'healtheat_dish_cat' );
		$title = get_the_title( $dish_id );

		// Le nom commercial d'un plat ne parle pas à un moteur de recherche
		// documentaire : on l'accompagne de sa catégorie.
		return trim( $title . ' ' . implode( ' ', $terms ) . ' food' );
	}

	/**
	 * Imports one free photo for a dish.
	 *
	 * @param int    $dish_id Dish ID.
	 * @param string $query   Search terms, defaults to the dish name.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function import_for_dish( $dish_id, $query = '' ) {
		$query  = $query ? $query : self::build_query( $dish_id );
		$photos = self::search_commons( $query );

		if ( is_wp_error( $photos ) ) {
			return $photos;
		}

		if ( empty( $photos ) ) {
			return new WP_Error(
				'healtheat_no_photo',
				sprintf(
					/* translators: %s: search terms. */
					__( 'Aucune photo librement réutilisable trouvée pour « %s ».', 'healtheat' ),
					$query
				)
			);
		}

		$last_error = null;

		foreach ( $photos as $photo ) {
			$attachment_id = Healtheat_Media::sideload( $photo['url'], $dish_id );

			if ( is_wp_error( $attachment_id ) ) {
				$last_error = $attachment_id;
				continue;
			}

			self::mark_provisional( $attachment_id );

			update_post_meta(
				$attachment_id,
				self::CREDIT,
				array(
					'author'  => $photo['author'],
					'license' => $photo['license'],
					'source'  => $photo['source'],
				)
			);

			return $attachment_id;
		}

		return $last_error ? $last_error : new WP_Error( 'healtheat_no_photo', __( 'Import impossible.', 'healtheat' ) );
	}

	/**
	 * Flags an attachment as provisional.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function mark_provisional( $attachment_id ) {
		update_post_meta( (int) $attachment_id, self::FLAG, 1 );
	}

	/**
	 * Returns the provisional attachments.
	 *
	 * @return int[]
	 */
	public static function get_provisional_ids() {
		return get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => self::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Deletes every provisional photo.
	 *
	 * @return int Number of deleted attachments.
	 */
	public static function purge() {
		$deleted = 0;

		foreach ( self::get_provisional_ids() as $attachment_id ) {
			$parent = (int) get_post_field( 'post_parent', $attachment_id );

			if ( $parent && (int) get_post_thumbnail_id( $parent ) === (int) $attachment_id ) {
				delete_post_thumbnail( $parent );
			}

			if ( wp_delete_attachment( $attachment_id, true ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * Builds a temporary tile for a dish, without any network access.
	 *
	 * Nothing pretends to be a photograph here: c'est un aplat dégradé aux
	 * couleurs du site, décliné par plat, le temps du shooting.
	 *
	 * @param int $dish_id Dish ID.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function generate_tile( $dish_id ) {
		$contents = self::render_tile_bytes( $dish_id );

		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		$upload = wp_upload_bits( 'healtheat-provisoire-' . $dish_id . '.jpg', null, $contents );

		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'healtheat_upload_failed', $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sprintf(
					/* translators: %s: dish name. */
					__( 'Visuel provisoire — %s', 'healtheat' ),
					get_the_title( $dish_id )
				),
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$dish_id
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return is_wp_error( $attachment_id ) ? $attachment_id : new WP_Error( 'healtheat_attach_failed', __( 'Impossible d\'enregistrer le visuel.', 'healtheat' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		set_post_thumbnail( $dish_id, $attachment_id );
		self::mark_provisional( $attachment_id );

		return $attachment_id;
	}

	/**
	 * Draws the tile itself and returns the JPEG bytes.
	 *
	 * Isolé de WordPress pour être vérifiable en dehors du site.
	 *
	 * @param int $dish_id Dish ID, used as colour seed.
	 * @return string|WP_Error
	 */
	public static function render_tile_bytes( $dish_id ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return new WP_Error( 'healtheat_no_gd', __( 'La bibliothèque GD n\'est pas disponible sur ce serveur.', 'healtheat' ) );
		}

		$width  = 1200;
		$height = 900;
		$image  = imagecreatetruecolor( $width, $height );

		/*
		 * Les teintes restent dans la palette du site — vert, cyan, violet —
		 * plutôt que de parcourir toute la roue chromatique : un aplat rouge
		 * n'aurait rien à faire sur la carte d'un restaurant healthy.
		 */
		$palettes = array(
			array( 152, 176 ),
			array( 186, 208 ),
			array( 96, 148 ),
			array( 264, 196 ),
			array( 168, 104 ),
		);
		$palette  = $palettes[ absint( $dish_id ) % count( $palettes ) ];

		list( $r1, $g1, $b1 ) = self::hue_to_rgb( $palette[0], 0.5, 0.11 );
		list( $r2, $g2, $b2 ) = self::hue_to_rgb( $palette[1], 0.72, 0.36 );

		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				// Dégradé diagonal, adouci vers les bords.
				$t = ( $x / $width * 0.6 + $y / $height * 0.4 );
				$d = sqrt( pow( ( $x - $width * 0.62 ) / $width, 2 ) + pow( ( $y - $height * 0.42 ) / $height, 2 ) );
				$g = max( 0, 1 - $d * 1.9 ) * 0.45;

				imagesetpixel(
					$image,
					$x,
					$y,
					imagecolorallocate(
						$image,
						(int) min( 255, $r1 + ( $r2 - $r1 ) * $t + 255 * $g * 0.12 ),
						(int) min( 255, $g1 + ( $g2 - $g1 ) * $t + 255 * $g * 0.20 ),
						(int) min( 255, $b1 + ( $b2 - $b1 ) * $t + 255 * $g * 0.16 )
					)
				);
			}
		}

		self::draw_bowl( $image, $width, $height );

		ob_start();
		imagejpeg( $image, null, 82 );
		$contents = (string) ob_get_clean();
		imagedestroy( $image );

		return $contents;
	}

	/**
	 * Draws a stylised bowl, using primitives only so no font is required.
	 *
	 * @param resource|GdImage $image  Image handle.
	 * @param int              $width  Image width.
	 * @param int              $height Image height.
	 * @return void
	 */
	protected static function draw_bowl( $image, $width, $height ) {
		$cx    = (int) ( $width * 0.5 );
		$cy    = (int) ( $height * 0.54 );
		$rim   = (int) ( $width * 0.26 );
		$light = imagecolorallocatealpha( $image, 255, 255, 255, 96 );
		$deep  = imagecolorallocatealpha( $image, 0, 0, 0, 100 );

		// Ombre portée puis corps du bol.
		imagefilledellipse( $image, $cx, $cy + (int) ( $rim * 0.62 ), (int) ( $rim * 2.1 ), (int) ( $rim * 0.5 ), $deep );
		imagefilledarc( $image, $cx, $cy, $rim * 2, (int) ( $rim * 1.7 ), 0, 180, $light, IMG_ARC_PIE );
		imagefilledellipse( $image, $cx, $cy, $rim * 2, (int) ( $rim * 0.5 ), $light );

		// Ingrédients : trois disques posés sur le bord.
		foreach ( array( array( -0.42, -0.1, 0.3 ), array( 0.08, -0.2, 0.36 ), array( 0.46, -0.04, 0.26 ) ) as $blob ) {
			imagefilledellipse(
				$image,
				(int) ( $cx + $rim * $blob[0] ),
				(int) ( $cy + $rim * $blob[1] ),
				(int) ( $rim * $blob[2] ),
				(int) ( $rim * $blob[2] ),
				$light
			);
		}

		imagesetthickness( $image, 3 );
		imageellipse( $image, $cx, $cy, $rim * 2, (int) ( $rim * 0.5 ), $light );
		imagesetthickness( $image, 1 );
	}

	/**
	 * Converts an HSL colour to RGB.
	 *
	 * @param float $hue        Hue, 0-360.
	 * @param float $saturation Saturation, 0-1.
	 * @param float $lightness  Lightness, 0-1.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function hue_to_rgb( $hue, $saturation, $lightness ) {
		$c = ( 1 - abs( 2 * $lightness - 1 ) ) * $saturation;
		$x = $c * ( 1 - abs( fmod( $hue / 60, 2 ) - 1 ) );
		$m = $lightness - $c / 2;

		if ( $hue < 60 ) {
			$rgb = array( $c, $x, 0 );
		} elseif ( $hue < 120 ) {
			$rgb = array( $x, $c, 0 );
		} elseif ( $hue < 180 ) {
			$rgb = array( 0, $c, $x );
		} elseif ( $hue < 240 ) {
			$rgb = array( 0, $x, $c );
		} elseif ( $hue < 300 ) {
			$rgb = array( $x, 0, $c );
		} else {
			$rgb = array( $c, 0, $x );
		}

		return array(
			(int) round( ( $rgb[0] + $m ) * 255 ),
			(int) round( ( $rgb[1] + $m ) * 255 ),
			(int) round( ( $rgb[2] + $m ) * 255 ),
		);
	}

	/**
	 * Returns the dishes without any photo yet.
	 *
	 * @return WP_Post[]
	 */
	protected static function get_dishes_without_photo() {
		return get_posts(
			array(
				'post_type'      => 'healtheat_dish',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => 100,
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
	}

	/**
	 * Downloads a free photo for every dish still missing one.
	 *
	 * @return void
	 */
	public static function handle_fetch() {
		self::guard( 'healtheat_provisional_fetch' );

		$done   = 0;
		$errors = array();

		foreach ( self::get_dishes_without_photo() as $dish ) {
			$result = self::import_for_dish( $dish->ID );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s : %s', get_the_title( $dish ), $result->get_error_message() );
				continue;
			}

			$done++;
		}

		self::finish(
			$done,
			$errors,
			/* translators: %d: number of photos. */
			_n_noop( '%d photo provisoire importée depuis Wikimedia Commons.', '%d photos provisoires importées depuis Wikimedia Commons.', 'healtheat' )
		);
	}

	/**
	 * Generates a tile for every dish still missing a photo.
	 *
	 * @return void
	 */
	public static function handle_generate() {
		self::guard( 'healtheat_provisional_generate' );

		$done   = 0;
		$errors = array();

		foreach ( self::get_dishes_without_photo() as $dish ) {
			$result = self::generate_tile( $dish->ID );

			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s : %s', get_the_title( $dish ), $result->get_error_message() );
				continue;
			}

			$done++;
		}

		self::finish(
			$done,
			$errors,
			/* translators: %d: number of tiles. */
			_n_noop( '%d visuel provisoire généré.', '%d visuels provisoires générés.', 'healtheat' )
		);
	}

	/**
	 * Removes every provisional photo.
	 *
	 * @return void
	 */
	public static function handle_purge() {
		self::guard( 'healtheat_provisional_purge' );

		$deleted = self::purge();

		self::finish(
			$deleted,
			array(),
			/* translators: %d: number of photos. */
			_n_noop( '%d photo provisoire supprimée.', '%d photos provisoires supprimées.', 'healtheat' )
		);
	}

	/**
	 * Checks capability and nonce.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	protected static function guard( $action ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Permission refusée.', 'healtheat' ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Stores the outcome and returns to the photo screen.
	 *
	 * @param int      $done     Number of successful operations.
	 * @param string[] $errors   Error messages.
	 * @param array    $singular Result of _n_noop() used for the message.
	 * @return void
	 */
	protected static function finish( $done, $errors, $singular ) {
		set_transient(
			'healtheat_import_result_' . get_current_user_id(),
			array(
				'done'    => (int) $done,
				'errors'  => $errors,
				'message' => $done ? sprintf( translate_nooped_plural( $singular, (int) $done, 'healtheat' ), (int) $done ) : '',
			),
			60
		);

		wp_safe_redirect( admin_url( 'edit.php?post_type=healtheat_dish&page=healtheat-photos' ) );
		exit;
	}
}
