jQuery( document ).ready(
	function ($) {
		// Handle analyze content button click
		$( '.analyze-content' ).on(
			'click',
			function () {
				var $button       = $( this );
				var postId        = $( '#post_ID' ).val();
				var title         = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' );
				var editedContent = wp.data.select( 'core/editor' ).getEditedPostContent();
				var personas      = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'contentseer_personas' );
				var persona       = (personas && personas.length > 0) ? personas[0] : '';

				$button.prop( 'disabled', true ).text( 'Analyzing...' );

				// Send to ContentSeer API
				$.ajax(
					{
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'contentseer_analyze',
							post_id: postId,
							title: title,
							content: editedContent,
							persona: persona,
							nonce: contentSeerAnalysis.nonce
						},
						success: function (response) {
							if (response.success) {
								updateAnalysisDisplay( response.data.analysis );
							} else {
								alert( 'Analysis failed. Please try again.' );
								$button.text( 'Analyze Content' );
							}
							$button.prop( 'disabled', false );
						},
						error: function () {
							alert( 'Analysis failed. Please try again.' );
							$button.prop( 'disabled', false ).text( 'Analyze Content' );
						}
					}
				);
			}
		);

		// Handle recommendations modal
		$( document ).on(
			'click',
			'.view-all-recommendations',
			function (e) {
				e.preventDefault();
				$( '#contentseer-recommendations-modal' ).fadeIn( 200 );
			}
		);

		$( document ).on(
			'click',
			'.close-modal, .contentseer-modal-overlay',
			function () {
				$( '#contentseer-recommendations-modal' ).fadeOut( 200 );
			}
		);

		$( document ).on(
			'keyup',
			function (e) {
				if (e.key === 'Escape') {
					$( '#contentseer-recommendations-modal' ).fadeOut( 200 );
				}
			}
		);

		// Function to update the analysis display
		function updateAnalysisDisplay(analysis) {
			var $container = $( '.contentseer-meta-box' );
			$container.empty();

			// Add overall score
			var scoreClass = analysis.overall_score >= 80 ? 'good' :
			analysis.overall_score >= 60 ? 'moderate' : 'poor';

			var scoreLabel = analysis.overall_score >= 80 ? 'Excellent' :
			analysis.overall_score >= 60 ? 'Good' : 'Needs Improvement';

			var scoreDescription = analysis.overall_score >= 80 ? 'Your content is performing well' :
			analysis.overall_score >= 60 ? 'Your content is good but could be improved' :
			'Your content needs significant improvements';

			var $overallScore = $( '<div class="overall-score">' )
			.append( $( '<h3>' ).text( 'Overall Score' ) )
			.append( $( '<div class="score-value ' + scoreClass + '">' ).text( analysis.overall_score ) )
			.append( $( '<p class="score-label">' ).text( scoreLabel ) )
			.append( $( '<p class="score-description">' ).text( scoreDescription ) );

			$container.append( $overallScore );

			// Create radar chart
			var $radarChart = $( '<canvas id="contentseer-radar-chart"></canvas>' );
			$container.append( $( '<div class="chart-container">' ).append( $radarChart ) );

			// Initialize radar chart
			var ctx = $radarChart[0].getContext( '2d' );
			new Chart(
				ctx,
				{
					type: 'radar',
					data: {
						labels: ['Readability', 'Sentiment', 'SEO', 'Engagement'],
						datasets: [{
							label: 'Content Scores',
							data: [
							analysis.readability_score,
							analysis.sentiment_score,
							analysis.seo_score,
							analysis.engagement_score
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
				}
			);

			// Add score breakdown
			var $scoreBreakdown = $( '<div class="score-breakdown">' ).append( '<h3>Score Breakdown</h3>' );
			var scores          = [
				{ label: 'Readability', value: analysis.readability_score, icon: 'dashicons-book', color: 'blue' },
				{ label: 'Sentiment', value: analysis.sentiment_score, icon: 'dashicons-heart', color: 'red' },
				{ label: 'SEO', value: analysis.seo_score, icon: 'dashicons-search', color: 'green' },
				{ label: 'Engagement', value: analysis.engagement_score, icon: 'dashicons-chart-line', color: 'purple' }
			];

			scores.forEach(
				function (score) {
					var scoreClass = score.value >= 80 ? 'good' :
					score.value >= 60 ? 'moderate' : 'poor';

					var $scoreItem = $( '<div class="score-item">' )
					.append(
						$( '<span class="label">' )
						.append( $( '<span>' ).addClass( 'dashicons ' + score.icon ) )
						.append( score.label )
					)
					.append(
						$( '<div class="progress-bar">' )
						.append(
							$( '<div class="progress ' + scoreClass + '">' ).css( 'width', score.value + '%' )
						)
						.append( $( '<span class="value">' ).text( score.value ) )
					);

					$scoreBreakdown.append( $scoreItem );
				}
			);

			$container.append( $scoreBreakdown );

			// Add recommendations
			if (analysis.analysis_json) {
				var $recommendations = $( '<div class="recommendations">' ).append( '<h3>Key Recommendations</h3>' );
				var categories       = [
					{ key: 'readability', label: 'Readability', icon: 'dashicons-book' },
					{ key: 'sentiment', label: 'Sentiment', icon: 'dashicons-heart' },
					{ key: 'seo', label: 'SEO', icon: 'dashicons-search' },
					{ key: 'engagement', label: 'Engagement', icon: 'dashicons-chart-line' }
				];

				categories.forEach(
					function (category) {
						var catData = analysis.analysis_json[category.key];
						if (catData && catData.recommendations && catData.recommendations.length > 0) {
							var $rec = $( '<div class="recommendation-item">' )
							.append(
								$( '<span class="category">' )
								.append( $( '<span>' ).addClass( 'dashicons ' + category.icon ) )
								.append( category.label )
							)
							.append( $( '<p>' ).text( catData.recommendations[0] ) );
							$recommendations.append( $rec );
						}
					}
				);

				// Add view all recommendations button
				$recommendations.append(
					$( '<button type="button" class="view-all-recommendations">' ).text( 'View all recommendations' )
				);

				$container.append( $recommendations );

				// Create recommendations modal
				var $modal = $( '<div id="contentseer-recommendations-modal" class="contentseer-modal">' )
					.append( $( '<div class="contentseer-modal-overlay">' ) )
					.append(
						$( '<div class="contentseer-modal-content">' )
						.append(
							$( '<div class="contentseer-modal-header">' )
								.append( $( '<h3>' ).text( 'All Recommendations' ) )
								.append(
									$( '<button type="button" class="close-modal">' )
										.append( $( '<span class="dashicons dashicons-no-alt">' ) )
								)
						)
						.append(
							$( '<div class="contentseer-modal-body">' ).append(
								categories.map(
									function (category) {
										var catData = analysis.analysis_json[category.key];
										if (catData && catData.recommendations) {
											return $( '<div class="recommendation-section">' )
											.append(
												$( '<div class="recommendation-section-header">' )
													.append( $( '<span>' ).addClass( 'dashicons ' + category.icon ) )
													.append( $( '<h4>' ).text( category.label ) )
											)
												.append(
													$( '<ul>' ).append(
														catData.recommendations.map(
															function (rec) {
																return $( '<li>' ).text( rec );
															}
														)
													)
												);
										}
										return null;
									}
								)
							)
						)
						.append(
							$( '<div class="contentseer-modal-footer">' )
								.append( $( '<button type="button" class="button close-modal">' ).text( 'Close' ) )
						)
					);

				$container.append( $modal );
			}

			// Add analyze button
			var $button = $( '<button type="button" class="button button-primary analyze-content">' ).text( 'Reanalyze Content' );
			$container.append( $button );
		}
	}
);