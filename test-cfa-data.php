<?php
/**
 * CFA Fire Forecast Data Test Script
 * 
 * This script demonstrates the data fetching using CFA's official RSS feeds.
 * RSS feeds are publicly accessible and contain fire danger ratings for all districts.
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
 * CFA Fire Forecast RSS Feed Scraper (WordPress-free version)
 */
class CFA_Fire_Forecast_RSS_Scraper {
    
    private $rss_base_url = 'https://www.cfa.vic.gov.au/cfa/rssfeed/';
    
    // Map district slugs to RSS feed filenames
    private $rss_feed_map = array(
        'central-fire-district' => 'central-firedistrict_rss.xml',
        'mallee-fire-district' => 'mallee-firedistrict_rss.xml',
        'north-central-fire-district' => 'northcentral-firedistrict_rss.xml',
        'north-east-fire-district' => 'northeast-firedistrict_rss.xml',
        'northern-country-fire-district' => 'northerncountry-firedistrict_rss.xml',
        'south-west-fire-district' => 'southwest-firedistrict_rss.xml',
        'west-and-south-gippsland-fire-district' => 'westandsouthgippsland-firedistrict_rss.xml',
        'wimmera-fire-district' => 'wimmera-firedistrict_rss.xml',
        'east-gippsland-fire-district' => 'eastgippsland-firedistrict_rss.xml'
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
            echo "🔄 Fetching RSS feed for: " . ucwords(str_replace('-', ' ', $district)) . "<br>";
            $district_data = $this->scrape_fire_data($district);
            if ($district_data && !empty($district_data['data'])) {
                $all_data[$district] = $district_data['data'];
                $source_urls[] = $district_data['source_url'];
                echo "✅ Success - Rating: " . $district_data['data']['current_rating'] . "<br>";
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
     * Scrape fire danger data from RSS feed
     */
    public function scrape_fire_data($district_slug = 'north-central-fire-district') {
        if (!isset($this->rss_feed_map[$district_slug])) {
            echo "⚠️ Unknown district: $district_slug<br>";
            return false;
        }
        
        $rss_url = $this->rss_base_url . $this->rss_feed_map[$district_slug];
        echo "📡 Fetching: $rss_url<br>";
        
        // Fetch RSS feed using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $rss_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; CFA-Fire-Forecast-Test/1.0)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $xml_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ cURL Error: $error<br>";
            return false;
        }
        
        if ($http_code !== 200) {
            echo "⚠️ HTTP $http_code<br>";
            return false;
        }
        
        echo "✅ HTTP 200 - RSS feed loaded successfully<br>";
        
        return $this->parse_rss_feed($xml_content, $district_slug);
    }
    
    /**
     * Parse RSS feed XML
     */
    private function parse_rss_feed($xml_content, $district_slug) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            echo "❌ Failed to parse XML<br>";
            return false;
        }
        
        echo "📄 Parsing RSS items...<br>";
        
        $forecast_data = array();
        $current_rating = 'NO RATING';
        $total_fire_ban = false;
        
        $items = $xml->channel->item;
        $day_count = 0;
        
        foreach ($items as $item) {
            $title = (string)$item->title;
            
            // Skip municipality restrictions item
            if (stripos($title, 'municipality') !== false || stripos($title, 'restrictions') !== false) {
                continue;
            }
            
            if ($day_count >= 4) {
                break;
            }
            
            $description = (string)$item->description;
            
            // Extract rating
            $rating = $this->extract_rating($description);
            
            // Extract TFB status
            $tfb = $this->extract_tfb_status($description);
            
            // Day name
            if ($day_count === 0) {
                $day_name = 'Today';
                $current_rating = $rating;
                $total_fire_ban = $tfb;
            } elseif ($day_count === 1) {
                $day_name = 'Tomorrow';
            } else {
                $day_name = $title;
            }
            
            echo "  📅 $day_name: $rating" . ($tfb ? ' (TFB)' : '') . "<br>";
            
            $forecast_data[] = array(
                'day' => $day_name,
                'date' => $this->parse_date_from_title($title),
                'rating' => $rating,
                'total_fire_ban' => $tfb
            );
            
            $day_count++;
        }
        
