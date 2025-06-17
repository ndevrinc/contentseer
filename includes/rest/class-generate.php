<?php
/**
 * ContentSeer Rest Generate Class
 *
 * Custom WP REST API endpoints for ContentSeer.
 *
 * @package ContentSeer
 */

namespace ContentSeer\Rest;

class Generate {

	/**
	 * The API namespace for the object.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_namespace( 'ndevr/v1/generate' );
		add_action( 'rest_api_init', array( $this, 'register_site_endpoints' ), 10 );
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_site_endpoints() {
		register_rest_route(
			$this->get_namespace(),
			'/persona',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'generate_persona' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->get_namespace(),
			'/topic/(?P<term_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_persona_by_term_id' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'term_id' => array(
						'required'    => true,
						'type'        => 'integer',
						'description' => 'The ID of the taxonomy term.',
					),
				),
			)
		);
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
	 * Generate personas using Perplexity API and import them.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_persona() {
		// Get API key from settings
		$api_key = get_option( 'contentseer_perplexity_api_key' );

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'api_key_missing', 'Perplexity API key not configured in settings. Please configure it in ContentSeer Settings.', array( 'status' => 400 ) );
		}

		$url = 'https://api.perplexity.ai/chat/completions';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => 'Output only valid JSON. Format three personas (Persona 1, Persona 2, Persona 3) as a JSON object with fields: job_title, name, background, pain_points (array), goals, and motivations.',
			),
			array(
				'role'    => 'user',
				'content' => "Based on Ndevr's website and services, provide the three personas.",
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
			return new \WP_Error( 'error', "Something went wrong: $error_message", array( 'status' => 500 ) );
		}

		$body     = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body, true );
		$personas = array();

		if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
			$personas = json_decode( $data['choices'][0]['message']['content'], true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new \WP_Error( 'json_error', 'Invalid JSON response', array( 'status' => 500 ) );
			}
			$this->import_personas( $personas );
		}

		return rest_ensure_response( $personas );
	}

	/**
	 * Get persona by term ID.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_persona_by_term_id( $request ) {
		$term_id  = $request['term_id'];
		$taxonomy = 'contentseer_personas';

		$term = get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) || ! $term ) {
			return new \WP_Error( 'term_not_found', 'Taxonomy term not found', array( 'status' => 404 ) );
		}

		$custom_fields = array(
			'name'        => get_term_meta( $term_id, 'persona_name', true ),
			'background'  => get_term_meta( $term_id, 'persona_background', true ),
			'pain_points' => get_term_meta( $term_id, 'persona_pain_points', true ),
			'goals'       => get_term_meta( $term_id, 'persona_goals', true ),
			'motivations' => get_term_meta( $term_id, 'persona_motivations', true ),
		);

		if ( ! is_array( $custom_fields['pain_points'] ) ) {
			$custom_fields['pain_points'] = ! empty( $custom_fields['pain_points'] ) ? array( $custom_fields['pain_points'] ) : array();
		}

		$response = array(
			'term'          => array(
				'id'          => $term->term_id,
				'job_title'   => $term->name,
				'description' => $term->description,
				'slug'        => $term->slug,
			),
			'custom_fields' => $custom_fields,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Import personas into the taxonomy.
	 *
	 * @param array $personas Personas array.
	 */
	public function import_personas( $personas ) {
		$taxonomy = 'contentseer_personas';

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
			}
		}
	}

	/**
	 * Get the API namespace.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return $this->namespace;
	}

	/**
	 * Set the API namespace.
	 *
	 * @param string $namespace Namespace string.
	 */
	public function set_namespace( $ns ) {
		$this->namespace = $ns;
	}
}

new Generate();
