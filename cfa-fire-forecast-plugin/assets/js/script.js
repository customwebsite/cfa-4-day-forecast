/* CFA Fire Forecast Plugin JavaScript */

(function($) {
    'use strict';

    // Auto-refresh functionality
    function setupAutoRefresh() {
        // Refresh every 30 minutes
        setInterval(function() {
            cfaRefreshData();
        }, 30 * 60 * 1000);
    }

    // Initialize when document is ready
    $(document).ready(function() {
        setupAutoRefresh();
    });

    // Global refresh function
    window.cfaRefreshData = function() {
        var $container = $('#cfa-fire-forecast');
        var $refreshBtn = $('.cfa-refresh-btn');
        var $statusIcon = $('.cfa-status-icon');
        var $statusText = $statusIcon.next('span');

        // Update UI to show loading state
        $refreshBtn.prop('disabled', true).text('Refreshing...');
        $statusIcon.removeClass('online error').addClass('loading');
        $statusText.text('Fetching latest fire data...');

        // Get districts from container data attribute or default
        var districts = $container.data('districts') || $container.data('district') || 'north-central-fire-district';

        // Make AJAX request
        $.ajax({
            url: cfaAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'refresh_fire_data',
                districts: districts,
                nonce: cfaAjax.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Update the forecast display
                    updateForecastDisplay(response.data);
                    
                    // Update status
                    $statusIcon.removeClass('loading error').addClass('online');
                    $statusText.text('Data loaded successfully');
                } else {
                    showError('Failed to load fire data. Please try again.');
                }
            },
            error: function() {
                showError('Network error. Please check your connection and try again.');
            },
            complete: function() {
                // Reset button state
                $refreshBtn.prop('disabled', false).text('Refresh Now');
            }
        });
    };

    // Update forecast display with new data
    function updateForecastDisplay(data) {
        // Check if this is multi-district data
        if (data.multi_district) {
            updateMultiDistrictDisplay(data);
        } else {
            updateSingleDistrictDisplay(data);
        }

        // Update last updated time
        if (data.last_updated) {
            var lastUpdated = new Date(data.last_updated + ' Australia/Melbourne');
            var $statusBar = $('.cfa-status-bar');
            $statusBar.find('.cfa-status-item:contains("Last updated")').find('span').text(
                'Last updated: ' + formatDate(lastUpdated) + ' (Melbourne time)'
            );
        }
    }

    // Update single district display
    function updateSingleDistrictDisplay(data) {
        var $grid = $('.cfa-forecast-grid');
        if (!$grid.length || !data.data) return;

        // Update forecast cards
        $grid.empty();
        
        $.each(data.data, function(index, day) {
            var isToday = index === 0;
            var ratingClass = getRatingClass(day.fire_danger_rating);
            var totalFireBanHtml = day.total_fire_ban ? 
                '<div class="cfa-total-fire-ban">⚠️ TOTAL FIRE BAN</div>' : '';

            var cardHtml = 
                '<div class="cfa-forecast-day ' + (isToday ? 'today' : '') + '">' +
                    '<div class="cfa-day-header">' + escapeHtml(day.day) + '</div>' +
                    '<div class="cfa-day-date">' + escapeHtml(day.date) + '</div>' +
                    '<div class="cfa-fire-danger-badge rating-' + ratingClass + '">' +
                        escapeHtml(day.fire_danger_rating) +
                    '</div>' +
                    totalFireBanHtml +
                    '<div class="cfa-district-name">' + escapeHtml(day.district) + '</div>' +
                '</div>';

            $grid.append(cardHtml);
        });
    }

    // Update multi-district table display
    function updateMultiDistrictDisplay(data) {
        var $tbody = $('.cfa-forecast-table tbody');
        if (!$tbody.length || !data.data) return;

        $tbody.empty();
        
        $.each(data.districts, function(districtIndex, district) {
            if (!data.data[district]) return;
            
            // Format district name
            var districtName = district.replace(/-/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            
            var rowHtml = '<tr class="district-row">' +
                '<td class="district-name">' + escapeHtml(districtName) + '</td>';
            
            $.each(data.data[district], function(dayIndex, day) {
                var isToday = dayIndex === 0;
                var ratingClass = getRatingClass(day.fire_danger_rating);
                var totalFireBanHtml = day.total_fire_ban ? 
                    '<div class="cfa-total-fire-ban-small">🔴 TFB</div>' : '';
                
                rowHtml += '<td class="forecast-cell ' + (isToday ? 'today' : '') + '">' +
                    '<div class="cfa-fire-danger-badge rating-' + ratingClass + '">' +
                        escapeHtml(day.fire_danger_rating) +
                    '</div>' +
                    totalFireBanHtml +
                    '</td>';
            });
            
            rowHtml += '</tr>';
            $tbody.append(rowHtml);
        });
    }

    // Show error message
    function showError(message) {
        var $statusIcon = $('.cfa-status-icon');
        var $statusText = $statusIcon.next('span');
        
        $statusIcon.removeClass('online loading').addClass('error');
        $statusText.text(message);
    }

    // Get CSS class for fire danger rating
    function getRatingClass(rating) {
        var ratingLower = rating.toLowerCase();
        
        if (ratingLower.includes('low-moderate')) return 'low-moderate';
        if (ratingLower.includes('moderate') && !ratingLower.includes('low')) return 'moderate';
        if (ratingLower.includes('high')) return 'high';
        if (ratingLower.includes('extreme')) return 'extreme';
        if (ratingLower.includes('catastrophic')) return 'catastrophic';
        if (ratingLower.includes('error')) return 'error';
        
        return 'no-rating';
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Format date for display
    function formatDate(date) {
        return date.toLocaleDateString('en-AU', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

})(jQuery);