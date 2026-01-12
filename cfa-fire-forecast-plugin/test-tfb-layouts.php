<?php
/**
 * Test TFB Display in Different Layouts
 */

// Mock WordPress functions
if (!function_exists('_e')) { function _e($text, $domain = '') { echo $text; } }
if (!function_exists('__')) { function __($text, $domain = '') { return $text; } }
if (!function_exists('esc_attr')) { function esc_attr($text) { return htmlspecialchars($text); } }
if (!function_exists('esc_html')) { function esc_html($text) { return htmlspecialchars($text); } }
if (!function_exists('esc_url')) { function esc_url($url) { return htmlspecialchars($url); } }
if (!function_exists('sanitize_hex_color')) { function sanitize_hex_color($color) { return $color; } }
if (!function_exists('get_option')) { function get_option($opt, $default = array()) { 
    if ($opt === 'cfa_fire_forecast_options') {
        return array('color_scheme' => 'official', 'display_format' => 'table');
    }
    return $default; 
} }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($file) { return __DIR__ . '/'; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($file) { return ''; } }
if (!function_exists('add_shortcode')) { function add_shortcode($tag, $callback) { } }
if (!function_exists('add_action')) { function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { } }
if (!function_exists('current_time')) { function current_time($type) { return date('Y-m-d H:i:s'); } }
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs, $atts) { return array_merge($pairs, (array)$atts); } }
if (!function_exists('admin_url')) { function admin_url($path = '') { return '/wp-admin/' . $path; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1) { return 'mock_nonce'; } }

// Define constants
if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('CFA_FIRE_FORECAST_PLUGIN_URL')) define('CFA_FIRE_FORECAST_PLUGIN_URL', '');
if (!defined('CFA_FIRE_FORECAST_VERSION')) define('CFA_FIRE_FORECAST_VERSION', '4.8.2');

// Load the frontend class
require_once __DIR__ . '/includes/frontend.php';

$frontend = new CFA_Fire_Forecast_Frontend();

// Mock data with TFB active
$mock_data = array(
    'central-fire-district' => array(
        'current_rating' => 'EXTREME',
        'total_fire_ban' => true,
        'district' => 'Central',
        'forecast' => array(
            array('day' => 'Today', 'date' => '2026-01-11', 'rating' => 'EXTREME', 'total_fire_ban' => true),
            array('day' => 'Tomorrow', 'date' => '2026-01-12', 'rating' => 'HIGH', 'total_fire_ban' => true),
            array('day' => 'Tuesday', 'date' => '2026-01-13', 'rating' => 'MODERATE', 'total_fire_ban' => false),
            array('day' => 'Wednesday', 'date' => '2026-01-14', 'rating' => 'LOW-MODERATE', 'total_fire_ban' => false),
        )
    ),
    'north-central-fire-district' => array(
        'current_rating' => 'HIGH',
        'total_fire_ban' => false,
        'district' => 'North Central',
        'forecast' => array(
            array('day' => 'Today', 'date' => '2026-01-11', 'rating' => 'HIGH', 'total_fire_ban' => false),
            array('day' => 'Tomorrow', 'date' => '2026-01-12', 'rating' => 'MODERATE', 'total_fire_ban' => false),
            array('day' => 'Tuesday', 'date' => '2026-01-13', 'rating' => 'LOW-MODERATE', 'total_fire_ban' => false),
            array('day' => 'Wednesday', 'date' => '2026-01-14', 'rating' => 'NO RATING', 'total_fire_ban' => false),
        )
    )
);

// Use Reflection to access private rendering methods
function callPrivateMethod($object, $methodName, $parameters = array()) {
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
}

echo "<html><head>";
echo "<style>";
// Inject actual plugin CSS
echo file_get_contents(__DIR__ . '/assets/css/style.css');
echo "
    body { font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Oxygen-Sans, Ubuntu, Cantarell, \"Helvetica Neue\", sans-serif; padding: 40px; background: #f0f2f5; max-width: 1000px; margin: 0 auto; color: #1d2327; }
    .test-wrapper { margin-bottom: 60px; }
    h1 { color: #1d2327; border-bottom: 3px solid #2271b1; padding-bottom: 15px; margin-bottom: 30px; font-size: 2em; }
    h2 { background: #2271b1; color: white; padding: 10px 20px; border-radius: 6px 6px 0 0; margin: 0; font-size: 1.2em; }
    .test-content { background: white; padding: 30px; border: 1px solid #c3c4c7; border-top: none; border-radius: 0 0 6px 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .cfa-fire-forecast-container { margin: 0 !important; }
</style>";
echo "</head><body>";

echo "<h1>TFB Look & Feel Test - v4.8.2</h1>";

$districts = array('central-fire-district', 'north-central-fire-district');
$data = array(
    'districts' => $districts,
    'data' => $mock_data,
    'last_updated' => date('Y-m-d H:i:s')
);

// Test Table Layout
echo "<div class='test-wrapper'>";
echo "<h2>1. Official Table Layout</h2>";
echo "<div class='test-content'>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'table', 'auto_refresh' => 'false', 'show_scale' => 'true')));
echo ob_get_clean();
echo "</div></div>";

// Test Card Layout
echo "<div class='test-wrapper'>";
echo "<h2>2. Card Layout</h2>";
echo "<div class='test-content'>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'cards', 'auto_refresh' => 'false', 'show_scale' => 'false')));
echo ob_get_clean();
echo "</div></div>";

// Test Compact Layout
echo "<div class='test-wrapper'>";
echo "<h2>3. Compact Layout</h2>";
echo "<div class='test-content'>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'compact', 'auto_refresh' => 'false', 'show_scale' => 'false')));
echo ob_get_clean();
echo "</div></div>";

echo "</body></html>";
