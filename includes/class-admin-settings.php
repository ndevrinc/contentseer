<?php
/**
 * ContentSeer Admin Settings Class
 *
 * Handles settings for the ContentSeer plugin.
 */

namespace ContentSeer;

class Admin_Settings {

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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'create_default_webhook_settings' ) );
		add_action( 'admin_init', array( $this, 'create_default_feature_settings' ) );
		add_action( 'admin_init', array( $this, 'create_default_api_settings' ) );
		add_action( 'admin_init', array( $this, 'create_default_site_settings' ) );
		add_action( 'wp_ajax_contentseer_request_access', array( $this, 'handle_access_request' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_scripts' ) );
	}

	/**
	 * Enqueue scripts for settings page
	 */
	public function enqueue_settings_scripts( $hook ) {
		// Only load on ContentSeer settings page
		if ( 'settings_page_contentseer' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'contentseer-settings',
			CONTENTSEER_URL . 'assets/js/settings.js',
			array( 'jquery' ),
			CONTENTSEER_VERSION,
			true
		);

		wp_localize_script(
			'contentseer-settings',
			'contentSeerSettings',
			array(
				'nonce'   => wp_create_nonce( 'contentseer-settings' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Handle access request AJAX
	 */
	public function handle_access_request() {
		check_ajax_referer( 'contentseer-settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$site_name = sanitize_text_field( $_POST['site_name'] );
		$admin_email = sanitize_email( $_POST['admin_email'] );
		$site_url = home_url();

		if ( empty( $site_name ) || empty( $admin_email ) ) {
			wp_send_json_error( 'Site name and admin email are required' );
			return;
		}

		// Prepare request data
		$request_data = array(
			'site_name'    => $site_name,
			'site_url'     => $site_url,
			'admin_email'  => $admin_email,
			'wp_version'   => get_bloginfo( 'version' ),
			'plugin_version' => CONTENTSEER_VERSION,
			'request_type' => 'access_request',
		);

		// Send request to ContentSeer dashboard API
		$response = wp_remote_post(
			'https://dashboard.contentseer.io/api/v1/sites/request-access',
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ContentSeer-WordPress/' . CONTENTSEER_VERSION,
				),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to connect to ContentSeer: ' . $response->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $response_code !== 200 ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Unknown error occurred';
			wp_send_json_error( 'Request failed: ' . $error_message );
			return;
		}

		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : 'Access request failed';
			wp_send_json_error( $error_message );
			return;
		}

		// If successful, save the credentials
		if ( isset( $data['credentials'] ) ) {
			$credentials = $data['credentials'];
			
			// Update all the settings with the received credentials
			update_option( 'contentseer_id', sanitize_text_field( $credentials['site_id'] ) );
			update_option( 'contentseer_api_key', sanitize_text_field( $credentials['api_key'] ) );
			update_option( 'contentseer_api_secret', sanitize_text_field( $credentials['api_secret'] ) );
			
			// Update webhook URLs if provided
			if ( isset( $credentials['webhooks'] ) ) {
				$webhooks = $credentials['webhooks'];
				
				if ( isset( $webhooks['topics'] ) ) {
					update_option( 'contentseer_topics_webhook_url', esc_url_raw( $webhooks['topics'] ) );
				}
				if ( isset( $webhooks['blog_titles'] ) ) {
					update_option( 'contentseer_blog_titles_webhook_url', esc_url_raw( $webhooks['blog_titles'] ) );
				}
				if ( isset( $webhooks['content_generation'] ) ) {
					update_option( 'contentseer_content_generation_webhook_url', esc_url_raw( $webhooks['content_generation'] ) );
				}
				if ( isset( $webhooks['content_analysis'] ) ) {
					update_option( 'contentseer_content_analysis_webhook_url', esc_url_raw( $webhooks['content_analysis'] ) );
				}
				if ( isset( $webhooks['content_sync'] ) ) {
					update_option( 'contentseer_content_sync_webhook_url', esc_url_raw( $webhooks['content_sync'] ) );
				}
			}

			wp_send_json_success(
				array(
					'message' => 'Access granted! Your site has been successfully connected to ContentSeer.',
					'site_id' => $credentials['site_id'],
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => 'Access request submitted successfully. You will receive an email when your request is approved.',
					'pending' => true,
				)
			);
		}
	}

	/**
	 * Create default webhook settings if they don't exist
	 */
	public function create_default_webhook_settings() {
		// Create default webhook URLs if they don't exist
		$default_webhooks = array(
			'contentseer_topics_webhook_url'             => '',
			'contentseer_blog_titles_webhook_url'        => '',
			'contentseer_content_generation_webhook_url' => '',
			'contentseer_content_analysis_webhook_url'   => '',
			'contentseer_content_sync_webhook_url'       => '',
		);

		foreach ( $default_webhooks as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Create default feature settings if they don't exist
	 */
	public function create_default_feature_settings() {
		// Create default feature settings if they don't exist
		$default_features = array(
			'contentseer_enable_analyze_feature' => true,
			'contentseer_enable_create_feature'  => true,
		);

		foreach ( $default_features as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Create default API settings if they don't exist
	 */
	public function create_default_api_settings() {
		// Create default API settings if they don't exist
		$default_apis = array(
			'contentseer_perplexity_api_key' => '',
		);

		foreach ( $default_apis as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Create default site settings if they don't exist
	 */
	public function create_default_site_settings() {
		// Create default site settings if they don't exist
		$default_site = array(
			'contentseer_id' => '',
		);

		foreach ( $default_site as $option_name => $default_value ) {
			if ( false === get_option( $option_name ) ) {
				add_option( $option_name, $default_value );
			}
		}
	}

	/**
	 * Add a settings page to the WordPress admin menu.
	 */
	public function add_settings_page() {
		add_options_page(
			'ContentSeer Settings', // Page title
			'ContentSeer',          // Menu title
			'manage_options',        // Capability
			'contentseer',          // Menu slug
			array( $this, 'render_settings_page' ) // Callback function
		);
	}

	/**
	 * Register settings for the plugin.
	 */
	public function register_settings() {
		// Register the post types setting.
		register_setting(
			'contentseer_settings',
			'contentseer_post_types',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_post_types' ),
			)
		);

		// Register feature enable/disable settings
		register_setting(
			'contentseer_settings',
			'contentseer_enable_analyze_feature',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		register_setting(
			'contentseer_settings',
			'contentseer_enable_create_feature',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => true,
			)
		);

		// Register site settings
		register_setting(
			'contentseer_settings',
			'contentseer_id',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Register webhook URLs
		register_setting(
			'contentseer_settings',
			'contentseer_topics_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'contentseer_settings',
			'contentseer_blog_titles_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'contentseer_settings',
			'contentseer_content_generation_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'contentseer_settings',
			'contentseer_content_analysis_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		register_setting(
			'contentseer_settings',
			'contentseer_content_sync_webhook_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);

		// Register API keys
		register_setting(
			'contentseer_settings',
			'contentseer_perplexity_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Add the Features section.
		add_settings_section(
			'contentseer_features_section',
			'Feature Settings',
			array( $this, 'render_features_section_description' ),
			'contentseer'
		);

		// Add feature enable/disable fields
		add_settings_field(
			'contentseer_enable_analyze_feature',
			'Enable Analyze Feature',
			array( $this, 'render_enable_analyze_field' ),
			'contentseer',
			'contentseer_features_section'
		);

		add_settings_field(
			'contentseer_enable_create_feature',
			'Enable Create Feature',
			array( $this, 'render_enable_create_field' ),
			'contentseer',
			'contentseer_features_section'
		);

		// Add the Site Configuration section.
		add_settings_section(
			'contentseer_site_section',
			'Site Configuration',
			array( $this, 'render_site_section_description' ),
			'contentseer'
		);

		// Add the ContentSeer ID field.
		add_settings_field(
			'contentseer_id',
			'ContentSeer Site ID',
			array( $this, 'render_contentseer_id_field' ),
			'contentseer',
			'contentseer_site_section'
		);

		// Add the Post Types section.
		add_settings_section(
			'contentseer_post_types_section',
			'Post Types Settings',
			null,
			'contentseer'
		);

		// Add the Post Types field.
		add_settings_field(
			'contentseer_post_types',
			'Select Post Types',
			array( $this, 'render_post_types_field' ),
			'contentseer',
			'contentseer_post_types_section'
		);

		// Add the API Keys section.
		add_settings_section(
			'contentseer_api_keys_section',
			'API Keys',
			array( $this, 'render_api_keys_section_description' ),
			'contentseer'
		);

		// Add API key fields
		add_settings_field(
			'contentseer_perplexity_api_key',
			'Perplexity API Key',
			array( $this, 'render_perplexity_api_key_field' ),
			'contentseer',
			'contentseer_api_keys_section'
		);

		// Add the Webhooks section.
		add_settings_section(
			'contentseer_webhooks_section',
			'Make.com Webhook Settings',
			array( $this, 'render_webhooks_section_description' ),
			'contentseer'
		);

		// Add webhook URL fields
		add_settings_field(
			'contentseer_topics_webhook_url',
			'Topics Webhook URL',
			array( $this, 'render_topics_webhook_field' ),
			'contentseer',
			'contentseer_webhooks_section'
		);

		add_settings_field(
			'contentseer_blog_titles_webhook_url',
			'Blog Titles Webhook URL',
			array( $this, 'render_blog_titles_webhook_field' ),
			'contentseer',
			'contentseer_webhooks_section'
		);

		add_settings_field(
			'contentseer_content_generation_webhook_url',
			'Content Generation Webhook URL',
			array( $this, 'render_content_generation_webhook_field' ),
			'contentseer',
			'contentseer_webhooks_section'
		);

		add_settings_field(
			'contentseer_content_analysis_webhook_url',
			'Content Analysis Webhook URL',
			array( $this, 'render_content_analysis_webhook_field' ),
			'contentseer',
			'contentseer_webhooks_section'
		);

		add_settings_field(
			'contentseer_content_sync_webhook_url',
			'Content Sync Webhook URL',
			array( $this, 'render_content_sync_webhook_field' ),
			'contentseer',
			'contentseer_webhooks_section'
		);
	}

	/**
	 * Render features section description
	 */
	public function render_features_section_description() {
		echo '<p>Enable or disable specific ContentSeer features. Disabling a feature will remove it from menus and the interface.</p>';
	}

	/**
	 * Render the enable analyze feature field.
	 */
	public function render_enable_analyze_field() {
		$enabled = get_option( 'contentseer_enable_analyze_feature', true );
		echo '<label>';
		echo '<input type="checkbox" name="contentseer_enable_analyze_feature" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' Enable content analysis features (analysis dashboard, post meta box, etc.)';
		echo '</label>';
		echo '<p class="description">When disabled, analysis features will be hidden from menus and post edit screens.</p>';
	}

	/**
	 * Render the enable create feature field.
	 */
	public function render_enable_create_field() {
		$enabled = get_option( 'contentseer_enable_create_feature', true );
		echo '<label>';
		echo '<input type="checkbox" name="contentseer_enable_create_feature" value="1" ' . checked( $enabled, true, false ) . ' />';
		echo ' Enable content creation features (content generation, topic management, etc.)';
		echo '</label>';
		echo '<p class="description">When disabled, content creation features will be hidden from menus and interface.</p>';
	}

	/**
	 * Render site section description
	 */
	public function render_site_section_description() {
		echo '<p>Configure your site connection to ContentSeer services.</p>';
	}

	/**
	 * Render the ContentSeer ID field.
	 */
	public function render_contentseer_id_field() {
		$contentseer_id = get_option( 'contentseer_id', '' );
		echo '<input type="text" name="contentseer_id" value="' . esc_attr( $contentseer_id ) . '" class="regular-text" readonly />';
		echo '<p class="description">Your ContentSeer Site ID. This is automatically set when you request access.</p>';
	}

	/**
	 * Render API keys section description
	 */
	public function render_api_keys_section_description() {
		echo '<p>Configure API keys for external services used by ContentSeer.</p>';
	}

	/**
	 * Render the Perplexity API key field.
	 */
	public function render_perplexity_api_key_field() {
		$api_key = get_option( 'contentseer_perplexity_api_key', '' );
		echo '<input type="password" name="contentseer_perplexity_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
		echo '<p class="description">Enter your Perplexity API key for persona generation. <a href="https://www.perplexity.ai/settings/api" target="_blank">Get your API key here</a>.</p>';
	}

	/**
	 * Render webhooks section description
	 */
	public function render_webhooks_section_description() {
		echo '<p>Configure webhook URLs for Make.com integration. These webhooks are used for various ContentSeer operations.</p>';
	}

	/**
	 * Render the topics webhook field.
	 */
	public function render_topics_webhook_field() {
		$webhook_url = get_option( 'contentseer_topics_webhook_url', '' );
		echo '<input type="url" name="contentseer_topics_webhook_url" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
		echo '<p class="description">Enter the Make.com webhook URL for requesting topics.</p>';
	}

	/**
	 * Render the blog titles webhook field.
	 */
	public function render_blog_titles_webhook_field() {
		$webhook_url = get_option( 'contentseer_blog_titles_webhook_url', '' );
		echo '<input type="url" name="contentseer_blog_titles_webhook_url" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
		echo '<p class="description">Enter the Make.com webhook URL for generating blog titles.</p>';
	}

	/**
	 * Render the content generation webhook field.
	 */
	public function render_content_generation_webhook_field() {
		$webhook_url = get_option( 'contentseer_content_generation_webhook_url', '' );
		echo '<input type="url" name="contentseer_content_generation_webhook_url" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
		echo '<p class="description">Enter the Make.com webhook URL for generating content.</p>';
	}

	/**
	 * Render the content analysis webhook field.
	 */
	public function render_content_analysis_webhook_field() {
		$webhook_url = get_option( 'contentseer_content_analysis_webhook_url', '' );
		echo '<input type="url" name="contentseer_content_analysis_webhook_url" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
		echo '<p class="description">Enter the Make.com webhook URL for analyzing content.</p>';
	}

	/**
	 * Render the content sync webhook field.
	 */
	public function render_content_sync_webhook_field() {
		$webhook_url = get_option( 'contentseer_content_sync_webhook_url', '' );
		echo '<input type="url" name="contentseer_content_sync_webhook_url" value="' . esc_attr( $webhook_url ) . '" class="regular-text" />';
		echo '<p class="description">Enter the Make.com webhook URL for syncing content.</p>';
	}

	/**
	 * Render the Post Types field.
	 */
	public function render_post_types_field() {
		$selected_post_types = get_option( 'contentseer_post_types', array() );
		$post_types          = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			$checked = in_array( $post_type->name, $selected_post_types, true ) ? 'checked' : '';
			echo '<label>';
			echo '<input type="checkbox" name="contentseer_post_types[]" value="' . esc_attr( $post_type->name ) . '" ' . $checked . '> ';
			echo esc_html( $post_type->label );
			echo '</label><br>';
		}
	}

	/**
	 * Sanitize the selected post types.
	 */
	public function sanitize_post_types( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$api_key        = get_option( 'contentseer_api_key' );
		$api_secret     = get_option( 'contentseer_api_secret' );
		$contentseer_id = get_option( 'contentseer_id' );
		$site_url       = home_url();
		$is_connected   = ! empty( $contentseer_id ) && ! empty( $api_key ) && ! empty( $api_secret );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ContentSeer Settings', 'contentseer' ); ?></h1>
			
			<?php if ( ! $is_connected ) : ?>
				<div class="card" style="margin-bottom: 20px;">
					<h2><?php esc_html_e( 'Get Started with ContentSeer', 'contentseer' ); ?></h2>
					<p><?php esc_html_e( 'Welcome to ContentSeer! To get started, you need to request access to connect your site to our services.', 'contentseer' ); ?></p>
					
					<div id="access-request-form">
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="site_name"><?php esc_html_e( 'Site Name', 'contentseer' ); ?></label>
								</th>
								<td>
									<input type="text" id="site_name" class="regular-text" value="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'The name of your website.', 'contentseer' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="admin_email"><?php esc_html_e( 'Admin Email', 'contentseer' ); ?></label>
								</th>
								<td>
									<input type="email" id="admin_email" class="regular-text" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
									<p class="description"><?php esc_html_e( 'Email address for account notifications and support.', 'contentseer' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="site_url"><?php esc_html_e( 'Site URL', 'contentseer' ); ?></label>
								</th>
								<td>
									<input type="url" id="site_url" class="regular-text" value="<?php echo esc_attr( $site_url ); ?>" readonly />
									<p class="description"><?php esc_html_e( 'Your website URL (automatically detected).', 'contentseer' ); ?></p>
								</td>
							</tr>
						</table>
						
						<p class="submit">
							<button type="button" id="request-access-btn" class="button button-primary button-large">
								<?php esc_html_e( 'Request Access to ContentSeer', 'contentseer' ); ?>
							</button>
						</p>
						
						<div id="access-request-status" style="display: none; margin-top: 15px;"></div>
					</div>
					
					<div class="notice notice-info inline">
						<p>
							<strong><?php esc_html_e( 'What happens next?', 'contentseer' ); ?></strong><br>
							<?php esc_html_e( '1. We\'ll review your request and set up your account', 'contentseer' ); ?><br>
							<?php esc_html_e( '2. You\'ll receive an email with your account details', 'contentseer' ); ?><br>
							<?php esc_html_e( '3. Your site will be automatically configured with the necessary credentials', 'contentseer' ); ?><br>
							<?php esc_html_e( '4. You can start using ContentSeer features immediately', 'contentseer' ); ?>
						</p>
					</div>
				</div>
			<?php else : ?>
				<div class="card">
					<h2><?php esc_html_e( 'Connection Status', 'contentseer' ); ?></h2>
					<div class="notice notice-success inline">
						<p>
							<strong><?php esc_html_e( 'Your site is connected to ContentSeer!', 'contentseer' ); ?></strong><br>
							<?php esc_html_e( 'Site ID: ', 'contentseer' ); ?><code><?php echo esc_html( $contentseer_id ); ?></code>
						</p>
					</div>
				</div>
			<?php endif; ?>
			
			<div class="card">
				<h2><?php esc_html_e( 'API Credentials', 'contentseer' ); ?></h2>
				<p><?php esc_html_e( 'These credentials are automatically configured when you request access.', 'contentseer' ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'contentseer' ); ?></th>
						<td>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $api_key ? str_repeat( '*', 20 ) . substr( $api_key, -8 ) : 'Not configured' ); ?>" readonly />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'API Secret', 'contentseer' ); ?></th>
						<td>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $api_secret ? str_repeat( '*', 40 ) . substr( $api_secret, -8 ) : 'Not configured' ); ?>" readonly />
						</td>
					</tr>
				</table>
				
				<p class="description">
					<?php esc_html_e( 'These credentials are used to authenticate API requests between your site and ContentSeer.', 'contentseer' ); ?>
				</p>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Webhook Endpoints', 'contentseer' ); ?></h2>
				<p><?php esc_html_e( 'Use these endpoints in your Make.com scenarios to send data to WordPress.', 'contentseer' ); ?></p>
				
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Topics Webhook', 'contentseer' ); ?></th>
						<td>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $site_url . '/wp-json/contentseer/v1/topics/webhook' ); ?>" readonly />
							<p class="description"><?php esc_html_e( 'Use this URL in Make.com to send topics to WordPress.', 'contentseer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Blog Titles Webhook', 'contentseer' ); ?></th>
						<td>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $site_url . '/wp-json/contentseer/v1/blog-titles/webhook' ); ?>" readonly />
							<p class="description"><?php esc_html_e( 'Use this URL in Make.com to send blog titles to WordPress.', 'contentseer' ); ?></p>
						</td>
					</tr>
				</table>
				
				<h3><?php esc_html_e( 'Authentication', 'contentseer' ); ?></h3>
				<p><?php esc_html_e( 'Use Basic Authentication with the API credentials above when making requests to these webhooks.', 'contentseer' ); ?></p>
				
				<h3><?php esc_html_e( 'Expected Payload Formats', 'contentseer' ); ?></h3>
				
				<h4><?php esc_html_e( 'Topics Webhook:', 'contentseer' ); ?></h4>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
	"persona_id": 123,
	"topics": [
	"AI in Healthcare",
	"Sustainable Technology",
	"Remote Work Trends"
	]
}
				</pre>
				
				<h4><?php esc_html_e( 'Blog Titles Webhook:', 'contentseer' ); ?></h4>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">
{
	"topic_id": 456,
	"blog_titles": [
	"10 Ways AI is Revolutionizing Healthcare",
	"The Future of Medical Diagnosis with AI",
	"How Machine Learning is Saving Lives"
	]
}
				</pre>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'contentseer_settings' );
				do_settings_sections( 'contentseer' );
				submit_button();
				?>
			</form>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#request-access-btn').on('click', function() {
				var $button = $(this);
				var $status = $('#access-request-status');
				
				var siteName = $('#site_name').val().trim();
				var adminEmail = $('#admin_email').val().trim();
				
				if (!siteName || !adminEmail) {
					$status.html('<div class="notice notice-error inline"><p>Please fill in all required fields.</p></div>').show();
					return;
				}
				
				$button.prop('disabled', true).text('Requesting Access...');
				$status.html('<div class="notice notice-info inline"><p>Sending request to ContentSeer...</p></div>').show();
				
				$.ajax({
					url: contentSeerSettings.ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_request_access',
						site_name: siteName,
						admin_email: adminEmail,
						nonce: contentSeerSettings.nonce
					},
					success: function(response) {
						if (response.success) {
							if (response.data.pending) {
								$status.html('<div class="notice notice-info inline"><p>' + response.data.message + '</p></div>');
							} else {
								$status.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
								// Reload the page after a short delay to show the updated connection status
								setTimeout(function() {
									location.reload();
								}, 2000);
							}
						} else {
							$status.html('<div class="notice notice-error inline"><p>Error: ' + response.data + '</p></div>');
						}
					},
					error: function() {
						$status.html('<div class="notice notice-error inline"><p>Failed to send request. Please try again.</p></div>');
					},
					complete: function() {
						$button.prop('disabled', false).text('Request Access to ContentSeer');
					}
				});
			});
		});
		</script>
		<?php
	}
}

new Admin_Settings();