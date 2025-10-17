jQuery(document).ready(function($) {
	'use strict';

	const FastingTracker = {
		updateInterval: null,
		
		init: function() {
			this.bindEvents();
			this.startAutoUpdate();
		},

		bindEvents: function() {
			$(document).on('click', '.fasting-btn-preset', this.startFast.bind(this));
			$(document).on('click', '#fasting-pause-btn', this.pauseFast.bind(this));
			$(document).on('click', '#fasting-resume-btn', this.resumeFast.bind(this));
			$(document).on('click', '#fasting-end-btn', this.endFast.bind(this));
		},

		startFast: function(e) {
			e.preventDefault();
			const hours = $(e.currentTarget).data('hours');
			
			if (!hours) {
				return;
			}

			$.ajax({
				url: fastingTracker.restUrl + 'start',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', fastingTracker.nonce);
				},
				data: {
					target_hours: hours
				},
				success: function(response) {
					location.reload();
				},
				error: function(xhr) {
					alert(xhr.responseJSON?.message || 'Failed to start fast');
				}
			});
		},

		pauseFast: function(e) {
			e.preventDefault();
			const sessionId = $(e.currentTarget).data('session');

			$.ajax({
				url: fastingTracker.restUrl + 'pause',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', fastingTracker.nonce);
				},
				data: {
					session_id: sessionId
				},
				success: function(response) {
					location.reload();
				},
				error: function(xhr) {
					alert(xhr.responseJSON?.message || 'Failed to pause fast');
				}
			});
		},

		resumeFast: function(e) {
			e.preventDefault();
			const sessionId = $(e.currentTarget).data('session');

			$.ajax({
				url: fastingTracker.restUrl + 'resume',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', fastingTracker.nonce);
				},
				data: {
					session_id: sessionId
				},
				success: function(response) {
					location.reload();
				},
				error: function(xhr) {
					alert(xhr.responseJSON?.message || 'Failed to resume fast');
				}
			});
		},

		endFast: function(e) {
			e.preventDefault();
			
			if (!confirm('Are you sure you want to end your fast?')) {
				return;
			}

			const sessionId = $(e.currentTarget).data('session');

			$.ajax({
				url: fastingTracker.restUrl + 'end',
				method: 'POST',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-WP-Nonce', fastingTracker.nonce);
				},
				data: {
					session_id: sessionId
				},
				success: function(response) {
					if (response.milestones && response.milestones.length > 0) {
						let message = 'Fast completed! Duration: ' + response.duration.toFixed(1) + ' hours\n\nMilestones achieved:\n';
						response.milestones.forEach(function(m) {
							message += '- ' + m.badge + ' (' + m.hours + 'h)\n';
						});
						alert(message);
					}
					location.reload();
				},
				error: function(xhr) {
					alert(xhr.responseJSON?.message || 'Failed to end fast');
				}
			});
		},

		updateTimer: function() {
			if (!$('#fasting-elapsed-time').length) {
				this.stopAutoUpdate();
				return;
			}

			$.ajax({
				url: fastingTracker.ajaxUrl,
				method: 'POST',
				data: {
					action: 'fasting_update',
					nonce: fastingTracker.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						const data = response.data;
						$('#fasting-elapsed-time').text(data.elapsed_hours.toFixed(1) + 'h');
						
						const progressPercent = Math.min(100, data.progress_percent);
						const progressOffset = 534 - (534 * progressPercent / 100);
						$('.fasting-meter-progress').css('stroke-dashoffset', progressOffset);
						$('.fasting-meter-percent').text(Math.round(progressPercent) + '%');
						
						if (data.current_phase && data.current_phase.color) {
							$('.fasting-meter').css('--phase-color', data.current_phase.color);
							$('.fasting-meter-progress').css('stroke', data.current_phase.color);
						}
					}
				}
			});
		},

		startAutoUpdate: function() {
			if ($('#fasting-elapsed-time').length) {
				this.updateTimer();
				this.updateInterval = setInterval(this.updateTimer.bind(this), 10000);
			}
		},

		stopAutoUpdate: function() {
			if (this.updateInterval) {
				clearInterval(this.updateInterval);
				this.updateInterval = null;
			}
		}
	};

	FastingTracker.init();
});
