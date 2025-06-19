<?php
/**
 * ContentSeer Admin Analysis Class
 *
 * Handles the analysis dashboard in WordPress admin.
 */

namespace ContentSeer;

class Admin_Analysis {
	/**
	 * Constructor to initialize the admin analysis.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Enqueue required scripts and styles
	 */
	public function enqueue_scripts( $hook ) {
		wp_enqueue_style(
			'contentseer-analysis',
			CONTENTSEER_URL . 'assets/css/admin.css',
			array(),
			CONTENTSEER_VERSION
		);

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js',
			array( 'jquery' ),
			'3.7.0',
			true
		);

		wp_enqueue_script(
			'contentseer-analysis',
			CONTENTSEER_URL . 'assets/js/analysis.js',
			array( 'jquery', 'chartjs' ),
			CONTENTSEER_VERSION,
			true
		);

		// Localize script with initial analysis data
		global $post;
		$analysis = null;
		if ( $post ) {
			$analysis = get_post_meta( $post->ID, '_contentseer_analysis', true );
		}

		wp_localize_script(
			'contentseer-analysis',
			'contentSeerAnalysis',
			array(
				'nonce'   => wp_create_nonce( 'contentseer-analysis' ),
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'data'    => $analysis ? $analysis : null,
			)
		);
	}

	/**
	 * Render the analysis page
	 */
	public function render_analysis_page() {
		// Check if analyze feature is enabled
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );

		if ( ! $analyze_enabled ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'ContentSeer Analysis', 'contentseer' ); ?></h1>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Analysis feature is disabled', 'contentseer' ); ?></strong><br>
						<?php esc_html_e( 'The analysis feature has been disabled in the settings. Please enable it to use this functionality.', 'contentseer' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=contentseer-settings' ) ); ?>" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e( 'Enable in Settings', 'contentseer' ); ?>
						</a>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		// Get analysis data
		$total_content     = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;
		$analyzed_content  = $this->get_analyzed_content_count();
		$average_score     = $this->get_average_overall_score();
		$needs_improvement = $this->get_content_needing_improvement();

		// Get chart data
		$score_distribution = $this->get_score_distribution();
		$score_trends       = $this->get_score_trends();
		?>
		<div class="wrap">
			<h1>ContentSeer Analysis</h1>
			
			<!-- Stats Grid -->
			<div class="contentseer-stats-grid">
				<div class="contentseer-stat-card">
					<div class="stat-icon">
						<span class="dashicons dashicons-media-text"></span>
					</div>
					<div class="stat-content">
						<h3>Total Content</h3>
						<div class="stat-value"><?php echo esc_html( $total_content ); ?></div>
					</div>
				</div>

				<div class="contentseer-stat-card">
					<div class="stat-icon">
						<span class="dashicons dashicons-chart-bar"></span>
					</div>
					<div class="stat-content">
						<h3>Analyzed Content</h3>
						<div class="stat-value"><?php echo esc_html( $analyzed_content ); ?></div>
					</div>
				</div>

				<div class="contentseer-stat-card">
					<div class="stat-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="stat-content">
						<h3>Average Score</h3>
						<div class="stat-value"><?php echo esc_html( number_format( $average_score, 1 ) ); ?></div>
					</div>
				</div>

				<div class="contentseer-stat-card">
					<div class="stat-icon">
						<span class="dashicons dashicons-warning"></span>
					</div>
					<div class="stat-content">
						<h3>Needs Improvement</h3>
						<div class="stat-value"><?php echo esc_html( $needs_improvement ); ?></div>
					</div>
				</div>
			</div>

			<!-- Charts Grid -->
			<div class="contentseer-charts-grid">
				<div class="contentseer-chart-card">
					<h3>Score Distribution</h3>
					<div class="chart-container">
						<canvas id="scoreDistributionChart"></canvas>
					</div>
				</div>

				<div class="contentseer-chart-card">
					<h3>Score Trends</h3>
					<div class="chart-container">
						<canvas id="scoreTrendsChart"></canvas>
					</div>
				</div>
			</div>

			<!-- Recent Content Table -->
			<div class="contentseer-table-card">
				<h3>Recent Content Analysis</h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Title</th>
							<th>Status</th>
							<th>Date</th>
							<th>Overall Score</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php
						$recent_content = $this->get_recent_analyzed_content();
						foreach ( $recent_content as $content ) {
							$score_class = $content['overall_score'] >= 80 ? 'good' :
										( $content['overall_score'] >= 60 ? 'moderate' : 'poor' );
							?>
							<tr>
								<td><?php echo esc_html( $content['title'] ); ?></td>
								<td>
									<span class="content-status <?php echo esc_attr( $content['status'] ); ?>">
										<?php echo esc_html( ucfirst( $content['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( gmdate( 'M d, Y', strtotime( $content['date'] ) ) ); ?></td>
								<td>
									<span class="score-badge <?php echo esc_attr( $score_class ); ?>">
										<?php echo esc_html( $content['overall_score'] ); ?>
									</span>
								</td>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $content['id'] ) ); ?>" class="button button-small">
										View
									</a>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Initialize Score Distribution Chart
			var scoreDistCtx = document.getElementById('scoreDistributionChart');
			if (scoreDistCtx) {
				new Chart(scoreDistCtx, {
					type: 'bar',
					data: {
						labels: <?php echo json_encode( array_column( $score_distribution, 'range' ) ); ?>,
						datasets: [{
							label: 'Number of Content',
							data: <?php echo json_encode( array_column( $score_distribution, 'count' ) ); ?>,
							backgroundColor: '#3b82f6',
							borderColor: '#2563eb',
							borderWidth: 1
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								ticks: {
									stepSize: 1
								}
							}
						},
						plugins: {
							legend: {
								display: false
							}
						}
					}
				});
			}

			// Initialize Score Trends Chart
			var scoreTrendsCtx = document.getElementById('scoreTrendsChart');
			if (scoreTrendsCtx) {
				new Chart(scoreTrendsCtx, {
					type: 'line',
					data: {
						labels: <?php echo json_encode( array_column( $score_trends, 'date' ) ); ?>,
						datasets: [
							{
								label: 'Readability',
								data: <?php echo json_encode( array_column( $score_trends, 'readability' ) ); ?>,
								borderColor: '#3b82f6',
								backgroundColor: 'rgba(59, 130, 246, 0.1)',
								tension: 0.4
							},
							{
								label: 'Sentiment',
								data: <?php echo json_encode( array_column( $score_trends, 'sentiment' ) ); ?>,
								borderColor: '#10b981',
								backgroundColor: 'rgba(16, 185, 129, 0.1)',
								tension: 0.4
							},
							{
								label: 'SEO',
								data: <?php echo json_encode( array_column( $score_trends, 'seo' ) ); ?>,
								borderColor: '#f59e0b',
								backgroundColor: 'rgba(245, 158, 11, 0.1)',
								tension: 0.4
							},
							{
								label: 'Engagement',
								data: <?php echo json_encode( array_column( $score_trends, 'engagement' ) ); ?>,
								borderColor: '#8b5cf6',
								backgroundColor: 'rgba(139, 92, 246, 0.1)',
								tension: 0.4
							}
						]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						scales: {
							y: {
								beginAtZero: true,
								max: 100
							}
						},
						plugins: {
							legend: {
								position: 'bottom'
							}
						}
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Get count of analyzed content
	 */
	private function get_analyzed_content_count() {
		global $wpdb;
		$count = $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);
		return (int) $count;
	}

	/**
	 * Get average overall score
	 */
	private function get_average_overall_score() {
		global $wpdb;
		$scores = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);

		$total = 0;
		$count = 0;

		foreach ( $scores as $score ) {
			$analysis = maybe_unserialize( $score );
			if ( isset( $analysis['overall_score'] ) ) {
				$total += $analysis['overall_score'];
				++$count;
			}
		}

		return $count > 0 ? $total / $count : 0;
	}

	/**
	 * Get count of content needing improvement
	 */
	private function get_content_needing_improvement() {
		global $wpdb;
		$scores = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);

		$count = 0;
		foreach ( $scores as $score ) {
			$analysis = maybe_unserialize( $score );
			if ( isset( $analysis['overall_score'] ) && $analysis['overall_score'] < 70 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get score distribution data for chart
	 */
	private function get_score_distribution() {
		global $wpdb;
		$scores = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE meta_key = '_contentseer_analysis'"
		);

		$distribution = array(
			array(
				'range' => '0-20',
				'count' => 0,
			),
			array(
				'range' => '21-40',
				'count' => 0,
			),
			array(
				'range' => '41-60',
				'count' => 0,
			),
			array(
				'range' => '61-80',
				'count' => 0,
			),
			array(
				'range' => '81-100',
				'count' => 0,
			),
		);

		foreach ( $scores as $score ) {
			$analysis = maybe_unserialize( $score );
			if ( isset( $analysis['overall_score'] ) ) {
				$score_value = $analysis['overall_score'];
				if ( $score_value <= 20 ) {
					++$distribution[0]['count'];
				} elseif ( $score_value <= 40 ) {
					++$distribution[1]['count'];
				} elseif ( $score_value <= 60 ) {
					++$distribution[2]['count'];
				} elseif ( $score_value <= 80 ) {
					++$distribution[3]['count'];
				} else {
					++$distribution[4]['count'];
				}
			}
		}

		return $distribution;
	}

	/**
	 * Get score trends data for chart based on current content snapshot
	 */
	private function get_score_trends() {
		global $wpdb;

		// Get all analyzed content with dates
		$results = $wpdb->get_results(
			"SELECT p.post_date, pm.meta_value as analysis
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_contentseer_analysis'
             AND p.post_status IN ('publish', 'draft')
             ORDER BY p.post_date DESC"
		);

		// Group content by month and calculate averages
		$monthly_data = array();

		foreach ( $results as $result ) {
			$analysis = maybe_unserialize( $result->analysis );
			if ( isset( $analysis['readability_score'] ) ) {
				$month = gmdate( 'M Y', strtotime( $result->post_date ) );

				if ( ! isset( $monthly_data[ $month ] ) ) {
					$monthly_data[ $month ] = array(
						'readability' => array(),
						'sentiment'   => array(),
						'seo'         => array(),
						'engagement'  => array(),
					);
				}

				$monthly_data[ $month ]['readability'][] = $analysis['readability_score'];
				$monthly_data[ $month ]['sentiment'][]   = $analysis['sentiment_score'];
				$monthly_data[ $month ]['seo'][]         = $analysis['seo_score'];
				$monthly_data[ $month ]['engagement'][]  = $analysis['engagement_score'];
			}
		}

		// Calculate averages and prepare chart data
		$trends = array();
		$months = array_keys( $monthly_data );

		// Limit to last 6 months of data
		$months = array_slice( $months, 0, 6 );

		foreach ( $months as $month ) {
			$data     = $monthly_data[ $month ];
			$trends[] = array(
				'date'        => $month,
				'readability' => count( $data['readability'] ) > 0 ? round( array_sum( $data['readability'] ) / count( $data['readability'] ) ) : 0,
				'sentiment'   => count( $data['sentiment'] ) > 0 ? round( array_sum( $data['sentiment'] ) / count( $data['sentiment'] ) ) : 0,
				'seo'         => count( $data['seo'] ) > 0 ? round( array_sum( $data['seo'] ) / count( $data['seo'] ) ) : 0,
				'engagement'  => count( $data['engagement'] ) > 0 ? round( array_sum( $data['engagement'] ) / count( $data['engagement'] ) ) : 0,
			);
		}

		// If no data available, return sample data
		if ( empty( $trends ) ) {
			$sample_months = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun' );
			foreach ( $sample_months as $month ) {
				$trends[] = array(
					'date'        => $month,
					'readability' => rand( 70, 90 ),
					'sentiment'   => rand( 65, 85 ),
					'seo'         => rand( 75, 95 ),
					'engagement'  => rand( 60, 80 ),
				);
			}
		}

		return array_reverse( $trends ); // Show oldest to newest
	}

	/**
	 * Get recent analyzed content
	 */
	private function get_recent_analyzed_content() {
		global $wpdb;
		$results = $wpdb->get_results(
			"SELECT p.ID, p.post_title, p.post_status, p.post_date, pm.meta_value
             FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE pm.meta_key = '_contentseer_analysis'
             AND p.post_status IN ('publish', 'draft')
             ORDER BY p.post_date DESC
             LIMIT 10"
		);

		$content = array();
		foreach ( $results as $result ) {
			$analysis = maybe_unserialize( $result->meta_value );
			if ( isset( $analysis['overall_score'] ) ) {
				$content[] = array(
					'id'            => $result->ID,
					'title'         => $result->post_title,
					'status'        => $result->post_status,
					'date'          => $result->post_date,
					'overall_score' => $analysis['overall_score'],
				);
			}
		}

		return $content;
	}

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		// Check if analyze feature is enabled
		$analyze_enabled = get_option( 'contentseer_enable_analyze_feature', true );

		if ( ! $analyze_enabled ) {
			return;
		}

		add_meta_box(
			'contentseer-analysis',
			__( 'Content Analysis', 'contentseer' ),
			array( $this, 'render_analysis_meta_box' ),
			array( 'post', 'page' ),
			'side',
			'high'
		);
	}

	/**
	 * Render analysis meta box
	 */
	public function render_analysis_meta_box( $post ) {
		$analysis = get_post_meta( $post->ID, '_contentseer_analysis', true );
		?>
		<div class="contentseer-meta-box">
			<?php if ( $analysis ) : ?>
				<div class="overall-score">
					<h3><?php esc_html_e( 'Overall Score', 'contentseer' ); ?></h3>
					<div class="score-value <?php echo esc_attr( $analysis['overall_score'] >= 80 ? 'good' : ( $analysis['overall_score'] >= 60 ? 'moderate' : 'poor' ) ); ?>">
						<?php echo esc_html( $analysis['overall_score'] ); ?>
					</div>
					<p class="score-label">
						<?php
						if ( $analysis['overall_score'] >= 80 ) {
							esc_html_e( 'Excellent', 'contentseer' );
						} elseif ( $analysis['overall_score'] >= 60 ) {
							esc_html_e( 'Good', 'contentseer' );
						} else {
							esc_html_e( 'Needs Improvement', 'contentseer' );
						}
						?>
					</p>
				</div>

				<div class="chart-container">
					<canvas id="contentseer-radar-chart"></canvas>
				</div>

				<div class="score-breakdown">
					<h3><?php esc_html_e( 'Score Breakdown', 'contentseer' ); ?></h3>
					<?php
					$scores = array(
						'readability' => array(
							'label' => __( 'Readability', 'contentseer' ),
							'value' => $analysis['readability_score'],
							'icon'  => 'dashicons-book',
						),
						'sentiment'   => array(
							'label' => __( 'Sentiment', 'contentseer' ),
							'value' => $analysis['sentiment_score'],
							'icon'  => 'dashicons-heart',
						),
						'seo'         => array(
							'label' => __( 'SEO', 'contentseer' ),
							'value' => $analysis['seo_score'],
							'icon'  => 'dashicons-search',
						),
						'engagement'  => array(
							'label' => __( 'Engagement', 'contentseer' ),
							'value' => $analysis['engagement_score'],
							'icon'  => 'dashicons-chart-line',
						),
					);

					foreach ( $scores as $key => $score ) :
						$score_class = $score['value'] >= 80 ? 'good' : ( $score['value'] >= 60 ? 'moderate' : 'poor' );
						?>
						<div class="score-item">
							<span class="label">
								<span class="dashicons <?php echo esc_attr( $score['icon'] ); ?>"></span>
								<?php echo esc_html( $score['label'] ); ?>
							</span>
							<div class="progress-bar">
								<div class="progress <?php echo esc_attr( $score_class ); ?>" style="width: <?php echo esc_attr( $score['value'] ); ?>%"></div>
								<span class="value"><?php echo esc_html( $score['value'] ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( isset( $analysis['analysis_json'] ) && is_array( $analysis['analysis_json'] ) ) : ?>
					<div class="recommendations">
						<h3><?php esc_html_e( 'Key Recommendations', 'contentseer' ); ?></h3>
						<?php
						$categories = array( 'readability', 'sentiment', 'seo', 'engagement' );
						foreach ( $categories as $category ) :
							if ( isset( $analysis['analysis_json'][ $category ]['recommendations'][0] ) ) :
								?>
							<div class="recommendation-item">
								<span class="category">
									<span class="dashicons <?php echo esc_attr( $scores[ $category ]['icon'] ); ?>"></span>
									<?php echo esc_html( ucfirst( $category ) ); ?>
								</span>
								<p><?php echo esc_html( $analysis['analysis_json'][ $category ]['recommendations'][0] ); ?></p>
							</div>
								<?php
							endif;
						endforeach;
						?>
						<button type="button" class="button button-link view-all-recommendations">
							<?php esc_html_e( 'View all recommendations', 'contentseer' ); ?>
						</button>
					</div>

					<!-- Enhanced Recommendations Modal -->
					<div id="contentseer-recommendations-modal" class="contentseer-modal" style="display: none;">
						<div class="contentseer-modal-overlay"></div>
						<div class="contentseer-modal-content" style="max-width: 1200px;">
							<div class="contentseer-modal-header">
								<h3><?php esc_html_e( 'Detailed Content Analysis', 'contentseer' ); ?></h3>
								<button type="button" class="close-modal">
									<span class="dashicons dashicons-no-alt"></span>
								</button>
							</div>
							<div class="contentseer-modal-body">
								<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 20px;">
									
									<!-- Readability Analysis -->
									<div style="background: #f0f9ff; border-radius: 8px; padding: 20px;">
										<div style="display: flex; align-items: center; margin-bottom: 16px;">
											<span class="dashicons dashicons-book" style="color: #3b82f6; font-size: 20px; margin-right: 8px;"></span>
											<h4 style="margin: 0; font-size: 16px; color: #1f2937;"><?php esc_html_e( 'Readability Analysis', 'contentseer' ); ?></h4>
										</div>
										
										<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Grade Level', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['readability']['grade_level'] ?? 'College Level' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Avg Sentence Length', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['readability']['avg_sentence_length'] ?? 18 ) . ' words' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Complex Words', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['readability']['complex_words_percentage'] ?? 15 ) . '%' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Flesch-Kincaid', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['readability']['flesch_kincaid_score'] ?? 72 ); ?>
												</p>
											</div>
										</div>

										<div>
											<h5 style="margin: 0 0 12px; font-weight: 600; color: #1f2937;"><?php esc_html_e( 'Recommendations', 'contentseer' ); ?></h5>
											<ul style="margin: 0; padding-left: 0; list-style: none;">
												<?php foreach ( $analysis['analysis_json']['readability']['recommendations'] as $rec ) : ?>
													<li style="margin-bottom: 8px; font-size: 13px; color: #374151; display: flex; align-items: flex-start;">
														<span style="display: inline-block; width: 6px; height: 6px; background: #3b82f6; border-radius: 50%; margin-top: 6px; margin-right: 8px; flex-shrink: 0;"></span>
														<?php echo esc_html( $rec ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>

									<!-- Sentiment Analysis -->
									<div style="background: #fef2f2; border-radius: 8px; padding: 20px;">
										<div style="display: flex; align-items: center; margin-bottom: 16px;">
											<span class="dashicons dashicons-heart" style="color: #ef4444; font-size: 20px; margin-right: 8px;"></span>
											<h4 style="margin: 0; font-size: 16px; color: #1f2937;"><?php esc_html_e( 'Sentiment Analysis', 'contentseer' ); ?></h4>
										</div>
										
										<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Overall Tone', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['sentiment']['overall_tone'] ?? 'Professional' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Dominant Emotion', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['sentiment']['dominant_emotion'] ?? 'Confidence' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Positive', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['sentiment']['positive_percentage'] ?? 65 ) . '%' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Intensity', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['sentiment']['emotional_intensity'] ?? 'Moderate' ); ?>
												</p>
											</div>
										</div>

										<div>
											<h5 style="margin: 0 0 12px; font-weight: 600; color: #1f2937;"><?php esc_html_e( 'Recommendations', 'contentseer' ); ?></h5>
											<ul style="margin: 0; padding-left: 0; list-style: none;">
												<?php foreach ( $analysis['analysis_json']['sentiment']['recommendations'] as $rec ) : ?>
													<li style="margin-bottom: 8px; font-size: 13px; color: #374151; display: flex; align-items: flex-start;">
														<span style="display: inline-block; width: 6px; height: 6px; background: #ef4444; border-radius: 50%; margin-top: 6px; margin-right: 8px; flex-shrink: 0;"></span>
														<?php echo esc_html( $rec ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>

									<!-- SEO Analysis -->
									<div style="background: #f0fdf4; border-radius: 8px; padding: 20px;">
										<div style="display: flex; align-items: center; margin-bottom: 16px;">
											<span class="dashicons dashicons-search" style="color: #22c55e; font-size: 20px; margin-right: 8px;"></span>
											<h4 style="margin: 0; font-size: 16px; color: #1f2937;"><?php esc_html_e( 'SEO Analysis', 'contentseer' ); ?></h4>
										</div>
										
										<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Keyword Density', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['seo']['keyword_density'] ?? '1.2' ) . '%' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Internal Links', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( $analysis['analysis_json']['seo']['internal_links_count'] ?? 5 ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Meta Title Length', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['seo']['meta_title_length'] ?? 58 ) . ' chars' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Heading Structure', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['seo']['heading_structure_score'] ?? 85 ) . '/100' ); ?>
												</p>
											</div>
										</div>

										<?php if ( isset( $analysis['analysis_json']['seo']['keyword_suggestions'] ) ) : ?>
											<div style="margin-bottom: 20px;">
												<h5 style="margin: 0 0 12px; font-weight: 600; color: #1f2937;"><?php esc_html_e( 'Keyword Suggestions', 'contentseer' ); ?></h5>
												<div style="display: flex; flex-wrap: wrap; gap: 8px;">
													<?php foreach ( $analysis['analysis_json']['seo']['keyword_suggestions'] as $keyword ) : ?>
														<span style="display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; background: #dcfce7; color: #166534;">
															<?php echo esc_html( $keyword ); ?>
														</span>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endif; ?>

										<div>
											<h5 style="margin: 0 0 12px; font-weight: 600; color: #1f2937;"><?php esc_html_e( 'Recommendations', 'contentseer' ); ?></h5>
											<ul style="margin: 0; padding-left: 0; list-style: none;">
												<?php foreach ( $analysis['analysis_json']['seo']['recommendations'] as $rec ) : ?>
													<li style="margin-bottom: 8px; font-size: 13px; color: #374151; display: flex; align-items: flex-start;">
														<span style="display: inline-block; width: 6px; height: 6px; background: #22c55e; border-radius: 50%; margin-top: 6px; margin-right: 8px; flex-shrink: 0;"></span>
														<?php echo esc_html( $rec ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>

									<!-- Engagement Analysis -->
									<div style="background: #faf5ff; border-radius: 8px; padding: 20px;">
										<div style="display: flex; align-items: center; margin-bottom: 16px;">
											<span class="dashicons dashicons-chart-line" style="color: #8b5cf6; font-size: 20px; margin-right: 8px;"></span>
											<h4 style="margin: 0; font-size: 16px; color: #1f2937;"><?php esc_html_e( 'Engagement Analysis', 'contentseer' ); ?></h4>
										</div>
										
										<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px;">
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'CTA Effectiveness', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['engagement']['cta_effectiveness'] ?? '65' ) . '/100' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Read Time', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['engagement']['estimated_read_time'] ?? 6 ) . ' min' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Share Potential', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['engagement']['social_share_potential'] ?? '65' ) . '/100' ); ?>
												</p>
											</div>
											<div style="background: white; border-radius: 6px; padding: 12px;">
												<p style="margin: 0; font-size: 12px; color: #6b7280;"><?php esc_html_e( 'Multimedia Score', 'contentseer' ); ?></p>
												<p style="margin: 4px 0 0; font-weight: 600; color: #1f2937;">
													<?php echo esc_html( ( $analysis['analysis_json']['engagement']['multimedia_score'] ?? 75 ) . '/100' ); ?>
												</p>
											</div>
										</div>

										<div>
											<h5 style="margin: 0 0 12px; font-weight: 600; color: #1f2937;"><?php esc_html_e( 'Recommendations', 'contentseer' ); ?></h5>
											<ul style="margin: 0; padding-left: 0; list-style: none;">
												<?php foreach ( $analysis['analysis_json']['engagement']['recommendations'] as $rec ) : ?>
													<li style="margin-bottom: 8px; font-size: 13px; color: #374151; display: flex; align-items: flex-start;">
														<span style="display: inline-block; width: 6px; height: 6px; background: #8b5cf6; border-radius: 50%; margin-top: 6px; margin-right: 8px; flex-shrink: 0;"></span>
														<?php echo esc_html( $rec ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</div>
								</div>
							</div>
							<div class="contentseer-modal-footer">
								<button type="button" class="button close-modal">
									<?php esc_html_e( 'Close', 'contentseer' ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<button type="button" class="button button-primary analyze-content contentseer-btn-primary">
					<?php esc_html_e( 'Reanalyze Content', 'contentseer' ); ?>
				</button>
			<?php else : ?>
				<p><?php esc_html_e( 'No analysis available yet.', 'contentseer' ); ?></p>
				<button type="button" class="button button-primary analyze-content contentseer-btn-primary">
					<?php esc_html_e( 'Analyze Content', 'contentseer' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php

		if ( $analysis ) {
			?>
			<script>
			jQuery(document).ready(function($) {
				var ctx = document.getElementById('contentseer-radar-chart');
				if (ctx) {
					new Chart(ctx, {
						type: 'radar',
						data: {
							labels: ['Readability', 'Sentiment', 'SEO', 'Engagement'],
							datasets: [{
								label: 'Content Scores',
								data: [
									<?php echo esc_js( $analysis['readability_score'] ); ?>,
									<?php echo esc_js( $analysis['sentiment_score'] ); ?>,
									<?php echo esc_js( $analysis['seo_score'] ); ?>,
									<?php echo esc_js( $analysis['engagement_score'] ); ?>
								],
								backgroundColor: 'rgba(59, 130, 246, 0.2)',
								borderColor: 'rgb(59, 130, 246)',
								pointBackgroundColor: 'rgb(59, 130, 246)',
								pointBorderColor: '#fff',
								pointHoverBackgroundColor: '#fff',
								pointHoverBorderColor: 'rgb(59, 130, 246)'
							}]
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								r: {
									beginAtZero: true,
									max: 100,
									ticks: {
										stepSize: 20
									}
								}
							},
							plugins: {
								legend: {
									display: false
								}
							}
						}
					});
				}

				// Modal functionality
				$('.view-all-recommendations').on('click', function() {
					$('#contentseer-recommendations-modal').fadeIn(200);
				});

				$('.close-modal, .contentseer-modal-overlay').on('click', function() {
					$('#contentseer-recommendations-modal').fadeOut(200);
				});

				// Close modal on escape key
				$(document).keyup(function(e) {
					if (e.key === "Escape") {
						$('#contentseer-recommendations-modal').fadeOut(200);
					}
				});
			});
			</script>
			<?php
		}
	}
}

new Admin_Analysis();
