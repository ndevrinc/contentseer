<?php
/**
 * ContentSeer Admin Generate Class
 *
 * Handles the generation of content in WordPress admin.
 */

namespace ContentSeer;

class Admin_Generate {
	/**
	 * Constructor to initialize the admin generate.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_contentseer_delete_topic', array( $this, 'handle_delete_topic' ) );
		add_action( 'wp_ajax_contentseer_delete_blog_title', array( $this, 'handle_delete_blog_title' ) );
		add_action( 'wp_ajax_contentseer_request_topics', array( $this, 'handle_request_topics' ) );
		add_action( 'wp_ajax_contentseer_generate_blog_titles', array( $this, 'handle_generate_blog_titles' ) );
		add_action( 'init', array( $this, 'create_tables' ) );
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoints' ) );
	}

	/**
	 * Create topics and blog titles tables if they don't exist
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Topics table - now linked to personas with usage tracking
		$topics_table = $wpdb->prefix . 'contentseer_topics';
		$topics_sql   = "CREATE TABLE IF NOT EXISTS $topics_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			persona_id bigint(20) NOT NULL,
			topic_text text NOT NULL,
			source varchar(50) DEFAULT 'manual',
			status varchar(20) DEFAULT 'active',
			used_date datetime NULL,
			used_post_id bigint(20) NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY persona_id (persona_id),
			KEY status (status),
			KEY used_date (used_date),
			KEY created_at (created_at)
		) $charset_collate;";

		// Blog titles table with usage tracking
		$blog_titles_table = $wpdb->prefix . 'contentseer_blog_titles';
		$blog_titles_sql   = "CREATE TABLE IF NOT EXISTS $blog_titles_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			topic_id mediumint(9) NOT NULL,
			title_text text NOT NULL,
			source varchar(50) DEFAULT 'generated',
			status varchar(20) DEFAULT 'active',
			used_date datetime NULL,
			used_post_id bigint(20) NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY topic_id (topic_id),
			KEY status (status),
			KEY used_date (used_date),
			KEY created_at (created_at),
			FOREIGN KEY (topic_id) REFERENCES $topics_table(id) ON DELETE CASCADE
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $topics_sql );
		dbDelta( $blog_titles_sql );
	}

	/**
	 * Register webhook endpoints
	 */
	public function register_webhook_endpoints() {
		// Webhook for receiving topics from Make.com
		register_rest_route(
			'contentseer/v1',
			'/topics/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_topics_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook_auth' ),
			)
		);

		// Webhook for receiving blog titles from Make.com
		register_rest_route(
			'contentseer/v1',
			'/blog-titles/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_blog_titles_webhook' ),
				'permission_callback' => array( $this, 'verify_webhook_auth' ),
			)
		);
	}

	/**
	 * Handle topics webhook from Make.com
	 */
	public function handle_topics_webhook( $request ) {
		$data = $request->get_json_params();

		if ( ! isset( $data['persona_id'] ) || ! isset( $data['topics'] ) ) {
			return new \WP_Error( 'invalid_data', 'Missing persona_id or topics', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_name     = $wpdb->prefix . 'contentseer_topics';
		$inserted_count = 0;

		foreach ( $data['topics'] as $topic ) {
			if ( empty( $topic ) ) {
				continue;
			}

			// Check if topic already exists for this persona
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table_name WHERE persona_id = %d AND topic_text = %s AND status = 'active'",
					$data['persona_id'],
					$topic
				)
			);

			if ( ! $existing ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'persona_id' => intval( $data['persona_id'] ),
						'topic_text' => sanitize_text_field( $topic ),
						'source'     => 'webhook',
						'status'     => 'active',
					),
					array( '%d', '%s', '%s', '%s' )
				);

				if ( $result ) {
					++$inserted_count;
				}
			}
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'message'        => sprintf( 'Successfully added %d new topics', $inserted_count ),
				'inserted_count' => $inserted_count,
			)
		);
	}

	/**
	 * Handle blog titles webhook from Make.com
	 */
	public function handle_blog_titles_webhook( $request ) {
		$data = $request->get_json_params();

		if ( ! isset( $data['topic_id'] ) || ! isset( $data['blog_titles'] ) ) {
			return new \WP_Error( 'invalid_data', 'Missing topic_id or blog_titles', array( 'status' => 400 ) );
		}

		global $wpdb;
		$table_name     = $wpdb->prefix . 'contentseer_blog_titles';
		$inserted_count = 0;

		foreach ( $data['blog_titles'] as $title ) {
			if ( empty( $title ) ) {
				continue;
			}

			// Check if title already exists for this topic
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table_name WHERE topic_id = %d AND title_text = %s AND status = 'active'",
					$data['topic_id'],
					$title
				)
			);

			if ( ! $existing ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'topic_id'   => intval( $data['topic_id'] ),
						'title_text' => sanitize_text_field( $title ),
						'source'     => 'webhook',
						'status'     => 'active',
					),
					array( '%d', '%s', '%s', '%s' )
				);

				if ( $result ) {
					++$inserted_count;
				}
			}
		}

		return rest_ensure_response(
			array(
				'success'        => true,
				'message'        => sprintf( 'Successfully added %d new blog titles', $inserted_count ),
				'inserted_count' => $inserted_count,
			)
		);
	}

	/**
	 * Verify webhook authentication
	 */
	public function verify_webhook_auth( $request ) {
		$auth_header = $request->get_header( 'Authorization' );
		if ( ! $auth_header ) {
			return false;
		}

		// Extract API key and secret from header
		if ( strpos( $auth_header, 'Basic ' ) === 0 ) {
			$credentials                = base64_decode( substr( $auth_header, 6 ) );
			list($api_key, $api_secret) = explode( ':', $credentials, 2 );

			$stored_key    = get_option( 'contentseer_api_key' );
			$stored_secret = get_option( 'contentseer_api_secret' );

			return hash_equals( $stored_key, $api_key ) && hash_equals( $stored_secret, $api_secret );
		}

		return false;
	}

	/**
	 * Handle AJAX request for topics from Make.com
	 */
	public function handle_request_topics() {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$persona_id = intval( $_POST['persona_id'] );
		if ( ! $persona_id ) {
			wp_send_json_error( 'Persona ID is required' );
			return;
		}
		$persona = get_term( $persona_id, 'contentseer_personas' );
		if ( is_wp_error( $persona ) || ! $persona ) {
			wp_send_json_error( 'Invalid persona selected' );
			return;
		}
		$persona_meta = get_term_meta( $persona_id );

		$content = array(
			'term_id'     => $persona_id,
			'term_name'   => $persona_meta['persona_name'][0],
			'job_title'   => $persona->name,
			'background'  => $persona_meta['persona_background'][0],
			'goals'       => $persona_meta['persona_goals'][0],
			'motivations' => $persona_meta['persona_motivations'][0],
			'pain_points' => $persona_meta['persona_pain_points'][0],
		);

		// Get webhook URL from settings
		$webhook_url = get_option( 'contentseer_topics_webhook_url' );

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( 'Topics webhook URL not configured in settings. Please configure it in ContentSeer Settings.' );
			return;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ContentSeer/1.0',
				),
				'body'    => json_encode( $content ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to request topics: ' . $response->get_error_message() );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['topics'] ) ) {
			wp_send_json_error( 'Invalid response from topics service' );
			return;
		}

		// Store topics in database
		global $wpdb;
		$table_name     = $wpdb->prefix . 'contentseer_topics';
		$inserted_count = 0;

		foreach ( $data['topics'] as $topic ) {
			if ( empty( $topic ) ) {
				continue;
			}

			// Check if topic already exists for this persona
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table_name WHERE persona_id = %d AND topic_text = %s AND status = 'active'",
					$persona_id,
					$topic
				)
			);

			if ( ! $existing ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'persona_id' => $persona_id,
						'topic_text' => sanitize_text_field( $topic ),
						'source'     => 'requested',
						'status'     => 'active',
					),
					array( '%d', '%s', '%s', '%s' )
				);

				if ( $result ) {
					++$inserted_count;
				}
			}
		}

		wp_send_json_success(
			array(
				'message'        => sprintf( 'Successfully added %d new topics', $inserted_count ),
				'inserted_count' => $inserted_count,
			)
		);
	}

	/**
	 * Handle AJAX request to generate blog titles
	 */
	public function handle_generate_blog_titles() {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$topic_id = intval( $_POST['topic_id'] );
		if ( ! $topic_id ) {
			wp_send_json_error( 'Topic ID is required' );
			return;
		}

		// Store topics in database
		global $wpdb;
		$table_name     = $wpdb->prefix . 'contentseer_topics';
		$inserted_count = 0;
		// Check if topic already exists for this persona
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT topic_text, persona_id FROM $table_name WHERE id = %d",
				$topic_id
			)
		);

		if ( ! $results || count( $results ) === 0 ) {
			wp_send_json_error( 'Invalid topic selected' );
			return;
		}
		$persona_id = $results[0]->persona_id;
		$persona    = get_term( $persona_id, 'contentseer_personas' );
		$topic_text = $results[0]->topic_text;
		if ( is_wp_error( $persona ) || ! $persona ) {
			wp_send_json_error( 'Invalid persona selected' );
			return;
		}
		$persona_meta = get_term_meta( $persona_id );

		$content = array(
			'term_name'  => $persona_meta['persona_name'][0],
			'job_title'  => $persona->name,
			'topic_text' => $topic_text,
		);

		// Get webhook URL from settings
		$webhook_url = get_option( 'contentseer_blog_titles_webhook_url' );

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( 'Blog titles webhook URL not configured in settings. Please configure it in ContentSeer Settings.' );
			return;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'User-Agent'   => 'ContentSeer/1.0',
				),
				'body'    => json_encode( $content ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Failed to generate blog titles: ' . $response->get_error_message() );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['titles'] ) ) {
			wp_send_json_error( 'Invalid response from blog titles service' );
			return;
		}

		// Store blog titles in database
		$table_name     = $wpdb->prefix . 'contentseer_blog_titles';
		$inserted_count = 0;

		foreach ( $data['titles'] as $title ) {
			if ( empty( $title ) ) {
				continue;
			}

			// Check if title already exists for this topic
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $table_name WHERE topic_id = %d AND title_text = %s AND status = 'active'",
					$topic_id,
					$title
				)
			);

			if ( ! $existing ) {
				$result = $wpdb->insert(
					$table_name,
					array(
						'topic_id'   => $topic_id,
						'title_text' => sanitize_text_field( $title ),
						'source'     => 'generated',
						'status'     => 'active',
					),
					array( '%d', '%s', '%s', '%s' )
				);

				if ( $result ) {
					++$inserted_count;
				}
			}
		}

		wp_send_json_success(
			array(
				'message'        => sprintf( 'Successfully generated %d new blog titles', $inserted_count ),
				'inserted_count' => $inserted_count,
			)
		);
	}

	/**
	 * Handle AJAX topic deletion
	 */
	public function handle_delete_topic() {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$topic_id = intval( $_POST['topic_id'] );

		if ( ! $topic_id ) {
			wp_send_json_error( 'Invalid topic ID' );
			return;
		}

		global $wpdb;
		$topics_table      = $wpdb->prefix . 'contentseer_topics';
		$blog_titles_table = $wpdb->prefix . 'contentseer_blog_titles';

		// Delete associated blog titles first
		$wpdb->update(
			$blog_titles_table,
			array( 'status' => 'deleted' ),
			array( 'topic_id' => $topic_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Delete the topic
		$result = $wpdb->update(
			$topics_table,
			array( 'status' => 'deleted' ),
			array( 'id' => $topic_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( 'Failed to delete topic' );
			return;
		}

		wp_send_json_success( 'Topic and associated blog titles deleted successfully' );
	}

	/**
	 * Handle AJAX blog title deletion
	 */
	public function handle_delete_blog_title() {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$title_id = intval( $_POST['title_id'] );

		if ( ! $title_id ) {
			wp_send_json_error( 'Invalid title ID' );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'contentseer_blog_titles';

		$result = $wpdb->update(
			$table_name,
			array( 'status' => 'deleted' ),
			array( 'id' => $title_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			wp_send_json_error( 'Failed to delete blog title' );
			return;
		}

		wp_send_json_success( 'Blog title deleted successfully' );
	}

	/**
	 * Get topics for a specific persona with filter options
	 */
	private function get_topics_by_persona( $persona_id, $show_used = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'contentseer_topics';

		$where_clause = "WHERE persona_id = %d AND status = 'active'";
		$params       = array( $persona_id );

		if ( ! $show_used ) {
			$where_clause .= ' AND used_date IS NULL';
		}

		$sql = "SELECT * FROM $table_name $where_clause ORDER BY used_date IS NULL DESC, created_at DESC";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Get blog titles for a specific topic with filter options
	 */
	private function get_blog_titles_by_topic( $topic_id, $show_used = false ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'contentseer_blog_titles';

		$where_clause = "WHERE topic_id = %d AND status = 'active'";
		$params       = array( $topic_id );

		if ( ! $show_used ) {
			$where_clause .= ' AND used_date IS NULL';
		}

		$sql = "SELECT * FROM $table_name $where_clause ORDER BY used_date IS NULL DESC, created_at DESC";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Enqueue required scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on the create content page
		if ( 'contentseer_page_contentseer-create' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'contentseer-generate',
			CONTENTSEER_URL . 'assets/js/generate.js',
			array( 'jquery' ),
			CONTENTSEER_VERSION,
			true
		);

		wp_localize_script(
			'contentseer-generate',
			'contentSeerGenerate',
			array(
				'nonce'   => wp_create_nonce( 'contentseer-generate' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Render the create content page
	 */
	public function render_create_page() {
		// Check if create feature is enabled
		$create_enabled = get_option( 'contentseer_enable_create_feature', true );

		if ( ! $create_enabled ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Create Content', 'contentseer' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Create feature is disabled', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'The content creation feature has been disabled in the settings. Please enable it to use this functionality.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e( 'Enable in Settings', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		// Get all terms from the contentseer_personas taxonomy.
		$terms = get_terms(
			array(
				'taxonomy'   => 'contentseer_personas',
				'hide_empty' => false,
			)
		);

		// Prepare the personas array.
		$personas = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$personas[ $term->term_id ] = $term->name;
			}
		}

		// Check if webhook URLs are configured
		$topics_webhook_url      = get_option( 'contentseer_topics_webhook_url' );
		$blog_titles_webhook_url = get_option( 'contentseer_blog_titles_webhook_url' );
		$webhooks_configured     = ! empty( $topics_webhook_url ) && ! empty( $blog_titles_webhook_url );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Create Content', 'contentseer' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Generate AI-powered content based on your personas and topics.', 'contentseer' ); ?></p>
			
			<?php if ( ! $webhooks_configured ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Webhook URLs not configured!', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'To use the topic generation and blog title features, please configure the Make.com webhook URLs in the settings.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">
							<?php esc_html_e( 'Configure Settings', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			
			<div style="display: grid; grid-template-columns: 400px 1fr; gap: 20px; margin-top: 20px;">
				<!-- Topic & Title Management Sidebar -->
				<div class="card">
					<h2><?php esc_html_e( 'Topic & Title Management', 'contentseer' ); ?></h2>
					
					<div id="persona-selection" style="margin-bottom: 15px;">
						<label for="management_persona"><?php esc_html_e( 'Select Persona:', 'contentseer' ); ?></label>
						<select id="management_persona" class="regular-text" style="width: 100%; margin-top: 5px;">
							<option value=""><?php esc_html_e( 'Choose a persona...', 'contentseer' ); ?></option>
							<?php foreach ( $personas as $term_id => $persona ) : ?>
								<option value="<?php echo esc_attr( $term_id ); ?>"><?php echo esc_html( $persona ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					
					<div id="topic-management" style="display: none;">
						<div style="margin-bottom: 15px;">
							<button type="button" id="request-topics" class="button button-secondary contentseer-btn-secondary" style="width: 100%;" <?php echo ! $webhooks_configured ? 'disabled title="Configure webhook URLs in settings first"' : ''; ?>>
								<?php esc_html_e( 'Request New Topics', 'contentseer' ); ?>
							</button>
							<p class="description" style="margin-top: 5px; font-size: 11px;">
								<?php esc_html_e( 'Fetch fresh topic suggestions from Make.com', 'contentseer' ); ?>
							</p>
						</div>
						
						<div id="topics-list">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
								<h3 style="font-size: 14px; margin: 0; color: #23282d;">
									<?php esc_html_e( 'Available Topics', 'contentseer' ); ?>
									<span id="topics-count" style="color: #666; font-weight: normal;">(0)</span>
								</h3>
								<label style="font-size: 12px; color: #666;">
									<input type="checkbox" id="show-used-topics" style="margin-right: 4px;">
									<?php esc_html_e( 'Show used', 'contentseer' ); ?>
								</label>
							</div>
							
							<div id="topics-container" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
								<p style="color: #666; font-style: italic; font-size: 13px; padding: 20px; text-align: center;">
									<?php esc_html_e( 'Select a persona to view topics.', 'contentseer' ); ?>
								</p>
							</div>
						</div>
						
						<div id="blog-titles-section" style="display: none; margin-top: 20px;">
							<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
								<h3 style="font-size: 14px; margin: 0; color: #23282d;">
									<?php esc_html_e( 'Blog Titles', 'contentseer' ); ?>
									<span id="blog-titles-count" style="color: #666; font-weight: normal;">(0)</span>
								</h3>
								<label style="font-size: 12px; color: #666;">
									<input type="checkbox" id="show-used-titles" style="margin-right: 4px;">
									<?php esc_html_e( 'Show used', 'contentseer' ); ?>
								</label>
							</div>
							
							<div style="margin-bottom: 10px;">
								<button type="button" id="generate-blog-titles" class="button button-secondary contentseer-btn-secondary" style="width: 100%;" disabled <?php echo ! $webhooks_configured ? 'title="Configure webhook URLs in settings first"' : ''; ?>>
									<?php esc_html_e( 'Generate Blog Titles', 'contentseer' ); ?>
								</button>
							</div>
							
							<div id="blog-titles-container" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
								<p style="color: #666; font-style: italic; font-size: 13px; padding: 20px; text-align: center;">
									<?php esc_html_e( 'Select a topic to view blog titles.', 'contentseer' ); ?>
								</p>
							</div>
						</div>
					</div>
				</div>
				
				<!-- Content Generation Form -->
				<div class="card">
					<h2><?php esc_html_e( 'Content Generation', 'contentseer' ); ?></h2>
					
					<?php if ( empty( $personas ) ) : ?>
						<div class="notice notice-warning">
							<p>
								<?php esc_html_e( 'No personas found. Please create personas first to generate content.', 'contentseer' ); ?>
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=contentseer_personas' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">
									<?php esc_html_e( 'Manage Personas', 'contentseer' ); ?>
								</a>
							</p>
						</div>
					<?php else : ?>
						<form method="post" action="" id="contentseer_generate_content_form">
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="contentseer_persona"><?php esc_html_e( 'Target Persona', 'contentseer' ); ?></label>
									</th>
									<td>
										<select id="contentseer_persona" name="contentseer_persona" class="regular-text" required>
											<option value=""><?php esc_html_e( 'Select a persona...', 'contentseer' ); ?></option>
											<?php foreach ( $personas as $term_id => $persona ) : ?>
												<option value="<?php echo esc_attr( $term_id ); ?>"><?php echo esc_html( $persona ); ?></option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Choose the target audience persona for this content.', 'contentseer' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="contentseer_topic_select"><?php esc_html_e( 'Topic', 'contentseer' ); ?></label>
									</th>
									<td>
										<select id="contentseer_topic_select" name="contentseer_topic_select" class="regular-text" style="margin-bottom: 10px;">
											<option value=""><?php esc_html_e( 'Select a topic...', 'contentseer' ); ?></option>
										</select>
										<br>
										<span style="font-size: 12px; color: #666;"><?php esc_html_e( 'Or enter a custom topic below:', 'contentseer' ); ?></span>
										<br>
										<input type="text" id="contentseer_topic" name="contentseer_topic" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Enter a custom topic...', 'contentseer' ); ?>" style="margin-top: 5px;" />
										<p class="description"><?php esc_html_e( 'Select from available topics or enter a custom topic.', 'contentseer' ); ?></p>
									</td>
								</tr>
								<tr id="blog_title_row" style="display: none;">
									<th scope="row">
										<label for="contentseer_blog_title"><?php esc_html_e( 'Blog Title', 'contentseer' ); ?></label>
									</th>
									<td>
										<select id="contentseer_blog_title" name="contentseer_blog_title" class="regular-text">
											<option value=""><?php esc_html_e( 'Select a blog title...', 'contentseer' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose from generated blog titles for the selected topic.', 'contentseer' ); ?></p>
									</td>
								</tr>
							</table>
							
							<p class="submit">
								<button type="button" class="button button-primary button-large generate-content contentseer-btn-primary">
									<?php esc_html_e( 'Generate Content', 'contentseer' ); ?>
								</button>
							</p>
						</form>
						
						<div class="notice notice-info" style="margin-top: 20px;">
							<p>
								<strong><?php esc_html_e( 'How it works:', 'contentseer' ); ?></strong><br>
								<?php esc_html_e( '1. Select a target persona to tailor the content', 'contentseer' ); ?><br>
								<?php esc_html_e( '2. Choose a topic from the dropdown or enter a custom topic', 'contentseer' ); ?><br>
								<?php esc_html_e( '3. Optionally select a specific blog title', 'contentseer' ); ?><br>
								<?php esc_html_e( '4. Click "Generate Content" to create AI-powered content', 'contentseer' ); ?><br>
								<?php esc_html_e( '5. Review and edit the generated content in the post editor', 'contentseer' ); ?>
							</p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			var selectedPersonaId = null;
			var selectedTopicId = null;
			var selectedTopicText = null;
			var showUsedTopics = false;
			var showUsedTitles = false;
			var webhooksConfigured = <?php echo $webhooks_configured ? 'true' : 'false'; ?>;
			
			// Handle persona selection for management
			$('#management_persona').on('change', function() {
				selectedPersonaId = $(this).val();
				if (selectedPersonaId) {
					$('#topic-management').show();
					loadTopicsForPersona(selectedPersonaId);
				} else {
					$('#topic-management').hide();
					$('#blog-titles-section').hide();
				}
			});
			
			// Handle persona selection for content generation
			$('#contentseer_persona').on('change', function() {
				var personaId = $(this).val();
				if (personaId) {
					loadTopicsForPersona(personaId, true);
				} else {
					$('#contentseer_topic_select').empty().append('<option value=""><?php esc_html_e( 'Select a topic...', 'contentseer' ); ?></option>');
					$('#blog_title_row').hide();
				}
			});
			
			// Handle topic selection for content generation
			$('#contentseer_topic_select').on('change', function() {
				var selectedTopic = $(this).val();
				$('#contentseer_topic').val(selectedTopic);
				
				if (selectedTopic) {
					var topicId = $(this).find(':selected').data('topic-id');
					if (topicId) {
						loadBlogTitlesForTopic(topicId, true);
					}
				} else {
					$('#blog_title_row').hide();
				}
			});
			
			// Handle custom topic input
			$('#contentseer_topic').on('input', function() {
				if ($(this).val()) {
					$('#contentseer_topic_select').val('');
					$('#blog_title_row').hide();
				}
			});
			
			// Handle show used topics checkbox
			$('#show-used-topics').on('change', function() {
				showUsedTopics = $(this).is(':checked');
				if (selectedPersonaId) {
					loadTopicsForPersona(selectedPersonaId);
				}
			});
			
			// Handle show used titles checkbox
			$('#show-used-titles').on('change', function() {
				showUsedTitles = $(this).is(':checked');
				if (selectedTopicId) {
					loadBlogTitlesForTopic(selectedTopicId);
				}
			});
			
			// Load topics for a persona
			function loadTopicsForPersona(personaId, isContentForm = false) {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_get_topics_by_persona',
						persona_id: personaId,
						show_used: showUsedTopics,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							if (isContentForm) {
								updateContentFormTopics(response.data);
							} else {
								updateTopicsDisplay(response.data);
							}
						}
					}
				});
			}
			
			// Update content form topics dropdown
			function updateContentFormTopics(topics) {
				var $select = $('#contentseer_topic_select');
				$select.empty().append('<option value=""><?php esc_html_e( 'Select a topic...', 'contentseer' ); ?></option>');
				
				topics.forEach(function(topic) {
					if (!topic.used_date) { // Only show unused topics in content form
						$select.append('<option value="' + topic.topic_text + '" data-topic-id="' + topic.id + '">' + topic.topic_text + '</option>');
					}
				});
			}
			
			// Update topics display in management panel
			function updateTopicsDisplay(topics) {
				var $container = $('#topics-container');
				var $count = $('#topics-count');
				
				$count.text('(' + topics.length + ')');
				
				if (topics.length === 0) {
					$container.html('<p style="color: #666; font-style: italic; font-size: 13px; padding: 20px; text-align: center;"><?php esc_html_e( 'No topics available. Click "Request New Topics" to get started.', 'contentseer' ); ?></p>');
					return;
				}
				
				var html = '';
				topics.forEach(function(topic) {
					var isUsed = topic.used_date !== null;
					var usedStyle = isUsed ? 'opacity: 0.6; border-left: 3px solid #ccc;' : '';
					var usedLabel = isUsed ? ' (Used)' : '';
					var postLink = '';
					
					if (isUsed && topic.used_post_id) {
						postLink = '<a href="post.php?post=' + topic.used_post_id + '&action=edit" style="font-size: 11px; color: #0073aa; text-decoration: none; margin-left: 8px;" title="View Post">View Post</a>';
					}
					
					html += '<div class="topic-item" data-topic-id="' + topic.id + '" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 13px; cursor: pointer; ' + usedStyle + '" onclick="selectTopic(' + topic.id + ', \'' + topic.topic_text + '\')">';
					html += '<div style="flex: 1; min-width: 0;">';
					html += '<div style="font-weight: 500; color: #23282d; word-wrap: break-word;">' + topic.topic_text + usedLabel + '</div>';
					html += '<div style="font-size: 11px; color: #666; margin-top: 2px;">' + topic.source + ' • ' + new Date(topic.created_at).toLocaleDateString();
					if (isUsed) {
						html += ' • Used: ' + new Date(topic.used_date).toLocaleDateString();
					}
					html += postLink + '</div>';
					html += '</div>';
					html += '<button type="button" class="delete-topic" data-topic-id="' + topic.id + '" style="margin-left: 8px; padding: 2px 6px; font-size: 11px; color: #d63638; background: none; border: 1px solid #d63638; border-radius: 3px; cursor: pointer;" title="<?php esc_attr_e( 'Delete topic', 'contentseer' ); ?>" onclick="event.stopPropagation(); deleteTopic(' + topic.id + ');">×</button>';
					html += '</div>';
				});
				
				$container.html(html);
			}
			
			// Select topic in management panel
			window.selectTopic = function(topicId, topicText) {
				selectedTopicId = topicId;
				selectedTopicText = topicText;
				$('.topic-item').removeClass('selected');
				$('.topic-item[data-topic-id="' + topicId + '"]').addClass('selected').css('background-color', '#e3f2fd');
				
				$('#blog-titles-section').show();
				$('#generate-blog-titles').prop('disabled', !webhooksConfigured);
				loadBlogTitlesForTopic(topicId);
			};
			
			// Load blog titles for a topic
			function loadBlogTitlesForTopic(topicId, isContentForm = false) {
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_get_blog_titles_by_topic',
						topic_id: topicId,
						show_used: showUsedTitles,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							if (isContentForm) {
								updateContentFormBlogTitles(response.data);
							} else {
								updateBlogTitlesDisplay(response.data);
							}
						}
					}
				});
			}
			
			// Update content form blog titles dropdown
			function updateContentFormBlogTitles(blogTitles) {
				var $select = $('#contentseer_blog_title');
				$select.empty().append('<option value=""><?php esc_html_e( 'Select a blog title...', 'contentseer' ); ?></option>');
				
				blogTitles.forEach(function(title) {
					if (!title.used_date) { // Only show unused titles in content form
						$select.append('<option value="' + title.title_text + '">' + title.title_text + '</option>');
					}
				});
				
				$('#blog_title_row').show();
			}
			
			// Update blog titles display in management panel
			function updateBlogTitlesDisplay(blogTitles) {
				var $container = $('#blog-titles-container');
				var $count = $('#blog-titles-count');
				
				$count.text('(' + blogTitles.length + ')');
				
				if (blogTitles.length === 0) {
					$container.html('<p style="color: #666; font-style: italic; font-size: 13px; padding: 20px; text-align: center;"><?php esc_html_e( 'No blog titles available. Click "Generate Blog Titles" to create some.', 'contentseer' ); ?></p>');
					return;
				}
				
				var html = '';
				blogTitles.forEach(function(title) {
					var isUsed = title.used_date !== null;
					var usedStyle = isUsed ? 'opacity: 0.6; border-left: 3px solid #ccc;' : '';
					var usedLabel = isUsed ? ' (Used)' : '';
					var postLink = '';
					
					if (isUsed && title.used_post_id) {
						postLink = '<a href="post.php?post=' + title.used_post_id + '&action=edit" style="font-size: 11px; color: #0073aa; text-decoration: none; margin-left: 8px;" title="View Post">View Post</a>';
					}
					
					html += '<div class="blog-title-item" data-title-id="' + title.id + '" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 13px; ' + usedStyle + '">';
					html += '<div style="flex: 1; min-width: 0;">';
					html += '<div style="font-weight: 500; color: #23282d; word-wrap: break-word;">' + title.title_text + usedLabel + '</div>';
					html += '<div style="font-size: 11px; color: #666; margin-top: 2px;">' + title.source + ' • ' + new Date(title.created_at).toLocaleDateString();
					if (isUsed) {
						html += ' • Used: ' + new Date(title.used_date).toLocaleDateString();
					}
					html += postLink + '</div>';
					html += '</div>';
					html += '<div style="display: flex; align-items: center; gap: 5px;">';
					if (!isUsed) {
						html += '<button type="button" class="use-title-button" data-title-text="' + title.title_text + '" style="padding: 2px 6px; font-size: 11px; color: #0073aa; background: none; border: 1px solid #0073aa; border-radius: 3px; cursor: pointer;" title="<?php esc_attr_e( 'Use this title for content generation', 'contentseer' ); ?>" onclick="useTitleForGeneration(\'' + title.title_text + '\');">→</button>';
					}
					html += '<button type="button" class="delete-blog-title" data-title-id="' + title.id + '" style="padding: 2px 6px; font-size: 11px; color: #d63638; background: none; border: 1px solid #d63638; border-radius: 3px; cursor: pointer;" title="<?php esc_attr_e( 'Delete blog title', 'contentseer' ); ?>" onclick="deleteBlogTitle(' + title.id + ');">×</button>';
					html += '</div>';
					html += '</div>';
				});
				
				$container.html(html);
			}
			
			// Function to use title for content generation
			window.useTitleForGeneration = function(titleText) {
				// Set the persona in the content form
				if (selectedPersonaId) {
					$('#contentseer_persona').val(selectedPersonaId);
					
					// Trigger change to load topics
					$('#contentseer_persona').trigger('change');
					
					// Wait a moment for topics to load, then set the topic
					setTimeout(function() {
						if (selectedTopicText) {
							$('#contentseer_topic_select').val(selectedTopicText);
							$('#contentseer_topic').val(selectedTopicText);
							
							// Trigger change to load blog titles
							$('#contentseer_topic_select').trigger('change');
							
							// Wait a moment for blog titles to load, then set the title
							setTimeout(function() {
								$('#contentseer_blog_title').val(titleText);
								
								// Scroll to the content generation form
								$('html, body').animate({
									scrollTop: $('#contentseer_generate_content_form').offset().top - 50
								}, 500);
								
								// Highlight the form briefly
								$('#contentseer_generate_content_form').css('background-color', '#e3f2fd');
								setTimeout(function() {
									$('#contentseer_generate_content_form').css('background-color', '');
								}, 1000);
							}, 500);
						}
					}, 500);
				}
			};
			
			// Handle request topics
			$('#request-topics').on('click', function() {
				if (!webhooksConfigured) {
					alert('Please configure the webhook URLs in ContentSeer Settings first.');
					return;
				}
				
				if (!selectedPersonaId) {
					alert('Please select a persona first.');
					return;
				}
				
				var $button = $(this);
				$button.prop('disabled', true).text('Requesting...');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_request_topics',
						persona_id: selectedPersonaId,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							loadTopicsForPersona(selectedPersonaId);
						} else {
							alert('Failed to request topics: ' + response.data);
						}
					},
					error: function() {
						alert('Failed to request topics. Please try again.');
					},
					complete: function() {
						$button.prop('disabled', false).text('Request New Topics');
					}
				});
			});
			
			// Handle generate blog titles
			$('#generate-blog-titles').on('click', function() {
				if (!webhooksConfigured) {
					alert('Please configure the webhook URLs in ContentSeer Settings first.');
					return;
				}
				
				if (!selectedTopicId) {
					alert('Please select a topic first.');
					return;
				}
				
				var $button = $(this);
				$button.prop('disabled', true).text('Generating...');
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_generate_blog_titles',
						topic_id: selectedTopicId,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							alert(response.data.message);
							loadBlogTitlesForTopic(selectedTopicId);
						} else {
							alert('Failed to generate blog titles: ' + response.data);
						}
					},
					error: function() {
						alert('Failed to generate blog titles. Please try again.');
					},
					complete: function() {
						$button.prop('disabled', !webhooksConfigured).text('Generate Blog Titles');
					}
				});
			});
			
			// Delete topic function
			window.deleteTopic = function(topicId) {
				if (!confirm('Are you sure you want to delete this topic and all its blog titles?')) {
					return;
				}
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_delete_topic',
						topic_id: topicId,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							loadTopicsForPersona(selectedPersonaId);
							if (selectedTopicId == topicId) {
								$('#blog-titles-section').hide();
								selectedTopicId = null;
							}
						} else {
							alert('Failed to delete topic: ' + response.data);
						}
					}
				});
			};
			
			// Delete blog title function
			window.deleteBlogTitle = function(titleId) {
				if (!confirm('Are you sure you want to delete this blog title?')) {
					return;
				}
				
				$.ajax({
					url: ajaxurl,
					method: 'POST',
					data: {
						action: 'contentseer_delete_blog_title',
						title_id: titleId,
						nonce: contentSeerGenerate.nonce
					},
					success: function(response) {
						if (response.success) {
							loadBlogTitlesForTopic(selectedTopicId);
						} else {
							alert('Failed to delete blog title: ' + response.data);
						}
					}
				});
			};
		});
		</script>
		
		<style>
		.topic-item:hover {
			background-color: #f5f5f5 !important;
		}
		.topic-item.selected {
			background-color: #e3f2fd !important;
		}
		.blog-title-item:hover {
			background-color: #f5f5f5;
		}
		.use-title-button:hover {
			background-color: #0073aa !important;
			color: white !important;
		}
		</style>
		<?php
	}
}

