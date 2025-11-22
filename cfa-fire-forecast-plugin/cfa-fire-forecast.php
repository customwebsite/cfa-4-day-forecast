<?php
/**
 * Plugin Name: CFA Fire Forecast
 * Plugin URI: https://github.com/customwebsite/cfa-4-day-forecast
 * Description: Display CFA (Country Fire Authority) fire danger ratings and forecasts for Victoria, Australia. Shows 4-day fire danger forecast with automatic updates twice daily.
 * Version: 4.8.0
 * Author: Shaun Haddrill
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cfa-fire-forecast
 * GitHub Plugin URI: https://github.com/customwebsite/cfa-4-day-forecast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CFA_FIRE_FORECAST_VERSION', '4.8.0');
define('CFA_FIRE_FORECAST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CFA_FIRE_FORECAST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize Plugin Update Checker
require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$cfa_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/customwebsite/cfa-4-day-forecast/',
    __FILE__,
    'cfa-fire-forecast'
);

// Enable release assets (use uploaded zip file instead of source code)
$cfa_update_checker->getVcsApi()->enableReleaseAssets();

// Set the branch that contains the stable release (default is 'main')
$cfa_update_checker->setBranch('main');

/**
 * Main plugin class
 */
class CFA_Fire_Forecast {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('cfa-fire-forecast', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        $this->init_admin();
        $this->init_frontend();
        $this->init_scheduler();
    }
    
    public function activate() {
        // Set default options
        if (!get_option('cfa_fire_forecast_options')) {
            add_option('cfa_fire_forecast_options', array(
                'district' => 'north-central-fire-district',
                'cache_duration' => 3600, // 1 hour
                'update_frequency' => 'twice_daily' // 6am and 6pm
            ));
        }
        
        // Schedule cron events
        if (!wp_next_scheduled('cfa_fire_forecast_update')) {
            wp_schedule_event(time(), 'twice_daily', 'cfa_fire_forecast_update');
        }
        
        // Create database table if needed (for future use)
        $this->create_database_table();
    }
    
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('cfa_fire_forecast_update');
        
        // Clear cached data
        delete_transient('cfa_fire_forecast_data');
    }
    
    private function init_admin() {
        if (is_admin()) {
            require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/admin.php';
            new CFA_Fire_Forecast_Admin();
        }
    }
    
    private function init_frontend() {
        require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/frontend.php';
        new CFA_Fire_Forecast_Frontend();
    }
    
    private function init_scheduler() {
        require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/scheduler.php';
        new CFA_Fire_Forecast_Scheduler();
    }
    
    private function create_database_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cfa_fire_forecast';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            forecast_date date NOT NULL,
            district varchar(255) NOT NULL,
            fire_danger_rating varchar(50) NOT NULL,
            total_fire_ban tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY forecast_date (forecast_date),
            KEY district (district)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
new CFA_Fire_Forecast();

/**
 * Add custom cron schedule for twice daily updates
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['twice_daily'] = array(
        'interval' => 12 * HOUR_IN_SECONDS, // Every 12 hours
        'display'  => __('Twice Daily', 'cfa-fire-forecast')
    );
    return $schedules;
});