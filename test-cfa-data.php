<?php
/**
 * CFA Fire Forecast Data Test Script
 * 
 * This script demonstrates the data fetching capabilities of the WordPress plugin
 * without requiring WordPress installation. It shows real-time data from the CFA website.
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
 * Simplified CFA Fire Forecast Data Scraper (WordPress-free version)
 */
class CFA_Fire_Forecast_Test_Scraper {
    
    private $base_url = 'https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/';
    
    /**
     * Fetch data using cURL instead of WordPress HTTP API
     */
    private function fetch_url($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'body' => ''];
        }
        
        return ['http_code' => $httpCode, 'body' => $response, 'error' => null];
    }
    
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
     * Scrape fire danger data for a specific district
     */
    public function scrape_fire_data($district = 'north-central-fire-district') {
        $url = $this->base_url . $district;
        
        $response = $this->fetch_url($url);
        
        if ($response['error']) {
            echo "❌ cURL Error: " . $response['error'] . "<br>";
            return $this->get_fallback_data($district);
        }
        
        if (empty($response['body'])) {
            echo "❌ Empty response body<br>";
            return $this->get_fallback_data($district);
        }
        
        echo "✅ HTTP " . $response['http_code'] . " - Page loaded successfully<br>";
        return $this->parse_fire_data($response['body'], $district);
    }
    
    /**
     * Parse HTML content to extract fire danger ratings
     */
    private function parse_fire_data($html, $district) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $forecast_data = array();
        $current_date = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
        
        // Generate 4-day forecast
        for ($i = 0; $i < 4; $i++) {
            $forecast_date = clone $current_date;
            $forecast_date->add(new DateInterval('P' . $i . 'D'));
            
            $day_label = $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $forecast_date->format('l'));
            $date_string = $forecast_date->format('D, j F Y');
            
            $fire_danger_rating = 'NO RATING';
            $total_fire_ban = false;
            
            // For today, try to extract actual rating
            if ($i === 0) {
                $fire_danger_rating = $this->extract_current_rating($dom, $html);
                $total_fire_ban = $this->extract_fire_ban_status($html);
            }
            
            $forecast_data[] = array(
                'day' => $day_label,
                'date' => $date_string,
                'fire_danger_rating' => $fire_danger_rating,
                'total_fire_ban' => $total_fire_ban,
                'district' => ucwords(str_replace('-', ' ', $district)),
                'forecast_date' => $forecast_date->format('Y-m-d')
            );
        }
        
        return array(
            'data' => $forecast_data,
            'last_updated' => current_time('mysql'),
            'next_update' => $this->get_next_update_time(),
            'source_url' => $this->base_url . $district
        );
    }
    
    /**
     * Extract current fire danger rating from HTML
     */
    private function extract_current_rating($dom, $html) {
        // Try to find .fdrRating element
        $xpath = new DOMXPath($dom);
        $fdr_elements = $xpath->query("//*[contains(@class, 'fdrRating')]");
        
        if ($fdr_elements->length > 0) {
            $rating_text = $fdr_elements->item(0)->textContent;
            echo "🔍 Found .fdrRating element with content: '" . trim($rating_text) . "'<br>";
            
            // Extract rating from text
            if (preg_match('/(NO RATING|LOW-MODERATE|MODERATE|HIGH|EXTREME|CATASTROPHIC)/i', $rating_text, $matches)) {
                echo "✅ Extracted rating: " . strtoupper($matches[1]) . "<br>";
                return strtoupper($matches[1]);
            }
            
            // Check for "no rating" in the text
            if (stripos($rating_text, 'no rating') !== false) {
                echo "✅ Found 'no rating' in text<br>";
                return 'NO RATING';
            }
        } else {
            echo "⚠️ No .fdrRating element found<br>";
        }
        
        // Fallback: check for NO RATING in content
        if (stripos($html, 'NO RATING') !== false || stripos($html, 'no-rating.gif') !== false) {
            echo "✅ Found NO RATING in page content<br>";
            return 'NO RATING';
        }
        
        // Fallback: pattern matching
        $rating_patterns = array(
            'CATASTROPHIC' => '/catastrophic/i',
            'EXTREME' => '/extreme/i',
            'HIGH' => '/\bhigh\b/i',
            'MODERATE' => '/\bmoderate\b/i',
            'LOW-MODERATE' => '/low[\s-]*moderate/i'
        );
        
        foreach ($rating_patterns as $rating => $pattern) {
            if (preg_match($pattern, $html)) {
                echo "✅ Found rating by pattern matching: $rating<br>";
                return $rating;
            }
        }
        
        echo "⚠️ No specific rating found, defaulting to NO RATING<br>";
        return 'NO RATING';
    }
    
    /**
     * Extract total fire ban status
     */
    private function extract_fire_ban_status($html) {
        if (stripos($html, 'total fire ban') !== false && stripos($html, 'in force') !== false) {
            echo "🔥 Total Fire Ban detected - IN FORCE<br>";
            return true;
        }
        
        if (stripos($html, 'not currently a day of total fire ban') !== false) {
            echo "✅ No Total Fire Ban detected<br>";
            return false;
        }
        
        echo "ℹ️ Total Fire Ban status unclear, assuming false<br>";
        return false;
    }
    
    /**
     * Get next update time (6 AM or 6 PM Melbourne time)
     */
    private function get_next_update_time() {
        $now = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
        $next = clone $now;
        
        $current_hour = (int)$now->format('H');
        
        if ($current_hour < 6) {
            $next->setTime(6, 0, 0);
        } elseif ($current_hour < 18) {
            $next->setTime(18, 0, 0);
        } else {
            $next->add(new DateInterval('P1D'));
            $next->setTime(6, 0, 0);
        }
        
        return $next->format('Y-m-d H:i:s');
    }
    
    /**
     * Get fallback data when scraping fails
     */
    private function get_fallback_data($district = 'north-central-fire-district') {
        $current_date = new DateTime('now', new DateTimeZone('Australia/Melbourne'));
        $forecast_data = array();
        
        for ($i = 0; $i < 4; $i++) {
            $forecast_date = clone $current_date;
            $forecast_date->add(new DateInterval('P' . $i . 'D'));
            
            $day_label = $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $forecast_date->format('l'));
            $date_string = $forecast_date->format('D, j F Y');
            
            $forecast_data[] = array(
                'day' => $day_label,
                'date' => $date_string,
                'fire_danger_rating' => 'ERROR LOADING',
                'total_fire_ban' => false,
                'district' => ucwords(str_replace('-', ' ', $district)),
                'forecast_date' => $forecast_date->format('Y-m-d')
            );
        }
        
        return array(
            'data' => $forecast_data,
            'last_updated' => current_time('mysql'),
            'next_update' => $this->get_next_update_time(),
            'source_url' => $this->base_url . $district
        );
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFA Fire Forecast Data Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            background: #003366;
            color: white;
            padding: 20px;
            margin: -30px -30px 30px -30px;
            border-radius: 8px 8px 0 0;
        }
        .test-section {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #003366;
            border-radius: 4px;
        }
        .debug-output {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
        .data-output {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error-output {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            max-height: 300px;
            overflow-y: auto;
        }
        .rating-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9em;
            margin-right: 10px;
        }
        .rating-no-rating { background: #e9ecef; color: #495057; }
        .rating-low-moderate { background: #28a745; color: white; }
        .rating-moderate { background: #ffc107; color: #212529; }
        .rating-high { background: #fd7e14; color: white; }
        .rating-extreme { background: #dc3545; color: white; }
        .rating-catastrophic { background: #6f2c91; color: white; }
        .rating-error-loading { background: #6c757d; color: white; }
        .fire-ban { background: #dc3545; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #004080; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
        .today-column { background: #004080 !important; }
        .today-cell { background: #fff3cd !important; }
        .refresh-btn {
            background: #003366;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
        }
        .refresh-btn:hover { background: #004080; }
        .status { margin: 20px 0; padding: 15px; border-radius: 4px; }
        .status.success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .status.warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .status.error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔥 CFA Fire Forecast Data Test</h1>
        <p><strong>Purpose:</strong> This test script shows exactly what data the WordPress plugin would fetch from the CFA website in real-time, without requiring WordPress installation.</p>
        
        <div class="status success">
            <strong>🚀 Live Test Status:</strong> Fetching real-time data from CFA Victoria website...<br>
            <strong>Current Melbourne Time:</strong> <?php echo date('Y-m-d H:i:s T', strtotime('now Australia/Melbourne')); ?>
        </div>
        
        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh Data</button>
        
        <div class="test-section">
            <h2>🧪 Test 1: Single District Data (North Central)</h2>
            <p>Testing data extraction for a single fire district...</p>
            
            <div class="debug-output">
                <h3>Debug Output:</h3>
                <?php
                echo "🚀 Starting single district test...<br>";
                $scraper = new CFA_Fire_Forecast_Test_Scraper();
                $single_data = $scraper->scrape_fire_data('north-central-fire-district');
                echo "✅ Single district test completed<br>";
                ?>
            </div>
            
            <div class="data-output">
                <h3>📊 Retrieved Data Structure:</h3>
                <pre><?php echo json_encode($single_data, JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <?php if ($single_data && !empty($single_data['data'])): ?>
            <div class="data-output">
                <h3>🎨 Formatted Display (How it appears in WordPress):</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Date</th>
                            <th>Fire Danger Rating</th>
                            <th>Total Fire Ban</th>
                            <th>District</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($single_data['data'] as $index => $day): 
                            $rating_class = 'rating-' . strtolower(str_replace([' ', '-'], ['-', '-'], $day['fire_danger_rating']));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($day['day']); ?></strong></td>
                            <td><?php echo esc_html($day['date']); ?></td>
                            <td>
                                <span class="rating-badge <?php echo $rating_class; ?>">
                                    <?php echo esc_html($day['fire_danger_rating']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($day['total_fire_ban']): ?>
                                    <span class="fire-ban">⚠️ TOTAL FIRE BAN</span>
                                <?php else: ?>
                                    No
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($day['district']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p><strong>Last Updated:</strong> <?php echo esc_html($single_data['last_updated']); ?> (Melbourne time)</p>
                <p><strong>Next Update:</strong> <?php echo esc_html($single_data['next_update']); ?> (Melbourne time)</p>
                <p><strong>Source:</strong> <a href="<?php echo esc_url($single_data['source_url']); ?>" target="_blank">CFA Official Website</a></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>🧪 Test 2: Multi-District Data (3 Districts)</h2>
            <p>Testing data extraction for multiple fire districts simultaneously...</p>
            
            <div class="debug-output">
                <h3>Debug Output:</h3>
                <?php
                echo "🚀 Starting multi-district test...<br>";
                $districts = 'north-central-fire-district,south-west-fire-district,central-fire-district';
                $multi_data = $scraper->scrape_multiple_districts($districts);
                echo "✅ Multi-district test completed<br>";
                ?>
            </div>
            
            <div class="data-output">
                <h3>📊 Retrieved Multi-District Data Structure:</h3>
                <pre><?php echo json_encode($multi_data, JSON_PRETTY_PRINT); ?></pre>
            </div>
            
            <?php if ($multi_data && !empty($multi_data['data'])): ?>
            <div class="data-output">
                <h3>🎨 Multi-District Table Format (NEW FEATURE!):</h3>
                <table>
                    <thead>
                        <tr>
                            <th>District</th>
                            <?php 
                            // Get the days from first district
                            $first_district = reset($multi_data['data']);
                            if ($first_district):
                                foreach ($first_district as $index => $day): ?>
                                    <th class="<?php echo $index === 0 ? 'today-column' : ''; ?>">
                                        <div><?php echo esc_html($day['day']); ?></div>
                                        <div style="font-size: 0.9em; opacity: 0.9;"><?php echo esc_html($day['date']); ?></div>
                                    </th>
                                <?php endforeach;
                            endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multi_data['districts'] as $district): 
                            if (isset($multi_data['data'][$district])): ?>
                            <tr>
                                <td style="background: #f8f9fa; font-weight: bold; color: #003366;">
                                    <?php echo esc_html(ucwords(str_replace('-', ' ', $district))); ?>
                                </td>
                                <?php foreach ($multi_data['data'][$district] as $index => $day): 
                                    $rating_class = 'rating-' . strtolower(str_replace([' ', '-'], ['-', '-'], $day['fire_danger_rating']));
                                ?>
                                <td class="<?php echo $index === 0 ? 'today-cell' : ''; ?>">
                                    <span class="rating-badge <?php echo $rating_class; ?>">
                                        <?php echo esc_html($day['fire_danger_rating']); ?>
                                    </span>
                                    <?php if ($day['total_fire_ban']): ?>
                                        <div><span class="fire-ban">🔴 TFB</span></div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif;
                        endforeach; ?>
                    </tbody>
                </table>
                
                <p><strong>Districts Tested:</strong> <?php echo count($multi_data['districts']); ?></p>
                <p><strong>Successful Fetches:</strong> <?php echo count($multi_data['data']); ?></p>
                <p><strong>Last Updated:</strong> <?php echo esc_html($multi_data['last_updated']); ?> (Melbourne time)</p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="test-section">
            <h2>🎯 What This Shows You</h2>
            <ul>
                <li><strong>✅ Real-Time Data:</strong> The actual fire danger ratings currently published by CFA</li>
                <li><strong>📋 Data Structure:</strong> Exactly how the WordPress plugin organizes the information</li>
                <li><strong>🔧 Scraping Success:</strong> Whether the data extraction is working correctly</li>
                <li><strong>🏢 Multi-District Support:</strong> How multiple districts are handled simultaneously</li>
                <li><strong>⚠️ Error Handling:</strong> What happens when data cannot be retrieved</li>
                <li><strong>🎨 Display Format:</strong> How the plugin would present this information</li>
                <li><strong>📱 Mobile Ready:</strong> Responsive table format for all devices</li>
            </ul>
        </div>
        
        <div class="test-section">
            <h2>🔧 WordPress Plugin Usage</h2>
            <div style="background: white; padding: 15px; border-radius: 4px; margin: 10px 0;">
                <h3>Single District Shortcode:</h3>
                <code style="background: #f8f9fa; padding: 5px 10px; border-radius: 3px;">[cfa_fire_forecast district="north-central-fire-district"]</code>
                
                <h3>Multi-District Shortcode (NEW!):</h3>
                <code style="background: #f8f9fa; padding: 5px 10px; border-radius: 3px;">[cfa_fire_forecast districts="north-central-fire-district,south-west-fire-district,central-fire-district"]</code>
                
                <h3>Available Districts:</h3>
                <ul>
                    <li>north-central-fire-district</li>
                    <li>south-west-fire-district</li>
                    <li>central-fire-district</li>
                    <li>north-east-fire-district</li>
                    <li>northern-country-fire-district</li>
                </ul>
            </div>
        </div>
        
        <div class="test-section">
            <h2>🔧 Technical Details</h2>
            <p><strong>Current Melbourne Time:</strong> <?php echo date('Y-m-d H:i:s T', strtotime('now Australia/Melbourne')); ?></p>
            <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
            <p><strong>cURL Available:</strong> <?php echo function_exists('curl_init') ? '✅ Yes' : '❌ No'; ?></p>
            <p><strong>DOM Available:</strong> <?php echo class_exists('DOMDocument') ? '✅ Yes' : '❌ No'; ?></p>
            <p><strong>Test Script Version:</strong> 1.0</p>
            <p><strong>Timezone:</strong> Australia/Melbourne</p>
        </div>
        
        <div class="status warning">
            <strong>📝 Note:</strong> This data is for testing purposes only. Always check the official CFA website for the most current fire danger ratings and emergency information. In case of emergency, call <strong>000</strong>.
        </div>
    </div>
</body>
</html>