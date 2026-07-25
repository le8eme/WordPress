<?php
/**
 * Pickup slot computation.
 *
 * All slots are computed in the site timezone and exchanged with the browser
 * as "Y-m-d H:i" local strings, so no timezone conversion happens client side.
 *
 * @package Healtheat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the list of bookable pickup slots.
 */
class Healtheat_Slots {

	/**
	 * Returns the bookable slots grouped by day.
	 *
	 * @return array<int,array<string,mixed>> List of days with their slots.
	 */
	public static function get_available_days() {
		$settings = Healtheat_Settings::get_settings();
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$earliest = $now->modify( '+' . (int) $settings['lead_time'] . ' minutes' );
		$days     = array();

		for ( $offset = 0; $offset < (int) $settings['days_ahead']; $offset++ ) {
			$date  = $now->modify( '+' . $offset . ' days' )->setTime( 0, 0 );
			$slots = self::get_slots_for_date( $date, $earliest );

			if ( empty( $slots ) ) {
				continue;
			}

			$days[] = array(
				'date'  => $date->format( 'Y-m-d' ),
				'label' => wp_date( 'l j F', $date->getTimestamp() ),
				'slots' => $slots,
			);
		}

		return $days;
	}

	/**
	 * Returns the slots of a single day.
	 *
	 * @param DateTimeImmutable $date     Day to inspect (midnight).
	 * @param DateTimeImmutable $earliest Earliest bookable moment.
	 * @return array<int,array<string,mixed>>
	 */
	protected static function get_slots_for_date( DateTimeImmutable $date, DateTimeImmutable $earliest ) {
		$settings = Healtheat_Settings::get_settings();
		$weekday  = (int) $date->format( 'w' );
		$hours    = $settings['hours'][ $weekday ] ?? array();

		if ( empty( $hours['enabled'] ) ) {
			return array();
		}

		list( $open_h, $open_m )   = array_map( 'intval', explode( ':', $hours['open'] ) );
		list( $close_h, $close_m ) = array_map( 'intval', explode( ':', $hours['close'] ) );

		$start = $date->setTime( $open_h, $open_m );
		$end   = $date->setTime( $close_h, $close_m );

		if ( $end <= $start ) {
			return array();
		}

		$interval = max( 5, (int) $settings['slot_interval'] );
		$capacity = max( 1, (int) $settings['slot_capacity'] );
		$booked   = self::get_booked_counts( $date );
		$slots    = array();
		$cursor   = $start;

		while ( $cursor <= $end ) {
			if ( $cursor >= $earliest ) {
				$key   = $cursor->format( 'Y-m-d H:i' );
				$taken = $booked[ $key ] ?? 0;

				$slots[] = array(
					'value' => $key,
					'label' => $cursor->format( 'H:i' ),
					'left'  => max( 0, $capacity - $taken ),
					'full'  => $taken >= $capacity,
				);
			}

			$cursor = $cursor->modify( '+' . $interval . ' minutes' );
		}

		return $slots;
	}

	/**
	 * Counts the active orders already booked on a given day, per slot.
	 *
	 * @param DateTimeImmutable $date Day to inspect.
	 * @return array<string,int>
	 */
	protected static function get_booked_counts( DateTimeImmutable $date ) {
		global $wpdb;

		$day = $date->format( 'Y-m-d' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS pickup, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_healtheat_pickup'
				AND pm.meta_value LIKE %s
				AND p.post_type = 'healtheat_order'
				AND p.post_status IN ( 'he-pending', 'he-confirmed', 'he-ready' )
				GROUP BY pm.meta_value",
				$wpdb->esc_like( $day ) . '%'
			),
			ARRAY_A
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ $row['pickup'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Checks that a submitted slot is still bookable.
	 *
	 * @param string $slot Slot as "Y-m-d H:i".
	 * @return true|WP_Error
	 */
	public static function validate( $slot ) {
		foreach ( self::get_available_days() as $day ) {
			foreach ( $day['slots'] as $candidate ) {
				if ( $candidate['value'] !== $slot ) {
					continue;
				}

				if ( $candidate['full'] ) {
					return new WP_Error(
						'healtheat_slot_full',
						__( 'Ce créneau vient d\'être complet, merci d\'en choisir un autre.', 'healtheat' ),
						array( 'status' => 409 )
					);
				}

				return true;
			}
		}

		return new WP_Error(
			'healtheat_slot_invalid',
			__( 'Ce créneau de retrait n\'est pas disponible.', 'healtheat' ),
			array( 'status' => 400 )
		);
	}
}
