<?php
/**
 * ContentSeer Admin Persona Class
 *
 * Handles settings for the ContentSeer plugin.
 */

namespace ContentSeer;

class Admin_Persona {

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
		add_action( 'init', array( $this, 'register_personas_taxonomy' ) );
		// Add taxonomy field hooks
		add_action( 'contentseer_personas_add_form_fields', array( $this, 'add_persona_fields' ) );
		add_action( 'contentseer_personas_edit_form_fields', array( $this, 'edit_persona_fields' ) );
		add_action( 'created_contentseer_personas', array( $this, 'save_persona_fields' ) );
		add_action( 'edited_contentseer_personas', array( $this, 'save_persona_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_persona_scripts' ) );
		
		// Add generate personas section
		add_action( 'contentseer_personas_pre_add_form', array( $this, 'add_generate_personas_section' ) );
	}

	/**
	 * Register the custom taxonomy for selected post types.
	 */
	public function register_personas_taxonomy() {
		// Get the selected post types from the settings.
		$selected_post_types = get_option( 'contentseer_post_types', array() );

		// If no post types are selected, do nothing.
		if ( empty( $selected_post_types ) || ! is_array( $selected_post_types ) ) {
			return;
		}

		// Register the taxonomy for the selected post types.
		$args = array(
			'labels'            => array(
				'name'          => 'Personas',
				'singular_name' => 'Persona',
				'search_items'  => 'Search Personas',
				'all_items'     => 'All Personas',
				'edit_item'     => 'Edit Persona',
				'update_item'   => 'Update Persona',
				'add_new_item'  => 'Add New Persona',
				'new_item_name' => 'New Persona Name',
				'menu_name'     => 'Seer Personas',
			),
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'seer-persona' ),
		);

		register_taxonomy( 'contentseer_personas', $selected_post_types, $args );
	}

	/**
	 * Add generate personas section to the taxonomy page
	 */
	public function add_generate_personas_section() {
		$persona_generator = new Persona_Generator();
		$persona_generator->add_generate_button();
	}

	/**
	 * Add custom fields to the add persona form
	 */
	public function add_persona_fields() {
		?>
		<div class="form-field">
			<label for="persona_name"><?php esc_html_e( 'Name', 'contentseer' ); ?></label>
			<input type="text" name="persona_name" id="persona_name" value="" />
			<p><?php esc_html_e( 'The name of this persona (e.g., "Sarah Johnson")', 'contentseer' ); ?></p>
		</div>

		<div class="form-field">
			<label for="persona_background"><?php esc_html_e( 'Background', 'contentseer' ); ?></label>
			<textarea name="persona_background" id="persona_background" rows="5" cols="50"></textarea>
			<p><?php esc_html_e( 'Background information about this persona', 'contentseer' ); ?></p>
		</div>

		<div class="form-field">
			<label for="persona_pain_points"><?php esc_html_e( 'Pain Points', 'contentseer' ); ?></label>
			<div id="pain-points-container">
				<div class="pain-point-item">
					<input type="text" name="persona_pain_points[]" placeholder="Enter a pain point" />
					<button type="button" class="button remove-pain-point" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'contentseer' ); ?></button>
				</div>
			</div>
			<button type="button" id="add-pain-point" class="button"><?php esc_html_e( 'Add Pain Point', 'contentseer' ); ?></button>
			<p><?php esc_html_e( 'Key challenges and pain points this persona faces', 'contentseer' ); ?></p>
		</div>

		<div class="form-field">
			<label for="persona_goals"><?php esc_html_e( 'Goals', 'contentseer' ); ?></label>
			<textarea name="persona_goals" id="persona_goals" rows="3" cols="50"></textarea>
			<p><?php esc_html_e( 'Primary goals and objectives of this persona', 'contentseer' ); ?></p>
		</div>

		<div class="form-field">
			<label for="persona_motivations"><?php esc_html_e( 'Motivations', 'contentseer' ); ?></label>
			<textarea name="persona_motivations" id="persona_motivations" rows="3" cols="50"></textarea>
			<p><?php esc_html_e( 'What motivates and drives this persona', 'contentseer' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add custom fields to the edit persona form
	 */
	public function edit_persona_fields( $term ) {
		$persona_name        = get_term_meta( $term->term_id, 'persona_name', true );
		$persona_background  = get_term_meta( $term->term_id, 'persona_background', true );
		$persona_pain_points = get_term_meta( $term->term_id, 'persona_pain_points', true );
		$persona_goals       = get_term_meta( $term->term_id, 'persona_goals', true );
		$persona_motivations = get_term_meta( $term->term_id, 'persona_motivations', true );

		// Ensure pain points is an array
		if ( ! is_array( $persona_pain_points ) ) {
			$persona_pain_points = ! empty( $persona_pain_points ) ? array( $persona_pain_points ) : array( '' );
		}
		?>
		<tr class="form-field">
			<th scope="row">
				<label for="persona_name"><?php esc_html_e( 'Name', 'contentseer' ); ?></label>
			</th>
			<td>
				<input type="text" name="persona_name" id="persona_name" value="<?php echo esc_attr( $persona_name ); ?>" />
				<p class="description"><?php esc_html_e( 'The name of this persona (e.g., "Sarah Johnson")', 'contentseer' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="persona_background"><?php esc_html_e( 'Background', 'contentseer' ); ?></label>
			</th>
			<td>
				<textarea name="persona_background" id="persona_background" rows="5" cols="50"><?php echo esc_textarea( $persona_background ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Background information about this persona', 'contentseer' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="persona_pain_points"><?php esc_html_e( 'Pain Points', 'contentseer' ); ?></label>
			</th>
			<td>
				<div id="pain-points-container">
					<?php foreach ( $persona_pain_points as $index => $pain_point ) : ?>
						<div class="pain-point-item" style="margin-bottom: 10px;">
							<input type="text" name="persona_pain_points[]" value="<?php echo esc_attr( $pain_point ); ?>" placeholder="Enter a pain point" style="width: 300px;" />
							<button type="button" class="button remove-pain-point" style="margin-left: 10px;"><?php esc_html_e( 'Remove', 'contentseer' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" id="add-pain-point" class="button"><?php esc_html_e( 'Add Pain Point', 'contentseer' ); ?></button>
				<p class="description"><?php esc_html_e( 'Key challenges and pain points this persona faces', 'contentseer' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="persona_goals"><?php esc_html_e( 'Goals', 'contentseer' ); ?></label>
			</th>
			<td>
				<textarea name="persona_goals" id="persona_goals" rows="3" cols="50"><?php echo esc_textarea( $persona_goals ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Primary goals and objectives of this persona', 'contentseer' ); ?></p>
			</td>
		</tr>

		<tr class="form-field">
			<th scope="row">
				<label for="persona_motivations"><?php esc_html_e( 'Motivations', 'contentseer' ); ?></label>
			</th>
			<td>
				<textarea name="persona_motivations" id="persona_motivations" rows="3" cols="50"><?php echo esc_textarea( $persona_motivations ); ?></textarea>
				<p class="description"><?php esc_html_e( 'What motivates and drives this persona', 'contentseer' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save custom persona fields
	 */
	public function save_persona_fields( $term_id ) {
		// Verify nonce for security
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-tag_' . $term_id ) ) {
			return;
		}

		// Save persona name
		if ( isset( $_POST['persona_name'] ) ) {
			update_term_meta( $term_id, 'persona_name', sanitize_text_field( $_POST['persona_name'] ) );
		}

		// Save persona background
		if ( isset( $_POST['persona_background'] ) ) {
			update_term_meta( $term_id, 'persona_background', sanitize_textarea_field( $_POST['persona_background'] ) );
		}

		// Save pain points (array)
		if ( isset( $_POST['persona_pain_points'] ) && is_array( $_POST['persona_pain_points'] ) ) {
			$pain_points = array_filter( array_map( 'sanitize_text_field', $_POST['persona_pain_points'] ) );
			update_term_meta( $term_id, 'persona_pain_points', $pain_points );
		}

		// Save persona goals
		if ( isset( $_POST['persona_goals'] ) ) {
			update_term_meta( $term_id, 'persona_goals', sanitize_textarea_field( $_POST['persona_goals'] ) );
		}

		// Save persona motivations
		if ( isset( $_POST['persona_motivations'] ) ) {
			update_term_meta( $term_id, 'persona_motivations', sanitize_textarea_field( $_POST['persona_motivations'] ) );
		}
	}

	/**
	 * Enqueue scripts for persona fields
	 */
	public function enqueue_persona_scripts( $hook ) {
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
			'contentseer-persona-fields',
			CONTENTSEER_URL . 'assets/js/persona-fields.js',
			array( 'jquery' ),
			CONTENTSEER_VERSION,
			true
		);
	}
}

new Admin_Persona();