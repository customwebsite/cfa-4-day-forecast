<?php
/**
 * CFA Fire Forecast Data Test Script
 * 
 * This script demonstrates the data fetching capabilities of the WordPress plugin
 * using the CFA internal API (discovered through reverse engineering the JavaScript).
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock WordPress functions
function current_time($type) {
    return date('Y-m-d H:i:s');
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) {
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * CFA Fire Forecast API Scraper (uses internal CFA API)
 */
class CFA_Fire_Forecast_API_Scraper {
    
    private $api_url = 'https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district';
    private $admin_email = 'digitalworkflow@cfa.vic.gov.au';
    
    // Map URL slugs to proper district names for API
    private $district_map = array(
        'central-fire-district' => 'Central',
        'mallee-fire-district' => 'Mallee',
        'north-central-fire-district' => 'North Central',
        'north-east-fire-district' => 'North East',
        'northern-country-fire-district' => 'Northern Country',
        'south-west-fire-district' => 'South West',
        'west-and-south-gippsland-fire-district' => 'West and South Gippsland',
        'wimmera-fire-district' => 'Wimmera'
    );
    
    /**
     * Scrape fire danger data for multiple districts
     */
    public function scrape_multiple_districts($districts) {
        if (is_string($districts)) {
            $districts = array_map('trim', explode(',', $districts));
        }
        
        $all_data = array();
        $last_updated = current_time('mysql');
        $next_update = $this->get_next_update_time();
        $source_urls = array();
        
        foreach ($districts as $district) {
            echo "🔄 Fetching data for: " . ucwords(str_replace('-', ' ', $district)) . "<br>";
            $district_data = $this->scrape_fire_data($district);
            if ($district_data && !empty($district_data['data'])) {
                $all_data[$district] = $district_data['data'];
                $source_urls[] = $district_data['source_url'];
                echo "✅ Success<br>";
            } else {
                echo "❌ Failed<br>";
            }
        }
        
        return array(
            'data' => $all_data,
            'districts' => $districts,
            'last_updated' => $last_updated,
            'next_update' => $next_update,
            'source_urls' => $source_urls,
            'multi_district' => true
        );
    }
    
    /**
     * Scrape fire danger data for a specific district using CFA API
     */
    public function scrape_fire_data($district_slug = 'north-central-fire-district') {
        // Convert URL slug to proper district name
        $district_name = isset($this->district_map[$district_slug]) 
            ? $this->district_map[$district_slug] 
            : ucwords(str_replace('-', ' ', str_replace('-fire-district', '', $district_slug)));
        
        echo "📍 District: $district_name<br>";
        
        $forecast_data = array();
        $current_rating = 'NO RATING';
        $total_fire_ban = false;
        
        // Fetch 4-day forecast
        for ($i = 0; $i < 4; $i++) {
            $date = date('Y-m-d H:i:s', strtotime("+$i days"));
            $day_name = $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : date('D, j M Y', strtotime("+$i days")));
            
            $api_data = $this->fetch_api_data($district_name, $date);
            
            if ($api_data) {
                $forecast_data[] = array(
                    'day' => $day_name,
                    'date' => date('Y-m-d', strtotime("+$i days")),
                    'rating' => $api_data['rating'],
                    'total_fire_ban' => $api_data['total_fire_ban']
                );
                
                // Use first day's data for current rating
                if ($i === 0) {
                    $current_rating = $api_data['rating'];
                    $total_fire_ban = $api_data['total_fire_ban'];
                }
            } else {
                $forecast_data[] = array(
                    'day' => $day_name,
                    'date' => date('Y-m-d', strtotime("+$i days")),
                    'rating' => 'NO RATING',
                    'total_fire_ban' => false
                );
            }
        }
        
