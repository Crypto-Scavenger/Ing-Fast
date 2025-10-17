<?php
if (!defined('ABSPATH')) {
	exit;
}

class Fasting_Tracker_Core {
	private $database;
	private $milestones = array(
		12 => 'Ketosis Initiated',
		24 => '24-Hour Milestone',
		36 => 'Peak Performance',
		48 => '48-Hour Champion',
		72 => '72-Hour Master',
	);

	public function __construct($database) {
		$this->database = $database;
	}

	public function start_fast($user_id, $target_hours) {
		if (!is_user_logged_in() || $user_id !== get_current_user_id()) {
			return new WP_Error('unauthorized', __('Unauthorized action', 'fasting-tracker'));
		}

		$target_hours = absint($target_hours);
		if ($target_hours <= 0 || $target_hours > 72) {
			return new WP_Error('invalid_duration', __('Invalid fast duration', 'fasting-tracker'));
		}

		$session_id = $this->database->start_fast($user_id, $target_hours);
		if (false === $session_id) {
			return new WP_Error('db_error', __('Failed to start fast', 'fasting-tracker'));
		}

		return $session_id;
	}

	public function get_current_fast($user_id) {
		if (!is_user_logged_in() || $user_id !== get_current_user_id()) {
			return null;
		}

		$fast = $this->database->get_active_fast($user_id);
		if (!$fast) {
			return null;
		}

		$elapsed = $this->calculate_elapsed_time($fast);
		$phase = $this->get_current_phase($elapsed);
		$next_milestone = $this->get_next_milestone($elapsed);

		return array(
			'session_id' => $fast->id,
			'start_time' => $fast->start_time,
			'target_duration' => $fast->target_duration,
			'is_paused' => !empty($fast->paused_at),
			'elapsed_hours' => $elapsed,
			'current_phase' => $phase,
			'next_milestone' => $next_milestone,
			'progress_percent' => min(100, ($elapsed / $fast->target_duration) * 100),
		);
	}

	private function calculate_elapsed_time($fast) {
		$start = strtotime($fast->start_time);
		$now = current_time('timestamp');
		$elapsed_seconds = $now - $start;
		return round($elapsed_seconds / 3600, 2);
	}

	public function pause_fast($session_id, $user_id) {
		if (!is_user_logged_in() || $user_id !== get_current_user_id()) {
			return new WP_Error('unauthorized', __('Unauthorized action', 'fasting-tracker'));
		}

		$fast = $this->database->get_active_fast($user_id);
		if (!$fast || (int)$fast->id !== absint($session_id)) {
			return new WP_Error('invalid_session', __('Invalid fast session', 'fasting-tracker'));
		}

		$result = $this->database->pause_fast($session_id);
		if (!$result) {
			return new WP_Error('db_error', __('Failed to pause fast', 'fasting-tracker'));
		}

		return true;
	}

	public function resume_fast($session_id, $user_id) {
		if (!is_user_logged_in() || $user_id !== get_current_user_id()) {
			return new WP_Error('unauthorized', __('Unauthorized action', 'fasting-tracker'));
		}

		$fast = $this->database->get_active_fast($user_id);
		if (!$fast || (int)$fast->id !== absint($session_id)) {
			return new WP_Error('invalid_session', __('Invalid fast session', 'fasting-tracker'));
		}

		$result = $this->database->resume_fast($session_id);
		if (!$result) {
			return new WP_Error('db_error', __('Failed to resume fast', 'fasting-tracker'));
		}

		return true;
	}

	public function end_fast($session_id, $user_id) {
		if (!is_user_logged_in() || $user_id !== get_current_user_id()) {
			return new WP_Error('unauthorized', __('Unauthorized action', 'fasting-tracker'));
		}

		$fast = $this->database->get_active_fast($user_id);
		if (!$fast || (int)$fast->id !== absint($session_id)) {
			return new WP_Error('invalid_session', __('Invalid fast session', 'fasting-tracker'));
		}

		$elapsed = $this->calculate_elapsed_time($fast);
		$this->check_and_save_milestones($session_id, $elapsed);

		$result = $this->database->end_fast($session_id, (int) $elapsed);
		if (!$result) {
			return new WP_Error('db_error', __('Failed to end fast', 'fasting-tracker'));
		}

		return array(
			'duration' => $elapsed,
			'milestones_achieved' => $this->get_achieved_milestones($elapsed),
		);
	}

	private function check_and_save_milestones($session_id, $elapsed_hours) {
		foreach ($this->milestones as $hours => $badge) {
			if ($elapsed_hours >= $hours) {
				$this->database->save_milestone($session_id, $hours, $badge);
			}
		}
	}

	private function get_achieved_milestones($elapsed_hours) {
		$achieved = array();
		foreach ($this->milestones as $hours => $badge) {
			if ($elapsed_hours >= $hours) {
				$achieved[] = array(
					'hours' => $hours,
					'badge' => $badge,
				);
			}
		}
		return $achieved;
	}

	private function get_current_phase($hours) {
		if ($hours < 12) {
			return array(
				'name' => 'Glycogen Depletion',
				'description' => 'Initial hunger expected, body using stored glycogen',
				'color' => '#ff8c00',
			);
		} elseif ($hours < 24) {
			return array(
				'name' => 'Ketosis Begins',
				'description' => 'Fat burning starts, ketone production begins',
				'color' => '#ffd700',
			);
		} elseif ($hours < 36) {
			return array(
				'name' => 'Deep Ketosis',
				'description' => 'Full ketosis active, autophagy accelerates',
				'color' => '#90ee90',
			);
		} elseif ($hours < 48) {
			return array(
				'name' => 'Peak Metabolic State',
				'description' => 'Highest ketone production, mental clarity peaks',
				'color' => '#00ff00',
			);
		} else {
			return array(
				'name' => 'Extended Fasting',
				'description' => 'Sustained ketosis, cellular repair in full swing',
				'color' => '#006400',
			);
		}
	}

	private function get_next_milestone($current_hours) {
		foreach ($this->milestones as $hours => $badge) {
			if ($current_hours < $hours) {
				return array(
					'hours' => $hours,
					'badge' => $badge,
					'hours_remaining' => round($hours - $current_hours, 2),
				);
			}
		}
		return null;
	}

	public function get_phase_info($hours) {
		return $this->get_current_phase($hours);
	}

	public function get_milestone_info() {
		$info = array();
		foreach ($this->milestones as $hours => $badge) {
			$info[$hours] = array(
				'hours' => $hours,
				'badge' => $badge,
				'description' => $this->get_milestone_description($hours),
			);
		}
		return $info;
	}

	private function get_milestone_description($hours) {
		$descriptions = array(
			12 => 'Liver glycogen depletes, body shifts to fat burning, ketone production starts',
			24 => 'Full ketosis active, autophagy accelerates, fat burning increases significantly',
			36 => 'Highest ketone production, growth hormone increases, inflammation decreases',
			48 => 'Sustained ketosis and autophagy, cellular repair in full swing',
			72 => 'Extended fasting complete, complete cellular reset achieved',
		);
		return $descriptions[$hours] ?? '';
	}
}
