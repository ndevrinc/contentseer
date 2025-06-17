<?php
/**
 * ContentSeer Admin Dashboard Class
 *
 * Updates to the WordPress admin dashboard.
 */

namespace ContentSeer;

class Admin_Dashboard {

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
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Add a custom dashboard widget.
	 */
	public function add_dashboard_widget() {
		// Check if any features are enabled
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );
		$create_enabled  = get_option( 'contentseer_enable_create_feature', true );

		// Only add widget if at least one feature is enabled
		if ( ! $analyze_enabled && ! $create_enabled ) {
			return;
		}

		wp_add_dashboard_widget(
			'contentseer_dashboard_widget', // Widget ID
			'ContentSeer Insights', // Widget title
			array( $this, 'render_dashboard_widget' ) // Callback function
		);
	}

	/**
	 * Render the custom dashboard widget.
	 */
	public function render_dashboard_widget() {
		// Check which features are enabled
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );
		$create_enabled  = get_option( 'contentseer_enable_create_feature', true );

		// Get recent content analysis data
		global $wpdb;

		// Get total content count
		$total_content = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;

		// Get analyzed content count
		$analyzed_content = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);

		// Get average score
		$scores = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);

		$total_score = 0;
		$score_count = 0;

		foreach ( $scores as $score ) {
			$analysis = maybe_unserialize( $score );
			if ( isset( $analysis['overall_score'] ) ) {
				$total_score += $analysis['overall_score'];
				++$score_count;
			}
		}

		$average_score = $score_count > 0 ? round( $total_score / $score_count, 1 ) : 0;

		// Get recent analyzed content
		$recent_content = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_date, pm.meta_value
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE pm.meta_key = '_contentseer_analysis'
			 AND p.post_status IN ('publish', 'draft')
			 ORDER BY p.post_date DESC
			 LIMIT 5"
		);
		?>
		<div class="contentseer-dashboard-widget">
			<?php if ( $analyze_enabled ) : ?>
				<div class="contentseer-stats-overview" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
					<div class="stat-item" style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 6px;">
						<div style="font-size: 24px; font-weight: bold; color: #1f2937;"><?php echo esc_html( $total_content ); ?></div>
						<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Total Content</div>
					</div>
					<div class="stat-item" style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 6px;">
						<div style="font-size: 24px; font-weight: bold; color: #1f2937;"><?php echo esc_html( $analyzed_content ? $analyzed_content : 0 ); ?></div>
						<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Analyzed</div>
					</div>
					<div class="stat-item" style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 6px;">
						<div style="font-size: 24px; font-weight: bold; color: #1f2937;"><?php echo esc_html( $average_score ); ?></div>
						<div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Avg Score</div>
					</div>
				</div>
				
				<?php if ( ! empty( $recent_content ) ) : ?>
					<h4 style="margin: 0 0 10px; font-size: 14px; color: #374151;">Recent Analysis</h4>
					<div class="recent-content-list">
						<?php
						foreach ( $recent_content as $content ) :
							$analysis    = maybe_unserialize( $content->meta_value );
							$score       = isset( $analysis['overall_score'] ) ? $analysis['overall_score'] : 0;
							$score_class = $score >= 80 ? 'good' : ( $score >= 60 ? 'moderate' : 'poor' );
							$score_color = $score >= 80 ? '#22c55e' : ( $score >= 60 ? '#f59e0b' : '#ef4444' );
							?>
							<div style="display: flex; justify-content: between; align-items: center; padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
								<div style="flex: 1; min-width: 0;">
									<div style="font-size: 13px; font-weight: 500; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
										<a href="<?php echo esc_url( get_edit_post_link( $content->ID ) ); ?>" style="text-decoration: none; color: inherit;">
											<?php echo esc_html( $content->post_title ); ?>
										</a>
									</div>
									<div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
										<?php echo esc_html( gmdate( 'M j, Y', strtotime( $content->post_date ) ) ); ?>
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
					<div style="text-align: center; padding: 20px; color: #6b7280;">
						<p style="margin: 0; font-size: 14px;">No content analyzed yet.</p>
						<p style="margin: 5px 0 0; font-size: 12px;">Start analyzing your content to see insights here.</p>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<div style="text-align: center; padding: 20px; color: #6b7280;">
					<p style="margin: 0; font-size: 14px;">Analysis feature is disabled.</p>
					<p style="margin: 5px 0 0; font-size: 12px;">Enable it in settings to see content insights.</p>
				</div>
			<?php endif; ?>
			
			<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e5e7eb; text-align: center;">
				<?php if ( $create_enabled ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-create' ) ); ?>" class="button button-primary" style="margin-right: 10px;">
						<?php esc_html_e( 'Create Content', 'contentseer' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $analyze_enabled ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View Analysis', 'contentseer' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( ! $analyze_enabled && ! $create_enabled ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Enable Features', 'contentseer' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

new Admin_Dashboard();