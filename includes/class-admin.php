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
		add_action( 'admin_notices', array( $this, 'show_connection_notice' ) );
	}

	/**
	 * Show connection notice if not connected
	 */
	public function show_connection_notice() {
		// Only show on ContentSeer pages
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'contentseer' ) === false ) {
			return;
		}

		$contentseer_id = get_option( 'contentseer_id', '' );
		$api_key        = get_option( 'contentseer_api_key', '' );
		$api_secret     = get_option( 'contentseer_api_secret', '' );

		$is_connected = ! empty( $contentseer_id ) && ! empty( $api_key ) && ! empty( $api_secret );

		if ( ! $is_connected && 'settings_page_contentseer' !== $screen->id ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'ContentSeer Setup Required', 'contentseer' ); ?></strong><br>
					<?php esc_html_e( 'Your site is not yet connected to ContentSeer. Please request access to get started.', 'contentseer' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e( 'Request Access', 'contentseer' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
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
			array( $this, 'render_dashboard_page' ), // Callback function
			'dashicons-chart-bar',   // Icon
			30                       // Position
		);

		// Add dashboard submenu (always show if any feature is enabled)
		add_submenu_page(
			'contentseer',          // Parent slug
			'Dashboard',            // Page title
			'Dashboard',            // Menu title
			'edit_posts',           // Capability
			'contentseer', // Menu slug
			array( $this, 'render_dashboard_page' ) // Callback function
		);

		// Add analyze submenu if enabled
		if ( $analyze_enabled ) {
			add_submenu_page(
				'contentseer',          // Parent slug
				'Analyze',              // Page title
				'Analyze',              // Menu title
				'edit_posts',           // Capability
				'analyze',          // Menu slug (same as parent to make it the default page)
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
	 * Render the dashboard page
	 */
	public function render_dashboard_page() {
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );
		$create_enabled  = get_option( 'contentseer_enable_create_feature', true );
		$contentseer_id  = get_option( 'contentseer_id', '' );
		$api_key         = get_option( 'contentseer_api_key', '' );
		$api_secret      = get_option( 'contentseer_api_secret', '' );
		$is_connected    = ! empty( $contentseer_id ) && ! empty( $api_key ) && ! empty( $api_secret );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ContentSeer Dashboard', 'contentseer' ); ?></h1>
			
			<?php if ( ! $is_connected ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Setup Required', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'Your site is not yet connected to ContentSeer. Please request access to get started with all features.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e( 'Request Access', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
				
				<!-- Quick Actions Card -->
				<div class="card">
					<h2><?php esc_html_e( 'Quick Actions', 'contentseer' ); ?></h2>
					<div style="display: flex; flex-direction: column; gap: 10px;">
						<?php if ( $create_enabled ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-create' ) ); ?>" class="button button-primary button-large">
								<?php esc_html_e( 'Create New Content', 'contentseer' ); ?>
							</a>
						<?php endif; ?>
						
						<?php if ( $analyze_enabled ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer' ) ); ?>" class="button button-secondary button-large">
								<?php esc_html_e( 'View Analysis Dashboard', 'contentseer' ); ?>
							</a>
						<?php endif; ?>
						
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=contentseer_personas' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Manage Personas', 'contentseer' ); ?>
						</a>
						
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Settings', 'contentseer' ); ?>
						</a>
					</div>
				</div>

				<!-- Connection Status Card -->
				<div class="card">
					<h2><?php esc_html_e( 'Connection Status', 'contentseer' ); ?></h2>
					<table class="widefat">
						<tbody>
							<tr>
								<td><?php esc_html_e( 'ContentSeer Connection', 'contentseer' ); ?></td>
								<td>
									<?php if ( $is_connected ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Connected', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Connected', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<?php if ( $is_connected ) : ?>
								<tr>
									<td><?php esc_html_e( 'Site ID', 'contentseer' ); ?></td>
									<td><code><?php echo esc_html( $contentseer_id ); ?></code></td>
								</tr>
							<?php endif; ?>
							<tr>
								<td><?php esc_html_e( 'Content Analysis', 'contentseer' ); ?></td>
								<td>
									<?php if ( $analyze_enabled ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Enabled', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Disabled', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Content Creation', 'contentseer' ); ?></td>
								<td>
									<?php if ( $create_enabled ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Enabled', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Disabled', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Configuration Status Card -->
				<div class="card">
					<h2><?php esc_html_e( 'Configuration Status', 'contentseer' ); ?></h2>
					<?php
					$perplexity_key     = get_option( 'contentseer_perplexity_api_key', '' );
					$topics_webhook     = get_option( 'contentseer_topics_webhook_url', '' );
					$analysis_webhook   = get_option( 'contentseer_content_analysis_webhook_url', '' );
					$generation_webhook = get_option( 'contentseer_content_generation_webhook_url', '' );
					?>
					<table class="widefat">
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Perplexity API Key', 'contentseer' ); ?></td>
								<td>
									<?php if ( ! empty( $perplexity_key ) ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Configured', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Configured', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Topics Webhook', 'contentseer' ); ?></td>
								<td>
									<?php if ( ! empty( $topics_webhook ) ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Configured', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Configured', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Analysis Webhook', 'contentseer' ); ?></td>
								<td>
									<?php if ( ! empty( $analysis_webhook ) ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Configured', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Configured', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Generation Webhook', 'contentseer' ); ?></td>
								<td>
									<?php if ( ! empty( $generation_webhook ) ) : ?>
										<span style="color: #46b450; font-weight: 600;"><?php esc_html_e( 'Configured', 'contentseer' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: 600;"><?php esc_html_e( 'Not Configured', 'contentseer' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<?php if ( $analyze_enabled ) : ?>
					<!-- Recent Analysis Card -->
					<div class="card">
						<h2><?php esc_html_e( 'Recent Analysis', 'contentseer' ); ?></h2>
						<?php
						global $wpdb;
						$recent_analysis = $wpdb->get_results(
							"SELECT p.ID, p.post_title, p.post_date, pm.meta_value
							 FROM {$wpdb->posts} p
							 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
							 WHERE pm.meta_key = '_contentseer_analysis'
							 AND p.post_status IN ('publish', 'draft')
							 ORDER BY p.post_date DESC
							 LIMIT 5"
						);

						if ( ! empty( $recent_analysis ) ) :
							?>
							<div style="max-height: 200px; overflow-y: auto;">
								<?php
								foreach ( $recent_analysis as $analysis ) :
									$analysis_data = maybe_unserialize( $analysis->meta_value );
									$score         = isset( $analysis_data['overall_score'] ) ? $analysis_data['overall_score'] : 0;
									$score_color   = $score >= 80 ? '#46b450' : ( $score >= 60 ? '#ffb900' : '#dc3232' );
									?>
									<div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
										<div style="flex: 1; min-width: 0;">
											<div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
												<a href="<?php echo esc_url( get_edit_post_link( $analysis->ID ) ); ?>" style="text-decoration: none;">
													<?php echo esc_html( $analysis->post_title ); ?>
												</a>
											</div>
											<div style="font-size: 12px; color: #666;">
												<?php echo esc_html( gmdate( 'M j, Y', strtotime( $analysis->post_date ) ) ); ?>
											</div>
										</div>
										<div style="margin-left: 10px;">
											<span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 20px; border-radius: 10px; font-size: 11px; font-weight: 600; color: white; background-color: <?php echo esc_attr( $score_color ); ?>;">
												<?php echo esc_html( $score ); ?>
											</span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p style="color: #666; font-style: italic; text-align: center; padding: 20px;">
								<?php esc_html_e( 'No content analyzed yet.', 'contentseer' ); ?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>
		</div>
		<?php
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