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
if (!function_exists('get_option')) { function get_option($opt, $default = array()) { return $default; } }
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

// Load the frontend class - USE ABSOLUTE PATH
require_once __DIR__ . '/includes/frontend.php';

if (!class_exists('CFA_Fire_Forecast_Frontend')) {
    echo "Error: CFA_Fire_Forecast_Frontend class not found at " . __DIR__ . '/includes/frontend.php';
    exit;
}

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
echo "<link rel='stylesheet' href='assets/css/style.css'>";
echo "<style>
    body { font-family: sans-serif; padding: 20px; background: #f0f0f0; max-width: 1200px; margin: 0 auto; }
    .test-section { margin-bottom: 50px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
    h2 { color: #666; margin-top: 0; }
    .cfa-fire-forecast-container { margin-bottom: 20px; border: 1px solid #ddd; }
</style>";
echo "</head><body>";

echo "<h1>TFB Display Test - v4.8.2</h1>";

$districts = array('central-fire-district', 'north-central-fire-district');
$data = array(
    'districts' => $districts,
    'data' => $mock_data,
    'last_updated' => date('Y-m-d H:i:s')
);

// Test Table Layout
echo "<div class='test-section'>";
echo "<h2>1. Table Layout (Multi-District)</h2>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'table', 'auto_refresh' => 'false', 'show_scale' => 'false')));
echo ob_get_clean();
echo "</div>";

// Test Card Layout
echo "<div class='test-section'>";
echo "<h2>2. Card Layout (Multi-District)</h2>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'cards', 'auto_refresh' => 'false', 'show_scale' => 'false')));
echo ob_get_clean();
echo "</div>";

// Test Compact Layout
echo "<div class='test-section'>";
echo "<h2>3. Compact Layout (Multi-District)</h2>";
ob_start();
callPrivateMethod($frontend, 'render_multi_district_forecast', array($data, array('layout' => 'compact', 'auto_refresh' => 'false', 'show_scale' => 'false')));
echo ob_get_clean();
echo "</div>";

echo "</body></html>";
