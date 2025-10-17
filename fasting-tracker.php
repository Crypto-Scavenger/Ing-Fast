<?php
/**
 * Plugin Name: Fasting Tracker
 * Plugin URI: https://example.com/fasting-tracker
 * Description: Track fasting periods with visual progress meter, milestone achievements, and comprehensive stats up to 72 hours
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: fasting-tracker
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.8
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
	exit;
}

define('FASTING_TRACKER_VERSION', '1.0.0');
define('FASTING_TRACKER_DIR', plugin_dir_path(__FILE__));
define('FASTING_TRACKER_URL', plugin_dir_url(__FILE__));
define('FASTING_TRACKER_BASENAME', plugin_basename(__FILE__));

require_once FASTING_TRACKER_DIR . 'includes/class-fasting-database.php';
require_once FASTING_TRACKER_DIR . 'includes/class-fasting-core.php';
require_once FASTING_TRACKER_DIR . 'includes/class-fasting-api.php';
require_once FASTING_TRACKER_DIR . 'includes/class-fasting-public.php';

if (is_admin()) {
	require_once FASTING_TRACKER_DIR . 'includes/class-fasting-admin.php';
}

function fasting_tracker_init() {
	$database = new Fasting_Tracker_Database();
	$core = new Fasting_Tracker_Core($database);
	$api = new Fasting_Tracker_API($database);
	$public = new Fasting_Tracker_Public($database);
	
	if (is_admin()) {
		$admin = new Fasting_Tracker_Admin($database);
	}
}
add_action('plugins_loaded', 'fasting_tracker_init');

register_activation_hook(__FILE__, array('Fasting_Tracker_Database', 'activate'));
