<?php
/**
 * CFA Fire Forecast Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Fire_Forecast_Frontend {
    
    public function __construct() {
        add_shortcode('cfa_fire_forecast', array($this, 'display_forecast'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_refresh_fire_data', array($this, 'ajax_refresh_data'));
        add_action('wp_ajax_nopriv_refresh_fire_data', array($this, 'ajax_refresh_data'));
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'cfa-fire-forecast-style',
            CFA_FIRE_FORECAST_PLUGIN_URL . 'assets/css/style.css',
            array(),
            CFA_FIRE_FORECAST_VERSION
        );
        
        wp_enqueue_script(
            'cfa-fire-forecast-script',
            CFA_FIRE_FORECAST_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            CFA_FIRE_FORECAST_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('cfa-fire-forecast-script', 'cfaAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cfa_fire_forecast_nonce')
        ));
    }
    
    /**
     * Display fire forecast shortcode
     */
    public function display_forecast($atts) {
        $atts = shortcode_atts(array(
            'district' => 'north-central-fire-district',
            'districts' => '',
            'show_scale' => 'true',
            'auto_refresh' => 'true',
            'layout' => '' // Options: table, cards, compact (empty = use admin setting)
        ), $atts);
        
        // Determine if multiple districts requested
        $districts = !empty($atts['districts']) ? $atts['districts'] : $atts['district'];
        $is_multi_district = strpos($districts, ',') !== false;
        
        $data = $this->get_cached_data($districts, $is_multi_district);
        
        if (!$data || empty($data['data'])) {
            return '<div class="cfa-fire-forecast-error">Unable to load fire danger data. Please try again later.</div>';
        }
        
        ob_start();
        if ($is_multi_district) {
            $this->render_multi_district_forecast($data, $atts);
        } else {
            $this->render_forecast($data, $atts);
        }
        return ob_get_clean();
    }
    
    /**
     * Get cached fire data
     */
    private function get_cached_data($districts, $is_multi_district = false) {
        if ($is_multi_district) {
            $cache_key = 'cfa_fire_forecast_multi_' . md5($districts);
            $data = get_transient($cache_key);
            
            if (false === $data) {
                // Load scraper and fetch fresh data for multiple districts
                require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/scraper.php';
                $scraper = new CFA_Fire_Forecast_Scraper();
                $data = $scraper->scrape_multiple_districts($districts);
                
                // Cache for 1 hour
                set_transient($cache_key, $data, HOUR_IN_SECONDS);
            }
        } else {
            $cache_key = 'cfa_fire_forecast_data_' . $districts;
            $data = get_transient($cache_key);
            
            if (false === $data) {
                // Load scraper and fetch fresh data
                require_once CFA_FIRE_FORECAST_PLUGIN_DIR . 'includes/scraper.php';
                $scraper = new CFA_Fire_Forecast_Scraper();
                $data = $scraper->scrape_fire_data($districts);
                
                // Cache for 1 hour
                set_transient($cache_key, $data, HOUR_IN_SECONDS);
            }
        }
        
        return $data;
    }
    
    /**
     * Render multi-district forecast HTML as table
     */
    private function render_multi_district_forecast($data, $atts) {
        $districts = $data['districts'];
        $district_data = $data['data'];
        $options = get_option('cfa_fire_forecast_options');
        
        // Use shortcode layout if specified, otherwise use admin setting
        if (!empty($atts['layout']) && in_array($atts['layout'], array('table', 'cards', 'compact'))) {
            $display_format = $atts['layout'];
        } else {
            $display_format = isset($options['display_format']) ? $options['display_format'] : 'table';
        }
        
        // Get color scheme
        $color_scheme = isset($options['color_scheme']) ? $options['color_scheme'] : 'official';
        ?>
        <div class="cfa-fire-forecast-container cfa-multi-district cfa-layout-<?php echo esc_attr($display_format); ?> cfa-scheme-<?php echo esc_attr($color_scheme); ?>" id="cfa-fire-forecast" data-districts="<?php echo esc_attr(implode(',', $districts)); ?>" data-multi="true">
            <div class="cfa-header">
                <div class="cfa-header-content">
                    <h2>Multi-District Fire Danger Forecast</h2>
                    <p>Fire Danger Ratings and Total Fire Bans - Victoria, Australia</p>
                </div>
            </div>
            
            <div class="cfa-container">
                <div class="cfa-info-links">
                    <h3>Important Information:</h3>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-fire-danger-ratings" target="_blank">Fire Danger Ratings</a>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-total-fire-bans" target="_blank">Total Fire Bans</a>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/total-fire-bans-and-ratings/can-i-or-cant-i" target="_blank">What you can and can't do</a>
                </div>

                <div class="cfa-status-bar">
                    <div class="cfa-status-item">
                        <div class="cfa-status-icon online"></div>
                        <span>Data loaded successfully</span>
                    </div>
                    <div class="cfa-status-item">
                        <span>Last updated: <?php 
                            $datetime = new DateTime($data['last_updated'], new DateTimeZone('UTC'));
                            $datetime->setTimezone(new DateTimeZone('Australia/Melbourne'));
                            echo esc_html($datetime->format('j F Y, g:i A')); 
                        ?> (Melbourne time)</span>
                    </div>
                    <?php 
                    $options = get_option('cfa_fire_forecast_options');
                    $show_refresh = isset($options['show_refresh_button']) ? $options['show_refresh_button'] : 'yes';
                    if ($atts['auto_refresh'] === 'true' && $show_refresh === 'yes'): ?>
                    <div class="cfa-status-item">
                        <button class="cfa-refresh-btn" onclick="cfaRefreshData()">Refresh Now</button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cfa-forecast-section">
                    <div class="cfa-forecast-header">
                        4 Day Fire Danger Forecast - <?php echo count($districts); ?> Districts
                    </div>
                    <div class="cfa-forecast-content">
                        <?php if ($display_format === 'table'): ?>
                        <div class="cfa-multi-district-table">
                            <table class="cfa-forecast-table">
                                <thead>
                                    <tr>
                                        <th class="district-header">District</th>
                                        <?php 
                                        // Get dates from first district
                                        $first_district = reset($district_data);
                                        if ($first_district && isset($first_district['forecast'])):
                                            foreach ($first_district['forecast'] as $index => $day): ?>
                                                <th class="day-header <?php echo $index === 0 ? 'today' : ''; ?>">
                                                    <div class="day-name"><?php echo esc_html($day['day']); ?></div>
                                                    <div class="day-date"><?php echo esc_html($day['date']); ?></div>
                                                </th>
                                            <?php endforeach;
                                        endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($districts as $district): 
                                        if (isset($district_data[$district]) && isset($district_data[$district]['forecast'])): ?>
                                        <tr class="district-row">
                                            <td class="district-name">
                                                <?php echo esc_html(ucwords(str_replace('-', ' ', $district))); ?>
                                            </td>
                                            <?php foreach ($district_data[$district]['forecast'] as $index => $day): ?>
                                            <td class="forecast-cell <?php echo $index === 0 ? 'today' : ''; ?>">
                                                <div class="cfa-fire-danger-badge rating-<?php echo esc_attr($this->get_rating_class($day['rating'])); ?>">
                                                    <?php echo esc_html($day['rating']); ?>
                                                </div>
                                                <?php if ($day['total_fire_ban']): ?>
                                                <div class="cfa-total-fire-ban-small">🔴 TFB</div>
                                                <?php endif; ?>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php endif;
                                    endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php elseif ($display_format === 'cards'): ?>
                        <div class="cfa-multi-district-cards">
                            <?php foreach ($districts as $district): 
                                if (isset($district_data[$district]) && isset($district_data[$district]['forecast'])): ?>
                                <div class="cfa-district-card">
                                    <h3 class="district-card-title"><?php echo esc_html(ucwords(str_replace('-', ' ', $district))); ?></h3>
                                    <div class="cfa-forecast-grid">
                                        <?php foreach ($district_data[$district]['forecast'] as $index => $day): ?>
                                        <div class="cfa-forecast-day <?php echo $index === 0 ? 'today' : ''; ?>">
                                            <div class="cfa-day-header"><?php echo esc_html($day['day']); ?></div>
                                            <div class="cfa-day-date"><?php echo esc_html($day['date']); ?></div>
                                            <div class="cfa-fire-danger-badge rating-<?php echo esc_attr($this->get_rating_class($day['rating'])); ?>">
                                                <?php echo esc_html($day['rating']); ?>
                                            </div>
                                            <?php if ($day['total_fire_ban']): ?>
                                            <div class="cfa-total-fire-ban">⚠️ TOTAL FIRE BAN</div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif;
                            endforeach; ?>
                        </div>
                        <?php else: // compact ?>
                        <div class="cfa-multi-district-compact">
                            <?php foreach ($districts as $district): 
                                if (isset($district_data[$district]) && isset($district_data[$district]['forecast'])): ?>
                                <div class="cfa-district-compact-section">
                                    <h3 class="district-compact-title"><?php echo esc_html(ucwords(str_replace('-', ' ', $district))); ?></h3>
                                    <div class="cfa-compact-list">
                                        <?php foreach ($district_data[$district]['forecast'] as $index => $day): ?>
                                        <div class="cfa-compact-item <?php echo $index === 0 ? 'today' : ''; ?>">
                                            <span class="compact-day"><?php echo esc_html($day['day']); ?> (<?php echo esc_html($day['date']); ?>)</span>
                                            <span class="cfa-fire-danger-badge rating-<?php echo esc_attr($this->get_rating_class($day['rating'])); ?>">
                                                <?php echo esc_html($day['rating']); ?>
                                            </span>
                                            <?php if ($day['total_fire_ban']): ?>
                                            <span class="cfa-tfb-badge">⚠️ TFB</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif;
                            endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php 
                $options = get_option('cfa_fire_forecast_options');
                $show_rating_scale = isset($options['show_rating_scale']) ? $options['show_rating_scale'] : 'yes';
                if ($atts['show_scale'] === 'true' && $show_rating_scale === 'yes'): 
                ?>
                <div class="cfa-fire-danger-scale">
                    <h3>Fire Danger Ratings Scale</h3>
                    <p>Understanding what each fire danger rating means and what you should do:</p>
                    <img src="https://www.cfa.vic.gov.au/images/UserUploadedImages/11/map-bar.png" 
                         alt="Fire Danger Ratings Scale"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none;" class="cfa-scale-fallback">
                        <p><strong>Fire Danger Rating Scale:</strong></p>
                        <p>🟢 <strong>Low-Moderate:</strong> Fire can start and spread. Be alert.</p>
                        <p>🟡 <strong>Moderate:</strong> Fires can spread. Be prepared and stay alert.</p>
                        <p>🟠 <strong>High:</strong> Fires will spread rapidly and be difficult to control. Be ready to act.</p>
                        <p>🔴 <strong>Extreme:</strong> Fires will spread rapidly and be extremely difficult to control. Take action now.</p>
                        <p>🟣 <strong>Catastrophic:</strong> If a fire starts it will be uncontrollable, unpredictable and fast moving. Leave bushfire risk areas immediately.</p>
                    </div>
                    <p><a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-fire-danger-ratings" target="_blank">Learn more about Fire Danger Ratings</a></p>
                </div>
                <?php endif; ?>

                <div class="cfa-emergency-notice">
                    ⚠️ <strong>EMERGENCY:</strong> In case of fire emergency, call <strong>000</strong> immediately
                </div>
            </div>

            <div class="cfa-source-footer">
                <p><strong>Data Sources:</strong> 
                <?php 
                $base_url = 'https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/';
                $source_links = array();
                foreach ($districts as $district) {
                    if (isset($district_data[$district])) {
                        $district_url = $base_url . $district;
                        $source_links[] = '<a href="' . esc_url($district_url) . '" target="_blank">' . 
                                         esc_html(ucwords(str_replace('-', ' ', $district))) . '</a>';
                    }
                }
                echo implode(', ', $source_links);
                ?>
                </p>
                <p>This information is for general reference only. Always check the official CFA website for the most current fire danger ratings and restrictions.</p>
                <p><small>Data automatically updated twice daily at 6 AM and 6 PM (Melbourne time)</small></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render forecast HTML
     */
    private function render_forecast($data, $atts) {
        $options = get_option('cfa_fire_forecast_options');
        
        // Use shortcode layout if specified, otherwise use admin setting
        if (!empty($atts['layout']) && in_array($atts['layout'], array('table', 'cards', 'compact'))) {
            $display_format = $atts['layout'];
        } else {
            $display_format = isset($options['display_format']) ? $options['display_format'] : 'table';
        }
        
        // Get color scheme
        $color_scheme = isset($options['color_scheme']) ? $options['color_scheme'] : 'official';
        ?>
        <div class="cfa-fire-forecast-container cfa-layout-<?php echo esc_attr($display_format); ?> cfa-scheme-<?php echo esc_attr($color_scheme); ?>" id="cfa-fire-forecast">
            <div class="cfa-header">
                <div class="cfa-header-content">
                    <h2><?php echo esc_html($data['data']['district']); ?></h2>
                    <p>Fire Danger Ratings and Total Fire Bans - Victoria, Australia</p>
                </div>
            </div>
            
            <div class="cfa-container">
                <div class="cfa-info-links">
                    <h3>Important Information:</h3>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-fire-danger-ratings" target="_blank">Fire Danger Ratings</a>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-total-fire-bans" target="_blank">Total Fire Bans</a>
                    <a href="https://www.cfa.vic.gov.au/warnings-restrictions/total-fire-bans-and-ratings/can-i-or-cant-i" target="_blank">What you can and can't do</a>
                </div>

                <div class="cfa-status-bar">
                    <div class="cfa-status-item">
                        <div class="cfa-status-icon online"></div>
                        <span>Data loaded successfully</span>
                    </div>
                    <div class="cfa-status-item">
                        <span>Last updated: <?php 
                            $datetime = new DateTime($data['last_updated'], new DateTimeZone('UTC'));
                            $datetime->setTimezone(new DateTimeZone('Australia/Melbourne'));
                            echo esc_html($datetime->format('j F Y, g:i A')); 
                        ?> (Melbourne time)</span>
                    </div>
                    <?php 
                    $show_refresh = isset($options['show_refresh_button']) ? $options['show_refresh_button'] : 'yes';
                    if ($atts['auto_refresh'] === 'true' && $show_refresh === 'yes'): ?>
                    <div class="cfa-status-item">
                        <button class="cfa-refresh-btn" onclick="cfaRefreshData()">Refresh Now</button>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="cfa-forecast-section">
                    <div class="cfa-forecast-header">
                        4 Day Fire Danger Forecast
                    </div>
                    <div class="cfa-forecast-content">
                        <?php if ($display_format === 'compact'): ?>
                        <div class="cfa-forecast-compact">
                            <?php foreach ($data['data']['forecast'] as $index => $day): ?>
                            <div class="cfa-compact-item <?php echo $index === 0 ? 'today' : ''; ?>">
                                <span class="compact-day"><?php echo esc_html($day['day']); ?> (<?php echo esc_html($day['date']); ?>)</span>
                                <span class="cfa-fire-danger-badge rating-<?php echo esc_attr($this->get_rating_class($day['rating'])); ?>">
                                    <?php echo esc_html($day['rating']); ?>
                                </span>
                                <?php if ($day['total_fire_ban']): ?>
                                <span class="cfa-tfb-badge">⚠️ TFB</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="cfa-forecast-grid">
                            <?php foreach ($data['data']['forecast'] as $index => $day): ?>
                            <div class="cfa-forecast-day <?php echo $index === 0 ? 'today' : ''; ?>">
                                <div class="cfa-day-header"><?php echo esc_html($day['day']); ?></div>
                                <div class="cfa-day-date"><?php echo esc_html($day['date']); ?></div>
                                <div class="cfa-fire-danger-badge rating-<?php echo esc_attr($this->get_rating_class($day['rating'])); ?>">
                                    <?php echo esc_html($day['rating']); ?>
                                </div>
                                <?php if ($day['total_fire_ban']): ?>
                                <div class="cfa-total-fire-ban">⚠️ TOTAL FIRE BAN</div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php 
                $show_rating_scale = isset($options['show_rating_scale']) ? $options['show_rating_scale'] : 'yes';
                if ($atts['show_scale'] === 'true' && $show_rating_scale === 'yes'): 
                ?>
                <div class="cfa-fire-danger-scale">
                    <h3>Fire Danger Ratings Scale</h3>
                    <p>Understanding what each fire danger rating means and what you should do:</p>
                    <img src="https://www.cfa.vic.gov.au/images/UserUploadedImages/11/map-bar.png" 
                         alt="Fire Danger Ratings Scale"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none;" class="cfa-scale-fallback">
                        <p><strong>Fire Danger Rating Scale:</strong></p>
                        <p>🟢 <strong>Low-Moderate:</strong> Fire can start and spread. Be alert.</p>
                        <p>🟡 <strong>Moderate:</strong> Fires can spread. Be prepared and stay alert.</p>
                        <p>🟠 <strong>High:</strong> Fires will spread rapidly and be difficult to control. Be ready to act.</p>
                        <p>🔴 <strong>Extreme:</strong> Fires will spread rapidly and be extremely difficult to control. Take action now.</p>
                        <p>🟣 <strong>Catastrophic:</strong> If a fire starts it will be uncontrollable, unpredictable and fast moving. Leave bushfire risk areas immediately.</p>
                    </div>
                    <p><a href="https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/about-fire-danger-ratings" target="_blank">Learn more about Fire Danger Ratings</a></p>
                </div>
                <?php endif; ?>

                <div class="cfa-emergency-notice">
                    ⚠️ <strong>EMERGENCY:</strong> In case of fire emergency, call <strong>000</strong> immediately
                </div>
            </div>

            <div class="cfa-source-footer">
                <p><strong>Data Source:</strong> 
                <a href="<?php echo esc_url($data['source_url']); ?>" target="_blank">
                    CFA Victoria - Official Website
                </a></p>
                <p>This information is for general reference only. Always check the official CFA website for the most current fire danger ratings and restrictions.</p>
                <p><small>Data automatically updated twice daily at 6 AM and 6 PM (Melbourne time)</small></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get CSS class for fire danger rating
     */
    private function get_rating_class($rating) {
        $rating_lower = strtolower($rating);
        
        if (strpos($rating_lower, 'low-moderate') !== false) return 'low-moderate';
        if (strpos($rating_lower, 'moderate') !== false && strpos($rating_lower, 'low') === false) return 'moderate';
        if (strpos($rating_lower, 'high') !== false) return 'high';
        if (strpos($rating_lower, 'extreme') !== false) return 'extreme';
        if (strpos($rating_lower, 'catastrophic') !== false) return 'catastrophic';
        if (strpos($rating_lower, 'error') !== false) return 'error';
        
        return 'no-rating';
    }
    
    /**
     * AJAX handler for refreshing data
     */
    public function ajax_refresh_data() {
        check_ajax_referer('cfa_fire_forecast_nonce', 'nonce');
        
        $districts = sanitize_text_field($_POST['districts'] ?? 'north-central-fire-district');
        $is_multi_district = strpos($districts, ',') !== false;
        
        // Clear cache and fetch fresh data
        if ($is_multi_district) {
            delete_transient('cfa_fire_forecast_multi_' . md5($districts));
        } else {
            delete_transient('cfa_fire_forecast_data_' . $districts);
        }
        
        $data = $this->get_cached_data($districts, $is_multi_district);
        
        wp_send_json_success($data);
    }
}