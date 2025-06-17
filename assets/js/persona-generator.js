/**
 * Persona Generator JavaScript
 *
 * Handles the persona generation functionality on the taxonomy page.
 */

jQuery( document ).ready(
	function ($) {
		// Handle generate personas button click
		$( '#generate-personas' ).on(
			'click',
			function () {
				var $button = $( this );

				if ( $button.prop( 'disabled' ) ) {
					return;
				}

				if ( ! confirm( 'This will generate new personas using AI. Existing personas with the same job titles will be updated. Continue?' ) ) {
					return;
				}

				$button.prop( 'disabled', true ).text( 'Generating...' );

				// Send AJAX request to generate personas
				$.ajax(
					{
						url: contentSeerPersonaGenerator.ajaxurl,
						method: 'POST',
						data: {
							action: 'contentseer_generate_personas',
							nonce: contentSeerPersonaGenerator.nonce
						},
						success: function (response) {
							if (response.success) {
								alert( response.data.message );
								// Reload the page to show the new personas
								location.reload();
							} else {
								alert( 'Persona generation failed: ' + response.data );
							}
						},
						error: function () {
							alert( 'Persona generation failed. Please try again.' );
						},
						complete: function () {
							$button.prop( 'disabled', false ).text( 'Generate Personas with AI' );
						}
					}
				);
			}
		);
	}
);