jQuery( document ).ready(
	function ($) {
		// Handle generate content button click
		$( '.generate-content' ).on(
			'click',
			function () {
				var $button = $( this );

				// Get topic from either dropdown or text input
				var selectedTopic = $( '#contentseer_topic_select' ).val();
				var customTopic   = $( '#contentseer_topic' ).val();
				var topic         = selectedTopic || customTopic;

				var persona   = $( '#contentseer_persona' ).val();
				var blogTitle = $( '#contentseer_blog_title' ).val();

				if ( ! topic) {
					alert( 'Please select or enter a topic.' );
					return;
				}

				if ( ! persona) {
					alert( 'Please select a persona.' );
					return;
				}

				$button.prop( 'disabled', true ).text( 'Creating...' );

				// Send to ContentSeer API
				$.ajax(
					{
						url: ajaxurl,
						method: 'POST',
						data: {
							action: 'contentseer_generate_content',
							topic: topic,
							blog_title: blogTitle,
							persona: persona,
							nonce: contentSeerGenerate.nonce
						},
						success: function (response) {
							if (response.success) {
								if (response.data && response.data.post_id) {
									location.href = '/wp-admin/post.php?post=' + response.data.post_id + '&action=edit';
								} else {
									alert( 'Content generation initiated successfully.' );
								}
							} else {
								alert( 'Content generation failed. Please try again.' );
								$button.text( 'Generate Content' );
							}
							$button.prop( 'disabled', false );
						},
						error: function () {
							alert( 'Content generation failed. Please try again.' );
							$button.prop( 'disabled', false ).text( 'Generate Content' );
						}
					}
				);
			}
		);
	}
);