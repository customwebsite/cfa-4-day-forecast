<?php
/**
 * CFA Fire Forecast Data Scraper
 */

if (!defined('ABSPATH')) {
    exit;
}

class CFA_Fire_Forecast_Scraper {
    
    private $base_url = 'https://www.cfa.vic.gov.au/warnings-restrictions/fire-bans-ratings-and-restrictions/total-fire-bans-fire-danger-ratings/';
    
    /**
     * Scrape fire danger data for a specific district
     */
    public function scrape_fire_data($district = 'north-central-fire-district') {
        $url = $this->base_url . $district;
        
        // Use WordPress HTTP API
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36'
        ));
        
        if (is_wp_error($response)) {
            error_log('CFA Fire Forecast: Error fetching data - ' . $response->get_error_message());
            return $this->get_fallback_data();
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log('CFA Fire Forecast: Empty response body');
            return $this->get_fallback_data();
        }
        
        return $this->parse_fire_data($body, $district);
    }
    
    /**
     * Parse HTML content to extract fire danger ratings
     */
    private function parse_fire_data($html, $district) {
        // Use DOMDocument to parse HTML
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
            
            // Extract rating from text
            if (preg_match('/(NO RATING|LOW-MODERATE|MODERATE|HIGH|EXTREME|CATASTROPHIC)/i', $rating_text, $matches)) {
                return strtoupper($matches[1]);
            }
        }
        
        // Fallback: check for NO RATING in content
        if (stripos($html, 'NO RATING') !== false || stripos($html, 'no-rating.gif') !== false) {
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
                return $rating;
            }
        }
        
        return 'NO RATING';
    }
    
    /**
     * Extract total fire ban status
     */
    private function extract_fire_ban_status($html) {
        if (stripos($html, 'total fire ban') !== false && stripos($html, 'in force') !== false) {
            return true;
        }
        
        if (stripos($html, 'not currently a day of total fire ban') !== false) {
            return false;
        }
        
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
    private function get_fallback_data() {
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
                'district' => 'North Central Fire District',
                'forecast_date' => $forecast_date->format('Y-m-d')
            );
        }
        
        return array(
            'data' => $forecast_data,
            'last_updated' => current_time('mysql'),
            'next_update' => $this->get_next_update_time(),
            'source_url' => $this->base_url . 'north-central-fire-district'
        );
    }
}