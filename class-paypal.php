<?php


// Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {

	class Gravity_Flow_PayPal extends Gravity_Flow_Extension {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_PAYPAL_VERSION;

		public $edd_item_name = GRAVITY_FLOW_PAYPAL_EDD_ITEM_NAME;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravityflowpaypal';

		protected $_path = 'gravityflowpaypal/paypal.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'PayPal Extension';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'PayPal';

		protected $_capabilities = array(
			'gravityflowpaypal_uninstall',
			'gravityflowpaypal_settings',
		);

		protected $_capabilities_app_settings = 'gravityflowpaypal_settings';
		protected $_capabilities_uninstall = 'gravityflowpaypal_uninstall';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow_PayPal();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */


		public function pre_init() {
			parent::pre_init();
			add_action( 'gform_paypal_fulfillment', array( $this, 'action_gform_paypal_fulfillment' ), 10, 4 );
			add_action( 'wp', array( $this, 'maybe_redirect' ), 9 );
			add_action( 'admin_init', array( $this, 'maybe_redirect' ), 9 );
		}

		public function init() {
			parent::init();
			add_filter( 'gravityflow_permission_denied_message_entry_detail', array(
				$this,
				'filter_gravityflow_permission_denied_message_entry_detail'
			), 10, 2 );
		}

		/**
		 * Add the extension capabilities to the Gravity Flow group in Members.
		 *
		 * @since 1.0.4
		 *
		 * @param array $caps The capabilities and their human readable labels.
		 *
		 * @return array
		 */
		public function get_members_capabilities( $caps ) {
			$prefix = $this->get_short_title() . ': ';

			$caps['gravityflowpaypal_settings']  = $prefix . __( 'Manage Settings', 'gravityflowpaypal' );
			$caps['gravityflowpaypal_uninstall'] = $prefix . __( 'Uninstall', 'gravityflowpaypal' );

			return $caps;
		}

		public function action_gform_paypal_fulfillment( $entry, $feed, $transaction_id, $amount ) {
			$this->log_debug( __METHOD__ . '() - starting' );
			$api          = new Gravity_Flow_API( $entry['form_id'] );
			$current_step = $api->get_current_step( $entry );
			if ( $current_step && $current_step->get_type() == 'paypal' ) {

				$custom = $_REQUEST['custom'];
				list( $entry_id, $entry_id_hash, $assignee_key_b64, $assignee_key_hash ) = explode( '|', $custom );
				$assignee_key            = base64_decode( $assignee_key_b64 );
				$assignee_key_hash_check = substr( wp_hash( 'gflow' . $assignee_key ), 0, 4 );

				if ( empty( $assignee_key ) || empty( $assignee_key_hash ) || $assignee_key_hash !== $assignee_key_hash_check ) {
					$this->log_debug( __METHOD__ . '() - invalid assignee' );

					return;
				}

				$assignee = new Gravity_Flow_Assignee( $assignee_key, $current_step );

				$assignee->update_status( 'complete' );

				$current_step->update_step_status( 'complete' );

				$user_id = ( $assignee->get_type() == 'user_id' ) ? $assignee->get_id() : 0;

				$note = sprintf( esc_html__( 'Payment has been completed. Transaction ID: %s', 'gravityflowpaypal' ), $transaction_id );

				$current_step->add_note( $note, $user_id, $assignee->get_display_name() );

				$api->process_workflow( $entry['id'] );
			}
		}

		function maybe_redirect() {

			if ( ! isset( $_REQUEST['workflow_paypal_assignee_key'] ) ) {
				return;
			}

			// todo: log note
			// todo: log event

			$entry_id = absint( rgget( 'lid' ) );
			$entry    = GFAPI::get_entry( $entry_id );
			$form     = GFAPI::get_form( $entry['form_id'] );
			$form_id  = absint( $form['id'] );

			/* @var GFPayPal $add_on */
			$add_on = gf_paypal();

			$feed            = $add_on->get_paypal_feed( $form_id, $entry );
			$submission_data = $add_on->get_submission_data( $feed, $form, $entry );

			add_filter( 'gform_paypal_return_url', array( $this, 'filter_gform_paypal_return_url' ), 10, 4 );

			$url = $add_on->redirect_url( $feed, $submission_data, $form, $entry );

			$assignee_key = sanitize_text_field( $_REQUEST['workflow_paypal_assignee_key'] );

			// Add Gravity Flow custom vars to the IPN custom query var

			parse_str( $url, $vars ); // Extract the 'custom' var
			$custom = $vars['custom'];
			$custom .= '|' . base64_encode( $assignee_key );
			$custom .= '|' . substr( wp_hash( 'gflow' . $assignee_key ), 0, 4 );
			$url    = add_query_arg( array( 'custom' => $custom ), $url );
			header( "Location: {$url}" );
		}

		public function filter_gravityflow_permission_denied_message_entry_detail( $message, $current_step ) {

			if ( ! $this->is_gravityforms_supported() ) {
				return $message;
			}

			if ( $str = rgget( 'gf_paypal_return' ) ) {
				$str = base64_decode( $str );

				parse_str( $str, $query );
				if ( wp_hash( 'ids=' . $query['ids'] ) == $query['hash'] ) {
					list( $form_id, $entry_id ) = explode( '|', $query['ids'] );
					$entry              = GFAPI::get_entry( $entry_id );
					$step_id            = rgget( 'workflow_step_id' );
					$step_id_hash       = rgget( 'workflow_step_id_hash' );
					$step_id_hash_check = wp_hash( 'gflow' . $step_id );
					if ( $step_id_hash === $step_id_hash_check ) {
						$api  = new Gravity_Flow_Api( $form_id );
						$step = $api->get_step( $step_id, $entry );
						if ( $step ) {
							$message = $step->confirmation_message;
						}
					}
				}
			}

			return $message;
		}

		public function filter_gform_paypal_return_url( $url, $form_id, $lead_id, $query ) {
			$step_id = absint( $_REQUEST['workflow_paypal_step_id'] );

			if ( empty( $step_id ) ) {
				return $url;
			}

			$url = add_query_arg( array( 'workflow_step_id' => $step_id ), $url );
			$url = add_query_arg( array( 'workflow_step_id_hash' => wp_hash( 'gflow' . $step_id ) ), $url );

			return $url;
		}
	}
}
