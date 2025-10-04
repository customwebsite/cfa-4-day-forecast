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
        
        // Keep only last 50 entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('cfa_fire_forecast_fetch_logs', $logs);
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
            $tfb = $this->extract_tfb_status($description);
            
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
        while (count($forecast_data) < 4) {
            $forecast_data[] = array(
                'day' => 'Day ' . (count($forecast_data) + 1),
                'date' => date('Y-m-d', strtotime('+' . count($forecast_data) . ' days')),
                'rating' => 'NO RATING',
                'total_fire_ban' => false
            );
        }
        
        return array(
            'data' => array(
                'current_rating' => $current_rating,
                'total_fire_ban' => $total_fire_ban,
                'forecast' => $forecast_data,
                'last_updated' => current_time('mysql')
            ),
            'source_url' => $this->rss_base_url . $this->rss_feed_map[$district]
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
    private function extract_tfb_status($description) {
        // Check for "is not currently a day of Total Fire Ban"
        if (stripos($description, 'is not currently a day of Total Fire Ban') !== false) {
            return false;
        }
        
        // Check for positive indicators
        if (stripos($description, 'Total Fire Ban in force') !== false ||
            stripos($description, 'is a day of Total Fire Ban') !== false ||
            stripos($description, 'Total Fire Ban declared') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse date from RSS item title
     */
    private function parse_date_from_title($title) {
        // Try to parse date from title like "Thursday, 02 October 2025"
        $timestamp = strtotime($title);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        // Fallback to current date
        return date('Y-m-d');
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
        return array(
            'data' => array(
                'current_rating' => 'NO RATING',
                'total_fire_ban' => false,
                'forecast' => array(
                    array('day' => 'Today', 'date' => date('Y-m-d'), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Tomorrow', 'date' => date('Y-m-d', strtotime('+1 day')), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Day 3', 'date' => date('Y-m-d', strtotime('+2 days')), 'rating' => 'NO RATING', 'total_fire_ban' => false),
                    array('day' => 'Day 4', 'date' => date('Y-m-d', strtotime('+3 days')), 'rating' => 'NO RATING', 'total_fire_ban' => false)
                ),
                'last_updated' => current_time('mysql')
            ),
            'source_url' => 'https://www.cfa.vic.gov.au/rss-feeds'
        );
    }
}
