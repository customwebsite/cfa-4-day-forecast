# CFA Fire Danger Forecast WordPress Plugin

## Overview

This is a WordPress plugin that provides real-time fire danger forecasts for Victoria, Australia by scraping data from the Country Fire Authority (CFA) website. The plugin displays fire danger ratings, total fire ban status, and 4-day forecasts for different CFA fire districts. It features automated data collection, WordPress transient caching for performance, and a responsive web interface with color-coded danger levels that can be embedded in any WordPress post or page using shortcodes.

## User Preferences

Preferred communication style: Simple, everyday language.

## WordPress Plugin Architecture

### Plugin Structure
- **Main Plugin File**: `cfa-fire-forecast.php` - WordPress plugin header and initialization
- **Modular Components**: Organized includes directory with separate classes for different functionality
- **WordPress Standards**: Follows WordPress coding standards and plugin development best practices
- **Shortcode Integration**: `[cfa_fire_forecast]` shortcode for easy content embedding

### Frontend Architecture
- **WordPress Shortcode**: Easy integration into posts and pages via shortcode system
- **Responsive Design**: CSS-based responsive layout optimized for desktop and mobile devices
- **Color-coded Interface**: Visual fire danger indicators using official CFA colors and styling
- **AJAX Refresh**: Client-side JavaScript with WordPress AJAX for data refresh functionality

### Backend Architecture
- **PHP Classes**: Object-oriented PHP structure with separate classes for scraping, frontend, admin, and scheduling
- **WordPress HTTP API**: Uses WordPress native HTTP functions for secure external requests
- **WordPress Transients**: Built-in WordPress caching system for efficient data storage
- **WordPress Cron**: Native WordPress scheduling system for automated twice-daily updates
- **Admin Interface**: WordPress admin settings page for plugin configuration

### Data Processing
- **DOMDocument Parsing**: PHP DOMDocument for reliable HTML parsing of CFA website data
- **Data Transformation**: Converts scraped HTML data into structured arrays for WordPress consumption
- **Error Handling**: Graceful fallbacks with WordPress error logging when primary data sources fail
- **Timezone Handling**: Proper Melbourne/Australia timezone handling for accurate scheduling

### WordPress Integration
- **Database Tables**: Optional custom table for historical data storage
- **WordPress Options**: Plugin settings stored in WordPress options table
- **WordPress Security**: Proper nonce verification and capability checks for admin functions
- **WordPress Standards**: Follows WordPress plugin development guidelines and security practices

## External Dependencies

### Core Web Scraping
- **CFA Website**: Primary data source at `cfa.vic.gov.au` for fire danger ratings and total fire ban information
- **WordPress HTTP API**: Built-in WordPress functions for secure HTTP requests
- **PHP DOMDocument**: Native PHP library for HTML parsing

### WordPress Infrastructure
- **WordPress Core**: Minimum WordPress 5.0 requirement
- **WordPress Transients**: Built-in caching system
- **WordPress Cron**: Native scheduling system
- **WordPress Admin**: Settings page integration

### Development Dependencies
- **PHP 7.4+**: Minimum PHP version requirement
- **WordPress Environment**: Must be installed as WordPress plugin

### Data Sources
- **Multiple Fire Districts**: Support for all CFA fire districts including North Central, South West, Northern Country, North East, and Central Fire Districts
- **User-Agent Headers**: Mimics browser requests to avoid bot detection on CFA website
- **Melbourne Timezone**: All scheduling and timestamps use Australia/Melbourne timezone