<?php
/**
 * Main plugin class
 */
namespace ContentSeer;

class ContentSeer {
	/**
	 * The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Main ContentSeer Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Initialize API
		$api = new API();
		$api->init();

		// Initialize admin
		if ( is_admin() ) {
			$admin = new Admin();
			$admin->init();
		}
	}
}