        return array(
            'data' => array(
                'current_rating' => $current_rating,
                'total_fire_ban' => $total_fire_ban,
                'forecast' => $forecast_data,
                'last_updated' => current_time('mysql')
            ),
            'source_url' => 'https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/' . $district_slug
        );
    }
    
    /**
     * Fetch data from CFA API
     */
    private function fetch_api_data($district_name, $date) {
        $payload = json_encode(array(
            'IssueDate' => $date,
            'DistrictName' => $district_name,
            'AdminEmailAddress' => $this->admin_email
        ));
        
        echo "🌐 API Request: $district_name for " . date('Y-m-d', strtotime($date)) . "<br>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            echo "⚠️ API request failed: HTTP $http_code<br>";
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (!$data || !is_array($data) || count($data) === 0) {
            echo "⚠️ No data returned from API<br>";
            return false;
        }
        
        $item = $data[0];
        $rating = isset($item['DistrictRating']) && !empty($item['DistrictRating']) 
            ? strtoupper($item['DistrictRating']) 
            : 'NO RATING';
        $district_data = isset($item['DistrictData']) ? $item['DistrictData'] : '';
        $forecast_info = isset($item['ForeCastInformation']) ? $item['ForeCastInformation'] : '';
        
        // Check for total fire ban - YES at start of DistrictData or in ForeCastInformation
        $total_fire_ban = false;
        if (stripos($district_data, 'YES') === 0 || stripos($forecast_info, 'Total Fire Ban') !== false) {
            $total_fire_ban = true;
        }
        
        echo "✅ API Response: Rating = $rating, TFB = " . ($total_fire_ban ? 'YES' : 'NO') . "<br>";
        
        return array(
            'rating' => $rating,
            'total_fire_ban' => $total_fire_ban
        );
    }
    
    /**
     * Calculate next update time (6 AM or 6 PM Melbourne time)
     */
    private function get_next_update_time() {
        $melbourne_tz = new DateTimeZone('Australia/Melbourne');
        $now = new DateTime('now', $melbourne_tz);
        $next_update = clone $now;
        
        $current_hour = (int)$now->format('H');
        
        if ($current_hour < 6) {
            $next_update->setTime(6, 0, 0);
        } elseif ($current_hour < 18) {
            $next_update->setTime(18, 0, 0);
        } else {
            $next_update->modify('+1 day');
            $next_update->setTime(6, 0, 0);
        }
        
        return $next_update->format('Y-m-d H:i:s');
    }
    
    /**
     * Get color class for rating
     */
    private function get_rating_color($rating) {
        $colors = array(
            'CATASTROPHIC' => 'catastrophic',
            'EXTREME' => 'extreme',
            'HIGH' => 'high',
            'MODERATE' => 'moderate',
            'LOW-MODERATE' => 'low-moderate',
            'NO RATING' => 'no-rating'
        );
        
        $rating_upper = strtoupper($rating);
        return isset($colors[$rating_upper]) ? $colors[$rating_upper] : 'no-rating';
    }
}

// Initialize the scraper
$scraper = new CFA_Fire_Forecast_API_Scraper();

