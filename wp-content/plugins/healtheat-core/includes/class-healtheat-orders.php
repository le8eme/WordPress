<?php
/**
 * Click &amp; collect orders: creation, admin screen and notifications.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the order lifecycle.
 */
class Healtheat_Orders {

	/**
	 * Hooks the admin behaviours.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'preserve_status' ), 10, 2 );
		add_filter( 'manage_healtheat_order_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_healtheat_order_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_action( 'transition_post_status', array( __CLASS__, 'on_status_change' ), 10, 3 );
		add_action( 'admin_head', array( __CLASS__, 'admin_styles' ) );
	}

	/**
	 * Creates an order from a validated payload.
	 *
	 * Prices are always recomputed from the database: the browser only sends
	 * dish IDs and quantities, never amounts.
	 *
	 * @param array<int,array<string,int>> $items    Items as id/qty pairs.
	 * @param array<string,string>         $customer Customer details and pickup slot.
	 * @return int|WP_Error Order ID on success.
	 */
	public static function create( $items, $customer ) {
		$lines = array();
		$total = 0;

		foreach ( $items as $item ) {
			$dish_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			$qty     = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;

			if ( ! $dish_id || $qty < 1 ) {
				continue;
			}

			$qty  = min( 20, $qty );
			$dish = get_post( $dish_id );

			if ( ! $dish || 'healtheat_dish' !== $dish->post_type ) {
				return new WP_Error(
					'healtheat_unknown_dish',
					__( 'Un des plats de votre panier n\'existe plus.', 'healtheat' ),
					array( 'status' => 400 )
				);
			}

			if ( ! healtheat_dish_is_available( $dish_id ) ) {
				return new WP_Error(
					'healtheat_dish_unavailable',
					sprintf(
						/* translators: %s: dish name. */
						__( '« %s » n\'est plus disponible aujourd\'hui.', 'healtheat' ),
						get_the_title( $dish )
					),
					array( 'status' => 409 )
				);
			}

			$price = healtheat_get_dish_price( $dish_id );

			$lines[] = array(
				'id'       => $dish_id,
				'name'     => get_the_title( $dish ),
				'qty'      => $qty,
				'price'    => $price,
				'subtotal' => $price * $qty,
			);

			$total += $price * $qty;
		}

		if ( empty( $lines ) ) {
			return new WP_Error(
				'healtheat_empty_cart',
				__( 'Votre panier est vide.', 'healtheat' ),
				array( 'status' => 400 )
			);
		}

		$minimum = (int) healtheat_get_setting( 'min_order', 0 );

		if ( $minimum && $total < $minimum ) {
			return new WP_Error(
				'healtheat_min_order',
				sprintf(
					/* translators: %s: formatted minimum amount. */
					__( 'La commande minimum est de %s.', 'healtheat' ),
					healtheat_format_price( $minimum )
				),
				array( 'status' => 400 )
			);
		}

		$order_id = wp_insert_post(
			array(
				'post_type'   => 'healtheat_order',
				'post_status' => 'he-pending',
				'post_title'  => __( 'Commande', 'healtheat' ),
			),
			true
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$reference = self::build_reference( $order_id );

		wp_update_post(
			array(
				'ID'         => $order_id,
				'post_title' => $reference,
			)
		);

		update_post_meta( $order_id, '_healtheat_reference', $reference );
		update_post_meta( $order_id, '_healtheat_items', $lines );
		update_post_meta( $order_id, '_healtheat_total', $total );
		update_post_meta( $order_id, '_healtheat_customer_name', $customer['name'] );
		update_post_meta( $order_id, '_healtheat_customer_email', $customer['email'] );
		update_post_meta( $order_id, '_healtheat_customer_phone', $customer['phone'] );
		update_post_meta( $order_id, '_healtheat_pickup', $customer['pickup'] );
		update_post_meta( $order_id, '_healtheat_notes', $customer['notes'] );

		self::notify_restaurant( $order_id );
		self::notify_customer( $order_id, 'created' );

		/**
		 * Fires once a click &amp; collect order has been stored.
		 *
		 * @param int $order_id Order ID.
		 */
		do_action( 'healtheat_order_created', $order_id );

		return $order_id;
	}

	/**
	 * Builds a human readable order reference.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	protected static function build_reference( $order_id ) {
		return sprintf( 'HE-%s-%04d', wp_date( 'ymd' ), $order_id );
	}

	/**
	 * Returns the full data of an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string,mixed>
	 */
	public static function get_order( $order_id ) {
		$items = get_post_meta( $order_id, '_healtheat_items', true );

		return array(
			'id'        => (int) $order_id,
			'reference' => (string) get_post_meta( $order_id, '_healtheat_reference', true ),
			'items'     => is_array( $items ) ? $items : array(),
			'total'     => (int) get_post_meta( $order_id, '_healtheat_total', true ),
			'name'      => (string) get_post_meta( $order_id, '_healtheat_customer_name', true ),
			'email'     => (string) get_post_meta( $order_id, '_healtheat_customer_email', true ),
			'phone'     => (string) get_post_meta( $order_id, '_healtheat_customer_phone', true ),
			'pickup'    => (string) get_post_meta( $order_id, '_healtheat_pickup', true ),
			'notes'     => (string) get_post_meta( $order_id, '_healtheat_notes', true ),
			'status'    => get_post_status( $order_id ),
		);
	}

	/**
	 * Formats a pickup slot for humans.
	 *
	 * @param string $pickup Slot as "Y-m-d H:i".
	 * @return string
	 */
	public static function format_pickup( $pickup ) {
		$timestamp = strtotime( $pickup );

		if ( ! $timestamp ) {
			return $pickup;
		}

		return wp_date( 'l j F à H:i', $timestamp );
	}

	/**
	 * Sends the new order notification to the restaurant.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	protected static function notify_restaurant( $order_id ) {
		$order = self::get_order( $order_id );
		$to    = healtheat_get_setting( 'email', get_option( 'admin_email' ) );

		if ( ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: order reference. */
			__( 'Nouvelle commande %s', 'healtheat' ),
			$order['reference']
		);

