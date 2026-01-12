<?php
/**
 * Real Data Test for TFB Layouts (Self-Contained Mock)
 */

// Mock WordPress functions
if (!function_exists('_e')) { function _e($text, $domain = '') { echo $text; } }
if (!function_exists('__')) { function __($text, $domain = '') { return $text; } }
if (!function_exists('esc_attr')) { function esc_attr($text) { return htmlspecialchars($text); } }
if (!function_exists('esc_html')) { function esc_html($text) { return htmlspecialchars($text); } }
if (!function_exists('esc_url')) { function esc_url($url) { return htmlspecialchars($url); } }
if (!function_exists('sanitize_hex_color')) { function sanitize_hex_color($color) { return $color; } }
if (!function_exists('get_option')) { 
    function get_option($opt, $default = array()) { 
        if ($opt === 'cfa_fire_forecast_options') {
            return array('color_scheme' => 'official', 'display_format' => 'table', 'enable_logging' => 'no');
        }
        return $default; 
    } 
}
if (!function_exists('update_option')) { function update_option($opt, $val) { return true; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($file) { return __DIR__ . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($file) { return ''; } }
if (!function_exists('add_shortcode')) { function add_shortcode($tag, $callback) { } }
if (!function_exists('add_action')) { function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { } }
if (!function_exists('current_time')) { function current_time($type) { return date('Y-m-d H:i:s'); } }
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs, $atts) { return array_merge($pairs, (array)$atts); } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return '/wp-admin/' . $path; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1) { return 'mock_nonce'; } }
if (!function_exists('wp_remote_get')) { 
    function wp_remote_get($url, $args = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CFA-Fire-Forecast-WordPress-Plugin/1.0)');
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return new WP_Error('http_error', 'Failed to fetch HTTP ' . $code);
        return array('body' => $body);
    }
}
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($response) { return isset($response['body']) ? $response['body'] : ''; } }
if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return $thing instanceof WP_Error; } }
if (!function_exists('get_transient')) { function get_transient($key) { return false; } }
if (!function_exists('set_transient')) { function set_transient($key, $val, $exp) { return true; } }

class WP_Error {
    public $msg;
    public function __construct($code, $msg) { $this->msg = $msg; }
    public function get_error_message() { return $this->msg; }
}

// Define constants
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('CFA_FIRE_FORECAST_PLUGIN_URL')) define('CFA_FIRE_FORECAST_PLUGIN_URL', '');
if (!defined('CFA_FIRE_FORECAST_VERSION')) define('CFA_FIRE_FORECAST_VERSION', '4.8.2');
if (!defined('CFA_FIRE_FORECAST_PLUGIN_DIR')) define('CFA_FIRE_FORECAST_PLUGIN_DIR', __DIR__ . '/');
if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);

// Load the classes
require_once __DIR__ . '/includes/scraper.php';
require_once __DIR__ . '/includes/frontend.php';

$scraper = new CFA_Fire_Forecast_Scraper();
$frontend = new CFA_Fire_Forecast_Frontend();

// Fetch REAL data for Central District
$real_data = $scraper->scrape_fire_data('central-fire-district');

// Use Reflection to access private rendering methods
function callPrivateMethod($object, $methodName, $parameters = array()) {
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

echo "<html><head>";
echo "<style>";
echo file_get_contents(__DIR__ . '/assets/css/style.css');
echo "
    body { font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Oxygen-Sans, Ubuntu, Cantarell, \"Helvetica Neue\", sans-serif; padding: 40px; background: #f0f2f5; max-width: 1000px; margin: 0 auto; color: #1d2327; }
    .test-wrapper { margin-bottom: 60px; }
    h1 { color: #1d2327; border-bottom: 3px solid #2271b1; padding-bottom: 15px; margin-bottom: 10px; font-size: 2em; }
    .data-source { margin-bottom: 30px; font-style: italic; color: #666; }
    h2 { background: #2271b1; color: white; padding: 10px 20px; border-radius: 6px 6px 0 0; margin: 0; font-size: 1.2em; }
    .test-content { background: white; padding: 30px; border: 1px solid #c3c4c7; border-top: none; border-radius: 0 0 6px 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .cfa-fire-forecast-container { margin: 0 !important; }
    .error-box { background: #f8d7da; color: #721c24; padding: 20px; border: 1px solid #f5c6cb; border-radius: 6px; margin-bottom: 20px; }
</style>";
echo "</head><body>";

echo "<h1>REAL Data TFB Test - v4.8.2</h1>";

if (isset($real_data['data']) && !empty($real_data['data']['forecast'])) {
    echo "<p class=\"data-source\">Source: " . htmlspecialchars($real_data['source_url']) . " (Fetched live from CFA)</p>";

    $districts = array('central-fire-district');
    $data = array(
        'districts' => $districts,
        'data' => array('central-fire-district' => $real_data['data']),
        'last_updated' => $real_data['last_updated']
    );

    // Test Table Layout
    echo "<div class='test-wrapper'>";
    echo "<h2>1. Official Table Layout (Live Data)</h2>";
    echo "<div class='test-content'>";
    ob_start();
    callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'table', 'auto_refresh' => 'false', 'show_scale' => 'true')));
    echo ob_get_clean();
    echo "</div></div>";

    // Test Card Layout
    echo "<div class='test-wrapper'>";
    echo "<h2>2. Card Layout (Live Data)</h2>";
    echo "<div class='test-content'>";
    ob_start();
    callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'cards', 'auto_refresh' => 'false', 'show_scale' => 'false')));
    echo ob_get_clean();
    echo "</div></div>";
} else {
    echo "<div class='error-box'><h3>Failed to fetch real data</h3><p>CFA RSS feed might be temporarily unavailable or blocking the request. Check if the server IP is restricted.</p></div>";
}

echo "</body></html>";