// Add AJAX handlers for getting topics and blog titles with filter support
add_action(
	'wp_ajax_contentseer_get_topics_by_persona',
	function () {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$persona_id = intval( $_POST['persona_id'] );
		$show_used  = isset( $_POST['show_used'] ) ? (bool) $_POST['show_used'] : false;

		if ( ! $persona_id ) {
			wp_send_json_error( 'Invalid persona ID' );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'contentseer_topics';

		$where_clause = "WHERE persona_id = %d AND status = 'active'";
		$params       = array( $persona_id );

		if ( ! $show_used ) {
			$where_clause .= ' AND used_date IS NULL';
		}

		$sql = "SELECT * FROM $table_name $where_clause ORDER BY used_date IS NULL DESC, created_at DESC";

		$topics = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		wp_send_json_success( $topics );
	}
);

add_action(
	'wp_ajax_contentseer_get_blog_titles_by_topic',
	function () {
		check_ajax_referer( 'contentseer-generate', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		$topic_id  = intval( $_POST['topic_id'] );
		$show_used = isset( $_POST['show_used'] ) ? (bool) $_POST['show_used'] : false;

		if ( ! $topic_id ) {
			wp_send_json_error( 'Invalid topic ID' );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'contentseer_blog_titles';

		$where_clause = "WHERE topic_id = %d AND status = 'active'";
		$params       = array( $topic_id );

		if ( ! $show_used ) {
			$where_clause .= ' AND used_date IS NULL';
		}

		$sql = "SELECT * FROM $table_name $where_clause ORDER BY used_date IS NULL DESC, created_at DESC";

		$blog_titles = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		wp_send_json_success( $blog_titles );
	}
);

new Admin_Generate();