		$lines = array(
			sprintf( __( 'Client : %s', 'healtheat' ), $order['name'] ),
			sprintf( __( 'Téléphone : %s', 'healtheat' ), $order['phone'] ),
			sprintf( __( 'E-mail : %s', 'healtheat' ), $order['email'] ),
			sprintf( __( 'Retrait : %s', 'healtheat' ), self::format_pickup( $order['pickup'] ) ),
			'',
		);

		foreach ( $order['items'] as $item ) {
			$lines[] = sprintf( '%d × %s — %s', $item['qty'], $item['name'], healtheat_format_price( $item['subtotal'] ) );
		}

		$lines[] = '';
		$lines[] = sprintf( __( 'Total : %s', 'healtheat' ), healtheat_format_price( $order['total'] ) );

		if ( $order['notes'] ) {
			$lines[] = '';
			$lines[] = sprintf( __( 'Note : %s', 'healtheat' ), $order['notes'] );
		}

		$lines[] = '';
		$lines[] = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Sends a status e-mail to the customer.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $context  created|he-confirmed|he-ready|he-cancelled.
	 * @return void
	 */
	protected static function notify_customer( $order_id, $context ) {
		$order = self::get_order( $order_id );

		if ( ! is_email( $order['email'] ) ) {
			return;
		}

		$restaurant = healtheat_get_setting( 'restaurant_name', "Health'eat" );
		$pickup     = self::format_pickup( $order['pickup'] );

		$messages = array(
			'created'      => array(
				sprintf( __( 'Commande %s bien reçue', 'healtheat' ), $order['reference'] ),
				sprintf( __( 'Merci %1$s ! Nous avons bien reçu votre commande. Elle vous attendra le %2$s chez %3$s.', 'healtheat' ), $order['name'], $pickup, $restaurant ),
			),
			'he-confirmed' => array(
				sprintf( __( 'Commande %s confirmée', 'healtheat' ), $order['reference'] ),
				sprintf( __( 'Bonne nouvelle %1$s, votre commande est confirmée pour le %2$s.', 'healtheat' ), $order['name'], $pickup ),
			),
			'he-ready'     => array(
				sprintf( __( 'Commande %s prête', 'healtheat' ), $order['reference'] ),
				sprintf( __( '%1$s, votre commande vous attend au comptoir. À tout de suite !', 'healtheat' ), $order['name'] ),
			),
			'he-cancelled' => array(
				sprintf( __( 'Commande %s annulée', 'healtheat' ), $order['reference'] ),
				sprintf( __( '%1$s, votre commande du %2$s a été annulée. Contactez-nous pour toute question.', 'healtheat' ), $order['name'], $pickup ),
			),
		);

		if ( ! isset( $messages[ $context ] ) ) {
			return;
		}

		list( $subject, $intro ) = $messages[ $context ];

		$lines = array( $intro, '' );

		foreach ( $order['items'] as $item ) {
			$lines[] = sprintf( '%d × %s — %s', $item['qty'], $item['name'], healtheat_format_price( $item['subtotal'] ) );
		}

		$lines[] = '';
		$lines[] = sprintf( __( 'Total : %s', 'healtheat' ), healtheat_format_price( $order['total'] ) );

		$address = healtheat_get_setting( 'address', '' );

		if ( $address ) {
			$lines[] = '';
			$lines[] = $address;
		}

		wp_mail( $order['email'], $subject, implode( "\n", $lines ) );
	}

