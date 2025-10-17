<?php
if (!defined('ABSPATH')) {
	exit;
}

class Fasting_Tracker_Public {
	private $database;
	private $core;

	public function __construct($database) {
		$this->database = $database;
		$this->core = new Fasting_Tracker_Core($database);
		add_shortcode('fasting_tracker', array($this, 'render_dashboard'));
		add_shortcode('fasting_meter', array($this, 'render_meter'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
		add_action('wp_ajax_fasting_update', array($this, 'ajax_update_timer'));
	}

	public function enqueue_assets() {
		global $post;
		
		if (!is_a($post, 'WP_Post')) {
			return;
		}

		if (!has_shortcode($post->post_content, 'fasting_tracker') && 
			!has_shortcode($post->post_content, 'fasting_meter')) {
			return;
		}

		wp_enqueue_style(
			'fasting-tracker-public',
			FASTING_TRACKER_URL . 'assets/public.css',
			array(),
			FASTING_TRACKER_VERSION
		);

		wp_enqueue_script(
			'fasting-tracker-public',
			FASTING_TRACKER_URL . 'assets/public.js',
			array('jquery'),
			FASTING_TRACKER_VERSION,
			true
		);

		wp_localize_script('fasting-tracker-public', 'fastingTracker', array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'restUrl' => rest_url('fasting/v1/'),
			'nonce' => wp_create_nonce('wp_rest'),
			'userId' => get_current_user_id(),
		));
	}

	public function render_dashboard($atts) {
		if (!is_user_logged_in()) {
			return '<div class="fasting-tracker-notice">' . 
				esc_html__('Please log in to use the fasting tracker', 'fasting-tracker') . 
				'</div>';
		}

		$user_id = get_current_user_id();
		$current = $this->core->get_current_fast($user_id);
		$stats = $this->database->get_user_stats($user_id);

		ob_start();
		?>
		<div class="fasting-tracker-dashboard" id="fasting-tracker-dashboard">
			<div class="fasting-tracker-header">
				<h2><?php echo esc_html__('Fasting Tracker', 'fasting-tracker'); ?></h2>
			</div>

			<?php if ($current): ?>
				<?php echo $this->render_active_fast($current); ?>
			<?php else: ?>
				<?php echo $this->render_start_fast(); ?>
			<?php endif; ?>

			<?php echo $this->render_stats($stats); ?>
			<?php echo $this->render_history($user_id); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_active_fast($current) {
		ob_start();
		?>
		<div class="fasting-tracker-active">
			<div class="fasting-meter-container">
				<?php echo $this->render_meter_html($current); ?>
			</div>

			<div class="fasting-controls">
				<?php if ($current['is_paused']): ?>
					<button class="fasting-btn fasting-btn-primary" id="fasting-resume-btn" 
						data-session="<?php echo esc_attr($current['session_id']); ?>">
						<i class="fas fa-play"></i> <?php echo esc_html__('Resume', 'fasting-tracker'); ?>
					</button>
				<?php else: ?>
					<button class="fasting-btn fasting-btn-warning" id="fasting-pause-btn" 
						data-session="<?php echo esc_attr($current['session_id']); ?>">
						<i class="fas fa-pause"></i> <?php echo esc_html__('Pause', 'fasting-tracker'); ?>
					</button>
				<?php endif; ?>
				
				<button class="fasting-btn fasting-btn-danger" id="fasting-end-btn" 
					data-session="<?php echo esc_attr($current['session_id']); ?>">
					<i class="fas fa-stop"></i> <?php echo esc_html__('End Fast', 'fasting-tracker'); ?>
				</button>
			</div>

			<div class="fasting-phase-info">
				<h3><?php echo esc_html($current['current_phase']['name']); ?></h3>
				<p><?php echo esc_html($current['current_phase']['description']); ?></p>
				
				<?php if ($current['next_milestone']): ?>
					<div class="fasting-next-milestone">
						<i class="fas fa-trophy"></i>
						<span>
							<?php 
							echo esc_html(
								sprintf(
									__('Next milestone: %s in %s hours', 'fasting-tracker'),
									$current['next_milestone']['badge'],
									number_format($current['next_milestone']['hours_remaining'], 1)
								)
							);
							?>
						</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_start_fast() {
		ob_start();
		?>
		<div class="fasting-tracker-start">
			<h3><?php echo esc_html__('Start a New Fast', 'fasting-tracker'); ?></h3>
			<p><?php echo esc_html__('Choose your fasting duration:', 'fasting-tracker'); ?></p>
			
			<div class="fasting-presets">
				<button class="fasting-btn fasting-btn-preset" data-hours="16">
					<i class="fas fa-clock"></i>
					<span>16:8</span>
					<small><?php echo esc_html__('16 hours', 'fasting-tracker'); ?></small>
				</button>
				
				<button class="fasting-btn fasting-btn-preset" data-hours="20">
					<i class="fas fa-clock"></i>
					<span>20:4</span>
					<small><?php echo esc_html__('20 hours', 'fasting-tracker'); ?></small>
				</button>
				
				<button class="fasting-btn fasting-btn-preset" data-hours="24">
					<i class="fas fa-calendar-day"></i>
					<span>24h</span>
					<small><?php echo esc_html__('24 hours', 'fasting-tracker'); ?></small>
				</button>
				
				<button class="fasting-btn fasting-btn-preset" data-hours="48">
					<i class="fas fa-calendar-alt"></i>
					<span>48h</span>
					<small><?php echo esc_html__('48 hours', 'fasting-tracker'); ?></small>
				</button>
				
				<button class="fasting-btn fasting-btn-preset" data-hours="72">
					<i class="fas fa-calendar-week"></i>
					<span>72h</span>
					<small><?php echo esc_html__('72 hours', 'fasting-tracker'); ?></small>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_meter_html($current) {
		$progress = min(100, $current['progress_percent']);
		$color = $current['current_phase']['color'];
		$elapsed = $current['elapsed_hours'];
		$target = $current['target_duration'];

		ob_start();
		?>
		<div class="fasting-meter" style="--phase-color: <?php echo esc_attr($color); ?>">
			<svg class="fasting-meter-svg" viewBox="0 0 200 200">
				<circle class="fasting-meter-bg" cx="100" cy="100" r="85" />
				<circle class="fasting-meter-progress" cx="100" cy="100" r="85" 
					style="stroke-dashoffset: <?php echo esc_attr(534 - (534 * $progress / 100)); ?>" />
			</svg>
			
			<div class="fasting-meter-content">
				<div class="fasting-meter-time" id="fasting-elapsed-time">
					<?php echo esc_html(number_format($elapsed, 1)); ?>h
				</div>
				<div class="fasting-meter-label">
					<?php echo esc_html(sprintf(__('of %d hours', 'fasting-tracker'), $target)); ?>
				</div>
				<div class="fasting-meter-percent">
					<?php echo esc_html(number_format($progress, 0)); ?>%
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_meter($atts) {
		if (!is_user_logged_in()) {
			return '';
		}

		$user_id = get_current_user_id();
		$current = $this->core->get_current_fast($user_id);

		if (!$current) {
			return '<div class="fasting-tracker-notice">' . 
				esc_html__('No active fast', 'fasting-tracker') . 
				'</div>';
		}

		return '<div class="fasting-meter-container">' . $this->render_meter_html($current) . '</div>';
	}

	private function render_stats($stats) {
		ob_start();
		?>
		<div class="fasting-stats">
			<h3><?php echo esc_html__('Your Stats', 'fasting-tracker'); ?></h3>
			<div class="fasting-stats-grid">
				<div class="fasting-stat-card">
					<i class="fas fa-check-circle"></i>
					<div class="fasting-stat-value"><?php echo esc_html($stats['total_fasts']); ?></div>
					<div class="fasting-stat-label"><?php echo esc_html__('Total Fasts', 'fasting-tracker'); ?></div>
				</div>
				
				<div class="fasting-stat-card">
					<i class="fas fa-clock"></i>
					<div class="fasting-stat-value"><?php echo esc_html($stats['total_hours']); ?></div>
					<div class="fasting-stat-label"><?php echo esc_html__('Total Hours', 'fasting-tracker'); ?></div>
				</div>
				
				<div class="fasting-stat-card">
					<i class="fas fa-trophy"></i>
					<div class="fasting-stat-value"><?php echo esc_html($stats['longest_fast']); ?>h</div>
					<div class="fasting-stat-label"><?php echo esc_html__('Longest Fast', 'fasting-tracker'); ?></div>
				</div>
				
				<div class="fasting-stat-card">
					<i class="fas fa-fire"></i>
					<div class="fasting-stat-value"><?php echo esc_html($stats['current_streak']); ?></div>
					<div class="fasting-stat-label"><?php echo esc_html__('Current Streak', 'fasting-tracker'); ?></div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_history($user_id) {
		$history = $this->database->get_user_history($user_id, 5);
		
		if (empty($history)) {
			return '';
		}

		ob_start();
		?>
		<div class="fasting-history">
			<h3><?php echo esc_html__('Recent History', 'fasting-tracker'); ?></h3>
			<table class="fasting-history-table">
				<thead>
					<tr>
						<th><?php echo esc_html__('Date', 'fasting-tracker'); ?></th>
						<th><?php echo esc_html__('Duration', 'fasting-tracker'); ?></th>
						<th><?php echo esc_html__('Completed', 'fasting-tracker'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($history as $entry): ?>
						<tr>
							<td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($entry->start_time))); ?></td>
							<td><?php echo esc_html($entry->actual_duration . 'h'); ?></td>
							<td>
								<?php if ($entry->completed): ?>
									<i class="fas fa-check-circle" style="color: #00ff00;"></i>
								<?php else: ?>
									<i class="fas fa-times-circle" style="color: #d11c1c;"></i>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public function ajax_update_timer() {
		check_ajax_referer('wp_rest', 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(array('message' => 'Not logged in'));
		}

		$user_id = get_current_user_id();
		$current = $this->core->get_current_fast($user_id);

		if (!$current) {
			wp_send_json_error(array('message' => 'No active fast'));
		}

		wp_send_json_success($current);
	}
}
