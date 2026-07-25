<?php
/**
 * REST endpoints powering the click &amp; collect funnel.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the public REST routes.
 */
class Healtheat_Rest {

	const NAMESPACE = 'healtheat/v1';

	/**
	 * Hooks the route registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_slots' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/orders',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_order' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'pickup' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'notes'  => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'items'  => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);
	}

	/**
	 * Returns the bookable pickup slots.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_slots() {
		if ( ! healtheat_ordering_enabled() ) {
			return new WP_Error(
				'healtheat_ordering_closed',
				__( 'La commande en ligne est momentanément fermée.', 'healtheat' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response(
			array(
				'days' => Healtheat_Slots::get_available_days(),
			)
		);
	}

	/**
	 * Creates an order from the submitted cart.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_order( WP_REST_Request $request ) {
		if ( ! healtheat_ordering_enabled() ) {
			return new WP_Error(
				'healtheat_ordering_closed',
				__( 'La commande en ligne est momentanément fermée.', 'healtheat' ),
				array( 'status' => 503 )
			);
		}

		$throttle = self::check_throttle();

		if ( is_wp_error( $throttle ) ) {
			return $throttle;
		}

		// Honeypot: filled in by bots only.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_Error(
				'healtheat_spam',
				__( 'Commande refusée.', 'healtheat' ),
				array( 'status' => 400 )
			);
		}

		$name  = trim( (string) $request->get_param( 'name' ) );
		$email = (string) $request->get_param( 'email' );
		$phone = trim( (string) $request->get_param( 'phone' ) );

		if ( mb_strlen( $name ) < 2 ) {
			return new WP_Error( 'healtheat_invalid_name', __( 'Merci d\'indiquer votre nom.', 'healtheat' ), array( 'status' => 400 ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'healtheat_invalid_email', __( 'Cette adresse e-mail est invalide.', 'healtheat' ), array( 'status' => 400 ) );
		}

		if ( strlen( preg_replace( '/\D/', '', $phone ) ) < 8 ) {
			return new WP_Error( 'healtheat_invalid_phone', __( 'Merci d\'indiquer un numéro de téléphone valide.', 'healtheat' ), array( 'status' => 400 ) );
		}

		$pickup = (string) $request->get_param( 'pickup' );
		$slot   = Healtheat_Slots::validate( $pickup );

		if ( is_wp_error( $slot ) ) {
			return $slot;
		}

		$items = $request->get_param( 'items' );

		if ( ! is_array( $items ) || count( $items ) > 50 ) {
			return new WP_Error( 'healtheat_invalid_cart', __( 'Panier invalide.', 'healtheat' ), array( 'status' => 400 ) );
		}

		$order_id = Healtheat_Orders::create(
			$items,
			array(
				'name'   => $name,
				'email'  => $email,
				'phone'  => $phone,
				'pickup' => $pickup,
				'notes'  => (string) $request->get_param( 'notes' ),
			)
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		self::bump_throttle();

		$order = Healtheat_Orders::get_order( $order_id );

		return rest_ensure_response(
			array(
				'reference'  => $order['reference'],
				'total_html' => healtheat_format_price( $order['total'] ),
				'pickup'     => Healtheat_Orders::format_pickup( $order['pickup'] ),
				'message'    => sprintf(
					/* translators: 1: order reference, 2: pickup slot. */
					__( 'Merci ! Votre commande %1$s est enregistrée pour le %2$s.', 'healtheat' ),
					$order['reference'],
					Healtheat_Orders::format_pickup( $order['pickup'] )
				),
			)
		);
	}

	/**
	 * Returns the throttle transient key for the current visitor.
	 *
	 * @return string
	 */
	protected static function get_throttle_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return 'healtheat_rate_' . md5( $ip );
	}

	/**
	 * Blocks visitors sending too many orders in a row.
	 *
	 * @return true|WP_Error
	 */
	protected static function check_throttle() {
		$count = (int) get_transient( self::get_throttle_key() );

		if ( $count >= 5 ) {
			return new WP_Error(
				'healtheat_too_many_orders',
				__( 'Trop de commandes envoyées, merci de réessayer dans une heure ou de nous appeler.', 'healtheat' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Increments the throttle counter.
	 *
	 * @return void
	 */
	protected static function bump_throttle() {
		$key = self::get_throttle_key();

		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	}
}
