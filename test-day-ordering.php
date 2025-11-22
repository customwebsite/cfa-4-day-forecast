<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CFA Day Ordering Feature Test - Issue #2</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dashicons/0.9.0/dashicons.min.css">
    <link rel="stylesheet" href="cfa-fire-forecast-plugin/assets/css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .demo-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 3px solid #2271b1;
            padding-bottom: 10px;
        }
        .feature-description {
            background: #e7f3ff;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin: 20px 0;
        }
        .test-result {
            background: #d5f4e6;
            border-left: 4px solid #00843D;
            padding: 15px;
            margin: 20px 0;
        }
        .label {
            font-weight: 600;
            margin-top: 20px;
            display: block;
        }
        #day_order_output {
            font-family: monospace;
            background: #f0f0f1;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .instructions {
            background: #fff3cd;
            border-left: 4px solid #FFB81C;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <h1>🎯 GitHub Issue #2: Drag & Drop Day Ordering</h1>
        
        <div class="feature-description">
            <h3>✨ Feature Request</h3>
            <p><strong>Requested by:</strong> cralwalker (WordPress site: cobawrange.com.au)</p>
            <p><strong>Requirement:</strong> Enable webmasters to choose the order of forecast day tiles</p>
            <p><strong>Plugin Version:</strong> 4.8.0</p>
        </div>

        <div class="instructions">
            <h3>📋 How to Use</h3>
            <ol>
                <li><strong>Drag</strong> the tiles below to reorder them</li>
                <li>Watch the <strong>hidden input value</strong> update in real-time</li>
                <li>This order will apply to <strong>all layouts</strong> (table, cards, compact)</li>
                <li>Order is saved when you click "Save Changes" in WordPress admin</li>
            </ol>
        </div>

        <label class="label">Forecast Day Order (Drag to Reorder):</label>
        <input type='hidden' name='cfa_fire_forecast_options[day_order]' id='cfa_day_order_input' value='0,1,2,3'>
        
        <ul id='cfa_sortable_days' class='cfa-sortable-list'>
            <li data-day='0'>
                <span class='dashicons dashicons-menu'></span>
                <span class='day-label'>Today</span>
            </li>
            <li data-day='1'>
                <span class='dashicons dashicons-menu'></span>
                <span class='day-label'>Tomorrow</span>
            </li>
            <li data-day='2'>
                <span class='dashicons dashicons-menu'></span>
                <span class='day-label'>Day 3</span>
            </li>
            <li data-day='3'>
                <span class='dashicons dashicons-menu'></span>
                <span class='day-label'>Day 4</span>
            </li>
        </ul>

        <label class="label">Hidden Input Value (Saved to Database):</label>
        <div id="day_order_output">0,1,2,3</div>

        <div class="test-result">
            <h3>✅ Implementation Complete</h3>
            <ul>
                <li>✅ Drag-and-drop UI with jQuery UI Sortable</li>
                <li>✅ Visual feedback (hover states, drag helper)</li>
                <li>✅ Real-time hidden input update</li>
                <li>✅ Applied to ALL layouts (table, cards, compact)</li>
                <li>✅ Applied to both single & multi-district views</li>
                <li>✅ Proper WordPress admin styling</li>
            </ul>
        </div>

        <div class="feature-description">
            <h3>🔧 Technical Implementation</h3>
            <ul>
                <li><strong>Admin UI:</strong> New "Forecast Day Order" field in Layout Settings</li>
                <li><strong>Storage:</strong> Comma-separated indices (e.g., "2,0,3,1")</li>
                <li><strong>Frontend:</strong> reorder_forecast_days() method applies ordering</li>
                <li><strong>Compatibility:</strong> Works with all color schemes and display formats</li>
            </ul>
        </div>
    </div>

    <script src="cfa-fire-forecast-plugin/assets/js/admin.js"></script>
    <script>
        // Update display when sortable changes
        jQuery(document).ready(function($) {
            $('#cfa_day_order_input').on('change input', function() {
                $('#day_order_output').text($(this).val());
            });
        });
    </script>
</body>
</html>
