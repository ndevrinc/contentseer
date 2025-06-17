<?php
/**
 * Front End Class
 *
 * This class handles the core functionality of the front end of the ContentSeer plugin.
 */
namespace ContentSeer;

class Front_End {

	/**
	 * Constructor to initialize the plugin.
	 */
	public function __construct() {
		// Initialize settings, hooks, and other necessary components.
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for the plugin.
	 */
	private function init_hooks() {
		// Add action and filter hooks here.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles for the front end.
	 */
	public function enqueue_scripts() {
		// Enqueue front-end scripts and styles.
		wp_enqueue_script(
			'contentseer-script',
			plugin_dir_url( __FILE__ ) . '../assets/js/script.js',
			array( 'jquery' ),
			'1.0.0',
			true
		);

		// Prepare data to pass to the script.
		$data = array(
			'personasTaxonomy' => 'contentseer_personas',
		);

		// If viewing a single post, include the associated personas.
		if ( is_single() ) {
			$post_id = get_the_ID();
			$terms   = get_the_terms( $post_id, 'contentseer_personas' );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$data['personas'] = wp_list_pluck( $terms, 'slug' ); // Get an array of term slugs.
			} else {
				$data['personas'] = array(); // No terms found.
			}
		}

		// Pass the data to the script.
		wp_localize_script( 'contentseer-script', 'contentSeerData', $data );
	}
}

new Front_End();
