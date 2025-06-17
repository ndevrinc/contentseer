// ContentSeer plugin JavaScript functionality

document.addEventListener(
	'DOMContentLoaded',
	function () {
		// Add event listeners to blog post links
		const blogLinks = document.querySelectorAll( 'a[href*="/blog/"]' );
		blogLinks.forEach(
			function (link) {
				link.addEventListener(
					'click',
					function () {
						// Fire a GA4 event
						if (typeof gtag === 'function') {
							gtag(
								'event',
								'click',
								{
									event_category: 'contentseer_link',
									event_label: link.href,
									transport_type: 'beacon'
								}
							);
						}
					}
				);
			}
		);

		// Track persona view counts
		if (
		typeof contentSeerData !== 'undefined' &&
		Array.isArray( contentSeerData.personas ) &&
		contentSeerData.personas.length > 0
		) {
			contentSeerData.personas.forEach(
				function (persona) {
					let viewCount = parseInt( localStorage.getItem( `contentSeerViewCount_${persona}` ), 10 ) || 0;
					viewCount++;
					localStorage.setItem( `contentSeerViewCount_${persona}`, viewCount );
					console.log( `Persona: ${persona}, View Count: ${viewCount}` );
				}
			);
		}
	}
);

// Fire a GA4 event on page exit to record view counts
window.addEventListener(
	'beforeunload',
	function () {
		if (
			typeof contentSeerData !== 'undefined' &&
			Array.isArray( contentSeerData.personas ) &&
			contentSeerData.personas.length > 0
		) {
			contentSeerData.personas.forEach(
				function (persona) {
					const viewCount = parseInt( localStorage.getItem( `contentSeerViewCount_${persona}` ) ) || 0;

					// Fire a GA4 event for the persona's view count
					if (typeof gtag === 'function' && viewCount > 0) {
						gtag(
							'event',
							'persona_view_count',
							{
								event_category: 'contentseer',
								event_label: persona,
								value: viewCount,
								transport_type: 'beacon', // Ensures the event is sent even if the user leaves the page
							}
						);
					}
				}
			);
		}
	}
);
