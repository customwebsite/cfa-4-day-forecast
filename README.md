# CFA Fire Danger Forecast WordPress Plugin

A WordPress plugin that displays real-time fire danger forecasts for Victoria, Australia by fetching data from official CFA (Country Fire Authority) RSS feeds.

## Features

- **4-Day Fire Danger Forecast** - Shows current and upcoming fire danger ratings
- **Multiple Districts** - Support for all CFA fire districts across Victoria
- **Automatic Updates** - Data refreshes automatically twice daily (6 AM & 6 PM Melbourne time)
- **WordPress Caching** - Efficient performance using WordPress transient caching
- **Responsive Design** - Mobile-friendly interface with color-coded danger levels
- **Multiple Display Formats** - Table view, card view, and compact list layouts
- **Customizable** - Comprehensive admin settings for colors, borders, and visibility options
- **Request Logging** - Track last 50 data fetch requests with timestamps and status

## Download

Download the latest version:

**[Download CFA Fire Forecast Plugin (v4.0.4)](https://github.com/customwebsite/cfa-4-day-forecast/raw/main/cfa-fire-forecast-plugin.zip)**

## Installation

1. Download the plugin zip file from the link above
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins > Add New**
4. Click **Upload Plugin** at the top of the page
5. Click **Choose File** and select the downloaded `cfa-fire-forecast-plugin.zip`
6. Click **Install Now**
7. After installation completes, click **Activate Plugin**

## Usage

### Basic Shortcode

Display fire forecast for a single district:

```
[cfa_fire_forecast]
```

By default, this shows the North Central Fire District.

### Single District with Custom District

```
[cfa_fire_forecast district="south-west-fire-district"]
```

### Multiple Districts (Table View)

Display multiple districts in a side-by-side comparison table:

```
[cfa_fire_forecast districts="north-central-fire-district,south-west-fire-district,central-fire-district"]
```

### Available Districts

- `north-central-fire-district`
- `south-west-fire-district`
- `northern-country-fire-district`
- `north-east-fire-district`
- `central-fire-district`
- `mallee-fire-district`
- `wimmera-fire-district`
- `east-gippsland-fire-district`
- `west-and-south-gippsland-fire-district`

### Shortcode Options

- `district` - Specify a single fire district
- `districts` - Comma-separated list of districts for multi-district table view
- `show_scale` - Show/hide the fire danger rating scale legend (default: `true`)
- `auto_refresh` - Enable/disable manual refresh button (default: `true`)

## Plugin Settings

Access plugin settings at **Settings > CFA Fire Forecast** in your WordPress admin.

### General Settings
- **Fire District** - Default district to display
- **Cache Duration** - How long to cache data (300-43200 seconds)
- **Update Frequency** - Automatic update schedule (twice daily, hourly, daily)

### Display Settings
- **Color Scheme** - Official CFA colors, high contrast, or minimal
- **Show Rating Scale** - Display the fire danger ratings legend
- **Show Total Fire Ban Indicator** - Show TFB warnings
- **Show Last Updated Time** - Display data refresh timestamp
- **Show Manual Refresh Button** - Allow users to manually refresh data
- **Custom Header Text** - Customize the forecast heading

### Layout Settings
- **Display Format** - Table view, card view, or compact list
- **Number of Forecast Days** - Show 1-4 days of forecast
- **Table Header Background Color** - Customize table header colors (with reset button)
- **Border Style** - None, minimal, normal, or bold borders
- **Mobile Responsive** - Enable responsive design for mobile devices

### Data Fetch Logging
View the last 50 data fetch requests with:
- Timestamp (Melbourne time)
- District requested
- HTTP status code
- Response time in milliseconds

## Technical Details

### Data Source
The plugin uses official CFA RSS feeds:
```
https://www.cfa.vic.gov.au/cfa/rssfeed/{district-slug}-firedistrict_rss.xml
```

### Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher

### Caching & Performance
- Uses WordPress transient API for efficient caching
- Default cache duration: 1 hour
- Automatic data refresh via WordPress Cron
- Minimal database queries

### Fire Danger Ratings
The plugin displays the following official CFA fire danger ratings:
- **Low-Moderate** - Fire can start and spread
- **Moderate** - Fires can spread, be prepared
- **High** - Fires will spread rapidly
- **Extreme** - Fires extremely difficult to control
- **Catastrophic** - Fires uncontrollable, leave immediately

### Total Fire Ban Detection
Automatically detects and displays Total Fire Ban status for each district and day.

## Customization

### Color Schemes
- **Official CFA Colors** - Uses authentic CFA fire danger rating colors
- **High Contrast** - Enhanced visibility for accessibility
- **Minimal/Grayscale** - Subdued color palette

### Layout Formats
- **Table View** - Grid layout showing all days side-by-side
- **Card View** - Responsive card-based design
- **Compact List** - Horizontal list format with hover effects

## Support & Updates

For issues, feature requests, or contributions, please visit the GitHub repository.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Disclaimer

This information is for general reference only. Always check the official CFA website for the most current fire danger ratings and restrictions.

**In case of fire emergency, call 000 immediately.**

## Credits

- **Author**: Shaun Haddrill
- **Data Source**: Country Fire Authority (CFA) Victoria
- **Version**: 4.0.4

## Changelog

### Version 4.0.4
- Added "Reset to Default" button for table header color
- Fixed display manual refresh button setting not saving
- Fixed card layout and compact layout not working
- Added new compact view with horizontal list format
- Improved layout switching functionality

### Version 4.0.3
- Fixed single district display table
- Fixed multi-district table empty cells
- Corrected data array access paths
- Fixed rating key references

### Version 4.0.0
- Multi-district table view support
- Comprehensive display and layout customization
- Data fetch request logging system
- Enhanced admin settings interface
