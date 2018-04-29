<?php
/**
 * Gravity Flow PayPal
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Steps
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-3.0.php GNU Public License
 * @since       1.0
 */
if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_Feed_Paypal extends Gravity_Flow_Step_Feed_Add_On{
		public $_step_type = 'paypal';

		protected $_class_name = 'GFPayPal';

		public function get_label() {
			return esc_html__( 'PayPal', 'gravityflowpaypal' );
		}

		public function get_icon_url() {
			return gravity_flow_paypal()->get_base_url() . '/images/paypal.svg';
		}

		function intercept_submission() {
			$add_on = call_user_func( array( $this->get_feed_add_on_class_name(), 'get_instance' ) );
			remove_filter( 'gform_entry_post_save', array( $add_on, 'entry_post_save' ), 10 );
		}

		function get_settings() {

			$settings = parent::get_settings();

			if ( ! $this->is_supported() ) {
				return $settings;
			}

			$settings_api = $this->get_common_settings_api();

			$paypal_settings = array(
				$settings_api->get_setting_assignee_type(),
				$settings_api->get_setting_assignees(),
				$settings_api->get_setting_assignee_routing(),
				$settings_api->get_setting_instructions(),
				$settings_api->get_setting_display_fields(),
				$settings_api->get_setting_notification_tabs( array(
					array(
						'label'  => __( 'Assignee Email', 'gravityflowpaypal' ),
						'id'     => 'tab_assignee_notification',
						'fields' => $settings_api->get_setting_notification( array(
							'checkbox_default_value' => true,
						) ),
					)
				) ),
				array(
					'name'          => 'confirmation_message',
					'label'         => esc_html__( 'Confirmation Message', 'gravityflowpaypal' ),
					'type'          => 'visual_editor',
					'default_value' => esc_html__( 'Thank you. Your payment is currently being processed', 'gravityflowpaypal' ),
				),
			);

			$settings['fields'] = array_merge( $settings['fields'], $paypal_settings );

			return $settings;
		}

		function process() {
			$complete = $this->is_complete();

			$assignees = $this->get_assignees();

			if ( empty( $assignees ) ) {
				$note = sprintf( __( '%s: not required', 'gravityflowpaypal' ), $this->get_name() );
				$this->add_note( $note, 0 , 'gravityflowpaypal' );
			} else {
				foreach ( $assignees as $assignee ) {
					$assignee->update_status( 'pending' );
					// send notification
					$this->maybe_send_assignee_notification( $assignee );
					$complete = false;
				}
			}
			return $complete;
		}

		/**
		 * @todo Remove once min Gravity Flow version reaches 1.4.2.
		 */
		public function evaluate_status() {
			if ( $this->is_queued() ) {
				return 'queued';
			}

			if ( $this->is_expired() ) {
				return $this->get_expiration_status_key();
			}

			$status = $this->get_status();

			if ( empty( $status ) ) {
				return 'pending';
			}

			return $this->status_evaluation();
		}

		/**
		 * Evaluates the status for the step.
		 *
		 * The step is only complete when the assignee status has been updated to complete by the gform_paypal_fulfillment hook.
		 *
		 * @return string 'pending' or 'complete'
		 */
		public function status_evaluation() {
			$assignee_details = $this->get_assignees();

			$step_status = 'complete';

			foreach ( $assignee_details as $assignee ) {
				$user_status = $assignee->get_status();

				if ( empty( $user_status ) || $user_status == 'pending' ) {
					$step_status = 'pending';
				}
			}

			return $step_status;
		}

		/**
		 * @deprecated
		 * @param $form
		 */
		public function workflow_detail_status_box( $form ) {

			_deprecated_function( 'workflow_detail_status_box', '1.3.2', 'workflow_detail_box' );

			$default_args = array(
				'display_empty_fields' => true,
				'check_permissions' => true,
				'show_header' => true,
				'timeline' => true,
				'display_instructions' => true,
				'sidebar' => true,
				'step_status' => true,
				'workflow_info' => true,
			);

			$this->workflow_detail_box( $form, $default_args );
		}

		public function workflow_detail_box( $form, $args ) {
			global $current_user;

			if ( rgget( 'gf_paypal_return' ) ) {
				echo $this->confirmation_message;
				return;
			}

			$form_id = absint( $form['id'] );

			$status_str            = __( 'Pending Payment', 'gravityflowpaypal' );
			$approve_icon      = '<i class="fa fa-check" style="color:green"></i>';
			$input_step_status = $this->get_status();
			if ( $input_step_status == 'complete' ) {
				$status_str = $approve_icon . __( 'Complete', 'gravityflowpaypal' );
			} elseif ( $input_step_status == 'queued' ) {
				$status_str = __( 'Queued', 'gravityflowpaypal' );
			}

			$display_step_status = (bool) $args['step_status'];
			?>

			<div>
				<?php if ( $display_step_status ) : ?>
				<h4 style="margin-bottom:10px;"><?php echo $this->get_name() . ' (' . $status_str . ')'?></h4>
				<ul>
					<?php
					$assignees = $this->get_assignees();

					gravity_flow()->log_debug( __METHOD__ . '(): assignee details: ' . print_r( $assignees, true ) );

					foreach ( $assignees as $assignee ) {

						gravity_flow()->log_debug( __METHOD__ . '(): showing status for: ' . $assignee->get_key() );

						$assignee_status = $assignee->get_status();

						gravity_flow()->log_debug( __METHOD__ . '(): assignee status: ' . $assignee_status );

						if ( ! empty( $assignee_status ) ) {

							$assignee_type = $assignee->get_type();
							$assignee_id = $assignee->get_id();

							if ( $assignee_type == 'user_id' ) {
								$user_info = get_user_by( 'id', $assignee_id );
								$status_label = $this->get_status_label( $assignee_status );
								echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'User', 'gravityflowpaypal' ), $user_info->display_name,  $status_label );
							} elseif ( $assignee_type == 'email' ) {
								$email = $assignee_id;
								$status_label = $this->get_status_label( $assignee_status );
								echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Email', 'gravityflowpaypal' ), $email,  $status_label );

							} elseif ( $assignee_type == 'role' ) {
								$status_label = $this->get_status_label( $assignee_status );
								$role_name = translate_user_role( $assignee_id );
								echo sprintf( '<li>%s: (%s)</li>', esc_html__( 'Role', 'gravityflowpaypal' ), $role_name, $status_label );
								echo '<li>' . $role_name . ': ' . $assignee_status . '</li>';
							}
						}
					}

					?>
					</ul>
					<?php endif; ?>
				<div>
					<?php

					if ( $token = gravity_flow()->decode_access_token() ) {
						$assignee_key = sanitize_text_field( $token['sub'] );
					} else {
						$assignee_key = 'user_id|' . $current_user->ID;

					}
					$assignee = new Gravity_Flow_Assignee( $assignee_key, $this );
					$assignee_status = $assignee->get_status();

					$role_status = false;
					foreach ( gravity_flow()->get_user_roles() as $role ) {
						$role_status = $this->get_role_status( $role );
						if ( $role_status == 'pending' ) {
							break;
						}
					}

					$paypal_assignee_key = '';

					if ( $assignee_status == 'pending' ) {
						$paypal_assignee_key = $assignee_key;
					} elseif ( $role_status == 'pending' ) {
						$paypal_assignee_key = 'role|' . $role;
					}

					?>
				</div>
				<?php

				$can_update = $assignee_status == 'pending' || $role_status == 'pending';

				if ( $can_update ) {
					?>
					<input type="hidden" name="workflow_paypal_assignee_key" value="<?php echo $paypal_assignee_key; ?>" />
					<input type="hidden" name="workflow_paypal_step_id" value="<?php echo $this->get_id(); ?>" />
					<br /><br />
					<div style="text-align:right;">
						<input class="button button-primary" type="submit" name="gravityflow_paypal_pay" value="<?php esc_html_e( 'Pay', 'gravityflowpaypal' ); ?>" />
					</div>
					<?php
				}

				?>
			</div>
			<?php
		}

		public function entry_detail_status_box( $form ) {
			$status = $this->get_status();
			?>
			<h4 style="padding:10px;"><?php echo $this->get_name() . ': ' . $status ?></h4>

			<div style="padding:10px;">
				<ul>
					<?php

					$assignees = $this->get_assignees();

					foreach ( $assignees as $assignee ) {

						$assignee_type = $this->get_type();

						$status = $assignee->get_status();

						if ( ! empty( $user_status ) ) {
							$status_label = $this->get_status_label( $status );
							switch ( $assignee_type ) {
								case 'email':
									echo sprintf( '<li>%s: %s (%s)</li>', esc_html__( 'Email', 'gravityflowpaypal' ), $this->get_id(),  $status_label );
									break;
								case 'user_id' :
									$user_info = get_user_by( 'id', $assignee->get_id() );
									echo '<li>' . esc_html__( 'User', 'gravityflowpaypal' ) . ': ' . $user_info->display_name . '<br />' . esc_html__( 'Status', 'gravityflowpaypal' ) . ': ' . esc_html( $status_label ) . '</li>';
									break;
								case 'role' :

									$role_name = translate_user_role( $assignee->get_id() );
									echo '<li>' . $role_name . ': ' . esc_html( $status_label ) . '</li>';
									break;
							}
						}
					}

					?>
				</ul>
			</div>
			<?php
		}

		public function get_assignees() {

			$assignees = array();

			$assignee_details = array();

			$input_type = $this->type;

			switch ( $input_type ) {
				case 'select':
					foreach ( $this->assignees as $assignee_key ) {
						list( $assignee_type, $assignee_id ) = explode( '|', $assignee_key );
						$assignee_details[] = new Gravity_Flow_Assignee( array(
							'id'              => $assignee_id,
							'type'            => $assignee_type,
						), $this );
					}
					break;
				case 'routing' :
					$routings = $this->routing;
					if ( is_array( $routings ) ) {
						$entry = $this->get_entry();
						foreach ( $routings as $routing ) {
							$assignee_key = rgar( $routing, 'assignee' );
							if ( in_array( $assignee_key, $assignees ) ) {
								continue;
							}
							list( $assignee_type, $assignee_id ) = explode( '|', $assignee_key );
							if ( $entry ) {
								if ( $this->evaluate_routing_rule( $routing ) ) {
									$assignee_details[] = new Gravity_Flow_Assignee( array(
										'id'              => $assignee_id,
										'type'            => $assignee_type,
									), $this );
									$assignees[] = $assignee_key;
								}
							} else {
								$assignee_details[] = new Gravity_Flow_Assignee( array(
									'id'              => $assignee_id,
									'type'            => $assignee_type,
								), $this );
								$assignees[] = $assignee_key;
							}
						}
					}

					break;
			}

			gravity_flow()->log_debug( __METHOD__ . '(): assignees: ' . print_r( $assignees, true ) );

			return $assignee_details;
		}
	}
}