// Get test parameters
$test_type = isset($_GET['test']) ? $_GET['test'] : 'single';
$district = isset($_GET['district']) ? $_GET['district'] : 'north-central-fire-district';
$districts = isset($_GET['districts']) ? $_GET['districts'] : 'north-central-fire-district,south-west-fire-district';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 CFA Fire Forecast Data Test</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .header {
            background: #004080;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #004080;
            padding: 20px;
            margin: 20px;
        }
        
        .status-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px;
        }
        
        .content {
            padding: 20px;
        }
        
        .debug-output {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .cfa-forecast-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .cfa-forecast-table th {
            background: #004080;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        
        .cfa-forecast-table td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            color: white;
            text-align: center;
            min-width: 120px;
        }
        
        .catastrophic { background: #8B0000; }
        .extreme { background: #DC143C; }
        .high { background: #FF6347; }
        .moderate { background: #FFA500; }
        .low-moderate { background: #FFD700; color: #333; }
        .no-rating { background: #999; }
        
        .tfb-yes {
            color: #dc3545;
            font-weight: bold;
        }
        
        .tfb-no {
            color: #28a745;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #004080;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }
        
        .btn:hover {
            background: #003366;
        }
        
        .test-section {
            margin: 30px 0;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .test-section h2 {
            color: #004080;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 CFA Fire Forecast Data Test</h1>
            <p>Using CFA Internal API (Reverse Engineered)</p>
        </div>
        
        <div class="info-box">
            <strong>Purpose:</strong> This test script shows exactly what data the WordPress plugin fetches from the CFA API in real-time, without requiring WordPress installation.
        </div>
        
        <div class="status-box">
            <strong>📡 Live Test Status:</strong> Fetching real-time data from CFA API...<br>
            <strong>Current Melbourne Time:</strong> <?php echo date('Y-m-d H:i:s'); ?> UTC
        </div>
        
        <div class="content">
            <form method="get" style="margin: 20px 0;">
                <button type="submit" name="refresh" value="1" class="btn">🔄 Refresh Data</button>
            </form>
            
            <!-- Test 1: Single District -->
            <div class="test-section">
                <h2>🧪 Test 1: Single District Data (North Central)</h2>
                <p>Testing data extraction for a single fire district...</p>
                
                <div class="debug-output">
                    <strong>Debug Output:</strong><br><br>
                    <?php
                    $single_data = $scraper->scrape_fire_data('north-central-fire-district');
                    ?>
                </div>
                
                <?php if ($single_data && !empty($single_data['data'])): ?>
                    <table class="cfa-forecast-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Fire Danger Rating</th>
                                <th>Total Fire Ban</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($single_data['data']['forecast'] as $day): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($day['day']); ?></strong><br>
                                        <small><?php echo esc_html($day['date']); ?></small>
                                    </td>
                                    <td>
                                        <span class="rating-badge <?php echo strtolower(str_replace(' ', '-', $day['rating'])); ?>">
                                            <?php echo esc_html($day['rating']); ?>
                                        </span>
                                    </td>
                                    <td class="<?php echo $day['total_fire_ban'] ? 'tfb-yes' : 'tfb-no'; ?>">
                                        <?php echo $day['total_fire_ban'] ? '⚠️ YES' : '✓ NO'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p><small><strong>Last Updated:</strong> <?php echo esc_html($single_data['data']['last_updated']); ?></small></p>
                    <p><small><strong>Source:</strong> <a href="<?php echo esc_url($single_data['source_url']); ?>" target="_blank">CFA Official Website</a></small></p>
                <?php else: ?>
                    <p style="color: #dc3545;"><strong>⚠️ Failed to fetch data</strong></p>
                <?php endif; ?>
            </div>
            
            <!-- Test 2: Multi-District Table -->
            <div class="test-section">
                <h2>🧪 Test 2: Multi-District Comparison</h2>
                <p>Testing multi-district table view with North Central and South West districts...</p>
                
                <div class="debug-output">
                    <strong>Debug Output:</strong><br><br>
                    <?php
                    $multi_data = $scraper->scrape_multiple_districts('north-central-fire-district,south-west-fire-district');
                    ?>
                </div>
                
                <?php if ($multi_data && !empty($multi_data['data'])): ?>
                    <table class="cfa-forecast-table">
                        <thead>
                            <tr>
                                <th>District</th>
                                <?php
                                $first_district_data = reset($multi_data['data']);
                                foreach ($first_district_data['forecast'] as $day):
                                ?>
                                    <th><?php echo esc_html($day['day']); ?><br>
                                        <small><?php echo esc_html($day['date']); ?></small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($multi_data['data'] as $district_slug => $district_data): ?>
                                <tr>
                                    <td><strong><?php echo esc_html(ucwords(str_replace('-', ' ', str_replace('-fire-district', '', $district_slug)))); ?></strong></td>
                                    <?php foreach ($district_data['forecast'] as $day): ?>
                                        <td>
                                            <span class="rating-badge <?php echo strtolower(str_replace(' ', '-', $day['rating'])); ?>">
                                                <?php echo esc_html($day['rating']); ?>
                                            </span>
                                            <br><small class="<?php echo $day['total_fire_ban'] ? 'tfb-yes' : 'tfb-no'; ?>">
                                                <?php echo $day['total_fire_ban'] ? 'TFB' : 'No TFB'; ?>
                                            </small>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p><small><strong>Last Updated:</strong> <?php echo esc_html($multi_data['last_updated']); ?></small></p>
                <?php else: ?>
                    <p style="color: #dc3545;"><strong>⚠️ Failed to fetch multi-district data</strong></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
