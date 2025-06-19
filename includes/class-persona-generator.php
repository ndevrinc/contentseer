<?php
/**
 * ContentSeer Persona Generator Class
 *
 * Handles persona generation functionality using Perplexity API.
 */

namespace ContentSeer;

class Persona_Generator {

	/**
	 * Constructor to initialize the persona generator.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_contentseer_generate_personas', array( $this, 'handle_generate_personas' ) );
	}

	/**
	 * Enqueue scripts for persona generation
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on taxonomy pages
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		// Check if we're on the personas taxonomy
		$screen = get_current_screen();
		if ( ! $screen || 'contentseer_personas' !== $screen->taxonomy ) {
			return;
		}

		wp_enqueue_script(
			'contentseer-persona-generator',
			CONTENTSEER_URL . 'assets/js/persona-generator.js',
			array( 'jquery' ),
			CONTENTSEER_VERSION,
			true
		);

		wp_localize_script(
			'contentseer-persona-generator',
			'contentSeerPersonaGenerator',
			array(
				'nonce'   => wp_create_nonce( 'contentseer-persona-generator' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Handle AJAX request to generate personas
	 */
	public function handle_generate_personas() {
		check_ajax_referer( 'contentseer-persona-generator', 'nonce' );

		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_send_json_error( 'Permission denied' );
			return;
		}

		// Get API key from settings
		$api_key = get_option( 'contentseer_perplexity_api_key' );

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'Perplexity API key not configured in settings. Please configure it in ContentSeer Settings.' );
			return;
		}

		$url = 'https://api.perplexity.ai/chat/completions';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Output only valid JSON. Format three personas (Persona 1, Persona 2, Persona 3) as a JSON object with fields: job_title, name, background, pain_points (array), goals, and motivations.',
			),
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Based on %s's website %s, provide the three personas.",
					get_bloginfo( 'name' ),
					home_url()
				),
			),
		);

		$payload = array(
			'model'           => 'sonar-pro',
			'messages'        => $messages,
			'response_format' => array(
				'type'        => 'json_schema',
				'json_schema' => $this->get_json_schema(),
			),
		);

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
			'timeout' => 120,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			wp_send_json_error( "Something went wrong: $error_message" );
			return;
		}

		$body     = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body, true );
		$personas = array();

		if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
			$personas = json_decode( $data['choices'][0]['message']['content'], true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wp_send_json_error( 'Invalid JSON response from API' );
				return;
			}

			$imported_count = $this->import_personas( $personas );
			wp_send_json_success(
				array(
					'message'        => sprintf( 'Successfully generated and imported %d personas', $imported_count ),
					'imported_count' => $imported_count,
					'personas'       => $personas,
				)
			);
		} else {
			wp_send_json_error( 'No personas generated from API' );
		}
	}

	/**
	 * Get the JSON schema for personas.
	 *
	 * @return array
	 */
	public function get_json_schema() {
		return array(
			'schema' => array(
				'type'       => 'object',
				'properties' => array(
					'persona_1' => array(
						'type'       => 'object',
						'properties' => array(
							'job_title'   => array( 'type' => 'string' ),
							'name'        => array( 'type' => 'string' ),
							'background'  => array( 'type' => 'string' ),
							'pain_points' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'goals'       => array( 'type' => 'string' ),
							'motivations' => array( 'type' => 'string' ),
						),
						'required'   => array( 'job_title', 'name', 'background', 'pain_points', 'motivations' ),
					),
					'persona_2' => array(
						'type'       => 'object',
						'properties' => array(
							'job_title'   => array( 'type' => 'string' ),
							'name'        => array( 'type' => 'string' ),
							'background'  => array( 'type' => 'string' ),
							'pain_points' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'goals'       => array( 'type' => 'string' ),
							'motivations' => array( 'type' => 'string' ),
						),
						'required'   => array( 'job_title', 'name', 'background', 'pain_points', 'motivations' ),
					),
					'persona_3' => array(
						'type'       => 'object',
						'properties' => array(
							'job_title'   => array( 'type' => 'string' ),
							'name'        => array( 'type' => 'string' ),
							'background'  => array( 'type' => 'string' ),
							'pain_points' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
							'goals'       => array( 'type' => 'string' ),
							'motivations' => array( 'type' => 'string' ),
						),
						'required'   => array( 'job_title', 'name', 'background', 'pain_points', 'motivations' ),
					),
				),
				'required'   => array( 'persona_1', 'persona_2', 'persona_3' ),
			),
		);
	}

	/**
	 * Import personas into the taxonomy.
	 *
	 * @param array $personas Personas array.
	 * @return int Number of personas imported.
	 */
	public function import_personas( $personas ) {
		$taxonomy       = 'contentseer_personas';
		$imported_count = 0;

		foreach ( $personas as $persona ) {
			$term = term_exists( $persona['job_title'], $taxonomy );
			if ( 0 === $term || null === $term ) {
				$term = wp_insert_term(
					$persona['job_title'],
					$taxonomy,
					array(
						'description' => $persona['background'],
						'slug'        => sanitize_title( $persona['job_title'] ),
					)
				);
			} else {
				$term = get_term( $term['term_id'], $taxonomy );
			}

			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? $term['term_id'] : $term->term_id;

				update_term_meta( $term_id, 'persona_name', $persona['name'] );
				update_term_meta( $term_id, 'persona_background', $persona['background'] );
				update_term_meta( $term_id, 'persona_pain_points', $persona['pain_points'] );
				update_term_meta( $term_id, 'persona_goals', $persona['goals'] );
				update_term_meta( $term_id, 'persona_motivations', $persona['motivations'] );

				++$imported_count;
			}
		}

		return $imported_count;
	}

	/**
	 * Add generate personas button to taxonomy page
	 */
	public function add_generate_button() {
		// Check if API key is configured
		$api_key         = get_option( 'contentseer_perplexity_api_key' );
		$button_disabled = empty( $api_key ) ? 'disabled' : '';
		$button_title    = empty( $api_key ) ? 'Configure Perplexity API key in ContentSeer Settings first' : '';
		?>
		<div class="form-wrap">
			<h2><?php esc_html_e( 'Generate Personas', 'contentseer' ); ?></h2>
			<p><?php esc_html_e( 'Use AI to automatically generate personas based on your website and services.', 'contentseer' ); ?></p>
			
			<?php if ( empty( $api_key ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'API Key Required', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'To generate personas, please configure your Perplexity API key in the ContentSeer settings.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-secondary" style="margin-left: 10px;">
							<?php esc_html_e( 'Configure Settings', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			
			<button type="button" id="generate-personas" class="button button-primary" <?php echo esc_attr( $button_disabled ); ?> title="<?php echo esc_attr( $button_title ); ?>">
				<?php esc_html_e( 'Generate Personas with AI', 'contentseer' ); ?>
			</button>
			
			<p class="description">
				<?php esc_html_e( 'This will generate 3 personas based on your website content and services. Existing personas with the same job titles will be updated.', 'contentseer' ); ?>
			</p>
		</div>
		<?php
	}
}

new Persona_Generator();