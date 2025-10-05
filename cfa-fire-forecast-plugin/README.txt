=== CFA Fire Forecast ===
Contributors: Shaun Haddrill
Tags: fire, forecast, cfa, australia, weather, emergency, victoria
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: MiT or later
License URI: https://mit-license.org/

Display CFA (Country Fire Authority) fire danger ratings and forecasts for Victoria, Australia with automatic twice-daily updates.

== Description ==

The CFA Fire Forecast plugin displays real-time fire danger ratings and 4-day forecasts from the Country Fire Authority (CFA) for Victoria, Australia. Perfect for websites serving communities in bushfire-prone areas.

**Key Features:**

* **4-Day Fire Danger Forecast** - Shows current and upcoming fire danger ratings
* **Real-time Data** - Scrapes data directly from official CFA website
* **Automatic Updates** - Updates twice daily at 6 AM and 6 PM Melbourne time
* **Multiple Districts** - Support for all CFA fire districts
* **Responsive Design** - Mobile-friendly display
* **Total Fire Ban Alerts** - Displays when total fire bans are in effect
* **Fire Danger Scale** - Includes official CFA fire danger ratings scale
* **WordPress Integration** - Easy shortcode implementation
* **Caching System** - Efficient data caching to reduce server load

**Fire Districts Supported:**
* North Central Fire District
* South West Fire District
* Northern Country Fire District
* North East Fire District
* Central Fire District

**Emergency Information:**
This plugin provides general reference information only. Always check the official CFA website for the most current fire danger ratings and restrictions. In case of emergency, call 000.

== Installation ==

1. Upload the `cfa-fire-forecast` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in Settings > CFA Fire Forecast
4. Add `[cfa_fire_forecast]` shortcode to any post or page

== Usage ==

**Basic Shortcode:**
`[cfa_fire_forecast]`

**With Options:**
`[cfa_fire_forecast district="north-central-fire-district" show_scale="true" auto_refresh="true"]`

**Shortcode Parameters:**
* `district` - Fire district to display (default: north-central-fire-district)
* `show_scale` - Show fire danger ratings scale (default: true)
* `auto_refresh` - Enable automatic refresh button (default: true)

== Frequently Asked Questions ==

= How often is the data updated? =

The plugin automatically updates fire danger data twice daily at 6 AM and 6 PM Melbourne time. You can also configure different update frequencies in the settings.

= Which fire districts are supported? =

All CFA fire districts in Victoria are supported:
- North Central Fire District
- South West Fire District  
- Northern Country Fire District
- North East Fire District
- Central Fire District

= Is the data official? =

Yes, the plugin scrapes data directly from the official CFA website at cfa.vic.gov.au. However, this is for general reference only - always check the official CFA website for critical fire safety decisions.

= Can I customize the appearance? =

Yes, the plugin includes CSS classes that can be customized with your theme's stylesheet. All elements use the `cfa-` prefix for easy targeting.

= Does it work on mobile devices? =

Yes, the plugin is fully responsive and optimized for mobile devices.

= What happens if the CFA website is unavailable? =

The plugin includes fallback error handling and will display an appropriate error message while continuing to show cached data when possible.

== Screenshots ==

1. Fire danger forecast display showing 4-day forecast with color-coded ratings
2. Admin settings page for configuring districts and update frequency
3. Mobile-responsive display on smartphones and tablets
4. Fire danger ratings scale with official CFA colors and descriptions

== Changelog ==

= 1.0.0 =
* Initial release
* 4-day fire danger forecast display
* Automatic twice-daily updates
* Support for all CFA fire districts
* Responsive design
* WordPress admin interface
* Shortcode implementation
* Caching system
* Total fire ban alerts
* Fire danger ratings scale

== Upgrade Notice ==

= 1.0.0 =
Initial release of the CFA Fire Forecast plugin.

== Technical Details ==

**Data Source:** CFA Victoria - cfa.vic.gov.au
**Update Schedule:** Twice daily (6 AM and 6 PM Melbourne time)
**Cache Duration:** 1 hour (configurable)
**Timezone:** Australia/Melbourne
**Requirements:** PHP 7.4+, WordPress 5.0+

**Developer Information:**
This plugin uses the WordPress HTTP API to securely fetch data from the CFA website and includes proper error handling, caching, and security measures.