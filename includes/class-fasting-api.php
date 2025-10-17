<?php
if (!defined('ABSPATH')) {
	exit;
}

class Fasting_Tracker_API {
	private $database;
	private $core;

	public function __construct($database) {
		$this->database = $database;
		$this->core = new Fasting_Tracker_Core($database);
		add_action('rest_api_init', array($this, 'register_routes'));
	}

	public function register_routes() {
		register_rest_route('fasting/v1', '/start', array(
			'methods' => 'POST',
			'callback' => array($this, 'start_fast'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/pause', array(
			'methods' => 'POST',
			'callback' => array($this, 'pause_fast'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/resume', array(
			'methods' => 'POST',
			'callback' => array($this, 'resume_fast'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/end', array(
			'methods' => 'POST',
			'callback' => array($this, 'end_fast'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/current', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_current'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/history', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_history'),
			'permission_callback' => array($this, 'check_permissions'),
		));

		register_rest_route('fasting/v1', '/stats', array(
			'methods' => 'GET',
			'callback' => array($this, 'get_stats'),
			'permission_callback' => array($this, 'check_permissions'),
		));
	}

	public function check_permissions() {
		return is_user_logged_in();
	}

	public function start_fast($request) {
		$user_id = get_current_user_id();
		$target_hours = absint($request->get_param('target_hours'));

		if ($target_hours <= 0 || $target_hours > 72) {
			return new WP_Error('invalid_duration', __('Invalid fast duration', 'fasting-tracker'), array('status' => 400));
		}

		$result = $this->core->start_fast($user_id, $target_hours);

		if (is_wp_error($result)) {
			return $result;
		}

		return new WP_REST_Response(array(
			'success' => true,
			'session_id' => $result,
			'message' => __('Fast started successfully', 'fasting-tracker'),
		), 200);
	}

	public function pause_fast($request) {
		$user_id = get_current_user_id();
		$session_id = absint($request->get_param('session_id'));

		$result = $this->core->pause_fast($session_id, $user_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return new WP_REST_Response(array(
			'success' => true,
			'message' => __('Fast paused', 'fasting-tracker'),
		), 200);
	}

	public function resume_fast($request) {
		$user_id = get_current_user_id();
		$session_id = absint($request->get_param('session_id'));

		$result = $this->core->resume_fast($session_id, $user_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return new WP_REST_Response(array(
			'success' => true,
			'message' => __('Fast resumed', 'fasting-tracker'),
		), 200);
	}

	public function end_fast($request) {
		$user_id = get_current_user_id();
		$session_id = absint($request->get_param('session_id'));

		$result = $this->core->end_fast($session_id, $user_id);

		if (is_wp_error($result)) {
			return $result;
		}

		return new WP_REST_Response(array(
			'success' => true,
			'duration' => $result['duration'],
			'milestones' => $result['milestones_achieved'],
			'message' => __('Fast completed', 'fasting-tracker'),
		), 200);
	}

	public function get_current($request) {
		$user_id = get_current_user_id();
		$current = $this->core->get_current_fast($user_id);

		if (!$current) {
			return new WP_REST_Response(array(
				'active' => false,
			), 200);
		}

		return new WP_REST_Response(array(
			'active' => true,
			'fast' => $current,
		), 200);
	}

	public function get_history($request) {
		$user_id = get_current_user_id();
		$limit = absint($request->get_param('limit')) ?: 10;
		$history = $this->database->get_user_history($user_id, $limit);

		return new WP_REST_Response(array(
			'history' => $history,
		), 200);
	}

	public function get_stats($request) {
		$user_id = get_current_user_id();
		$stats = $this->database->get_user_stats($user_id);

		return new WP_REST_Response(array(
			'stats' => $stats,
		), 200);
	}
}
