<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-fasting-database.php';

$database = new Fasting_Tracker_Database();
$delete_data = $database->get_setting('delete_data_on_uninstall', '0');

if ('1' === $delete_data) {
	$database->delete_all_data();
}
