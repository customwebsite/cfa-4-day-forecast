<?php
/**
 * CFA Fire Forecast Admin Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Fire_Forecast_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'CFA Fire Forecast Settings',
            'CFA Fire Forecast',
            'manage_options',
            'cfa-fire-forecast',
            array($this, 'options_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting('cfa_fire_forecast', 'cfa_fire_forecast_options');
        
        add_settings_section(
            'cfa_fire_forecast_section',
            __('CFA Fire Forecast Settings', 'cfa-fire-forecast'),
            array($this, 'settings_section_callback'),
            'cfa_fire_forecast'
        );
        
        add_settings_field(
            'district',
            __('Fire District', 'cfa-fire-forecast'),
            array($this, 'district_render'),
            'cfa_fire_forecast',
            'cfa_fire_forecast_section'
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'cfa-fire-forecast'),
            array($this, 'cache_duration_render'),
            'cfa_fire_forecast',
            'cfa_fire_forecast_section'
        );
        
        add_settings_field(
            'update_frequency',
            __('Update Frequency', 'cfa-fire-forecast'),
            array($this, 'update_frequency_render'),
            'cfa_fire_forecast',
            'cfa_fire_forecast_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if ('settings_page_cfa-fire-forecast' !== $hook) {
            return;
        }
        
        wp_enqueue_script('jquery');
    }
    
    /**
     * Render district field
     */
    public function district_render() {
        $options = get_option('cfa_fire_forecast_options');
        $current_district = isset($options['district']) ? $options['district'] : 'north-central-fire-district';
        
        $districts = array(
            'north-central-fire-district' => 'North Central Fire District',
            'south-west-fire-district' => 'South West Fire District',
            'northern-country-fire-district' => 'Northern Country Fire District',
            'north-east-fire-district' => 'North East Fire District',
            'central-fire-district' => 'Central Fire District'
        );
        ?>
        <select name='cfa_fire_forecast_options[district]'>
            <?php foreach ($districts as $value => $label): ?>
            <option value='<?php echo esc_attr($value); ?>' <?php selected($current_district, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e('Select the CFA fire district to display data for.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Render cache duration field
     */
    public function cache_duration_render() {
        $options = get_option('cfa_fire_forecast_options');
        $cache_duration = isset($options['cache_duration']) ? $options['cache_duration'] : 3600;
        ?>
        <input type='number' name='cfa_fire_forecast_options[cache_duration]' value='<?php echo esc_attr($cache_duration); ?>' min="300" max="43200">
        <p class="description"><?php _e('How long to cache fire data (300-43200 seconds). Default is 3600 (1 hour).', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Render update frequency field
     */
    public function update_frequency_render() {
        $options = get_option('cfa_fire_forecast_options');
        $frequency = isset($options['update_frequency']) ? $options['update_frequency'] : 'twice_daily';
        ?>
        <select name='cfa_fire_forecast_options[update_frequency]'>
            <option value='twice_daily' <?php selected($frequency, 'twice_daily'); ?>>
                <?php _e('Twice Daily (6 AM & 6 PM)', 'cfa-fire-forecast'); ?>
            </option>
            <option value='hourly' <?php selected($frequency, 'hourly'); ?>>
                <?php _e('Every Hour', 'cfa-fire-forecast'); ?>
            </option>
            <option value='daily' <?php selected($frequency, 'daily'); ?>>
                <?php _e('Once Daily', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('How often to automatically update fire data.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo __('Configure the CFA Fire Forecast plugin settings below.', 'cfa-fire-forecast');
    }
    
    /**
     * Options page
     */
    public function options_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('CFA Fire Forecast Settings', 'cfa-fire-forecast'); ?></h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
                <h3><?php _e('How to Use', 'cfa-fire-forecast'); ?></h3>
                <p><?php _e('Add the fire forecast to any post or page using the shortcode:', 'cfa-fire-forecast'); ?></p>
                <code>[cfa_fire_forecast]</code>
                
                <h4><?php _e('Shortcode Options:', 'cfa-fire-forecast'); ?></h4>
                <ul>
                    <li><code>[cfa_fire_forecast district="north-central-fire-district"]</code> - <?php _e('Specify a different district', 'cfa-fire-forecast'); ?></li>
                    <li><code>[cfa_fire_forecast show_scale="false"]</code> - <?php _e('Hide the fire danger ratings scale', 'cfa-fire-forecast'); ?></li>
                    <li><code>[cfa_fire_forecast auto_refresh="false"]</code> - <?php _e('Disable auto-refresh functionality', 'cfa-fire-forecast'); ?></li>
                </ul>
            </div>
            
            <form action='options.php' method='post'>
                <?php
                settings_fields('cfa_fire_forecast');
                do_settings_sections('cfa_fire_forecast');
                submit_button();
                ?>
            </form>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #dc3232;">
                <h3><?php _e('Cache Management', 'cfa-fire-forecast'); ?></h3>
                <p><?php _e('Use the button below to clear the cached fire data and force a fresh update.', 'cfa-fire-forecast'); ?></p>
                <button type="button" class="button button-secondary" onclick="cfaClearCache()">
                    <?php _e('Clear Cache', 'cfa-fire-forecast'); ?>
                </button>
                <div id="cache-status" style="margin-top: 10px;"></div>
            </div>
            
            <script>
            function cfaClearCache() {
                var button = event.target;
                var status = document.getElementById('cache-status');
                
                button.disabled = true;
                button.textContent = '<?php _e('Clearing...', 'cfa-fire-forecast'); ?>';
                
                jQuery.post(ajaxurl, {
                    action: 'cfa_clear_cache',
                    nonce: '<?php echo wp_create_nonce('cfa_clear_cache'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.innerHTML = '<span style="color: green;"><?php _e('Cache cleared successfully!', 'cfa-fire-forecast'); ?></span>';
                    } else {
                        status.innerHTML = '<span style="color: red;"><?php _e('Error clearing cache.', 'cfa-fire-forecast'); ?></span>';
                    }
                    
                    button.disabled = false;
                    button.textContent = '<?php _e('Clear Cache', 'cfa-fire-forecast'); ?>';
                    
                    setTimeout(function() {
                        status.innerHTML = '';
                    }, 3000);
                });
            }
            </script>
        </div>
        <?php
    }
}