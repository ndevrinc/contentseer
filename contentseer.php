<?php
/**
 * Plugin Name: ContentSeer
 * Description: A plugin that provides insightful content recommendations.
 * Version: 1.0.0
 * Author: Ndevr, Inc.
 * Author URI: https://www.ndevr.io
 * License: GPL2
 * Text Domain: contentseer
 */

namespace ContentSeer;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version
define( 'CONTENTSEER_VERSION', '1.0.2' );

// Plugin path
define( 'CONTENTSEER_PATH', plugin_dir_path( __FILE__ ) );

// Plugin URL
define( 'CONTENTSEER_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files
require_once CONTENTSEER_PATH . 'includes/class-contentseer.php';
require_once CONTENTSEER_PATH . 'includes/class-admin.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-analysis.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-dashboard.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-edit.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-generate.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-persona.php';
require_once CONTENTSEER_PATH . 'includes/class-admin-settings.php';
require_once CONTENTSEER_PATH . 'includes/class-front-end.php';
require_once CONTENTSEER_PATH . 'includes/class-api.php';
require_once CONTENTSEER_PATH . 'includes/class-persona-generator.php';

// Initialize the plugin
function contentseer_init() {
	$plugin = new ContentSeer();
	$plugin->init();
}
add_action( 'plugins_loaded', '\ContentSeer\contentseer_init' );

// Activation hook
register_activation_hook( __FILE__, '\ContentSeer\contentseer_activate' );
function contentseer_activate() {
	// Generate API credentials if they don't exist
	$api_key    = get_option( 'contentseer_api_key' );
	$api_secret = get_option( 'contentseer_api_secret' );

	if ( ! $api_key || ! $api_secret ) {
		$api_key    = wp_generate_password( 32, false );
		$api_secret = wp_generate_password( 64, true );

		update_option( 'contentseer_api_key', $api_key );
		update_option( 'contentseer_api_secret', $api_secret );
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, '\ContentSeer\contentseer_deactivate' );
function contentseer_deactivate() {
	// Cleanup if needed
}