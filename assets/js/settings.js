/**
 * ContentSeer Settings JavaScript
 *
 * Handles settings page functionality including access requests.
 */

jQuery(document).ready(function($) {
	// Handle access request
	$('#request-access-btn').on('click', function() {
		var $button = $(this);
		var $status = $('#access-request-status');
		
		var siteName = $('#site_name').val().trim();
		var adminEmail = $('#admin_email').val().trim();
		
		if (!siteName || !adminEmail) {
			$status.html('<div class="notice notice-error inline"><p>Please fill in all required fields.</p></div>').show();
			return;
		}
		
		// Validate email format
		var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRegex.test(adminEmail)) {
			$status.html('<div class="notice notice-error inline"><p>Please enter a valid email address.</p></div>').show();
			return;
		}
		
		$button.prop('disabled', true).text('Requesting Access...');
		$status.html('<div class="notice notice-info inline"><p>Sending request to ContentSeer...</p></div>').show();
		
		$.ajax({
			url: contentSeerSettings.ajaxurl,
			method: 'POST',
			data: {
				action: 'contentseer_request_access',
				site_name: siteName,
				admin_email: adminEmail,
				nonce: contentSeerSettings.nonce
			},
			success: function(response) {
				if (response.success) {
					if (response.data.pending) {
						$status.html('<div class="notice notice-info inline"><p><strong>Request Submitted!</strong><br>' + response.data.message + '</p></div>');
						$button.text('Request Submitted');
					} else {
						$status.html('<div class="notice notice-success inline"><p><strong>Access Granted!</strong><br>' + response.data.message + '</p></div>');
						$button.text('Connected Successfully');
						
						// Reload the page after a short delay to show the updated connection status
						setTimeout(function() {
							location.reload();
						}, 2000);
					}
				} else {
					$status.html('<div class="notice notice-error inline"><p><strong>Request Failed:</strong><br>' + response.data + '</p></div>');
					$button.prop('disabled', false).text('Request Access to ContentSeer');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				$status.html('<div class="notice notice-error inline"><p><strong>Connection Error:</strong><br>Failed to send request. Please check your internet connection and try again.</p></div>');
				$button.prop('disabled', false).text('Request Access to ContentSeer');
			},
			timeout: 30000 // 30 second timeout
		});
	});
	
	// Auto-fill site name if empty
	if ($('#site_name').val() === '') {
		$('#site_name').val(document.title.replace(' — WordPress', '').replace(' – WordPress', ''));
	}
	
	// Form validation on input
	$('#site_name, #admin_email').on('input', function() {
		var siteName = $('#site_name').val().trim();
		var adminEmail = $('#admin_email').val().trim();
		var isValid = siteName.length > 0 && adminEmail.length > 0;
		
		$('#request-access-btn').prop('disabled', !isValid);
		
		// Clear any previous error messages when user starts typing
		if (isValid) {
			$('#access-request-status').hide();
		}
	});
	
	// Initial validation check
	$('#site_name, #admin_email').trigger('input');
});