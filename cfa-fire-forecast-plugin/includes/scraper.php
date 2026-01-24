<?php
/**
 * CFA Fire Forecast RSS Feed Scraper
 * 
 * Uses official CFA RSS feeds to fetch fire danger ratings
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Fire_Forecast_Scraper {
    
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
            $district_data = $this->scrape_fire_data($district);
            if ($district_data && !empty($district_data['data'])) {
                $all_data[$district] = $district_data['data'];
                $source_urls[] = $district_data['source_url'];
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
     * Scrape fire danger data from RSS feed for a specific district
     */
    public function scrape_fire_data($district = 'north-central-fire-district') {
        $start_time = microtime(true);
        
        // Get RSS feed URL for this district
        if (!isset($this->rss_feed_map[$district])) {
            error_log('CFA Fire Forecast: Unknown district - ' . $district);
            $this->log_fetch_request($district, false, 'Unknown district', 0);
            return $this->get_fallback_data($district);
        }
        
        $rss_url = $this->rss_base_url . $this->rss_feed_map[$district];
        
        // Fetch RSS feed
        $response = wp_remote_get($rss_url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (compatible; CFA-Fire-Forecast-WordPress-Plugin/1.0)'
        ));
        
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('CFA Fire Forecast: Error fetching RSS - ' . $error_msg);
            $this->log_fetch_request($district, false, $error_msg, $response_time, $rss_url);
            return $this->get_fallback_data($district);
        }
        
        $xml_content = wp_remote_retrieve_body($response);
        if (empty($xml_content)) {
            error_log('CFA Fire Forecast: Empty RSS response');
            $this->log_fetch_request($district, false, 'Empty RSS response', $response_time, $rss_url);
            return $this->get_fallback_data($district);
        }
        
        $this->log_fetch_request($district, true, 'Success', $response_time, $rss_url);
        return $this->parse_rss_feed($xml_content, $district);
    }
    
    /**
     * Log fetch request for debugging
     */
    private function log_fetch_request($district, $success, $message, $response_time, $url = '') {
        // Check if logging is enabled
        $options = get_option('cfa_fire_forecast_options');
        $enable_logging = isset($options['enable_logging']) ? $options['enable_logging'] : 'yes';
        
        if ($enable_logging !== 'yes') {
            return;
        }
        
        $logs = get_option('cfa_fire_forecast_fetch_logs', array());
        
        // Add new log entry
        $logs[] = array(
            'timestamp' => current_time('mysql'),
            'district' => $district,
            'success' => $success,
            'message' => $message,
            'response_time' => $response_time,
            'url' => $url
        );
        
        // Apply retention cleanup
        $retention = isset($options['log_retention']) ? $options['log_retention'] : '28days';
        $logs = $this->cleanup_old_logs($logs, $retention);
        
        update_option('cfa_fire_forecast_fetch_logs', $logs);
    }
    
    /**
     * Cleanup old logs based on retention period
     */
    private function cleanup_old_logs($logs, $retention) {
        if ($retention === 'indefinite') {
            // Keep all logs
            return $logs;
        }
        
        // Calculate cutoff timestamp based on retention period
        $cutoff_time = null;
        switch ($retention) {
            case '7days':
                $cutoff_time = strtotime('-7 days');
                break;
            case '28days':
                $cutoff_time = strtotime('-28 days');
                break;
            case '1year':
                $cutoff_time = strtotime('-1 year');
                break;
            default:
                $cutoff_time = strtotime('-28 days');
        }
        
        // Filter logs to keep only those newer than cutoff
        $filtered_logs = array();
        foreach ($logs as $log) {
            $log_timestamp = strtotime($log['timestamp']);
            if ($log_timestamp >= $cutoff_time) {
                $filtered_logs[] = $log;
            }
        }
        
        return $filtered_logs;
    }
    
    /**
     * Parse RSS feed XML to extract fire danger data
     */
    private function parse_rss_feed($xml_content, $district) {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        
        if ($xml === false) {
            error_log('CFA Fire Forecast: Failed to parse RSS XML');
            return $this->get_fallback_data($district);
        }
        
        $forecast_data = array();
        $current_rating = 'NO RATING';
        $total_fire_ban = false;
        
        // Parse RSS items (first 4 are the 4-day forecast)
        $items = $xml->channel->item;
        $day_count = 0;
        
        foreach ($items as $item) {
            // Skip municipality restrictions item
            $title = (string)$item->title;
            if (stripos($title, 'municipality') !== false || stripos($title, 'restrictions') !== false) {
                continue;
            }
            
            // Only get first 4 days
            if ($day_count >= 4) {
                break;
            }
            
            $description = (string)$item->description;
            
            // Extract fire danger rating
            $rating = $this->extract_rating_from_description($description);
            
            // Extract total fire ban status
            $tfb = $this->extract_tfb_status($description, $district);
            
            // Determine day name
            if ($day_count === 0) {
                $day_name = 'Today';
                $current_rating = $rating;
                $total_fire_ban = $tfb;
            } elseif ($day_count === 1) {
                $day_name = 'Tomorrow';
            } else {
                $day_name = $title; // Use title like "Saturday, 04 October 2025"
            }
            
            $forecast_data[] = array(
                'day' => $day_name,
                'date' => $this->parse_date_from_title($title),
                'rating' => $rating,
                'total_fire_ban' => $tfb
            );
            
            $day_count++;
        }
        
        // If we didn't get 4 days, pad with NO RATING
        // Use Melbourne timezone for consistency
        $melbourne_tz = new DateTimeZone('Australia/Melbourne');
        while (count($forecast_data) < 4) {
            $date_offset = new DateTime('now', $melbourne_tz);
            $date_offset->modify('+' . count($forecast_data) . ' days');
            
            $forecast_data[] = array(
                'day' => 'Day ' . (count($forecast_data) + 1),
                'date' => $date_offset->format('Y-m-d'),
                'rating' => 'NO RATING',
                'total_fire_ban' => false
            );
        }
        
        return array(
            'data' => array(
                'current_rating' => $current_rating,
                'total_fire_ban' => $total_fire_ban,
                'forecast' => $forecast_data,
                'district' => ucwords(str_replace('-', ' ', $district))
            ),
            'source_url' => $this->rss_base_url . $this->rss_feed_map[$district],
            'last_updated' => current_time('mysql')
        );
    }
    
    /**
     * Extract fire danger rating from RSS description
     */
    private function extract_rating_from_description($description) {
        // Pattern: "North Central: MODERATE" or similar
        if (preg_match('/:\s*(CATASTROPHIC|EXTREME|HIGH|MODERATE|LOW-MODERATE|NO RATING)/i', $description, $matches)) {
            return strtoupper($matches[1]);
        }
        
        // Fallback: look for rating words anywhere
        $ratings = array('CATASTROPHIC', 'EXTREME', 'HIGH', 'MODERATE', 'LOW-MODERATE');
        foreach ($ratings as $rating) {
            if (stripos($description, $rating) !== false) {
                return $rating;
            }
        }
        
        return 'NO RATING';
    }
    
    /**
     * Extract Total Fire Ban status from description
     */
    private function extract_tfb_status($description, $district_slug = '') {
        // Normalize description for parsing
        // CFA RSS descriptions often contain HTML and encoded characters
        $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        
        // Extract only the first paragraph to avoid matching legend/footer text
        // The actual TFB status is always in the first paragraph of CFA RSS feeds
        if (preg_match('/<p>(.*?)<\/p>/is', $description, $matches)) {
            $first_paragraph = $matches[1];
        } else {
            // Fallback: use first chunk before any line breaks if no <p> tags found
            $chunks = preg_split('/(<br\s*\/?>|\n)/i', $description);
            $first_paragraph = $chunks[0];
        }
        
        // Clean up the paragraph for matching
        $first_paragraph = strip_tags($first_paragraph);
        $first_paragraph = trim($first_paragraph);
        
        // Check for negative indicators FIRST (no TFB)
        // These phrases explicitly state there is NO ban
        if (stripos($first_paragraph, 'is not currently a day of Total Fire Ban') !== false ||
            stripos($first_paragraph, 'is not a day of Total Fire Ban') !== false) {
            return false;
        }
        
        // Check for positive indicators (TFB active)
        // CFA uses various phrasings for actual Total Fire Bans:
        // - "Today is a day of Total Fire Ban"
        // - "Total Fire Ban in force for Monday"
        // - "Total Fire Ban has been declared"
        // - "Total Fire Ban declared"
        // - "has been declared a day of Total Fire Ban"
        // - "is a day of Total Fire Ban"
        
        // If we have a district name, check if it's explicitly mentioned in the ban declaration
        if ($district_slug && stripos($first_paragraph, 'Total Fire Ban') !== false) {
            $district_name = ucwords(str_replace('-', ' ', $district_slug));
            $district_name_short = str_ireplace(' fire district', '', $district_name);
            
            // CFA descriptions use "Central district(s)" for Central
            // But they also say "North Central" which contains "Central"
            // We need to be careful with word boundaries
            
            // Check for specific mention with word boundaries to avoid "North Central" matching "Central"
            $pattern = '/\b' . preg_quote($district_name_short, '/') . '\b(?!\s*Central)/i';
            if ($district_name_short === 'Central') {
                // For Central, we MUST ensure it's not preceded by "North" or "South" etc.
                $pattern = '/(?<!North\s)(?<!South\s)\bCentral\b/i';
            }
            
            if (preg_match($pattern, $first_paragraph)) {
                return true;
            }
            
            // If "Total Fire Ban" is mentioned but NOT our district, it's likely a generic message for other districts
            // We should only return true if the phrase implies it applies to ALL districts or explicitly mentions ours
            if (stripos($first_paragraph, 'all district') !== false) {
                return true;
            }
            
            return false;
        }

        if (stripos($first_paragraph, 'day of Total Fire Ban') !== false ||
            stripos($first_paragraph, 'Total Fire Ban in force') !== false ||
            stripos($first_paragraph, 'Total Fire Ban declared') !== false ||
            stripos($first_paragraph, 'Total Fire Ban has been declared') !== false) {
            return true;
        }
        
        // Default to false (no TFB)
        return false;
    }
    
    /**
     * Parse date from RSS item title
     */
    private function parse_date_from_title($title) {
        // Try to parse date from title like "Thursday, 02 October 2025"
        // Use Melbourne timezone to ensure dates match CFA's timezone
        $melbourne_tz = new DateTimeZone('Australia/Melbourne');
        
        try {
            $date = new DateTime($title, $melbourne_tz);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            // Fallback to current date in Melbourne timezone
            $now = new DateTime('now', $melbourne_tz);
            return $now->format('Y-m-d');
        }
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
     * Get fallback data when scraping fails
     */
    private function get_fallback_data($district = '') {
        // Use Melbourne timezone for fallback dates
        $melbourne_tz = new DateTimeZone('Australia/Melbourne');
        $today = new DateTime('now', $melbourne_tz);
        
        return array(
            'data' => array(
                'current_rating' => 'NO RATING',
                'total_fire_ban' => false,
                'forecast' => array(
                    array('day' => 'Today', 'date' => $today->format('Y-m-d'), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Tomorrow', 'date' => (clone $today)->modify('+1 day')->format('Y-m-d'), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Day 3', 'date' => (clone $today)->modify('+2 days')->format('Y-m-d'), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Day 4', 'date' => (clone $today)->modify('+3 days')->format('Y-m-d'), 'rating' => 'NO RATING', 'total_fire_ban' => false)
                ),
                'district' => !empty($district) ? ucwords(str_replace('-', ' ', $district)) : 'Unknown District'
            ),
            'source_url' => 'https://www.cfa.vic.gov.au/rss-feeds',
            'last_updated' => current_time('mysql')
        );
    }
}
