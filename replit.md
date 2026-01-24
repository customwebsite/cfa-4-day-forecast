# CFA Fire Danger Forecast WordPress Plugin

## Overview

This is a WordPress plugin that provides real-time fire danger forecasts for Victoria, Australia by scraping data from the Country Fire Authority (CFA) website. The plugin displays fire danger ratings, total fire ban status, and 4-day forecasts for different CFA fire districts. It features automated data collection, WordPress transient caching for performance, and a responsive web interface with color-coded danger levels that can be embedded in any WordPress post or page using shortcodes.

## User Preferences

Preferred communication style: Simple, everyday language.

## Recent Updates

### v4.8.6 (January 2026) - Total Fire Ban Detection Fix (Round 2)
**Fix:** Even more robust TFB parsing
- **Improved:** Added catch-all for "Total Fire Ban" phrase if no negative indicators are present.
- **Safety:** Maintained paragraph isolation to prevent legend matching.
- **Applies to:** All districts and forecast days

### v4.8.1 (November 2025) - GitHub Issue #3 Color Fix
**Fix:** Differentiate Low-Moderate and Moderate colors
- **Previous:** Both Low-Moderate and Moderate were #00843D (same dark green)
- **Fixed:** Low-Moderate is now #7BC142 (lighter green), Moderate remains #00843D (dark green)
- **Reason:** Official AFDRS uses distinct colors for each fire danger level
- **Text contrast:** Low-Moderate uses dark text on light green, Moderate uses white on dark green
- **Applies to:** All layouts (table, cards, compact) and both single/multi-district views

### v4.8.0 (November 2025) - GitHub Issue #2
**Feature:** Drag-and-drop forecast day tile ordering
- **User Request:** Enable webmasters to choose order of forecast tiles (requested by cralwalker @ cobawrange.com.au)
- **Implementation:** jQuery UI Sortable with drag-and-drop interface in admin settings
- **Storage:** Comma-separated day indices stored in WordPress options (e.g., "2,0,3,1" for custom order)
- **Frontend:** reorder_forecast_days() method applies custom ordering to all layouts
- **Critical Fix:** "Today" CSS class now checks day label instead of array index for correct highlighting after reordering
- **Applies to:** All layouts (table, cards, compact) and both single/multi-district views

### v4.7.0 (November 2025) - GitHub Issue #3
**Feature:** Official CFA/AFDRS colors and editable hex fields
- **Updated colors:** Moderate changed from orange to green (#00843D) to match official AFDRS
- **All colors updated:** Low-Moderate (#00843D), Moderate (#00843D), High (#FFB81C), Extreme (#DA291C), Catastrophic (#6D2077)
- **Editable hex fields:** Two-way sync between color picker and hex input with validation

### v4.6.2 (November 2025) - GitHub Issues #4 & #5
**Fixes:**
- **Issue #5:** Total Fire Ban false positive detection (was detecting legend text)
- **Issue #4:** Timezone bug causing dates to display one day ahead (Melbourne vs UTC)
- **Solution:** Extract only first paragraph from RSS, use Australia/Melbourne timezone throughout

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

### Admin Settings Interface
- **General Settings**: District selection, cache duration, update frequency
- **Display Settings**: Color schemes (official CFA/AFDRS, high contrast, minimal, custom with editable hex fields), visibility toggles, custom header text
- **Layout Settings**: Display format (table/cards/compact), forecast days, table styling, responsive design, **drag-and-drop tile ordering (v4.8.0)**

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

## CFA API Discovery (October 2025)

### Technical Findings
Through reverse-engineering the CFA website JavaScript, we discovered their internal API endpoint:

**API Endpoint:** `POST https://www.cfa.vic.gov.au/api/cfa/tfbfdr/district`

**Request Format:**
```json
{
  "IssueDate": "2025-10-01 00:00:00",
  "DistrictName": "North Central",
  "AdminEmailAddress": "digitalworkflow@cfa.vic.gov.au"
}
```

**Key Discovery Details:**
- API returns accurate fire danger ratings (confirmed: "MODERATE" for North Central)
- District names must use proper case format (not URL slugs)
- Date format: `YYYY-MM-DD HH:MM:SS`
- Response includes 4-day forecast data, TFB status, and municipality restrictions

### Critical Limitation: Access Blocked
- **Local requests:** ✅ Work correctly (HTTP 200)
- **Server requests:** ❌ Blocked with HTTP 403 Forbidden
- **Root cause:** CFA uses IP-based blocking or bot detection
- **Impact:** Cannot use API directly from WordPress servers

### Alternative Solutions Required
1. **Headless browser microservice** - Execute JavaScript in real browser context to bypass blocking
2. **Official API access** - Contact CFA for IP whitelisting or official API credentials
3. **Alternative data source** - Bureau of Meteorology (different district mapping)

### Current Status
- API fully documented in `CFA_API_DISCOVERY.md`
- HTML scraping approach fails (ratings loaded via JavaScript)
- Direct API approach blocked (403 Forbidden from servers)
- ✅ **SOLUTION IMPLEMENTED:** Official CFA RSS feeds work perfectly!

### RSS Feed Solution (October 2025)
**Successful implementation using official CFA RSS feeds:**

**RSS Feed Base URL:** `https://www.cfa.vic.gov.au/cfa/rssfeed/`

**Benefits:**
- ✅ Publicly accessible - no authentication or API keys required
- ✅ No blocking or IP restrictions
- ✅ Official CFA data source designed for automated consumption
- ✅ Contains complete 4-day forecast with ratings and TFB status
- ✅ Updates automatically (CFA manages feed refresh)

**RSS Feed Mapping:**
| District Slug | RSS Feed Filename |
|---------------|------------------|
| north-central-fire-district | northcentral-firedistrict_rss.xml |
| south-west-fire-district | southwest-firedistrict_rss.xml |
| central-fire-district | central-firedistrict_rss.xml |
| mallee-fire-district | mallee-firedistrict_rss.xml |
| wimmera-fire-district | wimmera-firedistrict_rss.xml |
| northern-country-fire-district | northerncountry-firedistrict_rss.xml |
| north-east-fire-district | northeast-firedistrict_rss.xml |
| east-gippsland-fire-district | eastgippsland-firedistrict_rss.xml |
| west-and-south-gippsland-fire-district | westandsouthgippsland-firedistrict_rss.xml |

**Implementation:**
- WordPress plugin updated to parse XML from RSS feeds
- Extracts fire danger ratings using regex: `/:\s*(CATASTROPHIC|EXTREME|HIGH|MODERATE|LOW-MODERATE|NO RATING)/i`
- Detects Total Fire Ban from description text
- Supports single and multi-district views
- Full 4-day forecast functionality

### District Name Mapping
| URL Slug | API District Name |
|----------|------------------|
| central-fire-district | Central |
| mallee-fire-district | Mallee |
| north-central-fire-district | North Central |
| north-east-fire-district | North East |
| northern-country-fire-district | Northern Country |
| south-west-fire-district | South West |
| west-and-south-gippsland-fire-district | West and South Gippsland |
| wimmera-fire-district | Wimmera |