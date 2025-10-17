<?php
if (!defined('ABSPATH')) {
	exit;
}

class Fasting_Tracker_Admin {
	private $database;

	public function __construct($database) {
		$this->database = $database;
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action('admin_init', array($this, 'handle_settings_save'));
	}

	public function add_menu_page() {
		add_menu_page(
			__('Fasting Tracker', 'fasting-tracker'),
			__('Fasting Tracker', 'fasting-tracker'),
			'manage_options',
			'fasting-tracker',
			array($this, 'render_settings_page'),
			'dashicons-heart',
			30
		);
	}

	public function handle_settings_save() {
		if (!isset($_POST['fasting_tracker_settings_nonce'])) {
			return;
		}

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'fasting-tracker'));
		}

		if (!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_POST['fasting_tracker_settings_nonce'])),
			'fasting_tracker_save_settings'
		)) {
			wp_die(esc_html__('Security check failed', 'fasting-tracker'));
		}

		$enable_notifications = isset($_POST['enable_notifications']) ? '1' : '0';
		$milestone_email = isset($_POST['milestone_email']) ? '1' : '0';
		$delete_on_uninstall = isset($_POST['delete_data_on_uninstall']) ? '1' : '0';

		$this->database->save_setting('enable_notifications', $enable_notifications);
		$this->database->save_setting('milestone_email', $milestone_email);
		$this->database->save_setting('delete_data_on_uninstall', $delete_on_uninstall);

		add_settings_error(
			'fasting_tracker_messages',
			'fasting_tracker_message',
			__('Settings saved successfully', 'fasting-tracker'),
			'updated'
		);
	}

	public function render_settings_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Unauthorized', 'fasting-tracker'));
		}

		$settings = $this->database->get_all_settings();
		$enable_notifications = $settings['enable_notifications'] ?? '1';
		$milestone_email = $settings['milestone_email'] ?? '0';
		$delete_on_uninstall = $settings['delete_data_on_uninstall'] ?? '0';

		settings_errors('fasting_tracker_messages');
		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Fasting Tracker Settings', 'fasting-tracker'); ?></h1>
			
			<form method="post" action="">
				<?php wp_nonce_field('fasting_tracker_save_settings', 'fasting_tracker_settings_nonce'); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="enable_notifications">
								<?php echo esc_html__('Enable Notifications', 'fasting-tracker'); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" 
								id="enable_notifications" 
								name="enable_notifications" 
								value="1" 
								<?php checked($enable_notifications, '1'); ?>>
							<p class="description">
								<?php echo esc_html__('Show milestone notifications in the dashboard', 'fasting-tracker'); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="milestone_email">
								<?php echo esc_html__('Email Alerts', 'fasting-tracker'); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" 
								id="milestone_email" 
								name="milestone_email" 
								value="1" 
								<?php checked($milestone_email, '1'); ?>>
							<p class="description">
								<?php echo esc_html__('Send email alerts when milestones are achieved', 'fasting-tracker'); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="delete_data_on_uninstall">
								<?php echo esc_html__('Delete Data on Uninstall', 'fasting-tracker'); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" 
								id="delete_data_on_uninstall" 
								name="delete_data_on_uninstall" 
								value="1" 
								<?php checked($delete_on_uninstall, '1'); ?>>
							<p class="description">
								<?php echo esc_html__('Remove all plugin data when uninstalling', 'fasting-tracker'); ?>
							</p>
						</td>
					</tr>
				</table>
				
				<?php submit_button(__('Save Settings', 'fasting-tracker')); ?>
			</form>
			
			<hr>
			
			<h2><?php echo esc_html__('Usage Instructions', 'fasting-tracker'); ?></h2>
			<p><?php echo esc_html__('Use the following shortcodes to display the fasting tracker:', 'fasting-tracker'); ?></p>
			<ul>
				<li><code>[fasting_tracker]</code> - <?php echo esc_html__('Full dashboard with controls and stats', 'fasting-tracker'); ?></li>
				<li><code>[fasting_meter]</code> - <?php echo esc_html__('Standalone progress meter', 'fasting-tracker'); ?></li>
			</ul>
		</div>
		<?php
	}
}
