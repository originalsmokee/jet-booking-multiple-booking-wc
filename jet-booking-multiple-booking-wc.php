<?php 
/**
 * Plugin Name: Jet Booking Multiple Booking.
 * Plugin URI: #
 * Description: Advanced Jet Booking Functionality
 * Version:     1.0.0
 * Author:      picoredo
 * Author URI:  https://crocoblock.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Allow to book multiple units/appartments/tickets at same date/dates with single purchase
 * You need to add '_capacity' field into the form. This field should be responsible for number of booked items
 */

class Jet_Booking_Multiple_Units_Booked {
	
	public function __construct() {
		
		add_filter( 'jet-booking/form-action/pre-process', array( $this, 'book_multiple_units' ), 0, 3 );
		add_action( 'jet-booking/wc-integration/process-order', array( $this, 'connect_bookings' ), 0, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'update_booking_status' ), 20, 3 );
		
	}
	
	public function book_multiple_units( $result, $booking, $action ) {

		$capacity = absint( $action->getRequest( '_capacity' ) );

		if ( ! $capacity && 1 === $capacity ) {
			return $result;
		}

		$booking_ids = [];

		$all_units = \JET_ABAF\Plugin::instance()->db->get_apartment_units( $booking['apartment_id'] );

		if ( empty( $all_units ) ) {
			return $result;
		}

		$booked_units       = \JET_ABAF\Plugin::instance()->db->get_booked_units( $booking );
		$all_units_count    = count( $all_units );
		$booked_units_count = count( $booked_units );

		if ( ( $available_units = $all_units_count - $booked_units_count ) < $capacity ) {
			throw new JET_ABAF\Vendor\Actions_Core\Base_Handler_Exception( 
				'Not enough available units. Only ' . $available_units . ' are available',
				'error'
			);
		}

		for ( $i = 1; $i <= $capacity; $i++ ) { 

			$booking_id = \JET_ABAF\Plugin::instance()->db->insert_booking( $booking );

			if ( $booking_id ) {
				$booking_ids[] = $booking_id;
			} else {
				throw new \JET_ABAF\Vendor\Actions_Core\Base_Handler_Exception( 
					esc_html__( 'Booking dates already taken', 'jet-booking' ),
					'error'
				);
			}

		}
		$booking['booking_id'] = $booking_id;
		$booking['booking_ids'] = $booking_ids;

		$action->setRequest( 'booking_id', implode( ',', $booking_ids ) );
		$action->setRequest( 'booking_ids', $booking_ids );

		WC()->session->set( 'jet_abaf_custom_booking_ids', $booking_ids );

		return $booking;

	}
	
	public function connect_bookings( $order_id, $order ) {
	
		if ( ! isset( WC()->session ) ) {
			return;
		} 

		$booking_ids = WC()->session->get( 'jet_abaf_custom_booking_ids', array() );

		if ( empty( $booking_ids ) ) {
			return;
		}

		foreach ( $booking_ids as $booking_id ) {
			jet_abaf()->db->update_booking(
				$booking_id,
				array(
					'order_id' => $order_id,
					'status'   => $order->get_status(),
				)
			);
		}
	}
	
	public function update_booking_status( $order_id, $old_status, $new_status ) {
	
		$bookings = jet_abaf()->db->query( array( 'order_id' => $order_id ) );

		foreach ( $bookings as $booking ) {
			jet_abaf()->db->update_booking(
				$booking['booking_id'],
				array(
					'order_id' => $order_id,
					'status'   => $new_status,
				)
			);
		}
		
	}	
	
}
new Jet_Booking_Multiple_Units_Booked();
