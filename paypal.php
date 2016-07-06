<?php
/*
Plugin Name: Gravity Flow PayPal Extension
Plugin URI: http://gravityflow.io
Description: PayPal Extension for Gravity Flow.
Version: 1.0.1.1
Author: Steve Henty
Author URI: http://www.stevenhenty.com
License: GPL-3.0+

------------------------------------------------------------------------
Copyright 2015-2016 Steven Henty

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

define( 'GRAVITY_FLOW_PAYPAL_VERSION', '1.0.1.1' );

define( 'GRAVITY_FLOW_PAYPAL_EDD_ITEM_NAME', 'PayPal' );

add_action( 'gravityflow_loaded', array( 'Gravity_Flow_Paypal_Bootstrap', 'load' ), 1 );

class Gravity_Flow_Paypal_Bootstrap {

	public static function load() {

		require_once( 'class-paypal.php' );

		require_once( 'includes/class-step-paypal.php' );

		Gravity_Flow_Steps::register( new Gravity_Flow_Step_Feed_Paypal() );

		gravity_flow_paypal();
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
		$settings = $gravity_flow_paypal->get_app_settings();

		$license_key = trim( rgar( $settings, 'license_key' ) );

		$edd_updater = new Gravity_Flow_EDD_SL_Plugin_Updater( GRAVITY_FLOW_EDD_STORE_URL, __FILE__, array(
			'version'   => GRAVITY_FLOW_PAYPAL_VERSION,
			'license'   => $license_key,
			'item_name' => GRAVITY_FLOW_PAYPAL_EDD_ITEM_NAME,
			'author'    => 'Steven Henty',
		) );
	}

}
