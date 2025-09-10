<?php
/**
 * CFA Fire Forecast Scheduler
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Fire_Forecast_Scheduler {
    
    public function __construct() {
        add_action('cfa_fire_forecast_update', array($this, 'scheduled_update'));
        add_action('wp_ajax_cfa_clear_cache', array($this, 'clear_cache'));
    }
    
    /**
     * Scheduled update of fire data
     */
    public function scheduled_update() {
        $options = get_option('cfa_fire_forecast_options');
        $district = isset($options['district']) ? $options['district'] : 'north-central-fire-district';
        
        // Load scraper and fetch fresh data
        require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/scraper.php';
        $scraper = new CFA_Fire_Forecast_Scraper();
        $data = $scraper->scrape_fire_data($district);
        
        // Update cache
        $cache_key = 'cfa_fire_forecast_data_' . $district;
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 3600;
        set_transient($cache_key, $data, $cache_duration);
        
        // Log the update
        error_log('CFA Fire Forecast: Scheduled update completed for ' . $district);
    }
    
    /**
     * AJAX handler to clear cache
     */
    public function clear_cache() {
        check_ajax_referer('cfa_clear_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get all possible cache keys and clear them
        $districts = array(
            'north-central-fire-district',
            'south-west-fire-district',
            'northern-country-fire-district',
            'north-east-fire-district',
            'central-fire-district'
        );
        
        foreach ($districts as $district) {
            delete_transient('cfa_fire_forecast_data_' . $district);
        }
        
        wp_send_json_success(array('message' => 'Cache cleared successfully'));
    }
    
    /**
     * Setup custom cron schedules
     */
    public static function setup_schedules() {
        // This is called from the main plugin file
        // Custom schedules are already defined there
    }
}