<?php
if (!defined('ABSPATH')) {
	exit;
}

class Fasting_Tracker_Database {
	private $table_sessions = null;
	private $table_milestones = null;
	private $table_settings = null;
	private $table_verified = false;
	private $settings_cache = null;

	public function __construct() {
		global $wpdb;
		$this->table_sessions = $wpdb->prefix . 'fasting_sessions';
		$this->table_milestones = $wpdb->prefix . 'fasting_milestones';
		$this->table_settings = $wpdb->prefix . 'fasting_settings';
	}

	public static function activate() {
		$instance = new self();
		$instance->create_tables();
		$instance->insert_default_settings();
	}

	private function ensure_tables_exist() {
		if ($this->table_verified) {
			return true;
		}

		global $wpdb;
		$sessions_exists = $wpdb->get_var($wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$this->table_sessions
		));

		if ($sessions_exists !== $this->table_sessions) {
			$this->create_tables();
			$sessions_exists = $wpdb->get_var($wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$this->table_sessions
			));
		}

		$this->table_verified = ($sessions_exists === $this->table_sessions);
		return $this->table_verified;
	}

	public function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				start_time datetime NOT NULL,
				end_time datetime DEFAULT NULL,
				target_duration int(11) NOT NULL,
				actual_duration int(11) DEFAULT NULL,
				is_active tinyint(1) NOT NULL DEFAULT 1,
				paused_at datetime DEFAULT NULL,
				completed tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY is_active (is_active),
				KEY created_at (created_at)
			)',
			$this->table_sessions
		) . ' ' . $charset_collate;
		dbDelta($sql);

		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				session_id bigint(20) unsigned NOT NULL,
				milestone_hours int(11) NOT NULL,
				achieved_at datetime NOT NULL,
				badge_name varchar(100) NOT NULL,
				PRIMARY KEY (id),
				KEY session_id (session_id),
				KEY milestone_hours (milestone_hours)
			)',
			$this->table_milestones
		) . ' ' . $charset_collate;
		dbDelta($sql);

		$sql = $wpdb->prepare(
			'CREATE TABLE IF NOT EXISTS %i (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(100) NOT NULL,
				setting_value longtext NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY setting_key (setting_key)
			)',
			$this->table_settings
		) . ' ' . $charset_collate;
		dbDelta($sql);
	}

	private function insert_default_settings() {
		$defaults = array(
			'enable_notifications' => '1',
			'milestone_email' => '0',
			'delete_data_on_uninstall' => '0',
		);

		foreach ($defaults as $key => $value) {
			$this->save_setting($key, $value);
		}
	}

	public function get_setting($key, $default = false) {
		if (!$this->ensure_tables_exist()) {
			return $default;
		}

		global $wpdb;
		$value = $wpdb->get_var($wpdb->prepare(
			'SELECT setting_value FROM %i WHERE setting_key = %s',
			$this->table_settings,
			$key
		));

		return (null !== $value) ? maybe_unserialize($value) : $default;
	}

	public function get_all_settings() {
		if (null !== $this->settings_cache) {
			return $this->settings_cache;
		}

		if (!$this->ensure_tables_exist()) {
			return array();
		}

		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare('SELECT setting_key, setting_value FROM %i', 
				$this->table_settings),
			ARRAY_A
		);

		$settings = array();
		foreach ($results as $row) {
			$key = $row['setting_key'] ?? '';
			$value = $row['setting_value'] ?? '';
			if (!empty($key)) {
				$settings[$key] = maybe_unserialize($value);
			}
		}

		$this->settings_cache = $settings;
		return $settings;
	}

	public function save_setting($key, $value) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->replace(
			$this->table_settings,
			array(
				'setting_key' => $key,
				'setting_value' => maybe_serialize($value),
			),
			array('%s', '%s')
		);

		if (false !== $result) {
			$this->settings_cache = null;
		}

		return false !== $result;
	}

	public function start_fast($user_id, $target_hours) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		$this->end_active_fasts($user_id);

		global $wpdb;
		$now = current_time('mysql');

		$result = $wpdb->insert(
			$this->table_sessions,
			array(
				'user_id' => $user_id,
				'start_time' => $now,
				'target_duration' => $target_hours,
				'is_active' => 1,
				'completed' => 0,
				'created_at' => $now,
				'updated_at' => $now,
			),
			array('%d', '%s', '%d', '%d', '%d', '%s', '%s')
		);

		return false !== $result ? $wpdb->insert_id : false;
	}

	public function get_active_fast($user_id) {
		if (!$this->ensure_tables_exist()) {
			return null;
		}

		global $wpdb;
		return $wpdb->get_row($wpdb->prepare(
			'SELECT * FROM %i WHERE user_id = %d AND is_active = 1 ORDER BY start_time DESC LIMIT 1',
			$this->table_sessions,
			$user_id
		));
	}

	public function pause_fast($session_id) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->update(
			$this->table_sessions,
			array(
				'paused_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			),
			array('id' => $session_id),
			array('%s', '%s'),
			array('%d')
		);

		return false !== $result;
	}

	public function resume_fast($session_id) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->update(
			$this->table_sessions,
			array(
				'paused_at' => null,
				'updated_at' => current_time('mysql'),
			),
			array('id' => $session_id),
			array('%s', '%s'),
			array('%d')
		);

		return false !== $result;
	}

	public function end_fast($session_id, $duration_hours) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		global $wpdb;
		$now = current_time('mysql');

		$result = $wpdb->update(
			$this->table_sessions,
			array(
				'end_time' => $now,
				'actual_duration' => $duration_hours,
				'is_active' => 0,
				'completed' => 1,
				'updated_at' => $now,
			),
			array('id' => $session_id),
			array('%s', '%d', '%d', '%d', '%s'),
			array('%d')
		);

		return false !== $result;
	}

	private function end_active_fasts($user_id) {
		if (!$this->ensure_tables_exist()) {
			return;
		}

		global $wpdb;
		$wpdb->update(
			$this->table_sessions,
			array(
				'is_active' => 0,
				'updated_at' => current_time('mysql'),
			),
			array(
				'user_id' => $user_id,
				'is_active' => 1,
			),
			array('%d', '%s'),
			array('%d', '%d')
		);
	}

	public function save_milestone($session_id, $hours, $badge_name) {
		if (!$this->ensure_tables_exist()) {
			return false;
		}

		global $wpdb;
		$existing = $wpdb->get_var($wpdb->prepare(
			'SELECT id FROM %i WHERE session_id = %d AND milestone_hours = %d',
			$this->table_milestones,
			$session_id,
			$hours
		));

		if ($existing) {
			return true;
		}

		$result = $wpdb->insert(
			$this->table_milestones,
			array(
				'session_id' => $session_id,
				'milestone_hours' => $hours,
				'achieved_at' => current_time('mysql'),
				'badge_name' => $badge_name,
			),
			array('%d', '%d', '%s', '%s')
		);

		return false !== $result;
	}

	public function get_user_stats($user_id) {
		if (!$this->ensure_tables_exist()) {
			return array(
				'total_fasts' => 0,
				'total_hours' => 0,
				'longest_fast' => 0,
				'current_streak' => 0,
				'milestones_earned' => 0,
			);
		}

		global $wpdb;
		
		$total_fasts = (int) $wpdb->get_var($wpdb->prepare(
			'SELECT COUNT(*) FROM %i WHERE user_id = %d AND completed = 1',
			$this->table_sessions,
			$user_id
		));

		$total_hours = (int) $wpdb->get_var($wpdb->prepare(
			'SELECT SUM(actual_duration) FROM %i WHERE user_id = %d AND completed = 1',
			$this->table_sessions,
			$user_id
		));

		$longest_fast = (int) $wpdb->get_var($wpdb->prepare(
			'SELECT MAX(actual_duration) FROM %i WHERE user_id = %d AND completed = 1',
			$this->table_sessions,
			$user_id
		));

		$milestones_earned = (int) $wpdb->get_var($wpdb->prepare(
			'SELECT COUNT(DISTINCT m.milestone_hours) FROM %i m 
			INNER JOIN %i s ON m.session_id = s.id 
			WHERE s.user_id = %d',
			$this->table_milestones,
			$this->table_sessions,
			$user_id
		));

		return array(
			'total_fasts' => $total_fasts,
			'total_hours' => $total_hours ?: 0,
			'longest_fast' => $longest_fast ?: 0,
			'current_streak' => $this->calculate_streak($user_id),
			'milestones_earned' => $milestones_earned,
		);
	}

	private function calculate_streak($user_id) {
		if (!$this->ensure_tables_exist()) {
			return 0;
		}

		global $wpdb;
		$sessions = $wpdb->get_results($wpdb->prepare(
			'SELECT DATE(created_at) as fast_date FROM %i 
			WHERE user_id = %d AND completed = 1 
			ORDER BY created_at DESC',
			$this->table_sessions,
			$user_id
		));

		if (empty($sessions)) {
			return 0;
		}

		$streak = 1;
		$current_date = current_time('Y-m-d');
		$last_date = $sessions[0]->fast_date;

		if ($last_date !== $current_date && $last_date !== date('Y-m-d', strtotime('-1 day', strtotime($current_date)))) {
			return 0;
		}

		for ($i = 1; $i < count($sessions); $i++) {
			$diff = strtotime($last_date) - strtotime($sessions[$i]->fast_date);
			$days_diff = $diff / (60 * 60 * 24);

			if ($days_diff <= 1) {
				$streak++;
				$last_date = $sessions[$i]->fast_date;
			} else {
				break;
			}
		}

		return $streak;
	}

	public function get_user_history($user_id, $limit = 10) {
		if (!$this->ensure_tables_exist()) {
			return array();
		}

		global $wpdb;
		$results = $wpdb->get_results($wpdb->prepare(
			'SELECT * FROM %i WHERE user_id = %d AND completed = 1 
			ORDER BY created_at DESC LIMIT %d',
			$this->table_sessions,
			$user_id,
			$limit
		));

		return $results ?: array();
	}

	public function delete_all_data() {
		if (!$this->ensure_tables_exist()) {
			return;
		}

		global $wpdb;
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->table_sessions));
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->table_milestones));
		$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $this->table_settings));
	}
}
