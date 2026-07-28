<?php
/**
 * Settings screen: restaurant identity and click &amp; collect rules.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and renders the plugin settings.
 */
class Healtheat_Settings {

	const OPTION = 'healtheat_settings';

	/**
	 * Runtime cache of the merged settings.
	 *
	 * @var array<string,mixed>|null
	 */
	protected static $cache = null;

	/**
	 * Hooks the settings screen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Returns the default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$hours = array();

		foreach ( range( 0, 6 ) as $day ) {
			$hours[ $day ] = array(
				'enabled' => ( 0 === $day ) ? 0 : 1,
				'open'    => '11:30',
				'close'   => '14:30',
			);
		}

		return array(
			'restaurant_name'  => "Health'eat",
			'email'            => get_option( 'admin_email' ),
			'phone'            => '',
			'address'          => '',
			'currency'         => '€',
			'ordering_enabled' => 1,
			'lead_time'        => 30,
			'slot_interval'    => 15,
			'slot_capacity'    => 6,
			'days_ahead'       => 5,
			'min_order'        => 0,
			'order_page_id'    => 0,
			'plan_categories'  => array(),
			'hours'            => $hours,
		);
	}

	/**
	 * Returns the stored settings merged with the defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();

			self::$cache = array_merge( self::get_defaults(), $stored );

			if ( empty( self::$cache['hours'] ) || ! is_array( self::$cache['hours'] ) ) {
				self::$cache['hours'] = self::get_defaults()['hours'];
			}
		}

		return self::$cache;
	}

	/**
	 * Clears the runtime cache, used after an update.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Registers the option and its sanitizer.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'healtheat_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Adds the settings page under the Health'eat menu.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_submenu_page(
			'edit.php?post_type=healtheat_dish',
			__( 'Réglages Health\'eat', 'healtheat' ),
			__( 'Réglages', 'healtheat' ),
			'manage_options',
			'healtheat-settings',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Sanitizes the submitted settings.
	 *
	 * @param mixed $input Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::get_defaults();
		$clean    = array();

		$clean['restaurant_name']  = sanitize_text_field( $input['restaurant_name'] ?? $defaults['restaurant_name'] );
		$clean['email']            = sanitize_email( $input['email'] ?? $defaults['email'] );
		$clean['phone']            = sanitize_text_field( $input['phone'] ?? '' );
		$clean['address']          = sanitize_textarea_field( $input['address'] ?? '' );
		$clean['currency']         = sanitize_text_field( $input['currency'] ?? '€' );
		$clean['ordering_enabled'] = empty( $input['ordering_enabled'] ) ? 0 : 1;
		$clean['lead_time']        = min( 1440, max( 0, absint( $input['lead_time'] ?? 30 ) ) );
		$clean['slot_interval']    = min( 120, max( 5, absint( $input['slot_interval'] ?? 15 ) ) );
		$clean['slot_capacity']    = max( 1, absint( $input['slot_capacity'] ?? 6 ) );
		$clean['days_ahead']       = min( 30, max( 1, absint( $input['days_ahead'] ?? 5 ) ) );
		$clean['min_order']        = healtheat_to_cents( $input['min_order'] ?? 0 );
		$clean['order_page_id']    = absint( $input['order_page_id'] ?? 0 );

		$clean['plan_categories'] = array();

		foreach ( (array) ( $input['plan_categories'] ?? array() ) as $slug ) {
			$slug = sanitize_title( $slug );

			if ( $slug ) {
				$clean['plan_categories'][] = $slug;
			}
		}

		$clean['hours'] = array();

		foreach ( range( 0, 6 ) as $day ) {
			$row = $input['hours'][ $day ] ?? array();

			$clean['hours'][ $day ] = array(
				'enabled' => empty( $row['enabled'] ) ? 0 : 1,
				'open'    => self::sanitize_time( $row['open'] ?? '11:30' ),
				'close'   => self::sanitize_time( $row['close'] ?? '14:30' ),
			);
		}

		self::flush_cache();

		return $clean;
	}

	/**
	 * Normalises a HH:MM string.
	 *
	 * @param mixed $value Raw time.
	 * @return string
	 */
	protected static function sanitize_time( $value ) {
		$value = trim( (string) $value );

		if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches ) ) {
			return '00:00';
		}

		return sprintf( '%02d:%02d', (int) $matches[1], (int) $matches[2] );
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$days     = array(
			1 => __( 'Lundi', 'healtheat' ),
			2 => __( 'Mardi', 'healtheat' ),
			3 => __( 'Mercredi', 'healtheat' ),
			4 => __( 'Jeudi', 'healtheat' ),
			5 => __( 'Vendredi', 'healtheat' ),
			6 => __( 'Samedi', 'healtheat' ),
			0 => __( 'Dimanche', 'healtheat' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Réglages Health\'eat', 'healtheat' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'healtheat_settings_group' ); ?>

				<h2><?php esc_html_e( 'Le restaurant', 'healtheat' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="he-name"><?php esc_html_e( 'Nom', 'healtheat' ); ?></label></th>
						<td><input type="text" class="regular-text" id="he-name" name="<?php echo esc_attr( self::OPTION ); ?>[restaurant_name]" value="<?php echo esc_attr( $settings['restaurant_name'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="he-email"><?php esc_html_e( 'E-mail des commandes', 'healtheat' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="he-email" name="<?php echo esc_attr( self::OPTION ); ?>[email]" value="<?php echo esc_attr( $settings['email'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Adresse qui reçoit les nouvelles commandes click &amp; collect.', 'healtheat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-phone"><?php esc_html_e( 'Téléphone', 'healtheat' ); ?></label></th>
						<td><input type="text" class="regular-text" id="he-phone" name="<?php echo esc_attr( self::OPTION ); ?>[phone]" value="<?php echo esc_attr( $settings['phone'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="he-address"><?php esc_html_e( 'Adresse', 'healtheat' ); ?></label></th>
						<td><textarea class="large-text" rows="3" id="he-address" name="<?php echo esc_attr( self::OPTION ); ?>[address]"><?php echo esc_textarea( $settings['address'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="he-currency"><?php esc_html_e( 'Devise', 'healtheat' ); ?></label></th>
						<td><input type="text" class="small-text" id="he-currency" name="<?php echo esc_attr( self::OPTION ); ?>[currency]" value="<?php echo esc_attr( $settings['currency'] ); ?>" /></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Click &amp; collect', 'healtheat' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Commande en ligne', 'healtheat' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[ordering_enabled]" value="1" <?php checked( $settings['ordering_enabled'], 1 ); ?> />
								<?php esc_html_e( 'Accepter les commandes à emporter', 'healtheat' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-lead"><?php esc_html_e( 'Délai de préparation', 'healtheat' ); ?></label></th>
						<td>
							<input type="number" min="0" max="1440" class="small-text" id="he-lead" name="<?php echo esc_attr( self::OPTION ); ?>[lead_time]" value="<?php echo esc_attr( $settings['lead_time'] ); ?>" />
							<?php esc_html_e( 'minutes avant le premier créneau disponible.', 'healtheat' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-interval"><?php esc_html_e( 'Intervalle des créneaux', 'healtheat' ); ?></label></th>
						<td>
							<input type="number" min="5" max="120" step="5" class="small-text" id="he-interval" name="<?php echo esc_attr( self::OPTION ); ?>[slot_interval]" value="<?php echo esc_attr( $settings['slot_interval'] ); ?>" />
							<?php esc_html_e( 'minutes.', 'healtheat' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-capacity"><?php esc_html_e( 'Commandes par créneau', 'healtheat' ); ?></label></th>
						<td><input type="number" min="1" class="small-text" id="he-capacity" name="<?php echo esc_attr( self::OPTION ); ?>[slot_capacity]" value="<?php echo esc_attr( $settings['slot_capacity'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="he-days"><?php esc_html_e( 'Réservation à l\'avance', 'healtheat' ); ?></label></th>
						<td>
							<input type="number" min="1" max="30" class="small-text" id="he-days" name="<?php echo esc_attr( self::OPTION ); ?>[days_ahead]" value="<?php echo esc_attr( $settings['days_ahead'] ); ?>" />
							<?php esc_html_e( 'jours.', 'healtheat' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-min"><?php esc_html_e( 'Montant minimum', 'healtheat' ); ?></label></th>
						<td>
							<input type="text" class="small-text" id="he-min" name="<?php echo esc_attr( self::OPTION ); ?>[min_order]" value="<?php echo esc_attr( number_format( $settings['min_order'] / 100, 2, '.', '' ) ); ?>" />
							<p class="description"><?php esc_html_e( '0 pour désactiver.', 'healtheat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="he-order-page"><?php esc_html_e( 'Page de commande', 'healtheat' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => self::OPTION . '[order_page_id]',
									'id'                => 'he-order-page',
									'selected'          => $settings['order_page_id'],
									'show_option_none'  => __( '— Aucune —', 'healtheat' ),
									'option_none_value' => 0,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page contenant le shortcode [healtheat_order].', 'healtheat' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Menu de la semaine', 'healtheat' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Catégories proposées', 'healtheat' ); ?></th>
						<td>
							<?php
							$categories = get_terms( array( 'taxonomy' => 'healtheat_dish_cat', 'hide_empty' => false ) );

							if ( is_wp_error( $categories ) || ! $categories ) {
								esc_html_e( 'Aucune catégorie pour le moment.', 'healtheat' );
							} else {
								foreach ( $categories as $category ) {
									printf(
										'<label style="margin-right:18px"><input type="checkbox" name="%1$s[plan_categories][]" value="%2$s" %3$s /> %4$s</label>',
										esc_attr( self::OPTION ),
										esc_attr( $category->slug ),
										checked( in_array( $category->slug, (array) $settings['plan_categories'], true ), true, false ),
										esc_html( $category->name )
									);
								}
							}
							?>
							<p class="description">
								<?php esc_html_e( 'Seules ces catégories entrent dans le menu personnalisé des clients : de quoi éviter qu\'un jus ou un dessert soit proposé comme déjeuner. Aucune case cochée : toute la carte est utilisée.', 'healtheat' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Horaires de retrait', 'healtheat' ); ?></h2>
				<table class="widefat striped" style="max-width:640px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Jour', 'healtheat' ); ?></th>
							<th><?php esc_html_e( 'Ouvert', 'healtheat' ); ?></th>
							<th><?php esc_html_e( 'De', 'healtheat' ); ?></th>
							<th><?php esc_html_e( 'À', 'healtheat' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $days as $index => $label ) : ?>
							<?php $row = $settings['hours'][ $index ] ?? array( 'enabled' => 0, 'open' => '11:30', 'close' => '14:30' ); ?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $index ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
								</td>
								<td>
									<input type="time" name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $index ); ?>][open]" value="<?php echo esc_attr( $row['open'] ); ?>" />
								</td>
								<td>
									<input type="time" name="<?php echo esc_attr( self::OPTION ); ?>[hours][<?php echo esc_attr( $index ); ?>][close]" value="<?php echo esc_attr( $row['close'] ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
