/**
 * Persona Fields JavaScript
 *
 * Handles the dynamic pain points repeating field functionality
 * for the ContentSeer personas taxonomy.
 */

jQuery( document ).ready(
	function ($) {
		// Add new pain point field
		$( document ).on(
			'click',
			'#add-pain-point',
			function (e) {
				e.preventDefault();

				var container = $( '#pain-points-container' );
				var newField  = $(
					'<div class="pain-point-item" style="margin-bottom: 10px;">' +
					'<input type="text" name="persona_pain_points[]" placeholder="Enter a pain point" style="width: 300px;" />' +
					'<button type="button" class="button remove-pain-point" style="margin-left: 10px;">Remove</button>' +
					'</div>'
				);

				container.append( newField );

				// Focus on the new input
				newField.find( 'input' ).focus();
			}
		);

		// Remove pain point field
		$( document ).on(
			'click',
			'.remove-pain-point',
			function (e) {
				e.preventDefault();

				var container = $( '#pain-points-container' );
				var items     = container.find( '.pain-point-item' );

				// Don't remove if it's the last item
				if (items.length > 1) {
					$( this ).closest( '.pain-point-item' ).remove();
				} else {
					// Clear the input instead of removing the field
					$( this ).siblings( 'input' ).val( '' );
				}
			}
		);

		// Remove empty pain point fields on form submit
		$( 'form' ).on(
			'submit',
			function () {
				$( '#pain-points-container input[name="persona_pain_points[]"]' ).each(
					function () {
						if ($( this ).val().trim() === '') {
							$( this ).remove();
						}
					}
				);
			}
		);

		// Ensure at least one pain point field exists
		function initializePainPoints() {
			var container = $( '#pain-points-container' );
			if (container.length && container.find( '.pain-point-item' ).length === 0) {
				$( '#add-pain-point' ).trigger( 'click' );
			}
		}

		// Initialize on page load
		initializePainPoints();

		// Handle AJAX form submissions for taxonomy terms
		$( document ).ajaxComplete(
			function (event, xhr, settings) {
				// Check if this was a taxonomy term save
				if (settings.data && settings.data.indexOf( 'action=add-tag' ) !== -1) {
					// Clear form fields after successful add
					if (xhr.responseText.indexOf( 'error' ) === -1) {
						$( '#persona_name, #persona_background, #persona_goals, #persona_motivations' ).val( '' );
						$( '#pain-points-container' ).empty();
						initializePainPoints();
					}
				}
			}
		);
	}
);