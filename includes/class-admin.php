<?php
/**
 * ContentSeer Admin Updates Class
 *
 * Handles admin-specific updates and functionality for the ContentSeer plugin.
 */

namespace ContentSeer;

class Admin {

	/**
	 * Initialize admin functionality
	 */
	public function init() {
		// Initialize admin hooks.
		$this->init_hooks();
	}

	/**
	 * Initialize admin-specific hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register admin menu items
	 */
	public function register_admin_menu() {
		// Check if any features are enabled
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );
		$create_enabled  = get_option( 'contentseer_enable_create_feature', true );

		// If no features are enabled, don't show the menu
		if ( ! $analyze_enabled && ! $create_enabled ) {
			return;
		}

		// Add main menu item
		add_menu_page(
			'ContentSeer',           // Page title
			'ContentSeer',           // Menu title
			'edit_posts',            // Capability
			'contentseer',           // Menu slug
			array( $this, 'render_default_page' ), // Callback function
			'dashicons-chart-bar',   // Icon
			30                       // Position
		);

		// Add analyze submenu if enabled
		if ( $analyze_enabled ) {
			add_submenu_page(
				'contentseer',          // Parent slug
				'Analyze',              // Page title
				'Analyze',              // Menu title
				'edit_posts',           // Capability
				'contentseer',          // Menu slug (same as parent to make it the default page)
				array( $this, 'render_analysis_page' ) // Callback function
			);
		}

		// Add create submenu if enabled
		if ( $create_enabled ) {
			add_submenu_page(
				'contentseer',          // Parent slug
				'Create Content',       // Page title
				'Create',               // Menu title
				'edit_posts',           // Capability
				'contentseer-create',   // Menu slug
				array( $this, 'render_create_page' ) // Callback function
			);
		}

		// Always show settings for admins
		add_submenu_page(
			'contentseer',          // Parent slug
			'Settings',             // Page title
			'Settings',             // Menu title
			'manage_options',       // Capability
			'contentseer-settings', // Menu slug
			array( $this, 'render_settings_page' ) // Callback function
		);
	}

	/**
	 * Render the default page (redirects to first available feature)
	 */
	public function render_default_page() {
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );
		$create_enabled  = get_option( 'contentseer_enable_create_feature', true );

		if ( $analyze_enabled ) {
			$this->render_analysis_page();
		} elseif ( $create_enabled ) {
			$this->render_create_page();
		} else {
			// Show a message if no features are enabled
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'ContentSeer', 'contentseer' ); ?></h1>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'No features enabled', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'Please enable at least one ContentSeer feature in the settings to get started.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e( 'Go to Settings', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render the analysis page
	 */
	public function render_analysis_page() {
		$analysis = new Admin_Analysis();
		$analysis->render_analysis_page();
	}

	/**
	 * Render the create content page
	 */
	public function render_create_page() {
		$generate = new Admin_Generate();
		$generate->render_create_page();
	}

	/**
	 * Render the settings page
	 */
	public function render_settings_page() {
		$settings = new Admin_Settings();
		$settings->render_settings_page();
	}
}