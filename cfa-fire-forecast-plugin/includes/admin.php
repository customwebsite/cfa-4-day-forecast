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
        add_action('wp_ajax_cfa_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_cfa_clear_logs', array($this, 'ajax_clear_logs'));
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
        
        // General Settings Section
        add_settings_section(
            'cfa_fire_forecast_section',
            __('General Settings', 'cfa-fire-forecast'),
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
        
        // Display Settings Section
        add_settings_section(
            'cfa_display_section',
            __('Display Settings', 'cfa-fire-forecast'),
            array($this, 'display_section_callback'),
            'cfa_fire_forecast'
        );
        
        add_settings_field(
            'color_scheme',
            __('Color Scheme', 'cfa-fire-forecast'),
            array($this, 'color_scheme_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        add_settings_field(
            'show_rating_scale',
            __('Show Fire Danger Rating Scale', 'cfa-fire-forecast'),
            array($this, 'show_rating_scale_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        add_settings_field(
            'show_tfb_indicator',
            __('Show Total Fire Ban Indicator', 'cfa-fire-forecast'),
            array($this, 'show_tfb_indicator_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        add_settings_field(
            'show_last_updated',
            __('Show Last Updated Time', 'cfa-fire-forecast'),
            array($this, 'show_last_updated_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        add_settings_field(
            'show_refresh_button',
            __('Show Manual Refresh Button', 'cfa-fire-forecast'),
            array($this, 'show_refresh_button_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        add_settings_field(
            'header_text',
            __('Custom Header Text', 'cfa-fire-forecast'),
            array($this, 'header_text_render'),
            'cfa_fire_forecast',
            'cfa_display_section'
        );
        
        // Layout Settings Section
        add_settings_section(
            'cfa_layout_section',
            __('Layout Settings', 'cfa-fire-forecast'),
            array($this, 'layout_section_callback'),
            'cfa_fire_forecast'
        );
        
        add_settings_field(
            'display_format',
            __('Display Format', 'cfa-fire-forecast'),
            array($this, 'display_format_render'),
            'cfa_fire_forecast',
            'cfa_layout_section'
        );
        
        add_settings_field(
            'forecast_days',
            __('Number of Forecast Days', 'cfa-fire-forecast'),
            array($this, 'forecast_days_render'),
            'cfa_fire_forecast',
            'cfa_layout_section'
        );
        
        add_settings_field(
            'table_header_color',
            __('Table Header Background Color', 'cfa-fire-forecast'),
            array($this, 'table_header_color_render'),
            'cfa_fire_forecast',
            'cfa_layout_section'
        );
        
        add_settings_field(
            'border_style',
            __('Border Style', 'cfa-fire-forecast'),
            array($this, 'border_style_render'),
            'cfa_fire_forecast',
            'cfa_layout_section'
        );
        
        add_settings_field(
            'responsive_breakpoint',
            __('Mobile Responsive', 'cfa-fire-forecast'),
            array($this, 'responsive_breakpoint_render'),
            'cfa_fire_forecast',
            'cfa_layout_section'
        );
        
        // Logging Settings Section
        add_settings_section(
            'cfa_logging_section',
            __('Logging Settings', 'cfa-fire-forecast'),
            array($this, 'logging_section_callback'),
            'cfa_fire_forecast'
        );
        
        add_settings_field(
            'enable_logging',
            __('Enable Data Fetch Logging', 'cfa-fire-forecast'),
            array($this, 'enable_logging_render'),
            'cfa_fire_forecast',
            'cfa_logging_section'
        );
        
        add_settings_field(
            'log_retention',
            __('Log Retention Period', 'cfa-fire-forecast'),
            array($this, 'log_retention_render'),
            'cfa_fire_forecast',
            'cfa_logging_section'
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
     * Display Settings - Color Scheme
     */
    public function color_scheme_render() {
        $options = get_option('cfa_fire_forecast_options');
        $color_scheme = isset($options['color_scheme']) ? $options['color_scheme'] : 'official';
        ?>
        <select name='cfa_fire_forecast_options[color_scheme]'>
            <option value='official' <?php selected($color_scheme, 'official'); ?>>
                <?php _e('Official CFA Colors', 'cfa-fire-forecast'); ?>
            </option>
            <option value='high_contrast' <?php selected($color_scheme, 'high_contrast'); ?>>
                <?php _e('High Contrast', 'cfa-fire-forecast'); ?>
            </option>
            <option value='minimal' <?php selected($color_scheme, 'minimal'); ?>>
                <?php _e('Minimal/Grayscale', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Choose the color scheme for fire danger ratings.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Display Settings - Show Rating Scale
     */
    public function show_rating_scale_render() {
        $options = get_option('cfa_fire_forecast_options');
        $show_scale = isset($options['show_rating_scale']) ? $options['show_rating_scale'] : 'yes';
        ?>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[show_rating_scale]' value='yes' <?php checked($show_scale, 'yes'); ?>>
            <?php _e('Display fire danger ratings scale legend', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Shows a visual guide explaining each fire danger rating level.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Display Settings - Show TFB Indicator
     */
    public function show_tfb_indicator_render() {
        $options = get_option('cfa_fire_forecast_options');
        $show_tfb = isset($options['show_tfb_indicator']) ? $options['show_tfb_indicator'] : 'yes';
        ?>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[show_tfb_indicator]' value='yes' <?php checked($show_tfb, 'yes'); ?>>
            <?php _e('Display Total Fire Ban indicator', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Shows a warning icon when a Total Fire Ban is in effect.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Display Settings - Show Last Updated
     */
    public function show_last_updated_render() {
        $options = get_option('cfa_fire_forecast_options');
        $show_updated = isset($options['show_last_updated']) ? $options['show_last_updated'] : 'yes';
        ?>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[show_last_updated]' value='yes' <?php checked($show_updated, 'yes'); ?>>
            <?php _e('Display last updated timestamp', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Shows when the fire data was last refreshed.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Display Settings - Show Refresh Button
     */
    public function show_refresh_button_render() {
        $options = get_option('cfa_fire_forecast_options');
        $show_button = isset($options['show_refresh_button']) ? $options['show_refresh_button'] : 'yes';
        ?>
        <input type='hidden' name='cfa_fire_forecast_options[show_refresh_button]' value='no'>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[show_refresh_button]' value='yes' <?php checked($show_button, 'yes'); ?>>
            <?php _e('Display manual refresh button', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Allows users to manually refresh the fire data. Data updates automatically twice daily regardless.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Display Settings - Header Text
     */
    public function header_text_render() {
        $options = get_option('cfa_fire_forecast_options');
        $header_text = isset($options['header_text']) ? $options['header_text'] : 'Fire Danger Forecast';
        ?>
        <input type='text' name='cfa_fire_forecast_options[header_text]' value='<?php echo esc_attr($header_text); ?>' style="width: 400px;" maxlength="100">
        <p class="description"><?php _e('Custom heading text to display above the forecast. Leave blank to hide header.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Layout Settings - Display Format
     */
    public function display_format_render() {
        $options = get_option('cfa_fire_forecast_options');
        $format = isset($options['display_format']) ? $options['display_format'] : 'table';
        ?>
        <select name='cfa_fire_forecast_options[display_format]'>
            <option value='table' <?php selected($format, 'table'); ?>>
                <?php _e('Table View', 'cfa-fire-forecast'); ?>
            </option>
            <option value='cards' <?php selected($format, 'cards'); ?>>
                <?php _e('Card View', 'cfa-fire-forecast'); ?>
            </option>
            <option value='compact' <?php selected($format, 'compact'); ?>>
                <?php _e('Compact List', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Choose how to display the fire forecast data.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Layout Settings - Forecast Days
     */
    public function forecast_days_render() {
        $options = get_option('cfa_fire_forecast_options');
        $days = isset($options['forecast_days']) ? $options['forecast_days'] : '4';
        ?>
        <select name='cfa_fire_forecast_options[forecast_days]'>
            <option value='1' <?php selected($days, '1'); ?>>
                <?php _e('Today Only', 'cfa-fire-forecast'); ?>
            </option>
            <option value='2' <?php selected($days, '2'); ?>>
                <?php _e('2 Days', 'cfa-fire-forecast'); ?>
            </option>
            <option value='3' <?php selected($days, '3'); ?>>
                <?php _e('3 Days', 'cfa-fire-forecast'); ?>
            </option>
            <option value='4' <?php selected($days, '4'); ?>>
                <?php _e('4 Days (Default)', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Number of forecast days to display.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Layout Settings - Table Header Color
     */
    public function table_header_color_render() {
        $options = get_option('cfa_fire_forecast_options');
        $color = isset($options['table_header_color']) ? $options['table_header_color'] : '#004080';
        ?>
        <input type='color' name='cfa_fire_forecast_options[table_header_color]' id='cfa_header_color' value='<?php echo esc_attr($color); ?>'>
        <input type='text' name='cfa_fire_forecast_options[table_header_color_text]' id='cfa_header_color_text' value='<?php echo esc_attr($color); ?>' readonly style="width: 100px; margin-left: 10px;">
        <button type='button' class='button' onclick='cfaResetHeaderColor()' style="margin-left: 10px;">Reset to Default</button>
        <p class="description"><?php _e('Background color for table headers. Default: #004080 (CFA Blue)', 'cfa-fire-forecast'); ?></p>
        <script>
        document.getElementById('cfa_header_color').addEventListener('input', function() {
            document.getElementById('cfa_header_color_text').value = this.value;
        });
        function cfaResetHeaderColor() {
            document.getElementById('cfa_header_color').value = '#004080';
            document.getElementById('cfa_header_color_text').value = '#004080';
        }
        </script>
        <?php
    }
    
    /**
     * Layout Settings - Border Style
     */
    public function border_style_render() {
        $options = get_option('cfa_fire_forecast_options');
        $border = isset($options['border_style']) ? $options['border_style'] : 'normal';
        ?>
        <select name='cfa_fire_forecast_options[border_style]'>
            <option value='none' <?php selected($border, 'none'); ?>>
                <?php _e('No Borders', 'cfa-fire-forecast'); ?>
            </option>
            <option value='minimal' <?php selected($border, 'minimal'); ?>>
                <?php _e('Minimal (Light)', 'cfa-fire-forecast'); ?>
            </option>
            <option value='normal' <?php selected($border, 'normal'); ?>>
                <?php _e('Normal (Default)', 'cfa-fire-forecast'); ?>
            </option>
            <option value='bold' <?php selected($border, 'bold'); ?>>
                <?php _e('Bold', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('Border styling for tables and cards.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Layout Settings - Responsive Breakpoint
     */
    public function responsive_breakpoint_render() {
        $options = get_option('cfa_fire_forecast_options');
        $responsive = isset($options['responsive_breakpoint']) ? $options['responsive_breakpoint'] : 'yes';
        ?>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[responsive_breakpoint]' value='yes' <?php checked($responsive, 'yes'); ?>>
            <?php _e('Enable mobile responsive layout', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Automatically adjusts layout for mobile devices (breakpoint: 768px).', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo __('Configure general plugin settings.', 'cfa-fire-forecast');
    }
    
    /**
     * Display section callback
     */
    public function display_section_callback() {
        echo __('Customize the visual appearance of the fire forecast display.', 'cfa-fire-forecast');
    }
    
    /**
     * Layout section callback
     */
    public function layout_section_callback() {
        echo __('Configure layout and formatting options.', 'cfa-fire-forecast');
    }
    
    /**
     * Logging section callback
     */
    public function logging_section_callback() {
        echo __('Configure data fetch logging and retention settings.', 'cfa-fire-forecast');
    }
    
    /**
     * Logging Settings - Enable Logging
     */
    public function enable_logging_render() {
        $options = get_option('cfa_fire_forecast_options');
        $enable_logging = isset($options['enable_logging']) ? $options['enable_logging'] : 'yes';
        ?>
        <input type='hidden' name='cfa_fire_forecast_options[enable_logging]' value='no'>
        <label>
            <input type='checkbox' name='cfa_fire_forecast_options[enable_logging]' value='yes' <?php checked($enable_logging, 'yes'); ?>>
            <?php _e('Enable logging of data fetch requests', 'cfa-fire-forecast'); ?>
        </label>
        <p class="description"><?php _e('Track all data fetch requests with timestamps, status, and response times. Logging can be viewed in the section below.', 'cfa-fire-forecast'); ?></p>
        <?php
    }
    
    /**
     * Logging Settings - Log Retention
     */
    public function log_retention_render() {
        $options = get_option('cfa_fire_forecast_options');
        $retention = isset($options['log_retention']) ? $options['log_retention'] : '28days';
        ?>
        <select name='cfa_fire_forecast_options[log_retention]'>
            <option value='7days' <?php selected($retention, '7days'); ?>>
                <?php _e('7 Days', 'cfa-fire-forecast'); ?>
            </option>
            <option value='28days' <?php selected($retention, '28days'); ?>>
                <?php _e('28 Days (Default)', 'cfa-fire-forecast'); ?>
            </option>
            <option value='1year' <?php selected($retention, '1year'); ?>>
                <?php _e('1 Year', 'cfa-fire-forecast'); ?>
            </option>
            <option value='indefinite' <?php selected($retention, 'indefinite'); ?>>
                <?php _e('Indefinite (Keep All Logs)', 'cfa-fire-forecast'); ?>
            </option>
        </select>
        <p class="description"><?php _e('How long to keep fetch logs before automatic cleanup. Older logs are deleted when new logs are added.', 'cfa-fire-forecast'); ?></p>
        <?php
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
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #00a32a;">
                <h3><?php _e('Test Data Script', 'cfa-fire-forecast'); ?></h3>
                <p><?php _e('Use the test script to verify that the RSS feed data is being fetched correctly from the CFA website.', 'cfa-fire-forecast'); ?></p>
                <p>
                    <a href="<?php echo esc_url(CFA_FIRE_FORECAST_PLUGIN_URL . 'test-cfa-data.php'); ?>" 
                       class="button button-primary" 
                       target="_blank">
                        <?php _e('Open Test Script', 'cfa-fire-forecast'); ?>
                    </a>
                </p>
                <p style="margin-top: 10px; font-size: 12px; color: #666;">
                    <?php _e('The test script shows raw data from CFA RSS feeds for all districts. Useful for troubleshooting data issues.', 'cfa-fire-forecast'); ?>
                </p>
            </div>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #f0ad4e;">
                <h3><?php _e('Data Fetch Logs', 'cfa-fire-forecast'); ?></h3>
                <p><?php _e('View the last 50 data fetch requests to CFA RSS feeds. Useful for debugging connection issues.', 'cfa-fire-forecast'); ?></p>
                
                <?php
                $logs = get_option('cfa_fire_forecast_fetch_logs', array());
                
                if (empty($logs)): ?>
                    <p style="color: #666; font-style: italic;"><?php _e('No fetch logs available yet. Logs will appear here after the plugin fetches data from CFA RSS feeds.', 'cfa-fire-forecast'); ?></p>
                <?php else:
                    $logs = array_reverse($logs); // Show newest first
                    ?>
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 15px;">
                        <table class="wp-list-table widefat fixed striped" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="width: 160px;"><?php _e('Timestamp', 'cfa-fire-forecast'); ?></th>
                                    <th style="width: 200px;"><?php _e('District', 'cfa-fire-forecast'); ?></th>
                                    <th style="width: 80px;"><?php _e('Status', 'cfa-fire-forecast'); ?></th>
                                    <th style="width: 100px;"><?php _e('Response Time', 'cfa-fire-forecast'); ?></th>
                                    <th><?php _e('Message', 'cfa-fire-forecast'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($log['timestamp']); ?></td>
                                    <td><?php echo esc_html(ucwords(str_replace('-', ' ', $log['district']))); ?></td>
                                    <td>
                                        <?php if ($log['success']): ?>
                                            <span style="color: #28a745; font-weight: bold;">✓ Success</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232; font-weight: bold;">✗ Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($log['response_time']); ?> ms</td>
                                    <td>
                                        <?php echo esc_html($log['message']); ?>
                                        <?php if (!empty($log['url'])): ?>
                                            <br><small style="color: #666;"><?php echo esc_html($log['url']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top: 15px;">
                        <button type="button" class="button button-secondary" onclick="cfaClearLogs()">
                            <?php _e('Clear Logs', 'cfa-fire-forecast'); ?>
                        </button>
                        <span id="log-status" style="margin-left: 10px;"></span>
                    </p>
                <?php endif; ?>
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
            
            function cfaClearLogs() {
                var button = event.target;
                var status = document.getElementById('log-status');
                
                if (!confirm('<?php _e('Are you sure you want to clear all fetch logs?', 'cfa-fire-forecast'); ?>')) {
                    return;
                }
                
                button.disabled = true;
                button.textContent = '<?php _e('Clearing...', 'cfa-fire-forecast'); ?>';
                
                jQuery.post(ajaxurl, {
                    action: 'cfa_clear_logs',
                    nonce: '<?php echo wp_create_nonce('cfa_clear_logs'); ?>'
                }, function(response) {
                    if (response.success) {
                        status.innerHTML = '<span style="color: green;"><?php _e('Logs cleared successfully!', 'cfa-fire-forecast'); ?></span>';
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        status.innerHTML = '<span style="color: red;"><?php _e('Error clearing logs.', 'cfa-fire-forecast'); ?></span>';
                    }
                    
                    button.disabled = false;
                    button.textContent = '<?php _e('Clear Logs', 'cfa-fire-forecast'); ?>';
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to clear cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('cfa_clear_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Clear all CFA fire forecast transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cfa_fire_forecast_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cfa_fire_forecast_%'");
        
        wp_send_json_success('Cache cleared successfully');
    }
    
    /**
     * AJAX handler to clear fetch logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('cfa_clear_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        delete_option('cfa_fire_forecast_fetch_logs');
        
        wp_send_json_success('Logs cleared successfully');
    }
}