        // Pad to 4 days if needed
        while (count($forecast_data) < 4) {
            $forecast_data[] = array(
                'day' => 'Day ' . (count($forecast_data) + 1),
                'date' => date('Y-m-d', strtotime('+' . count($forecast_data) . ' days')),
                'rating' => 'NO RATING',
                'total_fire_ban' => false
            );
        }
        
        echo "✅ Parsed " . count($forecast_data) . " days of forecast<br>";
        
        return array(
            'data' => array(
                'current_rating' => $current_rating,
                'total_fire_ban' => $total_fire_ban,
                'forecast' => $forecast_data,
                'last_updated' => current_time('mysql')
            ),
            'source_url' => $this->rss_base_url . $this->rss_feed_map[$district_slug]
        );
    }
    
    /**
     * Extract fire danger rating from description
     */
    private function extract_rating($description) {
        if (preg_match('/:\s*(CATASTROPHIC|EXTREME|HIGH|MODERATE|LOW-MODERATE|NO RATING)/i', $description, $matches)) {
            return strtoupper($matches[1]);
        }
        
        $ratings = array('CATASTROPHIC', 'EXTREME', 'HIGH', 'MODERATE', 'LOW-MODERATE');
        foreach ($ratings as $rating) {
            if (stripos($description, $rating) !== false) {
                return $rating;
            }
        }
        
        return 'NO RATING';
    }
    
    /**
     * Extract Total Fire Ban status
     */
    private function extract_tfb_status($description) {
        if (stripos($description, 'is not currently a day of Total Fire Ban') !== false) {
            return false;
        }
        
        if (stripos($description, 'Total Fire Ban in force') !== false ||
            stripos($description, 'is a day of Total Fire Ban') !== false ||
            stripos($description, 'Total Fire Ban declared') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse date from title
     */
    private function parse_date_from_title($title) {
        $timestamp = strtotime($title);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        return date('Y-m-d');
    }
    
    /**
     * Get next update time
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
}

// Initialize the scraper
$scraper = new CFA_Fire_Forecast_RSS_Scraper();

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
        
        .success-banner {
            background: #d4edda;
            border: 2px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            margin: 20px;
            text-align: center;
        }
        
        .success-banner h3 {
            color: #28a745;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔥 CFA Fire Forecast Data Test</h1>
            <p>Using Official CFA RSS Feeds</p>
        </div>
        
        <div class="success-banner">
            <h3>✅ Solution Found: RSS Feeds Work!</h3>
            <p>Official CFA RSS feeds are publicly accessible with no blocking or authentication required.</p>
        </div>
        
        <div class="info-box">
            <strong>Purpose:</strong> This test script shows exactly what data the WordPress plugin fetches from CFA's official RSS feeds in real-time, without requiring WordPress installation.
        </div>
        
        <div class="status-box">
            <strong>📡 Live Test Status:</strong> Fetching real-time data from CFA RSS feeds...<br>
            <strong>Current Melbourne Time:</strong> <?php echo date('Y-m-d H:i:s'); ?> UTC<br>
            <strong>RSS Feed Source:</strong> <a href="https://www.cfa.vic.gov.au/rss-feeds" target="_blank">CFA Official RSS Feeds</a>
        </div>
        
        <div class="content">
            <form method="get" style="margin: 20px 0;">
                <button type="submit" name="refresh" value="1" class="btn">🔄 Refresh Data</button>
            </form>
            
            <!-- Test 1: Single District -->
            <div class="test-section">
                <h2>🧪 Test 1: Single District Data (North Central)</h2>
                <p>Testing data extraction from RSS feed for a single fire district...</p>
                
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
                    <p><small><strong>RSS Feed:</strong> <a href="<?php echo esc_url($single_data['source_url']); ?>" target="_blank">View Raw RSS</a></small></p>
                <?php else: ?>
                    <p style="color: #dc3545;"><strong>⚠️ Failed to fetch data</strong></p>
                <?php endif; ?>
            </div>
            
            <!-- Test 2: Multi-District Table -->
            <div class="test-section">
                <h2>🧪 Test 2: Multi-District Comparison</h2>
                <p>Testing multi-district table view with RSS feeds...</p>
                
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
