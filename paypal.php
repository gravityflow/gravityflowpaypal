<?php
/*
Plugin Name: Gravity Flow PayPal Extension
Plugin URI: http://gravityflow.io
Description: PayPal Extension for Gravity Flow.
Version: 1.2.1-dev
Author: Gravity Flow
Author URI: https://gravityflow.io
License: GPL-2.0+

------------------------------------------------------------------------
Copyright 2015-2019 Steven Henty S.L.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'GRAVITY_FLOW_PAYPAL_VERSION', '1.2.1-dev' );
define( 'GRAVITY_FLOW_PAYPAL_EDD_ITEM_ID', 3815 );
define( 'GRAVITY_FLOW_PAYPAL_EDD_ITEM_NAME', 'PayPal' );

add_action( 'gravityflow_loaded', array( 'Gravity_Flow_Paypal_Bootstrap', 'load' ), 1 );

class Gravity_Flow_Paypal_Bootstrap {

	public static function load() {

		require_once( 'class-paypal.php' );

		require_once( 'includes/class-step-paypal.php' );

		Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Paypal() );

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'Gravity_Flow_PayPal' );

		if ( defined( 'GRAVITY_FLOW_PAYPAL_LICENSE_KEY' ) ) {
			gravity_flow_paypal()->license_key = GRAVITY_FLOW_PAYPAL_LICENSE_KEY;
		}
	}
}

function gravity_flow_paypal() {
	if ( class_exists( 'Gravity_Flow_PayPal' ) ) {
		return Gravity_Flow_PayPal::get_instance();
	}
}


add_action( 'admin_init', 'gravityflow_paypal_edd_plugin_updater', 0 );

function gravityflow_paypal_edd_plugin_updater() {

	if ( ! function_exists( 'gravity_flow_paypal' ) ) {
		return;
	}

	$gravity_flow_paypal = gravity_flow_paypal();
	if ( $gravity_flow_paypal ) {

		if ( defined( 'GRAVITY_FLOW_PAYPAL_LICENSE_KEY' ) ) {
			$license_key = GRAVITY_FLOW_PAYPAL_LICENSE_KEY;
		} else {
			$settings = $gravity_flow_paypal->get_app_settings();
			$license_key = trim( rgar( $settings, 'license_key' ) );
		}

		$edd_updater = new Gravity_Flow_EDD_SL_Plugin_Updater( GRAVITY_FLOW_EDD_STORE_URL, __FILE__, array(
			'version'   => GRAVITY_FLOW_PAYPAL_VERSION,
			'license'   => $license_key,
			'item_id' => GRAVITY_FLOW_PAYPAL_EDD_ITEM_ID,
			'author'    => 'Gravity Flow',
		) );
	}

}