	/**
	 * Notifies the customer when the kitchen moves the order forward.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post       Order post.
	 * @return void
	 */
	public static function on_status_change( $new_status, $old_status, $post ) {
		if ( 'healtheat_order' !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( in_array( $new_status, array( 'he-confirmed', 'he-ready', 'he-cancelled' ), true ) ) {
			self::notify_customer( $post->ID, $new_status );
		}
	}

	/**
	 * Registers the order meta boxes.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'healtheat-order-details',
			__( 'Détail de la commande', 'healtheat' ),
			array( __CLASS__, 'render_details' ),
			'healtheat_order',
			'normal',
			'high'
		);

		add_meta_box(
			'healtheat-order-status',
			__( 'Suivi', 'healtheat' ),
			array( __CLASS__, 'render_status' ),
			'healtheat_order',
			'side',
			'high'
		);
	}

	/**
	 * Renders the read-only order summary.
	 *
	 * @param WP_Post $post Order post.
	 * @return void
	 */
	public static function render_details( $post ) {
		$order = self::get_order( $post->ID );
		?>
		<table class="widefat striped">
			<tbody>
				<tr><th style="width:180px"><?php esc_html_e( 'Référence', 'healtheat' ); ?></th><td><?php echo esc_html( $order['reference'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Client', 'healtheat' ); ?></th><td><?php echo esc_html( $order['name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Téléphone', 'healtheat' ); ?></th><td><a href="tel:<?php echo esc_attr( $order['phone'] ); ?>"><?php echo esc_html( $order['phone'] ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'E-mail', 'healtheat' ); ?></th><td><a href="mailto:<?php echo esc_attr( $order['email'] ); ?>"><?php echo esc_html( $order['email'] ); ?></a></td></tr>
				<tr><th><?php esc_html_e( 'Retrait', 'healtheat' ); ?></th><td><strong><?php echo esc_html( self::format_pickup( $order['pickup'] ) ); ?></strong></td></tr>
				<?php if ( $order['notes'] ) : ?>
					<tr><th><?php esc_html_e( 'Note du client', 'healtheat' ); ?></th><td><?php echo esc_html( $order['notes'] ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<table class="widefat striped" style="margin-top:16px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plat', 'healtheat' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Qté', 'healtheat' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Prix', 'healtheat' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Sous-total', 'healtheat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $order['items'] as $item ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['name'] ); ?></a>
						</td>
						<td><?php echo esc_html( $item['qty'] ); ?></td>
						<td><?php echo esc_html( healtheat_format_price( $item['price'] ) ); ?></td>
						<td><?php echo esc_html( healtheat_format_price( $item['subtotal'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="3" style="text-align:right"><?php esc_html_e( 'Total', 'healtheat' ); ?></th>
					<th><?php echo esc_html( healtheat_format_price( $order['total'] ) ); ?></th>
				</tr>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Renders the status selector.
	 *
	 * @param WP_Post $post Order post.
	 * @return void
	 */
	public static function render_status( $post ) {
		wp_nonce_field( 'healtheat_save_order', 'healtheat_order_nonce' );
		?>
		<p>
			<label for="healtheat_order_status"><strong><?php esc_html_e( 'Statut de la commande', 'healtheat' ); ?></strong></label>
		</p>
		<select name="healtheat_order_status" id="healtheat_order_status" style="width:100%">
			<?php foreach ( Healtheat_Post_Types::get_order_statuses() as $status => $label ) : ?>
				<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $post->post_status, $status ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Le client est prévenu par e-mail lors du passage en « Confirmée », « Prête » ou « Annulée ».', 'healtheat' ); ?></p>
		<?php
	}

	/**
	 * Keeps the custom order status when the post is updated from the admin.
	 *
	 * @param array<string,mixed> $data    Slashed post data.
	 * @param array<string,mixed> $postarr Raw post array.
	 * @return array<string,mixed>
	 */
	public static function preserve_status( $data, $postarr ) {
		if ( 'healtheat_order' !== ( $data['post_type'] ?? '' ) ) {
			return $data;
		}

		$statuses = array_keys( Healtheat_Post_Types::get_order_statuses() );

		if (
			isset( $_POST['healtheat_order_status'], $_POST['healtheat_order_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['healtheat_order_nonce'] ) ), 'healtheat_save_order' )
			&& current_user_can( 'edit_post', (int) ( $postarr['ID'] ?? 0 ) )
		) {
			$requested = sanitize_key( wp_unslash( $_POST['healtheat_order_status'] ) );

			if ( in_array( $requested, $statuses, true ) ) {
				$data['post_status'] = $requested;

				return $data;
			}
		}

		if ( ! empty( $postarr['ID'] ) && ! in_array( $data['post_status'], array_merge( $statuses, array( 'trash' ) ), true ) ) {
			$current = get_post_status( (int) $postarr['ID'] );

			if ( in_array( $current, $statuses, true ) ) {
				$data['post_status'] = $current;
			}
		}

		return $data;
	}

	/**
	 * Defines the order list columns.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function columns( $columns ) {
		return array(
			'cb'        => $columns['cb'] ?? '',
			'title'     => __( 'Référence', 'healtheat' ),
			'he_status' => __( 'Statut', 'healtheat' ),
			'he_client' => __( 'Client', 'healtheat' ),
			'he_pickup' => __( 'Retrait', 'healtheat' ),
			'he_items'  => __( 'Plats', 'healtheat' ),
			'he_total'  => __( 'Total', 'healtheat' ),
			/*
			 * Colonne maison plutôt que celle du cœur : sur un statut autre
			 * que « publié », WordPress affiche « Last Modified », ce qui n'a
			 * pas de sens pour une commande.
			 */
			'he_date'   => __( 'Reçue le', 'healtheat' ),
		);
	}

	/**
	 * Renders the order list columns.
	 *
	 * @param string $column   Column key.
	 * @param int    $order_id Order ID.
	 * @return void
	 */
	public static function column_content( $column, $order_id ) {
		$order    = self::get_order( $order_id );
		$statuses = Healtheat_Post_Types::get_order_statuses();

		switch ( $column ) {
			case 'he_status':
				printf(
					'<span class="healtheat-badge healtheat-badge--%1$s">%2$s</span>',
					esc_attr( str_replace( 'he-', '', $order['status'] ) ),
					esc_html( $statuses[ $order['status'] ] ?? $order['status'] )
				);
				break;
			case 'he_client':
				echo esc_html( $order['name'] );
				echo '<br /><small>' . esc_html( $order['phone'] ) . '</small>';
				break;
			case 'he_pickup':
				echo esc_html( self::format_pickup( $order['pickup'] ) );
				break;
			case 'he_items':
				$labels = array();

				foreach ( $order['items'] as $item ) {
					$labels[] = $item['qty'] . ' × ' . $item['name'];
				}

				echo esc_html( implode( ', ', $labels ) );
				break;
			case 'he_total':
				echo '<strong>' . esc_html( healtheat_format_price( $order['total'] ) ) . '</strong>';
				break;
			case 'he_date':
				echo esc_html( get_the_date( '', $order_id ) );
				echo '<br /><small>' . esc_html( get_the_time( '', $order_id ) ) . '</small>';
				break;
		}
	}

	/**
	 * Small styles for the order badges.
	 *
	 * @return void
	 */
	public static function admin_styles() {
		$screen = get_current_screen();

		if ( ! $screen || 'healtheat_order' !== $screen->post_type ) {
			return;
		}
		?>
		<style>
			.healtheat-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; background:#e6f4ea; color:#1e6b3a; }
			.healtheat-badge--pending { background:#fff4e5; color:#8a5300; }
			.healtheat-badge--ready { background:#e5f1ff; color:#0b4f9e; }
			.healtheat-badge--collected { background:#eee; color:#555; }
			.healtheat-badge--cancelled { background:#fde8e8; color:#9b1c1c; }
			#minor-publishing-actions, #misc-publishing-actions { display:none; }
		</style>
		<?php
	}
}
