<?php
/**
 * ContentSeer Admin Edit Class
 *
 * Makes updates to the post listing page in the admin.
 */

namespace ContentSeer;

class Admin_Edit {
	/**
	 * Constructor to initialize the admin updates.
	 */
	public function __construct() {
		// Initialize admin hooks.
		$this->init_hooks();
	}

	/**
	 * Initialize admin-specific hooks.
	 */
	private function init_hooks() {
	}
}

new Admin_Edit();
