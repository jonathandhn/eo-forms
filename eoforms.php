<?php
/**
 * Plugin Name: EO Forms
 * Plugin URI: https://www.jonathan.dhn.one
 * Description: Manage your EmailOctopus subscribers with Elementor forms.
 * Author: Jonathan DAHAN
 * Author URI: https://www.jonathan.dhn.one
 * Version: 1.0.0
 * Text Domain: eo-forms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'EO_FORMS_PATH', plugin_dir_path( __FILE__ ) );

require_once EO_FORMS_PATH . 'ConfirmationHandler.php';

add_action( 'admin_post_nopriv_eof_confirm_subscription', [ 'EOF_Confirmation_Handler', 'confirm_subscription' ] );
add_action( 'admin_post_eof_confirm_subscription', [ 'EOF_Confirmation_Handler', 'confirm_subscription' ] );

add_action( 'elementor_pro/init', function() {
	require_once EO_FORMS_PATH . 'ActionSubscriber.php';

	$eof_action_subscribe = new \EOF_Subscribe_Action_After_Submit();

	\ElementorPro\Plugin::instance()
		->modules_manager
		->get_modules( 'forms' )
		->add_form_action( $eof_action_subscribe->get_name(), $eof_action_subscribe );
} );
